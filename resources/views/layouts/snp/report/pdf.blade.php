<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Report SNP Dewas</title>

    <style>
        @page {
            size: legal landscape;
            margin: 8mm 8mm 14mm;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table,
        th,
        td {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
        }

        th {
            text-align: center;
            font-weight: bold;
            background: #f2f2f2;
        }

        thead {
            display: table-header-group;
        }

        tbody td { border-top: 0; border-bottom: 0; }
        .butir-start td { border-top: 1px solid #000; }
        .butir-end td { border-bottom: 1px solid #000; }

        .snp-butir-cell { white-space: normal; }
        .snp-content-line { white-space: normal; }
        .continuation { color: #666; }

        .justify {
            text-align: justify;
        }

        .center {
            text-align: center;
        }

        .top {
            vertical-align: top;
        }

        .pre-line {
            white-space: pre-line;
        }

        .small {
            font-size: 7px;
        }

        .status {
            text-transform: uppercase;
            font-weight: bold;
        }

        a {
            color: #2377b9;
            text-decoration: underline;
        }

        .badge {
            display: inline-block;
            padding: 3px 5px;
            border-radius: 4px;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 6px;
            border-radius: 5px;
            font-weight: bold;
            color: #fff;
        }

        .status-belum {
            background: #64748b;
        }

        .status-reviu {
            background: #2377b9;
        }

        .status-tl {
            background: #c8e079;
        }

        .status-selesai {
            background: #6bb17e;
        }

        .print-footer {
            position: fixed;
            left: 0;
            bottom: -7mm;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6px;
            color: #444;
        }

        .watermark {
            position: fixed;
            top: 38%;
            left: 0;
            width: 100%;
            text-align: center;
            z-index: -1000;

            font-family: Arial, Helvetica, sans-serif;
            font-size: 85px;
            font-weight: bold;
            color: rgba(0, 0, 0, 0.08);

            transform: rotate(-30deg);
            transform-origin: center;
            letter-spacing: 4px;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    <div class="watermark">
        SIDEWAS SNP DEWAS
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">NOMOR, TANGGAL & PERIHAL SURAT</th>
                <th style="width: 5%;">ID BUTIR SNP</th>
                <th style="width: 28%;">ISI BUTIR SNP</th>
                <th style="width: 7%;">PIC UNIT KERJA</th>
                <th style="width: 12%;">TANGGAPAN & TINDAK LANJUT DIREKSI</th>
                <th style="width: 6%;">DELIVERABLE</th>
                <th style="width: 6%;">DOKUMEN PENDUKUNG</th>
                <th style="width: 7%;">TGL. JATUH TEMPO</th>
                <th style="width: 6%;">PIC KOMITE DEWAN PENGAWAS</th>
                <th style="width: 8%;">HASIL REVIU DEWAN PENGAWAS</th>
                <th style="width: 7%;">STATUS TINDAK LANJUT</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($records as $record)
                @foreach ($record->butirSnp as $butir)
                    @php
                        $picUtama = $butir->butirPics->where('jenis_pic', 'utama')->first();
                        $picPendukung = $butir->butirPics->where('jenis_pic', 'pendukung');
                        $komitePic = $butir->butirPics->where('jenis_pic', 'komite')->first();

                        $kompilasiTanggapan =
                            $butir->kompilasiTanggapan ??
                            $butir->kompilasis->where('tahap_kompilasi', 'tanggapan')->sortByDesc('id')->first();

                        $kompilasiTindakLanjuts = $butir->kompilasiTindakLanjuts;

                        if (!$kompilasiTindakLanjuts || $kompilasiTindakLanjuts->count() === 0) {
                            $kompilasiTindakLanjuts = $butir->kompilasis
                                ->where('tahap_kompilasi', 'tindak_lanjut')
                                ->sortBy('putaran_tl')
                                ->values();
                        }

                        $reviewTanggapan = $butir->reviews
                            ->where('tahap_review', 'tanggapan')
                            ->sortByDesc('id')
                            ->first();

                        $reviewTerbaruButir = $butir->reviews->sortByDesc('id')->first();

                        $reviewTlTerbaru = $butir->reviews
                            ->where('tahap_review', 'tindak_lanjut')
                            ->sortByDesc('id')
                            ->first();

                        $statusTerbaruButir =
                            $reviewTerbaruButir?->status ??
                            ($reviewTlTerbaru?->status ?? ($reviewTanggapan?->status ?? 'belum_ditanggapi'));

                        $statusTerbaruButirClass = match ($statusTerbaruButir) {
                            'belum_ditanggapi' => 'status-belum',
                            'dalam_proses_reviu_dewas' => 'status-reviu',
                            'dalam_proses_tindak_lanjut_direksi' => 'status-tl',
                            'selesai_tuntas', 'selesai' => 'status-selesai',
                            default => 'status-belum',
                        };

                        $jatuhTempoAwal = $record->tanggal_surat
                            ? \App\Models\SnpRecord::hitungJatuhTempo($record->tanggal_surat)
                            : null;

                        $jatuhTempoFinal = $jatuhTempoAwal;

                        if (
                            $kompilasiTanggapan &&
                            $kompilasiTanggapan->status_pengajuan_tgl === 'disetujui' &&
                            !empty($kompilasiTanggapan->ubah_tgl)
                        ) {
                            $jatuhTempoFinal = \Carbon\Carbon::parse($kompilasiTanggapan->ubah_tgl);
                        }

                        $contentChunks = app(\App\Services\SnpButirReportContent::class)->pdfChunks($butir->butir_snp, 346);
                        $rowCount = max(count($contentChunks), 1 + $kompilasiTindakLanjuts->count());
                    @endphp
                    @for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++)
                        @php
                            $hasStage = $rowIndex <= $kompilasiTindakLanjuts->count();
                            $stage = $rowIndex === 0 ? $kompilasiTanggapan : ($kompilasiTindakLanjuts[$rowIndex - 1] ?? null);
                            $review = $rowIndex === 0 ? $reviewTanggapan : ($stage
                                ? $butir->reviews->where('tahap_review', 'tindak_lanjut')->where('putaran_tl', $stage->putaran_tl)->sortByDesc('id')->first()
                                : null);
                        @endphp
                        <tr class="{{ $rowIndex === 0 ? 'butir-start' : '' }} {{ $rowIndex === $rowCount - 1 ? 'butir-end' : '' }}">
                            <td class="pre-line top">@if ($rowIndex === 0){{ implode("\n", [$record->nomor_surat, $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d-M-Y') : '-', $record->perihal_surat]) }}@endif</td>
                            <td class="center">{{ $butir->id_butir_snp }}@if ($rowIndex > 0 && isset($contentChunks[$rowIndex]))<br><span class="continuation">Lanjutan</span>@endif</td>
                            <td class="snp-butir-cell" data-snp-butir-content="{{ $butir->id_butir_snp }}">{!! $contentChunks[$rowIndex] ?? '' !!}</td>
                            <td class="pre-line top">@if ($rowIndex === 0){{ implode("\n", ['PIC UNIT KERJA UTAMA:', $picUtama?->unitKerja?->kode_unit ?? '-', '', 'PIC UNIT KERJA PENDUKUNG:', $picPendukung->map(fn($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') ?: '-']) }}@endif</td>
                            <td class="pre-line top">@if ($hasStage){{ $stage?->hasil_kompilasi ?? '-' }}@endif</td>
                            <td class="pre-line top">@if ($hasStage){{ $stage?->deliverables ?? '-' }}@endif</td>
                            <td class="center">
                                @if ($hasStage)
                                    @if ($stage?->dokumen)
                                        @if ($rowIndex > 0)Putaran {{ $stage->putaran_tl }}:<br>@endif
                                        <a href="{{ asset('storage/' . $stage->dokumen) }}">{{ $rowIndex === 0 ? 'Dokumen Kompilasi Tanggapan' : 'Dokumen Kompilasi TL' }}</a>
                                    @else
                                        -
                                    @endif
                                @endif
                            </td>
                            <td class="pre-line top">@if ($hasStage){{ ($rowIndex === 0 ? $jatuhTempoAwal : $jatuhTempoFinal)?->format('d-M-Y') ?? '-' }}@if ($rowIndex === 0 && $kompilasiTanggapan?->ubah_tgl){{ "\n\nPengajuan ubah tanggal:\n" . \Carbon\Carbon::parse($kompilasiTanggapan->ubah_tgl)->format('d-M-Y') . "\n\nStatus pengajuan:\n" . ucwords(str_replace('_', ' ', $kompilasiTanggapan->status_pengajuan_tgl ?? 'pending')) }}@endif @endif</td>
                            <td class="center">@if ($rowIndex === 0){{ $komitePic?->komite?->kode_komite ?? '-' }}@endif</td>
                            <td class="pre-line top">@if ($hasStage){{ $review?->hasil_review ?? '-' }}@endif</td>
                            <td class="center">
                                @if ($rowIndex === 0)
                                    <span class="status-badge {{ $statusTerbaruButirClass }}">{{ ucwords(str_replace('_', ' ', $statusTerbaruButir)) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endfor
                @endforeach
            @endforeach
        </tbody>
    </table>
    <div class="print-footer">
        Dokumen ini dicetak oleh {{ $printedBy ?? '-' }} pada {{ $printedAt ?? '-' }}
    </div>
</body>

</html>
