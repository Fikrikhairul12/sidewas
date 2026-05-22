<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\SnpCluster;
use App\Models\UnitKerja;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpTanggapan;
use App\Models\User;
use App\Models\SnpReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TanggapanSnpController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpTanggapan()) {
            abort(403, 'Anda tidak memiliki akses ke halaman tanggapan SNP.');
        }

        $query = SnpButir::with([
            'record.cluster',
            'record.subCluster',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tanggapan.creator',
        ])
            ->whereHas('record');

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

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_snp')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $query->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'sudah') {
                $query->whereHas('tanggapan');
            }

            if ($request->status === 'belum') {
                $query->whereDoesntHave('tanggapan');
            }
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('id_butir_snp', 'like', "%{$keyword}%")
                    ->orWhere('butir_snp', 'like', "%{$keyword}%")
                    ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                        $recordQuery->where('id_snp', 'like', "%{$keyword}%")
                            ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                            ->orWhere('perihal_surat', 'like', "%{$keyword}%");
                    });
            });
        }

        $butirs = $query
            ->latest('id')
            ->paginate(2)
            ->withQueryString();

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
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statusOptions'
        ));

        // return view('layouts.snp.tanggapan', compact('butirs'));
    }

    public function store(Request $request, SnpButir $butir)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateSnpTanggapanForButir($butir)) {
            abort(403, 'Anda tidak memiliki akses untuk memberi tanggapan pada butir ini.');
        }

        if ($butir->tanggapan()->exists()) {
            return back()->with('error', 'Butir SNP ini sudah memiliki tanggapan.');
        }

        $validated = $request->validate([
            'tanggapan' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
            'ubah_tgl' => ['nullable', 'date'],
        ]);

        DB::connection('mysql_snp')->transaction(function () use ($request, $validated, $butir, $user) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/tanggapan-snp', 'public');
            }

            $tanggapan = SnpTanggapan::create([
                'id_butir_snp' => $butir->id_butir_snp,
                'tanggapan' => $validated['tanggapan'],
                'deliverables' => $validated['deliverables'],
                'dokumen' => $dokumenPath,
                'ubah_tgl' => $validated['ubah_tgl'] ?? null,
                'status_pengajuan_tgl' => 'pending',
            ]);

            $komitePic = $butir->butirPics()
                ->where('jenis_pic', 'komite')
                ->whereNotNull('komite_id')
                ->first();

            $review = SnpReview::create([
                'id_butir_snp' => $butir->id_butir_snp,
                'id_tanggapan' => $tanggapan->id,
                'id_tindak_lanjut' => null,
                'tahap_review' => 'tanggapan',
                'komite_id' => $komitePic?->komite_id,
                'hasil_review' => null,
                'deliverables' => null,
                'status' => 'belum_ditanggapi',
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_tanggapan',
                'record_key' => $tanggapan->id_butir_snp,
                'action' => 'create',
                'description' => 'User membuat tanggapan SNP dan sistem membuat review awal.',
                'old_values' => null,
                'new_values' => [
                    'butir' => $butir->load('record')->toArray(),
                    'tanggapan' => $tanggapan->toArray(),
                    'review' => $review->toArray(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('snp.tanggapan.index')
            ->with('success', 'Tanggapan SNP berhasil disimpan.');
    }
}
