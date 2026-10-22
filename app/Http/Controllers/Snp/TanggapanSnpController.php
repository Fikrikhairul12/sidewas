<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\SnpButir;
use App\Models\SnpCluster;
use App\Models\SnpTanggapan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\ReviuPutaranService;
use App\Services\TanggapanUnitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TanggapanSnpController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (! $user || ! $user->canAccessSnpTanggapan()) {
            abort(403, 'Anda tidak memiliki akses ke halaman tanggapan SNP.');
        }

        $query = SnpButir::with([
            'record.cluster',
            'record.putaran',
            'record.subCluster',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tanggapanAktif.creator',
            'tanggapanAktif.updater',
            'tanggapanAktif.unitKerja',
            'tanggapanAktif.butirPic.unitKerja',
            'reviews',
        ])
            ->whereHas('record');

        if (ReviuPutaranService::enabled()) {
            $query->with([
                'tanggapanUnit.creator', 'tanggapanUnit.updater', 'tanggapanUnit.unitKerja',
                'kompilasis.creator', 'reviews.creator', 'reviews.updater',
            ]);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('created_at', '<=', $request->tanggal_selesai);
        }

        if ($request->filled('cluster_id')) {
            $query->whereHas('record', function ($recordQuery) use ($request) {
                $recordQuery->where('cluster_id', $request->cluster_id);
            });
        }

        if ($request->filled('sub_cluster_id')) {
            $query->whereHas('record', function ($recordQuery) use ($request) {
                $recordQuery->where('sub_cluster_id', $request->sub_cluster_id);
            });
        }

        if ($request->filled('direktorat_id')) {
            $unitKerjaIds = UnitKerja::where('direktorat_id', $request->direktorat_id)
                ->pluck('id')
                ->toArray();

            $query->whereHas('butirPics', function ($picQuery) use ($unitKerjaIds) {
                $picQuery->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $query->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $request->unit_kerja_utama_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $query->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('komite_id')) {
            $query->whereHas('butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'komite')
                    ->where('komite_id', $request->komite_id);
            });
        }

        if (! $user->isSuperAdmin() && ! $user->hasRoleType('admin_snp')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $query->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        $responses = app(TanggapanUnitService::class);
        $responses->applyStatusFilter($query, $user, (string) $request->input('status'));

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('id_butir_snp', 'like', "%{$keyword}%")
                    ->orWhere('butir_snp', 'like', "%{$keyword}%")
                    ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                        $recordQuery->where('id_snp', 'like', "%{$keyword}%")
                            ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                            ->orWhere('perihal_surat', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('tanggapanAktif', function ($tanggapanQuery) use ($keyword) {
                        $tanggapanQuery->where('tanggapan', 'like', "%{$keyword}%")
                            ->orWhere('deliverables', 'like', "%{$keyword}%");
                    });
            });
        }

        $butirs = $query
            ->latest('id')
            ->paginate(2)
            ->withQueryString();

        $responseStates = $butirs->getCollection()->mapWithKeys(fn ($butir): array => [$butir->id => $responses->presentation($butir, $user)]);

        $clusters = SnpCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $komites = Komite::orderBy('nama_komite')->get();

        $statusOptions = [
            'belum' => 'Belum Ditanggapi',
            'sudah' => 'Sudah Ditanggapi',
        ];

        return view('layouts.snp.tanggapan', compact(
            'butirs',
            'responseStates',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statusOptions'
        ));

    }

    public function store(Request $request, SnpButir $butir)
    {
        $user = User::find(Auth::id());
        abort_unless($user && $user->canAccessSnpTanggapan(), 403);
        $validated = $request->validate([
            'butir_pic_id' => ['required', 'integer'],
            'putaran_id' => ['nullable', 'integer'],
            'tanggapan' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);
        app(TanggapanUnitService::class)->create($butir, $user, $validated, $request);

        return redirect()->route('snp.tanggapan.index')->with('success', 'Tanggapan SNP berhasil disimpan.');
    }

    public function update(Request $request, SnpTanggapan $tanggapan)
    {
        $user = User::find(Auth::id());
        abort_unless($user && $user->canAccessSnpTanggapan(), 403);
        $validated = $request->validate([
            'tanggapan' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);
        app(TanggapanUnitService::class)->update($tanggapan, $user, $validated, $request);

        return redirect()->route('snp.tanggapan.index')->with('success', 'Tanggapan SNP berhasil diperbarui.');
    }
}
