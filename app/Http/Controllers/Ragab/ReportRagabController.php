<?php

namespace App\Http\Controllers\Ragab;

use App\Http\Controllers\Controller;
use App\Exports\RagabReportExport;
use App\Models\Direktorat;
use App\Models\RagabRecord;
use App\Models\UnitKerja;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ReportRagabController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses ke halaman report RAGAB.');
        }

        $recordsQuery = RagabRecord::with([
            'butirRagab.cluster',
            'butirRagab.subCluster',
            'butirRagab.butirDirektorats.direktorat',
            'butirRagab.tindakLanjuts.unitKerja.direktorat',
            'butirRagab.butirPics.unitKerja',
            'butirRagab.tindakLanjuts.unitKerja',
            'butirRagab.reviewTindakLanjut',
        ])
            ->withCount('butirRagab');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_ragab', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%")
                    ->orWhereHas('butirRagab', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_ragab', 'like', "%{$keyword}%")
                            ->orWhere('agenda_ragab', 'like', "%{$keyword}%")
                            ->orWhere('keputusan_ragab', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('butirRagab.tindakLanjuts', function ($tlQuery) use ($keyword) {
                        $tlQuery->where('tindak_lanjut', 'like', "%{$keyword}%")
                            ->orWhere('deliverables', 'like', "%{$keyword}%");
                    });
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
            $recordsQuery->whereHas('butirRagab.butirDirektorats', function ($query) use ($request) {
                $query->where('direktorat_id', $request->direktorat_id);
            });
        }

        if ($request->filled('unit_kerja_id')) {
            $recordsQuery->whereHas('butirRagab.butirPics', function ($query) use ($request) {
                $query->where('jenis_pic', 'unit')
                    ->where('unit_kerja_id', $request->unit_kerja_id);
            });
        }

        $records = $recordsQuery
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $direktorats = Direktorat::orderBy('nama_direktorat')->get();
        $unitKerjas = UnitKerja::orderBy('kode_unit')->orderBy('nama_unit')->get();

        $statusOptions = [
            'draft' => 'Draft',
            'terbit' => 'Terbit',
            'dalam_proses' => 'Dalam Proses',
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            'tuntas' => 'Tuntas',
        ];

        $reportFields = $this->reportFieldLabels();

        return view('layouts.ragab.report.index', compact(
            'records',
            'direktorats',
            'unitKerjas',
            'statusOptions',
            'reportFields'
        ));
    }

    public function cetak(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report RAGAB.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat RAGAB untuk dicetak.',
        ]);

        $records = $this->getRecordsForReport($validated['record_ids']);

        $printedBy = $user->name ?? $user->email ?? 'User';
        $printedAt = now()->format('d/m/Y H:i');

        $pdf = Pdf::loadView('layouts.ragab.report.pdf', compact(
            'records',
            'printedBy',
            'printedAt'
        ))->setPaper('legal', 'landscape');

        return $pdf->download('report-ragab.pdf');
    }

    public function cetakCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report RAGAB.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat RAGAB untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir RAGAB untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);

        $selectedFields = array_values(array_intersect($validated['fields'], array_keys($this->reportFieldLabels())));

        if (empty($selectedFields)) {
            return back()->with('error', 'Pilih minimal satu field report.');
        }

        $records = $this->getRecordsForReport($validated['record_ids'], $validated['butir_ids']);
        $fieldLabels = $this->reportFieldLabels();

        $printedBy = $user->name ?? $user->email ?? 'User';
        $printedAt = now()->format('d/m/Y H:i');

        $pdf = Pdf::loadView('layouts.ragab.report.pdf-custom', compact(
            'records',
            'selectedFields',
            'fieldLabels',
            'printedBy',
            'printedAt'
        ))->setPaper('legal', 'landscape');

        return $pdf->stream('report-ragab-custom.pdf');
    }

    public function cetakExcel(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report RAGAB.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat RAGAB untuk dicetak.',
        ]);

        $records = $this->getRecordsForReport($validated['record_ids']);
        $selectedFields = array_keys($this->reportFieldLabels());
        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(new RagabReportExport($records, $selectedFields, $fieldLabels), 'report-ragab.xlsx');
    }

    public function cetakExcelCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report RAGAB.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat RAGAB untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir RAGAB untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);

        $selectedFields = array_values(array_intersect($validated['fields'], array_keys($this->reportFieldLabels())));

        if (empty($selectedFields)) {
            return back()->with('error', 'Pilih minimal satu field report.');
        }

        $records = $this->getRecordsForReport($validated['record_ids'], $validated['butir_ids']);
        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(new RagabReportExport($records, $selectedFields, $fieldLabels), 'report-ragab-custom.xlsx');
    }

    private function getRecordsForReport(array $recordIds, ?array $butirIds = null)
    {
        return RagabRecord::with([
            'butirRagab' => function ($query) use ($butirIds) {
                if (!empty($butirIds)) {
                    $query->whereIn('id', $butirIds);
                }

                $query->orderBy('id');
            },
            'butirRagab.cluster',
            'butirRagab.subCluster',
            'butirRagab.butirDirektorats.direktorat',
            'butirRagab.butirPics.unitKerja',
            'butirRagab.tindakLanjuts' => function ($query) {
                $query->with('unitKerja')
                    ->orderBy('unit_kerja_id')
                    ->orderBy('created_at')
                    ->orderBy('id');
            },
            'butirRagab.reviewTindakLanjut',
        ])
            ->whereIn('id', $recordIds)
            ->when(!empty($butirIds), function ($query) use ($butirIds) {
                $query->whereHas('butirRagab', function ($butirQuery) use ($butirIds) {
                    $butirQuery->whereIn('id', $butirIds);
                });
            })
            ->orderBy('tanggal_surat')
            ->orderBy('id')
            ->get();
    }

    private function reportFieldLabels(): array
    {
        return [
            'surat' => 'NOMOR, TANGGAL & PERIHAL SURAT',
            'tgl_agenda' => 'TGL & AGENDA RAGAB',
            'keputusan' => 'KEPUTUSAN RAGAB',
            'direktorat' => 'DIREKTORAT',
            'unit_pic' => 'UNIT PIC',
            'tindak_lanjut' => 'TINDAK LANJUT KEPUTUSAN RAGAB',
            'deliverable' => 'DELIVERABLE',
            'dokumen' => 'DOKUMEN PENDUKUNG',
            'jatuh_tempo' => 'TGL. JATUH TEMPO',
            'hasil_reviu' => 'HASIL REVIU TINDAK LANJUT KEPUTUSAN RAGAB',
            'status' => 'STATUS TINDAK LANJUT',
        ];
    }
}
