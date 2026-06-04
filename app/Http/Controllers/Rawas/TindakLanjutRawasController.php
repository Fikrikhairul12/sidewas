<?php

namespace App\Http\Controllers\Rawas;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\RawasButir;
use App\Models\RawasCluster;
use App\Models\RawasReview;
use App\Models\RawasTindakLanjut;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TindakLanjutRawasController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasTindakLanjut()) {
            abort(403, 'Anda tidak memiliki akses ke halaman tindak lanjut RAWAS.');
        }

        /**
         * RAWAS tidak punya tanggapan.
         * Jadi tabel riwayat TL berbasis hybrid:
         * 1. Butir yang belum punya TL tetap tampil sebagai "Menunggu Tindak Lanjut".
         * 2. Butir yang punya banyak TL tampil sebanyak jumlah TL.
         */
        $butirsRiwayatQuery = RawasButir::with([
            'record.cluster',
            'record.subCluster',
            'record.creator',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'reviews.komite',
            'tindakLanjuts.creator',
            'tindakLanjuts.reviews.komite',
        ])
            ->whereHas('record');

        if ($request->filled('cluster_id')) {
            $butirsRiwayatQuery->whereHas('record', function ($recordQuery) use ($request) {
                $recordQuery->where('cluster_id', $request->cluster_id);
            });
        }

        if ($request->filled('sub_cluster_id')) {
            $butirsRiwayatQuery->whereHas('record', function ($recordQuery) use ($request) {
                $recordQuery->where('sub_cluster_id', $request->sub_cluster_id);
            });
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

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_rawas')) {
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
                $item = $row['item'];

                if ($status === 'belum_ditindaklanjuti') {
                    return empty($item);
                }

                if (!$item) {
                    return false;
                }

                $reviewTerakhir = $item->reviews
                    ->where('tahap_review', 'tindak_lanjut')
                    ->sortByDesc('id')
                    ->first();

                return $reviewTerakhir?->status === $status;
            });
        }

        if ($request->filled('keyword')) {
            $keyword = strtolower(trim($request->keyword));

            $rows = $rows->filter(function ($row) use ($keyword) {
                $butir = $row['butir'];
                $item = $row['item'];
                $record = $butir?->record;

                $reviewTerakhir = $item
                    ? $item->reviews
                        ->where('tahap_review', 'tindak_lanjut')
                        ->sortByDesc('id')
                        ->first()
                    : null;

                $komitePic = $butir?->butirPics
                    ?->where('jenis_pic', 'komite')
                    ->first();

                $values = [
                    $butir?->id_butir_rawas,
                    $butir?->butir_rawas,
                    $record?->id_rawas,
                    $record?->nomor_surat,
                    $record?->perihal_surat,
                    $item?->tindak_lanjut,
                    $item?->deliverables,
                    $reviewTerakhir?->hasil_review,
                    $reviewTerakhir?->deliverables,
                    $reviewTerakhir?->status,
                    $reviewTerakhir?->komite?->kode_komite,
                    $reviewTerakhir?->komite?->nama_komite,
                    $komitePic?->komite?->kode_komite,
                    $komitePic?->komite?->nama_komite,
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

                return $item?->id ?? (0 - (int) $butir?->id);
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

        /**
         * Kandidat modal tambah TL:
         * RAWAS tidak punya tanggapan, jadi semua butir yang sudah dibuat boleh dipilih.
         * Tidak pakai whereDoesntHave, karena 1 butir boleh punya banyak TL.
         */
        $butirSiapTindakLanjutQuery = RawasButir::with([
            'record',
            'butirPics.unitKerja',
            'butirPics.komite',
        ])
            ->whereHas('record');

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_rawas')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $butirSiapTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        $butirSiapTindakLanjut = $butirSiapTindakLanjutQuery
            ->orderByDesc('id')
            ->get();

        $clusters = RawasCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $komites = Komite::orderBy('nama_komite')->get();

        $statusOptions = [
            'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
            'belum_ditanggapi' => 'Belum Direviu',
            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];

        return view('layouts.rawas.tindak-lanjut', compact(
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
            'butir_id' => ['required', 'integer', 'exists:mysql_rawas.tb_butir_rawas,id'],
            'tindak_lanjut' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        $user = User::find(Auth::id());

        $butir = RawasButir::with([
            'record',
            'butirPics',
            'reviews',
        ])->findOrFail($validated['butir_id']);

        if (!$user || !$user->canCreateRawasTindakLanjutForButir($butir)) {
            abort(403, 'Anda tidak memiliki akses untuk membuat tindak lanjut pada butir ini.');
        }

        DB::connection('mysql_rawas')->transaction(function () use ($request, $validated, $butir, $user) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/tindak-lanjut-rawas', 'public');
            }

            $tindakLanjut = RawasTindakLanjut::create([
                'id_butir_rawas' => $butir->id_butir_rawas,
                'tindak_lanjut' => $validated['tindak_lanjut'],
                'deliverables' => $validated['deliverables'],
                'dokumen' => $dokumenPath,
                'jth_tempo' => $butir->record?->jth_tempo,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $komitePic = $butir->butirPics()
                ->where('jenis_pic', 'komite')
                ->whereNotNull('komite_id')
                ->first();

            $review = RawasReview::create([
                'id_butir_rawas' => $butir->id_butir_rawas,
                'id_tindak_lanjut' => $tindakLanjut->id,
                'komite_id' => $komitePic?->komite_id,
                'tahap_review' => 'tindak_lanjut',
                'hasil_review' => null,
                'deliverables' => null,
                'dokumen' => null,
                'status' => 'belum_ditanggapi',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($butir->record) {
                $butir->record->update([
                    'status' => 'dalam_proses',
                    'updated_by' => $user->id,
                ]);
            }

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'rawas',
                'database_name' => 'sidewas_rawas',
                'table_name' => 'tb_tindak_lanjut',
                'record_key' => $butir->id_butir_rawas,
                'action' => 'create',
                'description' => 'User membuat tindak lanjut RAWAS dan sistem membuat review tindak lanjut.',
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
            ->route('rawas.tindak-lanjut.index')
            ->with('success', 'Tindak lanjut RAWAS berhasil disimpan.');
    }
}
