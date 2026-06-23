<?php

namespace App\Http\Controllers\Eksternal;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\LogActivity;
use App\Models\EksternalButir;
use App\Models\EksternalCluster;
use App\Models\EksternalReview;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviuEksternalController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessEksternalReview()) {
            abort(403, 'Anda tidak memiliki akses ke halaman reviu EKSTERNAL.');
        }

        /*
         * Konsep final EKSTERNAL:
         * - Reviu dilakukan per 1 butir EKSTERNAL.
         * - 1 butir bisa memiliki banyak tindak lanjut dari beberapa PIC unit.
         * - tb_review tidak lagi terikat ke id_tindak_lanjut.
         * - Review dibuat/diambil berdasarkan id_butir_eksternal + tahap_review = tindak_lanjut.
         */
        $butirsForReviewQuery = EksternalButir::with([
            'record',
            'butirPics.unitKerja',
        ])
            ->whereHas('record')
            ->whereHas('tindakLanjuts');

        if (!$this->canReviewAllEksternal($user)) {
            $butirsForReviewQuery->whereHas('record', function ($recordQuery) use ($user) {
                $recordQuery->where('created_by', $user->id);
            });
        }

        $butirsForReview = $butirsForReviewQuery->get();

        DB::connection('mysql_eksternal')->transaction(function () use ($butirsForReview) {
            foreach ($butirsForReview as $butir) {

                EksternalReview::firstOrCreate(
                    [
                        'id_butir_eksternal' => $butir->id_butir_eksternal,
                        'tahap_review' => 'tindak_lanjut',
                    ],
                    [
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

        $query = EksternalReview::with([
            'butir.record.creator',
            'butir.cluster',
            'butir.subCluster',
            'butir.butirDirektorats.direktorat',
            'butir.butirPics.unitKerja.direktorat',
            'butir.tindakLanjuts.creator',
            'butir.tindakLanjuts.unitKerja.direktorat',
            'creator',
            'updater',
        ])
            ->where('tahap_review', 'tindak_lanjut')
            ->whereHas('butir.tindakLanjuts');

        if (!$this->canReviewAllEksternal($user)) {
            $query->whereHas('butir.record', function ($recordQuery) use ($user) {
                $recordQuery->where('created_by', $user->id);
            });
        }

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
            $query->whereHas('butir.butirDirektorats', function ($direktoratQuery) use ($request) {
                $direktoratQuery->where('direktorat_id', $request->direktorat_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $query->whereHas('butir.butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'unit')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('id_butir_eksternal', 'like', "%{$keyword}%")
                    ->orWhere('hasil_review', 'like', "%{$keyword}%")
                    ->orWhere('deliverables', 'like', "%{$keyword}%")
                    ->orWhere('status', 'like', "%{$keyword}%")
                    ->orWhereHas('butir', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_eksternal', 'like', "%{$keyword}%")
                            ->orWhere('agenda_eksternal', 'like', "%{$keyword}%")
                            ->orWhere('keputusan_eksternal', 'like', "%{$keyword}%")
                            ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                                $recordQuery->where('id_eksternal', 'like', "%{$keyword}%")
                                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                                    ->orWhere('nama_instansi_pengundang', 'like', "%{$keyword}%")
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
            ->paginate(1)
            ->withQueryString();

        $clusters = EksternalCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $statusOptions = [
            'belum_ditanggapi' => 'Belum Direviu',
            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];

        return view('layouts.eksternal.reviu', compact(
            'reviews',
            'clusters',
            'direktorats',
            'unitKerjas',
            'statusOptions'
        ));
    }

    public function update(Request $request, EksternalReview $review)
    {
        $user = User::find(Auth::id());

        $review->load([
            'butir.record',
            'butir.record.butirEksternal.reviewTindakLanjut',
            'butir.tindakLanjuts',
        ]);

        if (!$user || !$this->canReviewEksternalReview($user, $review)) {
            abort(403, 'Anda tidak memiliki akses untuk mereviu data ini.');
        }

        if ($review->tahap_review !== 'tindak_lanjut') {
            return back()->with('error', 'Data reviu EKSTERNAL tidak valid.');
        }

        if ($review->status === 'selesai_tuntas') {
            return back()->with('error', 'Reviu ini sudah selesai diproses dan tidak dapat diubah.');
        }

        if (!$review->butir || $review->butir->tindakLanjuts->count() === 0) {
            return back()->with('error', 'Butir EKSTERNAL belum memiliki tindak lanjut.');
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

        DB::connection('mysql_eksternal')->transaction(function () use ($request, $validated, $review, $user) {
            $oldReview = $review->toArray();
            $oldRecord = $review->butir?->record?->toArray();

            $dokumenPath = $review->dokumen;

            if ($request->hasFile('dokumen')) {
                if ($review->dokumen && Storage::disk('public')->exists($review->dokumen)) {
                    Storage::disk('public')->delete($review->dokumen);
                }

                $dokumenPath = $request->file('dokumen')->store('dokumen/reviu-eksternal', 'public');
            }

            $review->update([
                'hasil_review' => $validated['hasil_review'],
                'deliverables' => $validated['deliverables'] ?? null,
                'dokumen' => $dokumenPath,
                'status' => $validated['status'],
                'updated_by' => $user->id,
            ]);

            $record = $review->butir?->record;

            if ($record && $validated['status'] === 'selesai_tuntas') {
                $record->load('butirEksternal.reviewTindakLanjut');

                $allButirsReviewed = $record->butirEksternal->count() > 0
                    && $record->butirEksternal->every(function ($butir) {
                        return $butir->reviewTindakLanjut?->status === 'selesai_tuntas';
                    });

                if ($allButirsReviewed) {
                    $record->update([
                        'status' => 'tuntas',
                        'updated_by' => $user->id,
                    ]);
                }
            }

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'eksternal',
                'database_name' => 'sidewas_eksternal',
                'table_name' => 'tb_review',
                'record_key' => $review->id_butir_eksternal,
                'action' => 'update_review_butir',
                'description' => 'User melakukan reviu tindak lanjut EKSTERNAL per butir.',
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
            ->route('eksternal.reviu.index')
            ->with('success', 'Reviu EKSTERNAL berhasil disimpan.');
    }

    public function downloadDokumen(EksternalReview $review)
    {
        $user = User::find(Auth::id());

        $review->load('butir.record');

        if (!$user || !$this->canReviewEksternalReview($user, $review)) {
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

    private function canReviewAllEksternal(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRoleType('admin_eksternal')
            || $user->hasRoleType('moderator_eksternal');
    }

    private function canReviewEksternalReview(User $user, EksternalReview $review): bool
    {
        if ($this->canReviewAllEksternal($user)) {
            return true;
        }

        return (int) $review->butir?->record?->created_by === (int) $user->id;
    }
}
