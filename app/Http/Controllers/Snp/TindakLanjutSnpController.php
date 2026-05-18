<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
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

        $tindakLanjutsQuery = SnpTindakLanjut::with([
            'butir.record.cluster',
            'butir.record.subCluster',
            'butir.butirPics.unitKerja.direktorat',
            'butir.butirPics.komite',
            'creator',
            'reviews.komite',
        ]);

        if (!$user->isSuperAdmin() && !$user->hasRoleType('admin_snp')) {
            $userUnitKerjaIds = $user->unitKerjaIds();

            $tindakLanjutsQuery->whereHas('butir.butirPics', function ($picQuery) use ($userUnitKerjaIds) {
                $picQuery->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->whereIn('unit_kerja_id', $userUnitKerjaIds);
            });
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $tindakLanjutsQuery->where(function ($q) use ($keyword) {
                $q->where('tindak_lanjut', 'like', "%{$keyword}%")
                    ->orWhere('deliverables', 'like', "%{$keyword}%")
                    ->orWhereHas('butir', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_snp', 'like', "%{$keyword}%")
                            ->orWhere('butir_snp', 'like', "%{$keyword}%")
                            ->orWhereHas('record', function ($recordQuery) use ($keyword) {
                                $recordQuery->where('id_snp', 'like', "%{$keyword}%")
                                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                                    ->orWhere('perihal_surat', 'like', "%{$keyword}%");
                            });
                    });
            });
        }

        $tindakLanjuts = $tindakLanjutsQuery
            ->latest()
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

        return view('layouts.snp.tindak-lanjut', compact(
            'tindakLanjuts',
            'butirSiapTindakLanjut'
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
