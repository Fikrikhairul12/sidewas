<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\SnpCluster;
use App\Models\UnitKerja;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpReview;
use App\Models\SnpTindakLanjut;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TindakLanjutSnpController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpTindakLanjut()) {
            abort(403, 'Anda tidak memiliki akses ke halaman tindak lanjut SNP.');
        }

        $butirsTindakLanjutQuery = SnpButir::with([
            'record.cluster',
            'record.subCluster',
            'record.creator',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tanggapan',
            'reviews',
            'tindakLanjuts.creator',
            'tindakLanjuts.reviews.komite',
        ])
            ->whereHas('reviews', function ($reviewQuery) {
                $reviewQuery->where('tahap_review', 'tanggapan')
                    ->where('status', 'dalam_proses_tindak_lanjut_direksi');
            })
            ->whereHas('record');

        if ($request->filled('tanggal_mulai')) {
            $butirsTindakLanjutQuery->whereHas('tindakLanjuts', function ($tlQuery) use ($request) {
                $tlQuery->whereDate('created_at', '>=', $request->tanggal_mulai);
            });
        }

        if ($request->filled('tanggal_selesai')) {
            $butirsTindakLanjutQuery->whereHas('tindakLanjuts', function ($tlQuery) use ($request) {
                $tlQuery->whereDate('created_at', '<=', $request->tanggal_selesai);
            });
        }

        if ($request->filled('cluster_id')) {
            $butirsTindakLanjutQuery->whereHas('record', function ($recordQuery) use ($request) {
                $recordQuery->where('cluster_id', $request->cluster_id);
            });
        }

        if ($request->filled('sub_cluster_id')) {
            $butirsTindakLanjutQuery->whereHas('record', function ($recordQuery) use ($request) {
                $recordQuery->where('sub_cluster_id', $request->sub_cluster_id);
            });
        }

        if ($request->filled('direktorat_id')) {
            $unitKerjaIds = UnitKerja::where('direktorat_id', $request->direktorat_id)
                ->pluck('id')
                ->toArray();

            $butirsTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($unitKerjaIds) {
                $picQuery->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $butirsTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $request->unit_kerja_utama_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $butirsTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('komite_id')) {
            $butirsTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'komite')
                    ->where('komite_id', $request->komite_id);
            });
        }

        if ($request->filled('status')) {
            $butirsTindakLanjutQuery->whereHas('tindakLanjuts.reviews', function ($reviewQuery) use ($request) {
                $reviewQuery->where('tahap_review', 'tindak_lanjut')
                    ->where('status', $request->status);
            });
        }

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_snp')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $butirsTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $butirsTindakLanjutQuery->where(function ($q) use ($keyword) {
                $q->where('id_butir_snp', 'like', "%{$keyword}%")
                    ->orWhere('butir_snp', 'like', "%{$keyword}%")
                    ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                        $recordQuery->where('id_snp', 'like', "%{$keyword}%")
                            ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                            ->orWhere('perihal_surat', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('tanggapan', function ($tanggapanQuery) use ($keyword) {
                        $tanggapanQuery->where('tanggapan', 'like', "%{$keyword}%")
                            ->orWhere('deliverables', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('tindakLanjuts', function ($tlQuery) use ($keyword) {
                        $tlQuery->where('tindak_lanjut', 'like', "%{$keyword}%")
                            ->orWhere('deliverables', 'like', "%{$keyword}%");
                    });
            });
        }

        $butirsTindakLanjut = $butirsTindakLanjutQuery
            ->orderByDesc('id')
            ->paginate(2)
            ->withQueryString();

        $butirSiapTindakLanjutQuery = SnpButir::with([
            'record',
            'butirPics.unitKerja',
            'butirPics.komite',
            'reviews',
        ])
            ->whereHas('reviews', function ($reviewQuery) {
                $reviewQuery->where('tahap_review', 'tanggapan')
                    ->where('status', 'dalam_proses_tindak_lanjut_direksi');
            })
            ->whereDoesntHave('tindakLanjuts');

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_snp')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $butirSiapTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        $butirSiapTindakLanjut = $butirSiapTindakLanjutQuery
            ->orderBy('id', 'desc')
            ->get();

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

        return view('layouts.snp.tindak-lanjut', compact(
            'butirsTindakLanjut',
            'butirSiapTindakLanjut',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statusOptions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'butir_id' => ['required', 'integer', 'exists:mysql_snp.tb_butir_snp,id'],
            'tindak_lanjut' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        $user = User::find(Auth::id());

        $butir = SnpButir::with([
            'record',
            'butirPics',
            'reviews',
        ])
            ->findOrFail($validated['butir_id']);

        if (!$user || !$user->canCreateSnpTindakLanjutForButir($butir)) {
            abort(403, 'Anda tidak memiliki akses untuk membuat tindak lanjut pada butir ini.');
        }

        $hasReadyReview = $butir->reviews()
            ->where('tahap_review', 'tanggapan')
            ->where('status', 'dalam_proses_tindak_lanjut_direksi')
            ->exists();

        if (!$hasReadyReview) {
            return back()->with('error', 'Butir SNP ini belum masuk tahap tindak lanjut direksi.');
        }

        DB::connection('mysql_snp')->transaction(function () use ($request, $validated, $butir, $user) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/tindak-lanjut-snp', 'public');
            }

            $tindakLanjut = SnpTindakLanjut::create([
                'id_butir_snp' => $butir->id_butir_snp,
                'tindak_lanjut' => $validated['tindak_lanjut'],
                'deliverables' => $validated['deliverables'],
                'dokumen' => $dokumenPath,
                'jth_tempo' => $butir->record?->jth_tempo,
            ]);

            $komitePic = $butir->butirPics()
                ->where('jenis_pic', 'komite')
                ->whereNotNull('komite_id')
                ->first();

            $review = SnpReview::create([
                'id_butir_snp' => $butir->id_butir_snp,
                'id_tanggapan' => null,
                'id_tindak_lanjut' => $tindakLanjut->id,
                'tahap_review' => 'tindak_lanjut',
                'komite_id' => $komitePic?->komite_id,
                'hasil_review' => null,
                'deliverables' => null,
                'status' => 'belum_ditanggapi',
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_tindak_lanjut',
                'record_key' => $butir->id_butir_snp,
                'action' => 'create',
                'description' => 'User membuat tindak lanjut SNP dan sistem membuat review tindak lanjut.',
                'old_values' => null,
                'new_values' => [
                    'butir' => $butir->load('record')->toArray(),
                    'tindak_lanjut' => $tindakLanjut->toArray(),
                    'review' => $review->toArray(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('snp.tindak-lanjut.index')
            ->with('success', 'Tindak lanjut SNP berhasil disimpan.');
    }
}
