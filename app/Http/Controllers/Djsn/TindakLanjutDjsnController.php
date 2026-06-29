<?php

namespace App\Http\Controllers\Djsn;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\DjsnButir;
use App\Models\DjsnCluster;
use App\Models\DjsnReview;
use App\Models\DjsnTindakLanjut;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TindakLanjutDjsnController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnTindakLanjut()) {
            abort(403, 'Anda tidak memiliki akses ke halaman tindak lanjut DJSN.');
        }

        $butirsRiwayatQuery = DjsnButir::with([
            'record',
            'cluster',
            'subCluster',
            'record.creator',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tanggapan',
            'reviews.komite',
            'tindakLanjuts.creator',
            'tindakLanjuts.reviews.komite',
        ])
            ->whereHas('record')
            ->whereHas('reviews', function ($reviewQuery) {
                $reviewQuery->where('tahap_review', 'tanggapan')
                    ->where('status', 'dalam_proses_tindak_lanjut_direksi');
            });

        if ($request->filled('cluster_id')) {
            $butirsRiwayatQuery->where('cluster_id', $request->cluster_id);
        }

        if ($request->filled('sub_cluster_id')) {
            $butirsRiwayatQuery->where('sub_cluster_id', $request->sub_cluster_id);
        }

        if ($request->filled('direktorat_id')) {
            $unitKerjaIds = UnitKerja::where('direktorat_id', $request->direktorat_id)
                ->pluck('id')
                ->toArray();

            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($unitKerjaIds) {
                $picQuery->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $request->unit_kerja_utama_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('komite_id')) {
            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'komite')
                    ->where('komite_id', $request->komite_id);
            });
        }

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_djsn')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        $butirsRiwayat = $butirsRiwayatQuery
            ->orderByDesc('id')
            ->get();

        $rows = collect();

        foreach ($butirsRiwayat as $butir) {
            if ($butir->tindakLanjuts->count() > 0) {
                foreach ($butir->tindakLanjuts->sortByDesc('id') as $tindakLanjut) {
                    $rows->push([
                        'type' => 'tindak_lanjut',
                        'butir' => $butir,
                        'item' => $tindakLanjut,
                    ]);
                }
            } else {
                $rows->push([
                    'type' => 'kandidat',
                    'butir' => $butir,
                    'item' => null,
                ]);
            }
        }

        if ($request->filled('tanggal_mulai')) {
            $rows = $rows->filter(function ($row) use ($request) {
                $item = $row['item'];

                if (!$item) {
                    return false;
                }

                return Carbon::parse($item->created_at)->toDateString() >= $request->tanggal_mulai;
            });
        }

        if ($request->filled('tanggal_selesai')) {
            $rows = $rows->filter(function ($row) use ($request) {
                $item = $row['item'];

                if (!$item) {
                    return false;
                }

                return Carbon::parse($item->created_at)->toDateString() <= $request->tanggal_selesai;
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;

            $rows = $rows->filter(function ($row) use ($status) {
                return $row['butir']?->statusTindakLanjut() === $status;
            });
        }

        if ($request->filled('keyword')) {
            $keyword = strtolower(trim($request->keyword));

            $rows = $rows->filter(function ($row) use ($keyword) {
                $butir = $row['butir'];
                $item = $row['item'];
                $record = $butir?->record;
                $tanggapan = $butir?->tanggapan;

                $reviewTerakhir = $item
                    ? $item->reviews
                        ->where('tahap_review', 'tindak_lanjut')
                        ->sortByDesc('id')
                        ->first()
                    : null;

                $values = [
                    $butir?->id_butir_djsn,
                    $butir?->butir_djsn,
                    $record?->id_djsn,
                    $record?->nomor_surat,
                    $record?->perihal_surat,
                    $tanggapan?->tanggapan,
                    $tanggapan?->deliverables,
                    $item?->tindak_lanjut,
                    $item?->deliverables,
                    $reviewTerakhir?->hasil_review,
                    $reviewTerakhir?->deliverables,
                    $reviewTerakhir?->status,
                ];

                foreach ($values as $value) {
                    if (str_contains(strtolower((string) $value), $keyword)) {
                        return true;
                    }
                }

                return false;
            });
        }

        $rows = $rows
            ->sortByDesc(function ($row) {
                $item = $row['item'];
                $butir = $row['butir'];

                return $item?->id ?? ('0.' . $butir?->id);
            })
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 2;

        $tindakLanjutRows = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
        $butirSiapTindakLanjutQuery = DjsnButir::with([
            'record',
            'cluster',
            'subCluster',
            'butirPics.unitKerja',
            'butirPics.komite',
            'reviews',
        ])
            ->whereHas('reviews', function ($reviewQuery) {
                $reviewQuery->where('tahap_review', 'tanggapan')
                    ->where('status', 'dalam_proses_tindak_lanjut_direksi');
            });

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_djsn')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $butirSiapTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        $butirSiapTindakLanjut = $butirSiapTindakLanjutQuery
            ->orderBy('id', 'desc')
            ->get();

        $clusters = DjsnCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $komites = Komite::orderBy('nama_komite')->get();

        $statusOptions = [
            'terbit' => 'Terbit',
            'dalam_proses' => 'Dalam Proses',
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];

        return view('layouts.djsn.tindak-lanjut', compact(
            'tindakLanjutRows',
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
            'butir_id' => ['required', 'integer', 'exists:mysql_djsn.tb_butir_djsn,id'],
            'tindak_lanjut' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        $user = User::find(Auth::id());

        $butir = DjsnButir::with([
            'record',
            'butirPics',
            'reviews',
        ])
            ->findOrFail($validated['butir_id']);

        if (!$user || !$user->canCreateDjsnTindakLanjutForButir($butir)) {
            abort(403, 'Anda tidak memiliki akses untuk membuat tindak lanjut pada butir ini.');
        }

        $hasReadyReview = $butir->reviews()
            ->where('tahap_review', 'tanggapan')
            ->where('status', 'dalam_proses_tindak_lanjut_direksi')
            ->exists();

        if (!$hasReadyReview) {
            return back()->with('error', 'Butir DJSN ini belum masuk tahap tindak lanjut direksi.');
        }

        DB::connection('mysql_djsn')->transaction(function () use ($request, $validated, $butir, $user) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/tindak-lanjut-djsn', 'public');
            }

            $tindakLanjut = DjsnTindakLanjut::create([
                'id_butir_djsn' => $butir->id_butir_djsn,
                'tindak_lanjut' => $validated['tindak_lanjut'],
                'deliverables' => $validated['deliverables'],
                'dokumen' => $dokumenPath,
                'jth_tempo' => $butir->record?->jth_tempo,
            ]);

            $komitePic = $butir->butirPics()
                ->where('jenis_pic', 'komite')
                ->whereNotNull('komite_id')
                ->first();

            $review = DjsnReview::create([
                'id_butir_djsn' => $butir->id_butir_djsn,
                'id_tanggapan' => null,
                'id_tindak_lanjut' => $tindakLanjut->id,
                'tahap_review' => 'tindak_lanjut',
                'komite_id' => $komitePic?->komite_id,
                'hasil_review' => null,
                'deliverables' => null,
                'status' => 'belum_ditanggapi',
            ]);

            $butir->refresh()->syncStatusFromTindakLanjut($user->id);
            $butir->record?->refresh()->syncStatusFromButir($user->id);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'djsn',
                'database_name' => 'sidewas_djsn',
                'table_name' => 'tb_tindak_lanjut',
                'record_key' => $butir->id_butir_djsn,
                'action' => 'create',
                'description' => 'User membuat tindak lanjut DJSN dan sistem membuat review tindak lanjut.',
                'old_values' => null,
                'new_values' => [
                    'butir' => $butir->load('record')->toArray(),
                    'tindak_lanjut' => $tindakLanjut->toArray(),
                    'review' => $review->toArray(),
                    'status_butir' => $butir->fresh()->statusTindakLanjut(),
                    'status_record' => $butir->record?->fresh()?->status,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('djsn.tindak-lanjut.index')
            ->with('success', 'Tindak lanjut DJSN berhasil disimpan.');
    }
}
