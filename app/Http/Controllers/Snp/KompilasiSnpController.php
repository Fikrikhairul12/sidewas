<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpCluster;
use App\Models\SnpKompilasi;
use App\Models\SnpReview;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KompilasiSnpController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpKompilasi()) {
            abort(403, 'Anda tidak memiliki akses ke halaman kompilasi SNP.');
        }

        $query = SnpButir::with([
            'record.cluster',
            'record.subCluster',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tanggapan.creator',
            'tanggapan.butirPic.unitKerja.direktorat',
            'tindakLanjuts.creator',
            'tindakLanjuts.butirPic.unitKerja.direktorat',
            'kompilasis.creator',
            'reviews.komite',
        ])->whereHas('record');

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
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
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

        $butirs = $query->latest('id')->get();

        $items = collect();

        foreach ($butirs as $butir) {
            if ($this->allPicSudahTanggapan($butir)) {
                $kompilasi = $butir->kompilasis->firstWhere('tahap_kompilasi', 'tanggapan');

                $items->push((object) [
                    'butir' => $butir,
                    'tahap' => 'tanggapan',
                    'tahap_label' => 'Kompilasi Tanggapan',
                    'kompilasi' => $kompilasi,
                    'status' => $kompilasi?->status ?? 'belum_dikompilasi',
                    'data_unit' => $butir->tanggapan
                        ->sortBy(fn($t) => $t->butirPic?->unitKerja?->kode_unit ?? 'ZZZ')
                        ->groupBy('butir_pic_id'),
                ]);
            }

            $putaranTlList = $butir->tindakLanjuts
                ->pluck('putaran_tl')
                ->filter()
                ->map(fn($putaran) => (int) $putaran)
                ->unique()
                ->sort()
                ->values();

            foreach ($putaranTlList as $putaranTl) {
                if ($this->allPicSudahTindakLanjut($butir, $putaranTl)) {
                    $kompilasi = $butir->kompilasis
                        ->where('tahap_kompilasi', 'tindak_lanjut')
                        ->where('putaran_tl', $putaranTl)
                        ->first();

                    $items->push((object) [
                        'butir' => $butir,
                        'tahap' => 'tindak_lanjut',
                        'putaran_tl' => $putaranTl,
                        'tahap_label' => 'Kompilasi Tindak Lanjut Putaran ' . $putaranTl,
                        'kompilasi' => $kompilasi,
                        'status' => $kompilasi?->status ?? 'belum_dikompilasi',
                        'data_unit' => $butir->tindakLanjuts
                            ->where('putaran_tl', $putaranTl)
                            ->sortBy(fn($tl) => $tl->butirPic?->unitKerja?->kode_unit ?? 'ZZZ')
                            ->groupBy('butir_pic_id'),
                    ]);
                }
            }
        }

        if ($request->filled('tahap_kompilasi')) {
            $items = $items->where('tahap', $request->tahap_kompilasi)->values();
        }

        if ($request->filled('status')) {
            $items = $items->where('status', $request->status)->values();
        }

        $items = $items
            ->sortByDesc(function ($item) {
                $latestUnitInput = $item->data_unit
                    ->flatten(1)
                    ->max(fn ($row) => $row?->created_at?->timestamp ?? 0);

                return $item->kompilasi?->updated_at?->timestamp
                    ?? $item->kompilasi?->created_at?->timestamp
                    ?? $latestUnitInput
                    ?? $item->butir?->created_at?->timestamp
                    ?? $item->butir?->id
                    ?? 0;
            })
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 2;

        $kompilasiItems = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $clusters = SnpCluster::with('subClusters')->orderBy('nama_cluster')->get();
        $direktorats = Direktorat::orderBy('nama_direktorat')->get();
        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();
        $komites = Komite::orderBy('nama_komite')->get();

        $statusOptions = [
            'belum_dikompilasi' => 'Belum Dikompilasi',
            'dalam_proses_reviu_dewas' => 'Dalam Proses Reviu Dewas',
        ];
        $canCreateKompilasi = $user->canCreateSnpKompilasi();

        return view('layouts.snp.kompilasi', compact(
            'kompilasiItems',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statusOptions',
            'canCreateKompilasi'
        ));
    }

    public function store(Request $request, SnpButir $butir)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateSnpKompilasi()) {
            abort(403, 'Anda tidak memiliki akses untuk melakukan kompilasi SNP.');
        }

        $butir->load([
            'record',
            'butirPics.unitKerja',
            'butirPics.komite',
            'tanggapan',
            'tindakLanjuts',
            'kompilasis',
        ]);

        $validated = $request->validate([
            'tahap_kompilasi' => ['required', 'in:tanggapan,tindak_lanjut'],
            'putaran_tl' => ['nullable', 'integer', 'min:1'],
            'hasil_kompilasi' => ['required', 'string'],
            'deliverables' => ['nullable', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
            'ubah_tgl' => ['nullable', 'date'],
            'status_pengajuan_tgl' => ['nullable', 'in:pending,disetujui,ditolak'],
        ]);

        $putaranTl = (int) ($validated['putaran_tl'] ?? 1);

        if ($validated['tahap_kompilasi'] === 'tanggapan' && !$this->allPicSudahTanggapan($butir)) {
            return back()->with('error', 'Kompilasi tanggapan belum bisa dilakukan karena tanggapan PIC Unit belum lengkap.');
        }

        if ($validated['tahap_kompilasi'] === 'tindak_lanjut' && !$this->allPicSudahTindakLanjut($butir, $putaranTl)) {
            return back()->with('error', 'Kompilasi tindak lanjut belum bisa dilakukan karena tindak lanjut PIC Unit putaran ini belum lengkap.');
        }

        DB::connection('mysql_snp')->transaction(function () use ($request, $validated, $butir, $user, $putaranTl) {
            $kompilasi = SnpKompilasi::firstOrNew([
                'id_butir_snp' => $butir->id_butir_snp,
                'tahap_kompilasi' => $validated['tahap_kompilasi'],
                'putaran_tl' => $putaranTl,
            ]);

            if ($kompilasi->exists && $kompilasi->status === 'dalam_proses_reviu_dewas') {
                abort(422, 'Data ini sudah masuk proses reviu Dewas dan tidak bisa dikompilasi ulang.');
            }

            $dokumenPath = $kompilasi->dokumen;

            if ($request->hasFile('dokumen')) {
                if ($kompilasi->dokumen && Storage::disk('public')->exists($kompilasi->dokumen)) {
                    Storage::disk('public')->delete($kompilasi->dokumen);
                }

                $dokumenPath = $request->file('dokumen')->store('dokumen/kompilasi-snp', 'public');
            }

            $kompilasi->fill([
                'hasil_kompilasi' => $validated['hasil_kompilasi'],
                'deliverables' => $validated['deliverables'] ?? null,
                'dokumen' => $dokumenPath,
                'ubah_tgl' => $validated['tahap_kompilasi'] === 'tanggapan'
                    ? ($validated['ubah_tgl'] ?? null)
                    : null,
                'status_pengajuan_tgl' => $validated['tahap_kompilasi'] === 'tanggapan' && !empty($validated['ubah_tgl'])
                    ? 'pending'
                    : null,
                'status' => 'dalam_proses_reviu_dewas',
                'updated_by' => $user->id,
            ]);

            if (!$kompilasi->exists) {
                $kompilasi->created_by = $user->id;
            }

            $kompilasi->save();

            $komitePic = $butir->butirPics()
                ->where('jenis_pic', 'komite')
                ->whereNotNull('komite_id')
                ->first();

            SnpReview::updateOrCreate(
                [
                    'id_butir_snp' => $butir->id_butir_snp,
                    'tahap_review' => $validated['tahap_kompilasi'],
                    'putaran_tl' => $putaranTl,
                ],
                [
                    'id_tanggapan' => null,
                    'id_tindak_lanjut' => null,
                    'komite_id' => $komitePic?->komite_id,
                    'hasil_review' => null,
                    'deliverables' => null,
                    'dokumen' => null,
                    'status' => 'belum_ditanggapi',
                    'updated_by' => $user->id,
                ]
            );

            $butir->record?->refresh()->syncStatusFromButir($user->id);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_kompilasi',
                'record_key' => $butir->id_butir_snp,
                'action' => 'kompilasi_' . $validated['tahap_kompilasi'],
                'description' => 'User melakukan kompilasi SNP dan mengirim ke proses reviu Dewas.',
                'old_values' => null,
                'new_values' => [
                    'kompilasi' => $kompilasi->fresh()?->toArray(),
                    'butir' => $butir->toArray(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('snp.kompilasi.index')
            ->with('success', 'Kompilasi SNP berhasil disimpan dan dikirim ke proses reviu Dewas.');
    }

    public function downloadDokumen(SnpKompilasi $kompilasi)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpKompilasi()) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen kompilasi.');
        }

        if (!$kompilasi->dokumen) {
            abort(404, 'Dokumen kompilasi tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $kompilasi->dokumen);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        return response()->download($filePath);
    }

    private function picUnitIds(SnpButir $butir): array
    {
        return $butir->butirPics
            ->whereIn('jenis_pic', ['utama', 'pendukung'])
            ->whereNotNull('unit_kerja_id')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();
    }

    private function allPicSudahTanggapan(SnpButir $butir): bool
    {
        $picIds = $this->picUnitIds($butir);

        if (count($picIds) === 0) {
            return false;
        }

        $tanggapanPicIds = $butir->tanggapan
            ->whereNotNull('butir_pic_id')
            ->pluck('butir_pic_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        return empty(array_diff($picIds, $tanggapanPicIds));
    }

    private function allPicSudahTindakLanjut(SnpButir $butir, int $putaranTl): bool
    {
        $picIds = $this->picUnitIds($butir);

        if (count($picIds) === 0) {
            return false;
        }

        $tindakLanjutPicIds = $butir->tindakLanjuts
            ->where('putaran_tl', $putaranTl)
            ->whereNotNull('butir_pic_id')
            ->pluck('butir_pic_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        return empty(array_diff($picIds, $tindakLanjutPicIds));
    }
}
