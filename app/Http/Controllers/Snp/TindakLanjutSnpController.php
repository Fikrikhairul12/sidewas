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
use App\Models\SnpTindakLanjut;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TindakLanjutSnpController extends Controller
{
    private function getReviewTlTerakhir($butir): ?SnpReview
    {
        return $butir->reviews
            ->where('tahap_review', 'tindak_lanjut')
            ->sortByDesc('putaran_tl')
            ->sortByDesc('id')
            ->first();
    }

    private function canInputTindakLanjut($butir, User $user): bool
    {
        if ($user->isSuperAdmin() || $user->hasRoleType('admin_snp')) {
            return true;
        }

        $reviewTlTerakhir = $this->getReviewTlTerakhir($butir);

        if ($reviewTlTerakhir) {
            return blank($reviewTlTerakhir->hasil_review);
        }

        return $butir->reviews
            ->where('tahap_review', 'tanggapan')
            ->where('status', 'dalam_proses_tindak_lanjut_direksi')
            ->isNotEmpty();
    }

    private function getPutaranTlAktif($butir): int
    {
        $reviewTlTerakhir = $this->getReviewTlTerakhir($butir);

        if (
            $reviewTlTerakhir &&
            $reviewTlTerakhir->status === 'dalam_proses_tindak_lanjut_direksi'
        ) {
            return ((int) $reviewTlTerakhir->putaran_tl) + 1;
        }

        return max(
            1,
            (int) ($butir->tindakLanjuts?->max('putaran_tl') ?? 0)
        );
    }

    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpTindakLanjut()) {
            abort(403, 'Anda tidak memiliki akses ke halaman tindak lanjut SNP.');
        }

        $query = SnpButir::with([
            'record.cluster',
            'record.subCluster',
            'record.creator',
            'butirPics.unitKerja.direktorat',
            'butirPics.komite',
            'tindakLanjuts.creator',
            'tindakLanjuts.butirPic.unitKerja.direktorat',
            'reviews.komite',
            'kompilasiTindakLanjut',
            'kompilasiTindakLanjuts',
        ])
            ->whereHas('record')
            ->where(function ($q) {
                $q->whereHas('reviews', function ($reviewQuery) {
                    $reviewQuery->where('tahap_review', 'tanggapan')
                        ->where('status', 'dalam_proses_tindak_lanjut_direksi');
                })
                    ->orWhereHas('tindakLanjuts')
                    ->orWhereHas('reviews', function ($reviewQuery) {
                        $reviewQuery->where('tahap_review', 'tindak_lanjut');
                    })
                    ->orWhereHas('kompilasiTindakLanjuts');
            });

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

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_snp')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $query->whereHas('butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
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
                    ->orWhereHas('tindakLanjuts', function ($tlQuery) use ($keyword) {
                        $tlQuery->where('tindak_lanjut', 'like', "%{$keyword}%")
                            ->orWhere('deliverables', 'like', "%{$keyword}%");
                    });
            });
        }

        $butirs = $query
            ->orderByDesc('id')
            ->get();

        $rows = $butirs->map(function ($butir) use ($user) {
            $putaranAktif = $this->getPutaranTlAktif($butir);
            $canInputTl = $this->canInputTindakLanjut($butir, $user);

            $butir->putaran_tl_aktif = $putaranAktif;

            $picUnits = $butir->butirPics
                ->whereIn('jenis_pic', ['utama', 'pendukung'])
                ->values();

            // Ini untuk ditampilkan sebagai riwayat: semua TL semua putaran
            $riwayatTindakLanjutList = ($butir->tindakLanjuts ?? collect())
                ->sortBy([
                    ['putaran_tl', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();

            // Ini khusus untuk cek siapa yang belum isi di putaran aktif
            $tindakLanjutPutaranAktif = ($butir->tindakLanjuts ?? collect())
                ->where('putaran_tl', $putaranAktif)
                ->values();

            $tlPicIds = $tindakLanjutPutaranAktif
                ->pluck('butir_pic_id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->toArray();

            $availablePicUnits = $canInputTl
                ? $picUnits
                    ->reject(fn($pic) => in_array((int) $pic->id, $tlPicIds, true))
                    ->values()
                : collect();

            return [
                'butir' => $butir,
                'items' => $riwayatTindakLanjutList,
                'available_pic_units' => $availablePicUnits,
                'semua_pic_sudah_tl' => $picUnits->count() > 0 && $availablePicUnits->count() === 0,
                'putaran_tl' => $putaranAktif,
                'can_input_tl' => $canInputTl,
                'review_tl_terakhir' => $this->getReviewTlTerakhir($butir),
            ];
        });

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 2;

        $tindakLanjutRows = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );



        $butirSiapTindakLanjutQuery = SnpButir::with([
            'record',
            'butirPics.unitKerja',
            'butirPics.komite',
            'reviews',
            'tindakLanjuts',
        ])
            ->whereHas('record')
            ->where(function ($q) {
                $q->whereHas('reviews', function ($reviewQuery) {
                    $reviewQuery->where('tahap_review', 'tanggapan')
                        ->where('status', 'dalam_proses_tindak_lanjut_direksi');
                })
                    ->orWhereHas('reviews', function ($reviewQuery) {
                        $reviewQuery->where('tahap_review', 'tindak_lanjut')
                            ->where('status', 'dalam_proses_tindak_lanjut_direksi');
                    });
            });

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

        $butirSiapTindakLanjut = $butirSiapTindakLanjut
            ->map(function ($butir) {
                $butir->putaran_tl_aktif = $this->getPutaranTlAktif($butir);

                return $butir;
            })
            ->filter(fn ($butir) => $this->canInputTindakLanjut($butir, $user))
            ->values();

        $clusters = SnpCluster::with('subClusters')
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

        return view('layouts.snp.tindak-lanjut', compact(
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
            'butir_id' => ['required', 'integer', 'exists:mysql_snp.tb_butir_snp,id'],
            'butir_pic_id' => ['required', 'integer'],
            'tindak_lanjut' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        $user = User::find(Auth::id());

        $butir = SnpButir::with([
            'record',
            'butirPics.unitKerja',
            'reviews',
            'tindakLanjuts',
        ])->findOrFail($validated['butir_id']);

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

        $putaranAktif = $this->getPutaranTlAktif($butir);

        $putaranSudahDireviu = $butir->reviews
            ->where('tahap_review', 'tindak_lanjut')
            ->where('putaran_tl', $putaranAktif)
            ->contains(fn (SnpReview $review): bool => filled($review->hasil_review));

        if ($putaranSudahDireviu && ! $user->isSuperAdmin() && ! $user->hasRoleType('admin_snp')) {
            abort(403, 'Kompilasi tindak lanjut putaran ini sudah direviu.');
        }

        $butirPic = $butir->butirPics()
            ->whereIn('jenis_pic', ['utama', 'pendukung'])
            ->where('id', $validated['butir_pic_id'])
            ->first();

        if (!$butirPic) {
            return back()->with('error', 'PIC Unit yang dipilih tidak valid untuk butir SNP ini.');
        }

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_snp')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            if (!in_array((int) $butirPic->unit_kerja_id, array_map('intval', $userUnitKerjaIds), true)) {
                return back()->with('error', 'Anda tidak memiliki akses untuk menginput tindak lanjut atas PIC Unit tersebut.');
            }
        }

        $sudahInputPutaranIni = $butir->tindakLanjuts()
            ->where('putaran_tl', $putaranAktif)
            ->where('butir_pic_id', $butirPic->id)
            ->exists();

        if ($sudahInputPutaranIni) {
            return back()->with('error', 'PIC Unit yang dipilih sudah mengisi tindak lanjut untuk putaran ini.');
        }

        DB::connection('mysql_snp')->transaction(function () use ($request, $validated, $butir, $butirPic, $user, $putaranAktif) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/tindak-lanjut-snp', 'public');
            }

            $tindakLanjut = SnpTindakLanjut::create([
                'id_butir_snp' => $butir->id_butir_snp,
                'butir_pic_id' => $butirPic->id,
                'putaran_tl' => $putaranAktif,
                'tindak_lanjut' => $validated['tindak_lanjut'],
                'deliverables' => $validated['deliverables'],
                'dokumen' => $dokumenPath,
                'jth_tempo' => $butir->record?->jth_tempo,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $picIds = $butir->butirPics()
                ->whereIn('jenis_pic', ['utama', 'pendukung'])
                ->whereNotNull('unit_kerja_id')
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->toArray();

            $tindakLanjutPicIds = $butir->tindakLanjuts()
                ->where('putaran_tl', $putaranAktif)
                ->whereNotNull('butir_pic_id')
                ->pluck('butir_pic_id')
                ->map(fn($id) => (int) $id)
                ->unique()
                ->toArray();

            $allPicSudahTindakLanjut = count($picIds) > 0
                && empty(array_diff($picIds, $tindakLanjutPicIds));

            if ($allPicSudahTindakLanjut) {
                SnpKompilasi::firstOrCreate(
                    [
                        'id_butir_snp' => $butir->id_butir_snp,
                        'tahap_kompilasi' => 'tindak_lanjut',
                        'putaran_tl' => $putaranAktif,
                    ],
                    [
                        'status' => 'belum_dikompilasi',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]
                );
            }

            $butir->refresh()->syncStatusFromTindakLanjut($putaranAktif, $user->id);
            $butir->record?->refresh()->syncStatusFromButir($user->id);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_tindak_lanjut',
                'record_key' => $butir->id_butir_snp,
                'action' => 'create',
                'description' => 'User membuat tindak lanjut SNP.',
                'old_values' => null,
                'new_values' => [
                    'butir' => $butir->load('record')->toArray(),
                    'tindak_lanjut' => $tindakLanjut->toArray(),
                    'putaran_tl' => $putaranAktif,
                    'kompilasi_ready' => $allPicSudahTindakLanjut,
                    'status_butir' => $butir->fresh()->statusTindakLanjut(),
                    'status_record' => $butir->record?->fresh()?->status,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('snp.tindak-lanjut.index')
            ->with('success', 'Tindak lanjut SNP berhasil disimpan.');
    }

    public function update(Request $request, SnpTindakLanjut $tindakLanjut)
    {
        $user = User::find(Auth::id());
        $tindakLanjut->load(['butir.reviews', 'butirPic']);

        if (! $user || ! $this->canEdit($user, $tindakLanjut)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah tindak lanjut SNP ini.');
        }

        $validated = $request->validate([
            'tindak_lanjut' => ['required', 'string'],
            'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        DB::connection('mysql_snp')->transaction(function () use ($request, $validated, $tindakLanjut, $user): void {
            $oldValues = $tindakLanjut->toArray();
            $dokumenPath = $tindakLanjut->dokumen;

            if ($request->hasFile('dokumen')) {
                if ($dokumenPath && Storage::disk('public')->exists($dokumenPath)) {
                    Storage::disk('public')->delete($dokumenPath);
                }

                $dokumenPath = $request->file('dokumen')->store('dokumen/tindak-lanjut-snp', 'public');
            }

            $tindakLanjut->update([
                'tindak_lanjut' => $validated['tindak_lanjut'],
                'deliverables' => $validated['deliverables'],
                'dokumen' => $dokumenPath,
                'updated_by' => $user->id,
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_tindak_lanjut',
                'record_key' => $tindakLanjut->id_butir_snp,
                'action' => 'update',
                'description' => 'User mengubah tindak lanjut SNP.',
                'old_values' => $oldValues,
                'new_values' => $tindakLanjut->fresh()->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()->route('snp.tindak-lanjut.index')->with('success', 'Tindak lanjut SNP berhasil diperbarui.');
    }

    private function canEdit(User $user, SnpTindakLanjut $tindakLanjut): bool
    {
        if ($user->isSuperAdmin() || $user->hasRoleType('admin_snp')) {
            return true;
        }

        $kompilasiTindakLanjutSudahDireviu = $tindakLanjut->butir?->reviews
            ->where('tahap_review', 'tindak_lanjut')
            ->where('putaran_tl', $tindakLanjut->putaran_tl)
            ->contains(fn (SnpReview $review): bool => filled($review->hasil_review));

        return ! $kompilasiTindakLanjutSudahDireviu
            && $tindakLanjut->butir !== null
            && $user->canCreateSnpTindakLanjutForButir($tindakLanjut->butir)
            && in_array((int) $tindakLanjut->butirPic?->unit_kerja_id, array_map('intval', $user->unitKerjaIds()), true);
    }

}
