<?php

namespace App\Http\Controllers\Rawas;

use App\Http\Controllers\Controller;
use App\Models\LogActivity;
use App\Models\RawasButir;
use App\Models\RawasCluster;
use App\Models\RawasReview;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviuRawasController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasReview()) {
            abort(403, 'Anda tidak memiliki akses ke halaman reviu RAWAS.');
        }

        $butirsForReviewQuery = RawasButir::with([
            'record',
            'butirPics.unitKerja',
        ])
            ->whereHas('record')
            ->whereHas('tindakLanjuts');

        $butirsForReview = $butirsForReviewQuery->get();

        DB::connection('mysql_rawas')->transaction(function () use ($butirsForReview) {
            foreach ($butirsForReview as $butir) {
                RawasReview::firstOrCreate(
                    [
                        'id_butir_rawas' => $butir->id_butir_rawas,
                        'tahap_review' => 'tindak_lanjut',
                    ],
                    [
                        'id_tindak_lanjut' => null,
                        'komite_id' => null,
                        'hasil_review' => null,
                        'deliverables' => null,
                        'dokumen' => null,
                        'status' => 'belum_ditanggapi',
                        'created_by' => $butir->record?->created_by,
                        'updated_by' => $butir->record?->created_by,
                    ]
                );
            }
        });

        $query = RawasReview::with([
            'butir.record.creator',
            'butir.cluster',
            'butir.subCluster',
            'butir.butirPics.unitKerja.direktorat',
            'butir.tindakLanjuts.creator',
            'butir.tindakLanjuts.butirPic.unitKerja.direktorat',
            'creator',
            'updater',
        ])
            ->where('tahap_review', 'tindak_lanjut')
            ->whereHas('butir.tindakLanjuts');

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('updated_at', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('updated_at', '<=', $request->tanggal_selesai);
        }

        if ($request->filled('cluster_id')) {
            $query->whereHas('butir', function ($butirQuery) use ($request) {
                $butirQuery->where('cluster_id', $request->cluster_id);
            });
        }

        if ($request->filled('sub_cluster_id')) {
            $query->whereHas('butir', function ($butirQuery) use ($request) {
                $butirQuery->where('sub_cluster_id', $request->sub_cluster_id);
            });
        }

        if ($request->filled('direktorat_id')) {
            $query->whereHas('butir.butirPics', function ($picQuery) {
                $picQuery->where('jenis_pic', 'unit');
            });
        }

        $unitKerjaId = $request->input('unit_kerja_id', $request->input('unit_kerja_pendukung_id'));

        if (!empty($unitKerjaId)) {
            $query->whereHas('butir.butirPics', function ($picQuery) use ($unitKerjaId) {
                $picQuery->where('jenis_pic', 'unit')
                    ->where('unit_kerja_id', $unitKerjaId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('id_butir_rawas', 'like', "%{$keyword}%")
                    ->orWhere('hasil_review', 'like', "%{$keyword}%")
                    ->orWhere('deliverables', 'like', "%{$keyword}%")
                    ->orWhere('status', 'like', "%{$keyword}%")
                    ->orWhere('tahap_review', 'like', "%{$keyword}%")

                    ->orWhereHas('butir', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_rawas', 'like', "%{$keyword}%")
                            ->orWhere('agenda_rawas', 'like', "%{$keyword}%")
                            ->orWhere('keputusan_rawas', 'like', "%{$keyword}%")
                            ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                                $recordQuery->where('id_rawas', 'like', "%{$keyword}%")
                                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                                    ->orWhere('perihal_surat', 'like', "%{$keyword}%");
                            })
                            ->orWhereHas('tindakLanjuts', function ($tlQuery) use ($keyword) {
                                $tlQuery->where('tindak_lanjut', 'like', "%{$keyword}%")
                                    ->orWhere('deliverables', 'like', "%{$keyword}%");
                            })
                            ->orWhereHas('butirPics.unitKerja', function ($unitQuery) use ($keyword) {
                                $unitQuery->where('kode_unit', 'like', "%{$keyword}%")
                                    ->orWhere('nama_unit', 'like', "%{$keyword}%");
                            });
                    });
            });
        }

        $reviews = $query
            ->latest()
            ->paginate(2)
            ->withQueryString();

        $clusters = RawasCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $statusOptions = [
            'belum_ditanggapi' => 'Belum Direviu',
            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];

        return view('layouts.rawas.reviu', compact(
            'reviews',
            'clusters',
            'unitKerjas',
            'statusOptions'
        ));
    }

    public function update(Request $request, RawasReview $review)
    {
        $user = User::find(Auth::id());

        $review->load([
            'butir.record',
            'butir.record.butirRawas.reviewTindakLanjut',
            'butir.tindakLanjuts',
        ]);

        if (!$user || !$this->canSubmitRawasReview($user, $review)) {
            abort(403, 'Anda tidak memiliki akses untuk mereviu data ini.');
        }

        if ($review->tahap_review !== 'tindak_lanjut') {
            return back()->with('error', 'Data reviu RAWAS tidak valid.');
        }

        if ($review->status === 'selesai_tuntas') {
            return back()->with('error', 'Reviu ini sudah selesai diproses dan tidak dapat diubah.');
        }

        if (!$review->butir || $review->butir->tindakLanjuts->count() === 0) {
            return back()->with('error', 'Butir RAWAS belum memiliki tindak lanjut.');
        }

        $validated = $request->validate([
            'hasil_review' => ['required', 'string'],
            'deliverables' => ['nullable', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
            'status' => [
                'required',
                'in:belum_ditanggapi,dalam_proses_reviu_dewan_pengawas,selesai_tuntas',
            ],
        ]);

        DB::connection('mysql_rawas')->transaction(function () use ($request, $validated, $review, $user) {
            $oldReview = $review->toArray();
            $oldRecord = $review->butir?->record?->toArray();

            $dokumenPath = $review->dokumen;

            if ($request->hasFile('dokumen')) {
                if ($review->dokumen && Storage::disk('public')->exists($review->dokumen)) {
                    Storage::disk('public')->delete($review->dokumen);
                }

                $dokumenPath = $request->file('dokumen')->store('dokumen/reviu-rawas', 'public');
            }

            $review->update([
                'hasil_review' => $validated['hasil_review'],
                'deliverables' => $validated['deliverables'] ?? null,
                'dokumen' => $dokumenPath,
                'status' => $validated['status'],
                'updated_by' => $user->id,
            ]);

            $record = $review->butir?->record;

            if ($validated['status'] === 'selesai_tuntas' && $review->butir) {
                $review->butir->markSelesaiTuntas($user->id);
                $record?->refresh()->syncStatusFromButir($user->id);
            }

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'rawas',
                'database_name' => 'sidewas_rawas',
                'table_name' => 'tb_review',
                'record_key' => $review->id_butir_rawas,
                'action' => 'update_review_butir',
                'description' => 'User melakukan reviu tindak lanjut RAWAS per butir.',
                'old_values' => [
                    'review' => $oldReview,
                    'record' => $oldRecord,
                ],
                'new_values' => [
                    'review' => $review->fresh()->toArray(),
                    'record' => $record?->fresh()?->toArray(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('rawas.reviu.index')
            ->with('success', 'Reviu RAWAS berhasil disimpan.');
    }

    public function downloadDokumen(RawasReview $review)
    {
        $user = User::find(Auth::id());

        $review->load('butir.record');

        if (!$user || !$user->canAccessRawasReview()) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen ini.');
        }

        if (!$review->dokumen) {
            abort(404, 'Dokumen reviu tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $review->dokumen);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        return response()->download($filePath);
    }

    private function canReviewAllRawas(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    private function canSubmitRawasReview(User $user, RawasReview $review): bool
    {
        if ($this->canReviewAllRawas($user)) {
            return true;
        }

        return $this->canReviewOwnRawasRecord($user)
            && (int) $review->butir?->record?->created_by === (int) $user->id;
    }

    private function canReviewOwnRawasRecord(User $user): bool
    {
        return $user->hasRoleType('admin_rawas')
            || $user->hasRoleType('moderator_rawas');
    }
}
