<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\SnpCluster;
use App\Models\UnitKerja;
use App\Models\LogActivity;
use App\Models\SnpReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Storage;

class ReviuSnpController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpReview()) {
            abort(403, 'Anda tidak memiliki akses ke halaman reviu SNP.');
        }

        $komiteIds = $user->komiteIds();

        $query = SnpReview::with([
            'butir.record.cluster',
            'butir.record.subCluster',
            'tanggapan.creator',
            'tindakLanjut.creator',
            'komite',
        ])
            ->where(function ($q) {
                $q->where(function ($tanggapanQuery) {
                    $tanggapanQuery->where('tahap_review', 'tanggapan')
                        ->whereNotNull('id_tanggapan');
                })
                    ->orWhere(function ($tlQuery) {
                        $tlQuery->where('tahap_review', 'tindak_lanjut')
                            ->whereNotNull('id_tindak_lanjut');
                    });
            });

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

        if ($request->filled('tahap_review')) {
            $query->where('tahap_review', $request->tahap_review);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('id_butir_snp', 'like', "%{$keyword}%")
                    ->orWhereHas('butir', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('butir_snp', 'like', "%{$keyword}%")
                            ->orWhere('id_butir_snp', 'like', "%{$keyword}%")
                            ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                                $recordQuery->where('id_snp', 'like', "%{$keyword}%")
                                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                                    ->orWhere('perihal_surat', 'like', "%{$keyword}%");
                            });
                    })
                    ->orWhereHas('tanggapan', function ($tanggapanQuery) use ($keyword) {
                        $tanggapanQuery->where('tanggapan', 'like', "%{$keyword}%")
                            ->orWhere('deliverables', 'like', "%{$keyword}%");
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

        $clusters = SnpCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $komites = Komite::orderBy('nama_komite')->get();

        $statusOptions = [
            'belum_ditanggapi' => 'Belum Ditanggapi',
            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
            'dalam_proses_tindak_lanjut_direksi' => 'Dalam Proses Tindak Lanjut Direksi',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];

        return view('layouts.snp.reviu', compact(
            'reviews',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statusOptions'
        ));

        // return view('layouts.snp.reviu', compact('reviews'));
    }

    public function update(Request $request, SnpReview $review)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canReviewSnpByKomite($review->komite_id)) {
            abort(403, 'Anda tidak memiliki akses untuk mereviu data ini.');
        }

        $review->load([
            'tanggapan',
            'tindakLanjut',
            'butir.record',
        ]);

        if (
            ($review->tahap_review === 'tanggapan' && $review->status === 'dalam_proses_tindak_lanjut_direksi') ||
            ($review->tahap_review === 'tindak_lanjut' && $review->status === 'selesai_tuntas')
        ) {
            return back()->with('error', 'Reviu ini sudah selesai diproses dan tidak dapat diubah.');
        }

        if (
            ($review->tahap_review === 'tanggapan' && empty($review->id_tanggapan)) ||
            ($review->tahap_review === 'tindak_lanjut' && empty($review->id_tindak_lanjut))
        ) {
            return back()->with('error', 'Data reviu tidak valid.');
        }

        $validated = $request->validate([
            'hasil_review' => ['required', 'string'],
            'deliverables' => ['nullable', 'string'],
            'status' => [
                'required',
                'in:belum_ditanggapi,dalam_proses_reviu_dewan_pengawas,dalam_proses_tindak_lanjut_direksi,selesai_tuntas',
            ],
            'status_pengajuan_tgl' => ['nullable', 'in:pending,disetujui,ditolak'],
        ]);

        DB::connection('mysql_snp')->transaction(function () use ($request, $validated, $review, $user) {
            $oldReview = $review->toArray();
            $oldTanggapan = $review->tanggapan?->toArray();
            $oldRecord = $review->butir?->record?->toArray();

            $review->update([
                'hasil_review' => $validated['hasil_review'],
                'deliverables' => $validated['deliverables'] ?? null,
                'status' => $validated['status'],
            ]);

            if (
                $validated['status'] === 'dalam_proses_tindak_lanjut_direksi'
                && $review->butir
                && $review->butir->record
            ) {
                $review->butir->record->update([
                    'status' => 'dalam_proses',
                ]);
            }

            if (
                $review->tahap_review === 'tindak_lanjut'
                && $validated['status'] === 'selesai_tuntas'
                && $review->butir
                && $review->butir->record
            ) {
                $review->butir->record->update([
                    'status' => 'selesai',
                ]);
            }

            if ($review->tahap_review === 'tanggapan' && $review->tanggapan) {
                $review->tanggapan->update([
                    'status_pengajuan_tgl' => $validated['status_pengajuan_tgl'] ?? 'pending',
                ]);

                if (
                    ($validated['status_pengajuan_tgl'] ?? null) === 'disetujui'
                    && !empty($review->tanggapan->ubah_tgl)
                    && $review->butir
                    && $review->butir->record
                ) {
                    $review->butir->record->update([
                        'jth_tempo' => $review->tanggapan->ubah_tgl,
                    ]);
                }
            }

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_review',
                'record_key' => $review->id_butir_snp,
                'action' => 'update_review_tanggapan',
                'description' => 'User melakukan reviu tanggapan SNP.',
                'old_values' => [
                    'review' => $oldReview,
                    'tanggapan' => $oldTanggapan,
                    'record' => $oldRecord,
                ],
                'new_values' => [
                    'review' => $review->fresh()->toArray(),
                    'tanggapan' => $review->tanggapan?->fresh()?->toArray(),
                    'record' => $review->butir?->record?->fresh()?->toArray(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('snp.reviu.index')
            ->with('success', 'Reviu tanggapan SNP berhasil disimpan.');
    }

    public function downloadDokumen(SnpReview $review)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canReviewSnpByKomite($review->komite_id)) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen ini.');
        }

        $review->load(['tanggapan', 'tindakLanjut']);

        $dokumen = null;

        if ($review->tahap_review === 'tanggapan') {
            $dokumen = $review->tanggapan?->dokumen;
        }

        if ($review->tahap_review === 'tindak_lanjut') {
            $dokumen = $review->tindakLanjut?->dokumen;
        }

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
