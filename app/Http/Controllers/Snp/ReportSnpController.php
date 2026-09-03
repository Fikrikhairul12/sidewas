<?php

namespace App\Http\Controllers\Snp;

use App\Exports\SnpReportExport;
use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\SnpRecord;
use App\Models\UnitKerja;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ReportSnpController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (! $user || ! $user->canAccessSnpReport()) {
            abort(403, 'Anda tidak memiliki akses ke halaman report SNP.');
        }

        $recordsQuery = SnpRecord::with([
            'cluster',
            'subCluster',
            'butirSnp.butirPics.unitKerja',
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

    public function cetak(Request $request): View
    {
        $user = $this->authorizedReportUser();
        $validated = $this->validateRegularReportRequest($request);

        return $this->previewPdf(
            'layouts.snp.report.pdf',
            $this->regularReportData($validated['record_ids'], $user),
            route('snp.report.download'),
            $validated,
            'Pratinjau Report SNP Dewas',
            'report-snp-dewas.pdf'
        );
    }

    public function download(Request $request): Response
    {
        $user = $this->authorizedReportUser();
        $validated = $this->validateRegularReportRequest($request);

        return $this->downloadPdf(
            'layouts.snp.report.pdf',
            $this->regularReportData($validated['record_ids'], $user),
            'report-snp-dewas.pdf'
        );
    }

    public function cetakCustom(Request $request): View
    {
        $user = $this->authorizedReportUser();
        $validated = $this->validateCustomReportRequest($request);

        return $this->previewPdf(
            'layouts.snp.report.pdf-custom',
            $this->customReportData($validated, $user),
            route('snp.report.download-custom'),
            $validated,
            'Pratinjau Report SNP Dewas Custom',
            'report-snp-dewas-custom.pdf'
        );
    }

    public function downloadCustom(Request $request): Response
    {
        $user = $this->authorizedReportUser();
        $validated = $this->validateCustomReportRequest($request);

        return $this->downloadPdf(
            'layouts.snp.report.pdf-custom',
            $this->customReportData($validated, $user),
            'report-snp-dewas-custom.pdf'
        );
    }

    private function authorizedReportUser(): User
    {
        $user = User::find(Auth::id());

        if (! $user || ! $user->canAccessSnpReport()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report SNP.');
        }

        return $user;
    }

    /**
     * @return array{record_ids: array<int, int|string>}
     */
    private function validateRegularReportRequest(Request $request): array
    {
        return $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat SNP untuk dicetak.',
        ]);
    }

    /**
     * @return array<string, array<int, int|string>>
     */
    private function validateCustomReportRequest(Request $request): array
    {
        return $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
            'tanggapan_unit_kerja_ids' => ['nullable', 'array'],
            'tanggapan_unit_kerja_ids.*' => ['integer'],
            'tindak_lanjut_unit_kerja_ids' => ['nullable', 'array'],
            'tindak_lanjut_unit_kerja_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat SNP untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir SNP untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);
    }

    /**
     * @param  array<int, int|string>  $recordIds
     * @return array<string, mixed>
     */
    private function regularReportData(array $recordIds, User $user): array
    {
        $records = SnpRecord::with([
            'cluster',
            'subCluster',
            'butirSnp.butirPics.unitKerja',
            'butirSnp.butirPics.komite',
            'butirSnp.kompilasis',
            'butirSnp.kompilasiTanggapan',
            'butirSnp.kompilasiTindakLanjut',
            'butirSnp.kompilasiTindakLanjuts',
            'butirSnp.reviews.komite',
        ])
            ->whereIn('id', $recordIds)
            ->orderBy('id_snp')
            ->get();

        return [
            'records' => $records,
            'printedBy' => $user->name ?? $user->email ?? 'User',
            'printedAt' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * @param  array<string, array<int, int|string>>  $validated
     * @return array<string, mixed>
     */
    private function customReportData(array $validated, User $user): array
    {
        $selectedFields = array_values(array_intersect(
            $validated['fields'],
            array_keys($this->pdfFieldLabels())
        ));
        $selectedFields = $this->mapFieldsForPdf($selectedFields);

        abort_if(empty($selectedFields), 422, 'Pilih minimal satu field report.');

        $tanggapanUnitKerjaIds = $validated['tanggapan_unit_kerja_ids'] ?? [];
        $tindakLanjutUnitKerjaIds = $validated['tindak_lanjut_unit_kerja_ids'] ?? [];
        $butirIds = $validated['butir_ids'];

        $records = SnpRecord::with([
            'cluster',
            'subCluster',
            'butirSnp' => function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds)
                    ->orderBy('id');
            },
            'butirSnp.butirPics.unitKerja',
            'butirSnp.butirPics.komite',
            'butirSnp.tanggapan.creator',
            'butirSnp.tanggapan.butirPic.unitKerja',
            'butirSnp.tindakLanjuts.creator',
            'butirSnp.tindakLanjuts.butirPic.unitKerja',
            'butirSnp.kompilasis',
            'butirSnp.kompilasiTanggapan',
            'butirSnp.kompilasiTindakLanjut',
            'butirSnp.kompilasiTindakLanjuts',
            'butirSnp.reviews.komite',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->whereHas('butirSnp', function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds);
            })
            ->orderBy('id_snp')
            ->get();

        return [
            'records' => $records,
            'selectedFields' => $selectedFields,
            'fieldLabels' => $this->pdfFieldLabels(),
            'printedBy' => $user->name ?? $user->email ?? 'User',
            'printedAt' => now()->format('d/m/Y H:i'),
            'tanggapanUnitKerjaIds' => $tanggapanUnitKerjaIds,
            'tindakLanjutUnitKerjaIds' => $tindakLanjutUnitKerjaIds,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, int|string>>  $downloadParameters
     */
    private function previewPdf(
        string $reportView,
        array $data,
        string $downloadRoute,
        array $downloadParameters,
        string $title,
        string $filename
    ): View {
        return view('layouts.snp.report.preview', [
            'title' => $title,
            'filename' => $filename,
            'reportHtml' => view($reportView, $data)->render(),
            'downloadRoute' => $downloadRoute,
            'downloadParameters' => $downloadParameters,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function downloadPdf(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, $data)
            ->setPaper('legal', 'landscape')
            ->download($filename);
    }

    private function reportFieldLabels(): array
    {
        return [
            'surat' => 'NOMOR, TANGGAL & PERIHAL SURAT',
            'id_butir' => 'ID BUTIR SNP',
            'isi_butir' => 'ISI BUTIR SNP',
            'pic_unit' => 'PIC UNIT KERJA',
            'pic_utama' => 'PIC UNIT KERJA UTAMA',
            'pic_pendukung' => 'PIC UNIT KERJA PENDUKUNG',

            'tanggapan_unit' => 'TANGGAPAN UNIT KERJA',
            'tindak_lanjut_unit' => 'TINDAK LANJUT UNIT KERJA',
            'kompilasi_tanggapan' => 'KOMPILASI TANGGAPAN',
            'kompilasi_tindak_lanjut' => 'KOMPILASI TINDAK LANJUT',

            'deliverable' => 'DELIVERABLE',
            'dokumen' => 'DOKUMEN PENDUKUNG',
            'jatuh_tempo' => 'TGL. JATUH TEMPO',
            'komite' => 'PIC KOMITE DEWAN PENGAWAS',
            'hasil_reviu' => 'HASIL REVIU DEWAN PENGAWAS',
            'status' => 'STATUS',
        ];
    }

    private function pdfFieldLabels(): array
    {
        return [
            ...$this->reportFieldLabels(),
            'status' => 'STATUS TINDAK LANJUT',
        ];
    }

    private function mapFieldsForPdf(array $fields): array
    {
        $mapped = [];

        foreach ($fields as $field) {
            if ($field === 'pic_utama' || $field === 'pic_pendukung') {
                if (! in_array('pic_unit', $mapped, true)) {
                    $mapped[] = 'pic_unit';
                }

                continue;
            }

            if ($field === 'tanggapan' || $field === 'tindak_lanjut') {
                if (! in_array('tanggapan_tl', $mapped, true)) {
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

        if (! $user || ! $user->canAccessSnpReport()) {
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
            'butirSnp.kompilasiTanggapan',
            'butirSnp.kompilasiTindakLanjut',
            'butirSnp.kompilasiTindakLanjuts',
            'butirSnp.reviews.komite',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->orderBy('id_snp')
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
            new SnpReportExport($records, $selectedFields, $fieldLabels),
            'report-snp-dewas.xlsx'
        );
    }

    public function cetakExcelCustom(Request $request)
    {
        $user = User::find(Auth::id());

        if (! $user || ! $user->canAccessSnpReport()) {
            abort(403, 'Anda tidak memiliki akses untuk mencetak report SNP.');
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'butir_ids' => ['required', 'array', 'min:1'],
            'butir_ids.*' => ['integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
            'tanggapan_unit_kerja_ids' => ['nullable', 'array'],
            'tanggapan_unit_kerja_ids.*' => ['integer'],

            'tindak_lanjut_unit_kerja_ids' => ['nullable', 'array'],
            'tindak_lanjut_unit_kerja_ids.*' => ['integer'],
        ], [
            'record_ids.required' => 'Pilih minimal satu surat SNP untuk dicetak.',
            'butir_ids.required' => 'Pilih minimal satu butir SNP untuk dicetak.',
            'fields.required' => 'Pilih minimal satu field report.',
        ]);

        $allowedFields = [
            'surat',
            'id_butir',
            'isi_butir',
            'pic_utama',
            'pic_pendukung',
            'tanggapan_unit',
            'tindak_lanjut_unit',
            'kompilasi_tanggapan',
            'kompilasi_tindak_lanjut',
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

        $tanggapanUnitKerjaIds = $validated['tanggapan_unit_kerja_ids'] ?? [];
        $tindakLanjutUnitKerjaIds = $validated['tindak_lanjut_unit_kerja_ids'] ?? [];

        $butirIds = $validated['butir_ids'];

        $records = SnpRecord::with([
            'cluster',
            'subCluster',
            'butirSnp' => function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds)
                    ->orderBy('id');
            },
            'butirSnp.butirPics.unitKerja',
            'butirSnp.butirPics.komite',

            // detail unit
            'butirSnp.tanggapan.creator',
            'butirSnp.tanggapan.butirPic.unitKerja',
            'butirSnp.tindakLanjuts.creator',
            'butirSnp.tindakLanjuts.butirPic.unitKerja',

            // hasil kompilasi
            'butirSnp.kompilasiTanggapan',
            'butirSnp.kompilasiTindakLanjut',

            // reviu
            'butirSnp.reviews.komite',
        ])
            ->whereIn('id', $validated['record_ids'])
            ->whereHas('butirSnp', function ($query) use ($butirIds) {
                $query->whereIn('id', $butirIds);
            })
            ->orderBy('id_snp')
            ->get();

        $fieldLabels = $this->reportFieldLabels();

        return Excel::download(
            new SnpReportExport($records, $selectedFields, $fieldLabels, $tanggapanUnitKerjaIds, $tindakLanjutUnitKerjaIds),
            'report-snp-dewas-custom.xlsx'
        );
    }
}
