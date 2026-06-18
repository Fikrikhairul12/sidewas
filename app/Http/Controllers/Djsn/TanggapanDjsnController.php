<?php

namespace App\Http\Controllers\Djsn;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\DjsnButir;
use App\Models\DjsnCluster;
use App\Models\DjsnReview;
use App\Models\DjsnTanggapan;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TanggapanDjsnController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnTanggapan()) {
            abort(403, 'Anda tidak memiliki akses ke halaman tanggapan DJSN.');
        }

        $query = DjsnButir::with([
            'record',
            'cluster',
            'subCluster',
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
            $query->where('cluster_id', $request->cluster_id);
        }

        if ($request->filled('sub_cluster_id')) {
            $query->where('sub_cluster_id', $request->sub_cluster_id);
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

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_djsn')) {
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
                $q->where('id_butir_djsn', 'like', "%{$keyword}%")
                    ->orWhere('butir_djsn', 'like', "%{$keyword}%")
                    ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                        $recordQuery->where('id_djsn', 'like', "%{$keyword}%")
                            ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                            ->orWhere('perihal_surat', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('tanggapan', function ($tanggapanQuery) use ($keyword) {
                        $tanggapanQuery->where('tanggapan', 'like', "%{$keyword}%")
                            ->orWhere('deliverables', 'like', "%{$keyword}%");
                    });
            });
        }

        $butirs = $query
            ->latest('id')
            ->paginate(2)
            ->withQueryString();

        $clusters = DjsnCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $komites = Komite::orderBy('nama_komite')->get();

        $statusOptions = [
            'belum' => 'Belum Ditanggapi',
            'sudah' => 'Sudah Ditanggapi',
        ];

        return view('layouts.djsn.tanggapan', compact(
            'butirs',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statusOptions'
        ));

        // return view('layouts.djsn.tanggapan', compact('butirs'));
    }

    public function store(Request $request, DjsnButir $butir)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateDjsnTanggapanForButir($butir)) {
            abort(403, 'Anda tidak memiliki akses untuk memberi tanggapan pada butir ini.');
        }

        if ($butir->tanggapan()->exists()) {
            return back()->with('error', 'Butir DJSN ini sudah memiliki tanggapan.');
        }

        $validated = $request->validate([
            'tanggapan' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
            'ubah_tgl' => ['nullable', 'date'],
        ]);

        DB::connection('mysql_djsn')->transaction(function () use ($request, $validated, $butir, $user) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/tanggapan-djsn', 'public');
            }

            $tanggapan = DjsnTanggapan::create([
                'id_butir_djsn' => $butir->id_butir_djsn,
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

            $review = DjsnReview::create([
                'id_butir_djsn' => $butir->id_butir_djsn,
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
                'type_code' => 'djsn',
                'database_name' => 'sidewas_djsn',
                'table_name' => 'tb_tanggapan',
                'record_key' => $tanggapan->id_butir_djsn,
                'action' => 'create',
                'description' => 'User membuat tanggapan DJSN dan sistem membuat review awal.',
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
            ->route('djsn.tanggapan.index')
            ->with('success', 'Tanggapan DJSN berhasil disimpan.');
    }
}
