<?php

namespace App\Http\Controllers\Rawas;

use App\Http\Controllers\Controller;
use App\Models\LogActivity;
use App\Models\RawasButir;
use App\Models\RawasButirPic;
use App\Models\RawasCluster;
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

        $butirsRiwayatQuery = RawasButir::with([
            'record.creator',
            'cluster',
            'subCluster',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tindakLanjuts.creator',
            'tindakLanjuts.butirPic.unitKerja.direktorat',
            'reviewTindakLanjut',
        ])->whereHas('record');

        if ($request->filled('cluster_id')) {
            $butirsRiwayatQuery->where('cluster_id', $request->cluster_id);
        }

        if ($request->filled('sub_cluster_id')) {
            $butirsRiwayatQuery->where('sub_cluster_id', $request->sub_cluster_id);
        }

        if ($request->filled('direktorat_id')) {
            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) {
                $picQuery->where('jenis_pic', 'unit');
            });
        }

        $unitKerjaId = $request->input('unit_kerja_id', $request->input('unit_kerja_pendukung_id'));

        if (!empty($unitKerjaId)) {
            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($unitKerjaId) {
                $picQuery->where('jenis_pic', 'unit')
                    ->where('unit_kerja_id', $unitKerjaId);
            });
        }

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_rawas')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->where('jenis_pic', 'unit')
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
                $butir = $row['butir'];
                $item = $row['item'];

                if ($status === 'belum_ditindaklanjuti') {
                    return empty($item);
                }

                if (in_array($status, ['dalam_proses_tindak_lanjut', 'diusulkan_tuntas'], true)) {
                    return $butir->statusTindakLanjut() === $status;
                }

                $reviewTerakhir = $butir?->reviewTindakLanjut;

                return $reviewTerakhir?->status === $status;
            });
        }

        if ($request->filled('keyword')) {
            $keyword = strtolower(trim($request->keyword));

            $rows = $rows->filter(function ($row) use ($keyword) {
                $butir = $row['butir'];
                $item = $row['item'];
                $record = $butir?->record;

                $reviewTerakhir = $butir?->reviewTindakLanjut;

                $komitePic = $butir?->butirPics
                    ?->where('jenis_pic', 'komite')
                    ->first();

                $picUnits = $butir?->butirPics
                    ?->where('jenis_pic', 'unit')
                    ->map(fn ($pic) => trim(($pic->unitKerja?->kode_unit ?? '') . ' ' . ($pic->unitKerja?->nama_unit ?? '')))
                    ->implode(' ');

                $values = [
                    $record?->id_rawas,
                    $record?->nomor_surat,
                    $record?->perihal_surat,
                    $butir?->id_butir_rawas,
                    $butir?->tanggal_rawas?->format('d/m/Y'),
                    $butir?->agenda_rawas,
                    $butir?->keputusan_rawas,
                    $butir?->cluster?->nama_cluster,
                    $butir?->subCluster?->nama_sub_cluster,
                    'Dewan Pengawas',
                    $picUnits,
                    $item?->tindak_lanjut,
                    $item?->deliverables,
                    $reviewTerakhir?->hasil_review,
                    $reviewTerakhir?->deliverables,
                    $reviewTerakhir?->status,
                    $reviewTerakhir?->komite?->kode_komite,
                    $reviewTerakhir?->komite?->nama_komite,
                    $komitePic?->komite?->kode_komite,
                    $komitePic?->komite?->nama_komite,
                    $butir?->statusTindakLanjutLabel(),
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

                $isWaitingTl = empty($item) ? 1 : 0;

                $timestamp = $item?->created_at?->timestamp
                    ?? $butir?->created_at?->timestamp
                    ?? 0;

                return ($isWaitingTl * 10000000000) + $timestamp;
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
            'cluster',
            'subCluster',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tindakLanjuts',
        ])->whereHas('record');

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_rawas')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $butirSiapTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->where('jenis_pic', 'unit')
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        $butirSiapTindakLanjut = $butirSiapTindakLanjutQuery
            ->orderByDesc('id')
            ->get();

        $clusters = RawasCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $statusOptions = [
            'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
            'dalam_proses_tindak_lanjut' => 'Dalam Proses Tindak Lanjut',
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            'belum_ditanggapi' => 'Belum Direviu',
            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];

        return view('layouts.rawas.tindak-lanjut', compact(
            'tindakLanjutRows',
            'butirSiapTindakLanjut',
            'clusters',
            'unitKerjas',
            'statusOptions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'butir_id' => ['required', 'integer', 'exists:mysql_rawas.tb_butir_rawas,id'],
            'butir_pic_id' => ['required', 'integer', 'exists:mysql_rawas.tb_butir_pic,id'],
            'tindak_lanjut' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        $user = User::find(Auth::id());

        $butir = RawasButir::with([
            'record.butirRawas.butirPics',
            'record.butirRawas.tindakLanjuts',
            'butirPics.unitKerja',
            'tindakLanjuts',
        ])->findOrFail($validated['butir_id']);

        if (!$user || !$user->canCreateRawasTindakLanjutForButir($butir)) {
            abort(403, 'Anda tidak memiliki akses untuk membuat tindak lanjut pada butir ini.');
        }

        $butirPic = RawasButirPic::where('id', $validated['butir_pic_id'])
            ->where('id_butir_rawas', $butir->id_butir_rawas)
            ->where('jenis_pic', 'unit')
            ->whereNotNull('unit_kerja_id')
            ->first();

        if (!$butirPic) {
            return back()->with('error', 'PIC Unit yang dipilih tidak terdaftar pada butir RAWAS ini.');
        }

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_rawas') && !$user->hasRoleType('moderator_rawas')) {
            if (!in_array((int) $butirPic->unit_kerja_id, $user->unitKerjaIds(), true)) {
                abort(403, 'Anda tidak memiliki akses untuk membuat tindak lanjut atas PIC Unit ini.');
            }
        }

        DB::connection('mysql_rawas')->transaction(function () use ($request, $validated, $butir, $butirPic, $user) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/tindak-lanjut-rawas', 'public');
            }

            $tindakLanjut = RawasTindakLanjut::create([
                'id_butir_rawas' => $butir->id_butir_rawas,
                'butir_pic_id' => $butirPic->id,
                'tindak_lanjut' => $validated['tindak_lanjut'],
                'deliverables' => $validated['deliverables'],
                'dokumen' => $dokumenPath,
                'jth_tempo' => $butir->record?->jth_tempo,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $record = $butir->record;

            if ($record) {
                $record->loadMissing('butirRawas.butirPics', 'butirRawas.tindakLanjuts');

                $allButirsReady = $record->butirRawas->count() > 0
                    && $record->butirRawas->every(fn ($recordButir) => $recordButir->isTindakLanjutLengkap());

                $hasAnyTl = $record->butirRawas->contains(fn ($recordButir) => $recordButir->tindakLanjuts->count() > 0);

                $record->update([
                    'status' => $allButirsReady ? 'diusulkan_tuntas' : ($hasAnyTl ? 'dalam_proses' : $record->status),
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
                'description' => 'User membuat tindak lanjut RAWAS per PIC Unit.',
                'old_values' => null,
                'new_values' => [
                    'butir' => $butir->load('record')->toArray(),
                    'tindak_lanjut' => $tindakLanjut->toArray(),
                    'status_tindak_lanjut_butir' => $butir->fresh()->statusTindakLanjut(),
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
