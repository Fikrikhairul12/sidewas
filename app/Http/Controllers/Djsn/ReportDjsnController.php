<?php

namespace App\Http\Controllers\Djsn;

use App\Http\Controllers\Controller;
use App\Exports\DjsnReportExport;
use App\Models\Direktorat;
use App\Models\DjsnRecord;
use App\Models\Komite;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;

class ReportDjsnController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnReport()) {
            abort(403, 'Anda tidak memiliki akses ke halaman report DJSN.');
        }

        $recordsQuery = DjsnRecord::with([
            'butirDjsn.cluster',
            'butirDjsn.subCluster',
        ])
            ->withCount('butirDjsn');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_djsn', 'like', "%{$keyword}%")
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

            $recordsQuery->whereHas('butirDjsn.butirPics', function ($query) use ($unitKerjaIds) {
                $query->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $unitKerjaUtamaId = $request->unit_kerja_utama_id;

            $recordsQuery->whereHas('butirDjsn.butirPics', function ($query) use ($unitKerjaUtamaId) {
                $query->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $unitKerjaUtamaId);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $unitKerjaPendukungId = $request->unit_kerja_pendukung_id;

            $recordsQuery->whereHas('butirDjsn.butirPics', function ($query) use ($unitKerjaPendukungId) {
                $query->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $unitKerjaPendukungId);
            });
        }

        if ($request->filled('komite_id')) {
            $komiteId = $request->komite_id;

            $recordsQuery->whereHas('butirDjsn.butirPics', function ($query) use ($komiteId) {
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

        return view('layouts.djsn.report.index', compact(
            'records',
            'direktorats',
            'unitKerjas',
            'komites'
        ));
    }

    public function cetak(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnReport()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report DJSN.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat DJSN untuk dicetak.',
        ]);

        $records = DjsnRecord::with([
            'butirDjsn.cluster',
            'butirDjsn.subCluster',
            'butirDjsn.butirPics.unitKerja',
            'butirDjsn.butirPics.komite',
            'butirDjsn.tanggapan.review',
            'butirDjsn.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->orderBy('id_djsn')
            ->get();

        $printedBy = $user->name ?? $user->email ?? 'User';
        $printedAt = now()->format('d/m/Y H:i');

        return $this->streamBrowsershotPdf('layouts.djsn.report.pdf', compact(
            'records',
            'printedBy',
            'printedAt'
        ), 'report-djsn-dewas.pdf');
    }

    public function cetakCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnReport()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report DJSN.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat DJSN untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir DJSN untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);

        $allowedFields = [
            'surat',
            'id_butir',
            'isi_butir',
            'pic_unit',
            'pic_utama',
            'pic_pendukung',
            'tanggapan_tl',
            'tanggapan',
            'tindak_lanjut',
            'deliverable',
            'dokumen',
            'jatuh_tempo',
            'komite',
            'hasil_reviu',
            'status',
        ];

        $selectedFields = array_values(array_intersect($validated['fields'], $allowedFields));
        $selectedFields = $this->mapFieldsForPdf($selectedFields);

        if (empty($selectedFields)) {
            return back()->with('error', 'Pilih minimal satu field report.');
        }

        $butirIds = $validated['butir_ids'];

        $records = DjsnRecord::with([
            'butirDjsn' => function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds)
                    ->orderBy('id');
            },
            'butirDjsn.cluster',
            'butirDjsn.subCluster',
            'butirDjsn.butirPics.unitKerja',
            'butirDjsn.butirPics.komite',
            'butirDjsn.tanggapan.review',
            'butirDjsn.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->whereHas('butirDjsn', function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds);
            })
            ->orderBy('id_djsn')
            ->get();

        $fieldLabels = [
            'surat' => 'NOMOR, TANGGAL & PERIHAL SURAT',
            'id_butir' => 'ID BUTIR DJSN',
            'isi_butir' => 'ISI BUTIR DJSN',
            'pic_unit' => 'PIC UNIT KERJA',
            'tanggapan_tl' => 'TANGGAPAN & TINDAK LANJUT DIREKSI',
            'deliverable' => 'DELIVERABLE',
            'dokumen' => 'DOKUMEN PENDUKUNG',
            'jatuh_tempo' => 'TGL. JATUH TEMPO',
            'komite' => 'PIC KOMITE DEWAN PENGAWAS',
            'hasil_reviu' => 'HASIL REVIU DEWAN PENGAWAS',
            'status' => 'STATUS TINDAK LANJUT',
        ];

        $printedBy = $user->name ?? $user->email ?? 'User';
        $printedAt = now()->format('d/m/Y H:i');

        return $this->streamBrowsershotPdf('layouts.djsn.report.pdf-custom', compact(
            'records',
            'selectedFields',
            'fieldLabels',
            'printedBy',
            'printedAt'
        ), 'report-djsn-dewas-custom.pdf');
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

    private function reportFieldLabels(): array
    {
        return [
            'surat' => 'NOMOR, TANGGAL & PERIHAL SURAT',
            'id_butir' => 'ID BUTIR DJSN',
            'isi_butir' => 'ISI BUTIR DJSN',
            'pic_utama' => 'PIC UNIT KERJA UTAMA',
            'pic_pendukung' => 'PIC UNIT KERJA PENDUKUNG',
            'tanggapan' => 'TANGGAPAN DIREKSI',
            'tindak_lanjut' => 'TINDAK LANJUT DIREKSI',
            'deliverable' => 'DELIVERABLE',
            'dokumen' => 'DOKUMEN PENDUKUNG',
            'jatuh_tempo' => 'TGL. JATUH TEMPO',
            'komite' => 'PIC KOMITE DEWAN PENGAWAS',
            'hasil_reviu' => 'HASIL REVIU DEWAN PENGAWAS',
            'status' => 'STATUS TINDAK LANJUT',

            // Optional, kalau PDF custom masih pakai gabungan
            'pic_unit' => 'PIC UNIT KERJA',
            'tanggapan_tl' => 'TANGGAPAN & TINDAK LANJUT DIREKSI',
        ];
    }

    private function mapFieldsForPdf(array $fields): array
    {
        $mapped = [];

        foreach ($fields as $field) {
            if ($field === 'pic_utama' || $field === 'pic_pendukung') {
                if (!in_array('pic_unit', $mapped, true)) {
                    $mapped[] = 'pic_unit';
                }

                continue;
            }

            if ($field === 'tanggapan' || $field === 'tindak_lanjut') {
                if (!in_array('tanggapan_tl', $mapped, true)) {
                    $mapped[] = 'tanggapan_tl';
                }

                continue;
            }

            $mapped[] = $field;
        }

        return array_values(array_unique($mapped));
    }

    public function cetakExcel(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnReport()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report DJSN.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat DJSN untuk dicetak.',
        ]);

        $records = DjsnRecord::with([
            'butirDjsn.cluster',
            'butirDjsn.subCluster',
            'butirDjsn.butirPics.unitKerja',
            'butirDjsn.butirPics.komite',
            'butirDjsn.tanggapan.review',
            'butirDjsn.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->orderBy('id_djsn')
            ->get();

        $selectedFields = [
            'surat',
            'id_butir',
            'isi_butir',
            'pic_utama',
            'pic_pendukung',
            'tanggapan',
            'tindak_lanjut',
            'deliverable',
            'dokumen',
            'jatuh_tempo',
            'komite',
            'hasil_reviu',
            'status',
        ];

        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(
            new DjsnReportExport($records, $selectedFields, $fieldLabels),
            'report-djsn-dewas.xlsx'
        );
    }

    public function cetakExcelCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnReport()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report DJSN.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat DJSN untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir DJSN untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);

        $allowedFields = [
            'surat',
            'id_butir',
            'isi_butir',
            'pic_utama',
            'pic_pendukung',
            'tanggapan',
            'tindak_lanjut',
            'deliverable',
            'dokumen',
            'jatuh_tempo',
            'komite',
            'hasil_reviu',
            'status',
        ];

        $selectedFields = array_values(array_intersect($validated['fields'], $allowedFields));

        if (empty($selectedFields)) {
            return back()->with('error', 'Pilih minimal satu field report.');
        }

        $butirIds = $validated['butir_ids'];

        $records = DjsnRecord::with([
            'butirDjsn' => function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds)
                    ->orderBy('id');
            },
            'butirDjsn.cluster',
            'butirDjsn.subCluster',
            'butirDjsn.butirPics.unitKerja',
            'butirDjsn.butirPics.komite',
            'butirDjsn.tanggapan.review',
            'butirDjsn.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->whereHas('butirDjsn', function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds);
            })
            ->orderBy('id_djsn')
            ->get();

        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(
            new DjsnReportExport($records, $selectedFields, $fieldLabels),
            'report-djsn-dewas-custom.xlsx'
        );
    }
}
