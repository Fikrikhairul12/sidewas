<?php

namespace App\Http\Controllers\Eksternal;

use App\Http\Controllers\Controller;
use App\Exports\EksternalReportExport;
use App\Models\Direktorat;
use App\Models\EksternalRecord;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;

class ReportEksternalController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessEksternalPerekaman()) {
            abort(403, 'Anda tidak memiliki akses ke halaman report EKSTERNAL.');
        }

        $recordsQuery = EksternalRecord::with([
            'butirEksternal.cluster',
            'butirEksternal.subCluster',
            'butirEksternal.butirDirektorats.direktorat',
            'butirEksternal.tindakLanjuts.unitKerja.direktorat',
            'butirEksternal.butirPics.unitKerja',
            'butirEksternal.tindakLanjuts.unitKerja',
            'butirEksternal.reviewTindakLanjut',
        ])
            ->withCount('butirEksternal');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_eksternal', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('nama_instansi_pengundang', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%")
                    ->orWhereHas('butirEksternal', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_eksternal', 'like', "%{$keyword}%")
                            ->orWhere('agenda_eksternal', 'like', "%{$keyword}%")
                            ->orWhere('keputusan_eksternal', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('butirEksternal.tindakLanjuts', function ($tlQuery) use ($keyword) {
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
            $recordsQuery->whereHas('butirEksternal.butirDirektorats', function ($query) use ($request) {
                $query->where('direktorat_id', $request->direktorat_id);
            });
        }

        if ($request->filled('unit_kerja_id')) {
            $recordsQuery->whereHas('butirEksternal.butirPics', function ($query) use ($request) {
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

        return view('layouts.eksternal.report.index', compact(
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

        if (!$user || !$user->canAccessEksternalPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report EKSTERNAL.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat EKSTERNAL untuk dicetak.',
        ]);

        $records = $this->getRecordsForReport($validated['record_ids']);

        $printedBy = $user->name ?? $user->email ?? 'User';
        $printedAt = now()->format('d/m/Y H:i');

        return $this->streamBrowsershotPdf('layouts.eksternal.report.pdf', compact(
            'records',
            'printedBy',
            'printedAt'
        ), 'report-eksternal.pdf');
    }

    public function cetakCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessEksternalPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report EKSTERNAL.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat EKSTERNAL untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir EKSTERNAL untuk dicetak.',
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

        return $this->streamBrowsershotPdf('layouts.eksternal.report.pdf-custom', compact(
            'records',
            'selectedFields',
            'fieldLabels',
            'printedBy',
            'printedAt'
        ), 'report-eksternal-custom.pdf');
    }

    private function streamBrowsershotPdf(string $view, array $data, string $filename): Response
    {
        $html = view($view, $data)->render();

        $pdf = Browsershot::html($html)
            ->format('Legal')
            ->landscape()
            ->margins(8, 8, 8, 8)
            ->showBackground()
            ->timeout(120)
            ->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function cetakExcel(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessEksternalPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report EKSTERNAL.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat EKSTERNAL untuk dicetak.',
        ]);

        $records = $this->getRecordsForReport($validated['record_ids']);
        $selectedFields = array_keys($this->reportFieldLabels());
        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(new EksternalReportExport($records, $selectedFields, $fieldLabels), 'report-eksternal.xlsx');
    }

    public function cetakExcelCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessEksternalPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report EKSTERNAL.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat EKSTERNAL untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir EKSTERNAL untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);

        $selectedFields = array_values(array_intersect($validated['fields'], array_keys($this->reportFieldLabels())));

        if (empty($selectedFields)) {
            return back()->with('error', 'Pilih minimal satu field report.');
        }

        $records = $this->getRecordsForReport($validated['record_ids'], $validated['butir_ids']);
        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(new EksternalReportExport($records, $selectedFields, $fieldLabels), 'report-eksternal-custom.xlsx');
    }

    private function getRecordsForReport(array $recordIds, ?array $butirIds = null)
    {
        return EksternalRecord::with([
            'butirEksternal' => function ($query) use ($butirIds) {
                if (!empty($butirIds)) {
                    $query->whereIn('id', $butirIds);
                }

                $query->orderBy('id');
            },
            'butirEksternal.cluster',
            'butirEksternal.subCluster',
            'butirEksternal.butirDirektorats.direktorat',
            'butirEksternal.butirPics.unitKerja',
            'butirEksternal.tindakLanjuts' => function ($query) {
                $query->with('unitKerja')
                    ->orderBy('unit_kerja_id')
                    ->orderBy('created_at')
                    ->orderBy('id');
            },
            'butirEksternal.reviewTindakLanjut',
        ])
            ->whereIn('id', $recordIds)
            ->when(!empty($butirIds), function ($query) use ($butirIds) {
                $query->whereHas('butirEksternal', function ($butirQuery) use ($butirIds) {
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
            'tgl_agenda' => 'TGL & AGENDA EKSTERNAL',
            'keputusan' => 'KEPUTUSAN EKSTERNAL',
            'direktorat' => 'DIREKTORAT',
            'unit_pic' => 'UNIT PIC',
            'tindak_lanjut' => 'TINDAK LANJUT KEPUTUSAN EKSTERNAL',
            'deliverable' => 'DELIVERABLE',
            'dokumen' => 'DOKUMEN PENDUKUNG',
            'jatuh_tempo' => 'TGL. JATUH TEMPO',
            'hasil_reviu' => 'HASIL REVIU TINDAK LANJUT KEPUTUSAN EKSTERNAL',
            'status' => 'STATUS TINDAK LANJUT',
        ];
    }
}
