<?php

namespace App\Http\Controllers\Rawas;

use App\Http\Controllers\Controller;
use App\Models\RawasRecord;
use App\Models\User;
use App\Models\UnitKerja;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Exports\RawasReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ReportRawasController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses ke halaman report RAWAS.');
        }

        $recordsQuery = RawasRecord::with([
            'cluster',
            'subCluster',
            'butirRawas',
        ])
            ->withCount('butirRawas');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_rawas', 'like', "%{$keyword}%")
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

            $recordsQuery->whereHas('butirRawas.butirPics', function ($query) use ($unitKerjaIds) {
                $query->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $unitKerjaUtamaId = $request->unit_kerja_utama_id;

            $recordsQuery->whereHas('butirRawas.butirPics', function ($query) use ($unitKerjaUtamaId) {
                $query->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $unitKerjaUtamaId);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $unitKerjaPendukungId = $request->unit_kerja_pendukung_id;

            $recordsQuery->whereHas('butirRawas.butirPics', function ($query) use ($unitKerjaPendukungId) {
                $query->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $unitKerjaPendukungId);
            });
        }

        if ($request->filled('komite_id')) {
            $komiteId = $request->komite_id;

            $recordsQuery->whereHas('butirRawas.butirPics', function ($query) use ($komiteId) {
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

        return view('layouts.rawas.report.index', compact(
            'records',
            'direktorats',
            'unitKerjas',
            'komites'
        ));
    }

    public function cetak(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report RAWAS.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat RAWAS untuk dicetak.',
        ]);

        $records = RawasRecord::with([
            'cluster',
            'subCluster',
            'butirRawas.butirPics.unitKerja',
            'butirRawas.butirPics.komite',
            'butirRawas.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->orderBy('id_rawas')
            ->get();

        $printedBy = $user->name ?? $user->email ?? 'User';
        $printedAt = now()->format('d/m/Y H:i');

        $pdf = Pdf::loadView('layouts.rawas.report.pdf', compact(
            'records',
            'printedBy',
            'printedAt'
        ))
            ->setPaper('legal', 'landscape');

        return $pdf->download('report-rawas.pdf');
    }

    public function cetakCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report RAWAS.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat RAWAS untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir RAWAS untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);

        $allowedFields = [
            'surat',
            'id_butir',
            'isi_butir',
            'pic_unit',
            'pic_utama',
            'pic_pendukung',
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

        $records = RawasRecord::with([
            'cluster',
            'subCluster',
            'butirRawas' => function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds)
                    ->orderBy('id');
            },
            'butirRawas.butirPics.unitKerja',
            'butirRawas.butirPics.komite',
            'butirRawas.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->whereHas('butirRawas', function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds);
            })
            ->orderBy('id_rawas')
            ->get();

        $fieldLabels = [
            'surat' => 'NOMOR, TANGGAL & PERIHAL SURAT',
            'id_butir' => 'ID BUTIR RAWAS',
            'isi_butir' => 'ISI BUTIR RAWAS',
            'pic_unit' => 'PIC UNIT KERJA',
            'tindak_lanjut' => 'TINDAK LANJUT DIREKSI',
            'deliverable' => 'DELIVERABLE',
            'dokumen' => 'DOKUMEN PENDUKUNG',
            'jatuh_tempo' => 'TGL. JATUH TEMPO',
            'komite' => 'PIC KOMITE DEWAN PENGAWAS',
            'hasil_reviu' => 'HASIL REVIU DEWAN PENGAWAS',
            'status' => 'STATUS TINDAK LANJUT',
        ];

        $printedBy = $user->name ?? $user->email ?? 'User';
        $printedAt = now()->format('d/m/Y H:i');

        $pdf = Pdf::loadView('layouts.rawas.report.pdf-custom', compact(
            'records',
            'selectedFields',
            'fieldLabels',
            'printedBy',
            'printedAt'
        ))
            ->setPaper('legal', 'landscape');

        return $pdf->stream('report-rawas-custom.pdf');
    }

    private function reportFieldLabels(): array
    {
        return [
            'surat' => 'NOMOR, TANGGAL & PERIHAL SURAT',
            'id_butir' => 'ID BUTIR RAWAS',
            'isi_butir' => 'ISI BUTIR RAWAS',
            'pic_utama' => 'PIC UNIT KERJA UTAMA',
            'pic_pendukung' => 'PIC UNIT KERJA PENDUKUNG',
            'tindak_lanjut' => 'TINDAK LANJUT DIREKSI',
            'deliverable' => 'DELIVERABLE',
            'dokumen' => 'DOKUMEN PENDUKUNG',
            'jatuh_tempo' => 'TGL. JATUH TEMPO',
            'komite' => 'PIC KOMITE DEWAN PENGAWAS',
            'hasil_reviu' => 'HASIL REVIU DEWAN PENGAWAS',
            'status' => 'STATUS TINDAK LANJUT',

            // Optional, kalau PDF custom masih pakai gabungan
            'pic_unit' => 'PIC UNIT KERJA',
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
            $mapped[] = $field;
        }

        return array_values(array_unique($mapped));
    }

    public function cetakExcel(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report Rawas.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat Rawas untuk dicetak.',
        ]);

        $records = RawasRecord::with([
            'cluster',
            'subCluster',
            'butirRawas.butirPics.unitKerja',
            'butirRawas.butirPics.komite',
            'butirRawas.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->orderBy('id_rawas')
            ->get();

        $selectedFields = [
            'surat',
            'id_butir',
            'isi_butir',
            'pic_utama',
            'pic_pendukung',
            'tindak_lanjut',
            'deliverable',
            'dokumen',
            'jatuh_tempo',
            'komite',
            'hasil_reviu',
            'status',
        ];

        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(new RawasReportExport($records, $selectedFields, $fieldLabels), 'report-rawas.xlsx');
    }

    public function cetakExcelCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report Rawas.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat Rawas untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir Rawas untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);

        $allowedFields = [
            'surat',
            'id_butir',
            'isi_butir',
            'pic_unit',
            'pic_utama',
            'pic_pendukung',
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
            return redirect()->back()->with('error', 'Tidak ada field yang dipilih.');
        }

        $butirIds = $validated['butir_ids'];

        $records = RawasRecord::with([
            'cluster',
            'subCluster',
            'butirRawas' => function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds)
                    ->orderBy('id');
            },
            'butirRawas.butirPics.unitKerja',
            'butirRawas.butirPics.komite',
            'butirRawas.tindakLanjuts.reviews',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->whereHas('butirRawas', function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds);
            })
            ->orderBy('id_rawas')
            ->get();

        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(new RawasReportExport($records, $selectedFields, $fieldLabels), 'report-rawas-custom.xlsx');
    }
}
