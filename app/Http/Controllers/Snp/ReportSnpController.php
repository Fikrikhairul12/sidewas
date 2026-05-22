<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
use App\Models\SnpRecord;
use App\Models\User;
use App\Models\UnitKerja;
use App\Models\Direktorat;
use App\Models\Komite;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportSnpController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpPerekaman()) {
            abort(403, 'Anda tidak memiliki akses ke halaman report SNP.');
        }

        $recordsQuery = SnpRecord::with([
            'cluster',
            'subCluster',
        ])
            ->withCount('butirSnp');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_snp', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status')) {
            $recordsQuery->where('status', $request->status);
        }

        if ($request->filled('tanggal_mulai')) {
            $recordsQuery->whereDate('tanggal_surat', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $recordsQuery->whereDate('tanggal_surat', '<=', $request->tanggal_selesai);
        }

        if ($request->filled('direktorat_id')) {
            $unitKerjaIds = UnitKerja::where('direktorat_id', $request->direktorat_id)
                ->pluck('id')
                ->toArray();

            $recordsQuery->whereHas('butirSnp.butirPics', function ($query) use ($unitKerjaIds) {
                $query->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $unitKerjaUtamaId = $request->unit_kerja_utama_id;

            $recordsQuery->whereHas('butirSnp.butirPics', function ($query) use ($unitKerjaUtamaId) {
                $query->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $unitKerjaUtamaId);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $unitKerjaPendukungId = $request->unit_kerja_pendukung_id;

            $recordsQuery->whereHas('butirSnp.butirPics', function ($query) use ($unitKerjaPendukungId) {
                $query->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $unitKerjaPendukungId);
            });
        }

        if ($request->filled('komite_id')) {
            $komiteId = $request->komite_id;

            $recordsQuery->whereHas('butirSnp.butirPics', function ($query) use ($komiteId) {
                $query->where('jenis_pic', 'komite')
                    ->where('komite_id', $komiteId);
            });
        }

        $records = $recordsQuery
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();

        $unitKerjas = UnitKerja::orderBy('nama_unit')->get();

        $komites = Komite::orderBy('nama_komite')->get();

        return view('layouts.snp.report.index', compact(
            'records',
            'direktorats',
            'unitKerjas',
            'komites'
        ));
    }

    public function cetak(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report SNP.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat SNP untuk dicetak.',
        ]);

        $records = SnpRecord::with([
            'cluster',
            'subCluster',
            'butirSnp.butirPics.unitKerja',
            'butirSnp.butirPics.komite',
            'butirSnp.tanggapan.review',
            'butirSnp.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->orderBy('id_snp')
            ->get();

        $pdf = Pdf::loadView('layouts.snp.report.pdf', compact('records'))
            ->setPaper('legal', 'landscape');

        return $pdf->stream('report-snp-dewas.pdf');
    }
}
