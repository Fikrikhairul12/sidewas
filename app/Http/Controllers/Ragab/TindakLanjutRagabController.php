<?php

namespace App\Http\Controllers\Ragab;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\RagabButir;
use App\Models\RagabCluster;
use App\Models\RagabTindakLanjut;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TindakLanjutRagabController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabTindakLanjut()) {
            abort(403, 'Anda tidak memiliki akses ke halaman tindak lanjut RAGAB.');
        }

        $butirsRiwayatQuery = RagabButir::with([
            'record.creator',
            'cluster',
            'subCluster',
            'butirDirektorats.direktorat',
            'butirPics.unitKerja.direktorat',
            'tindakLanjuts.creator',
            'tindakLanjuts.unitKerja.direktorat',
        ])->whereHas('record');

        if ($request->filled('cluster_id')) {
            $butirsRiwayatQuery->where('cluster_id', $request->cluster_id);
        }

        if ($request->filled('sub_cluster_id')) {
            $butirsRiwayatQuery->where('sub_cluster_id', $request->sub_cluster_id);
        }

        if ($request->filled('direktorat_id')) {
            $butirsRiwayatQuery->whereHas('butirDirektorats', function ($direktoratQuery) use ($request) {
                $direktoratQuery->where('direktorat_id', $request->direktorat_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'unit')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('komite_id')) {
            $butirsRiwayatQuery->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'komite')
                    ->where('komite_id', $request->komite_id);
            });
        }

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_ragab') && !$user->hasRoleType('moderator_ragab')) {
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
                foreach ($butir->tindakLanjuts->sortByDesc('created_at') as $tindakLanjut) {
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

                $reviewTerakhir = $butir->reviews
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

                $reviewTerakhir = $butir?->reviews
                    ?->where('tahap_review', 'tindak_lanjut')
                    ->sortByDesc('id')
                    ->first();

                $picUnits = $butir?->butirPics
                    ?->where('jenis_pic', 'unit')
                    ->map(fn ($pic) => trim(($pic->unitKerja?->kode_unit ?? '') . ' ' . ($pic->unitKerja?->nama_unit ?? '')))
                    ->implode(' ');

                $direktorats = $butir?->butirDirektorats
                    ?->map(fn ($item) => $item->direktorat?->nama_direktorat)
                    ->filter()
                    ->implode(' ');

                $komitePic = $butir?->butirPics?->where('jenis_pic', 'komite')->first();

                $values = [
                    $record?->id_ragab,
                    $record?->nomor_surat,
                    $record?->perihal_surat,
                    $butir?->id_butir_ragab,
                    $butir?->tanggal_ragab?->format('d/m/Y'),
                    $butir?->agenda_ragab,
                    $butir?->keputusan_ragab,
                    $butir?->cluster?->nama_cluster,
                    $butir?->subCluster?->nama_sub_cluster,
                    $direktorats,
                    $picUnits,
                    $komitePic?->komite?->kode_komite,
                    $komitePic?->komite?->nama_komite,
                    $item?->unitKerja?->direktorat?->nama_direktorat,
                    $item?->unitKerja?->kode_unit,
                    $item?->unitKerja?->nama_unit,
                    $item?->tindak_lanjut,
                    $item?->deliverables,
                    $reviewTerakhir?->hasil_review,
                    $reviewTerakhir?->deliverables,
                    $reviewTerakhir?->status,
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

        $butirSiapTindakLanjutQuery = RagabButir::with([
            'record',
            'cluster',
            'subCluster',
            'butirDirektorats.direktorat',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tindakLanjuts',
        ])->whereHas('record');

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_ragab') && !$user->hasRoleType('moderator_ragab')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $butirSiapTindakLanjutQuery->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->where('jenis_pic', 'unit')
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        $butirSiapTindakLanjut = $butirSiapTindakLanjutQuery
            ->orderByDesc('id')
            ->get();

        $clusters = RagabCluster::with('subClusters')->orderBy('nama_cluster')->get();
        $direktorats = Direktorat::orderBy('nama_direktorat')->get();
        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();
        $komites = Komite::orderBy('nama_komite')->get();

        $statusOptions = [
            'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
            'dalam_proses_tindak_lanjut' => 'Dalam Proses Tindak Lanjut',
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            'belum_ditanggapi' => 'Belum Direviu',
            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];

        return view('layouts.ragab.tindak-lanjut', compact(
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
            'butir_id' => ['required', 'integer', 'exists:mysql_ragab.tb_butir_ragab,id'],
            'unit_kerja_id' => ['required', 'integer'],
            'tindak_lanjut' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        $user = User::find(Auth::id());

        $butir = RagabButir::with([
            'record.butirRagab.butirPics',
            'record.butirRagab.tindakLanjuts',
            'butirDirektorats',
            'butirPics.unitKerja',
            'tindakLanjuts',
        ])->findOrFail($validated['butir_id']);

        if (!$user || !$user->canCreateRagabTindakLanjutForButir($butir)) {
            abort(403, 'Anda tidak memiliki akses untuk membuat tindak lanjut pada butir ini.');
        }

        $picUnitKerjaIds = $butir->picUnitKerjaIds();

        if (!in_array((int) $validated['unit_kerja_id'], $picUnitKerjaIds, true)) {
            return back()->with('error', 'PIC Unit yang dipilih tidak terdaftar pada butir RAGAB ini.');
        }

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_ragab') && !$user->hasRoleType('moderator_ragab')) {
            if (!in_array((int) $validated['unit_kerja_id'], $user->unitKerjaIds(), true)) {
                abort(403, 'Anda tidak memiliki akses untuk membuat tindak lanjut atas PIC Unit ini.');
            }
        }

        DB::connection('mysql_ragab')->transaction(function () use ($request, $validated, $butir, $user) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/tindak-lanjut-ragab', 'public');
            }

            $tindakLanjut = RagabTindakLanjut::create([
                'id_butir_ragab' => $butir->id_butir_ragab,
                'unit_kerja_id' => $validated['unit_kerja_id'],
                'tindak_lanjut' => $validated['tindak_lanjut'],
                'deliverables' => $validated['deliverables'],
                'dokumen' => $dokumenPath,
                'jth_tempo' => $butir->record?->jth_tempo,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $record = $butir->record;

            if ($record) {
                $record->loadMissing('butirRagab.butirPics', 'butirRagab.tindakLanjuts');

                $allButirsReady = $record->butirRagab->count() > 0
                    && $record->butirRagab->every(fn ($recordButir) => $recordButir->isTindakLanjutLengkap());

                $hasAnyTl = $record->butirRagab->contains(fn ($recordButir) => $recordButir->tindakLanjuts->count() > 0);

                $record->update([
                    'status' => $allButirsReady ? 'diusulkan_tuntas' : ($hasAnyTl ? 'dalam_proses' : $record->status),
                    'updated_by' => $user->id,
                ]);
            }

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'ragab',
                'database_name' => 'sidewas_ragab',
                'table_name' => 'tb_tindak_lanjut',
                'record_key' => $butir->id_butir_ragab,
                'action' => 'create',
                'description' => 'User membuat tindak lanjut RAGAB.',
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
            ->route('ragab.tindak-lanjut.index')
            ->with('success', 'Tindak lanjut RAGAB berhasil disimpan.');
    }
}
