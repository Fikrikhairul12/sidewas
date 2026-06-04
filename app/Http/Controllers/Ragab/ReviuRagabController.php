<?php

namespace App\Http\Controllers\Ragab;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\RagabCluster;
use App\Models\RagabReview;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviuRagabController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabReview()) {
            abort(403, 'Anda tidak memiliki akses ke halaman reviu RAGAB.');
        }

        $komiteIds = $user->komiteIds();

        $query = RagabReview::with([
            'butir.record.cluster',
            'butir.record.subCluster',
            'tindakLanjut.creator',
            'komite',
        ])
            ->where('tahap_review', 'tindak_lanjut')
            ->whereNotNull('id_tindak_lanjut');

        if (!$user->isSuperAdmin()) {
            $query->whereIn('komite_id', $komiteIds);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('updated_at', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('updated_at', '<=', $request->tanggal_selesai);
        }

        if ($request->filled('cluster_id')) {
            $query->whereHas('butir.record', function ($recordQuery) use ($request) {
                $recordQuery->where('cluster_id', $request->cluster_id);
            });
        }

        if ($request->filled('sub_cluster_id')) {
            $query->whereHas('butir.record', function ($recordQuery) use ($request) {
                $recordQuery->where('sub_cluster_id', $request->sub_cluster_id);
            });
        }

        if ($request->filled('direktorat_id')) {
            $unitKerjaIds = UnitKerja::where('direktorat_id', $request->direktorat_id)
                ->pluck('id')
                ->toArray();

            $query->whereHas('butir.butirPics', function ($picQuery) use ($unitKerjaIds) {
                $picQuery->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $query->whereHas('butir.butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $request->unit_kerja_utama_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $query->whereHas('butir.butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('komite_id')) {
            $query->where('komite_id', $request->komite_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('id_butir_ragab', 'like', "%{$keyword}%")
                    ->orWhere('hasil_review', 'like', "%{$keyword}%")
                    ->orWhere('deliverables', 'like', "%{$keyword}%")
                    ->orWhere('status', 'like', "%{$keyword}%")
                    ->orWhere('tahap_review', 'like', "%{$keyword}%")

                    ->orWhereHas('butir', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_ragab', 'like', "%{$keyword}%")
                            ->orWhere('butir_ragab', 'like', "%{$keyword}%")
                            ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                                $recordQuery->where('id_ragab', 'like', "%{$keyword}%")
                                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                                    ->orWhere('perihal_surat', 'like', "%{$keyword}%");
                            });
                    })

                    ->orWhereHas('tindakLanjut', function ($tlQuery) use ($keyword) {
                        $tlQuery->where('tindak_lanjut', 'like', "%{$keyword}%")
                            ->orWhere('deliverables', 'like', "%{$keyword}%");
                    });
            });
        }

        $reviews = $query
            ->latest()
            ->paginate(2)
            ->withQueryString();

        $clusters = RagabCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $komites = Komite::orderBy('nama_komite')->get();

        $statusOptions = [
            'belum_ditanggapi' => 'Belum Direviu',
            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];

        return view('layouts.ragab.reviu', compact(
            'reviews',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statusOptions'
        ));
    }

    public function update(Request $request, RagabReview $review)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canReviewRagabByKomite($review->komite_id)) {
            abort(403, 'Anda tidak memiliki akses untuk mereviu data ini.');
        }

        $review->load([
            'tindakLanjut',
            'butir.record',
        ]);

        if ($review->tahap_review !== 'tindak_lanjut' || empty($review->id_tindak_lanjut)) {
            return back()->with('error', 'Data reviu RAGAB tidak valid.');
        }

        if ($review->status === 'selesai_tuntas') {
            return back()->with('error', 'Reviu ini sudah selesai diproses dan tidak dapat diubah.');
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

        DB::connection('mysql_ragab')->transaction(function () use ($request, $validated, $review, $user) {
            $oldReview = $review->toArray();
            $oldRecord = $review->butir?->record?->toArray();

            $dokumenPath = $review->dokumen;

            if ($request->hasFile('dokumen')) {
                if ($review->dokumen && Storage::disk('public')->exists($review->dokumen)) {
                    Storage::disk('public')->delete($review->dokumen);
                }

                $dokumenPath = $request->file('dokumen')->store('dokumen/reviu-ragab', 'public');
            }

            $review->update([
                'hasil_review' => $validated['hasil_review'],
                'deliverables' => $validated['deliverables'] ?? null,
                'dokumen' => $dokumenPath,
                'status' => $validated['status'],
            ]);

            if (
                $validated['status'] === 'selesai_tuntas'
                && $review->butir
                && $review->butir->record
            ) {
                $review->butir->record->update([
                    'status' => 'selesai',
                ]);
            }

            if (
                $validated['status'] === 'dalam_proses_reviu_dewan_pengawas'
                && $review->butir
                && $review->butir->record
            ) {
                $review->butir->record->update([
                    'status' => 'dalam_proses',
                ]);
            }

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'ragab',
                'database_name' => 'sidewas_ragab',
                'table_name' => 'tb_review',
                'record_key' => $review->id_butir_ragab,
                'action' => 'update_review_tindak_lanjut',
                'description' => 'User melakukan reviu tindak lanjut RAGAB.',
                'old_values' => [
                    'review' => $oldReview,
                    'record' => $oldRecord,
                ],
                'new_values' => [
                    'review' => $review->fresh()->toArray(),
                    'record' => $review->butir?->record?->fresh()?->toArray(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('ragab.reviu.index')
            ->with('success', 'Reviu RAGAB berhasil disimpan.');
    }

    public function downloadDokumen(RagabReview $review)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canReviewRagabByKomite($review->komite_id)) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen ini.');
        }

        $review->load(['tindakLanjut']);

        $dokumen = $review->tindakLanjut?->dokumen;

        if (!$dokumen) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $dokumen);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        return response()->download($filePath);
    }
}