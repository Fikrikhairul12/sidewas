<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Report RAWAS</title>

    <style>
        @page {
            size: legal landscape;
            margin: 8mm;
        }

        body {
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
            font-size: 8px;
        }

        th {
            text-align: center;
            font-weight: bold;
            background: #f2f2f2;
        }

        .center {
            text-align: center;
        }

        .pre-line {
            white-space: pre-line;
        }

        a {
            color: #2377b9;
            text-decoration: underline;
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

        .status-selesai {
            background: #6bb17e;
        }

        .print-footer {
            position: fixed;
            left: 8mm;
            bottom: 5mm;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7px;
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
        SIDEWAS RAWAS
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 11%;">NOMOR, TANGGAL & PERIHAL SURAT</th>
                <th style="width: 9%;">TGL & AGENDA RAWAS</th>
                <th style="width: 13%;">KEPUTUSAN RAWAS</th>
                <th style="width: 8%;">DIREKTORAT</th>
                <th style="width: 7%;">UNIT PIC</th>
                <th style="width: 13%;">TINDAK LANJUT KEPUTUSAN RAWAS</th>
                <th style="width: 8%;">DELIVERABLE</th>
                <th style="width: 8%;">DOKUMEN PENDUKUNG</th>
                <th style="width: 7%;">TGL. JATUH TEMPO</th>
                <th style="width: 10%;">HASIL REVIU TINDAK LANJUT KEPUTUSAN RAWAS</th>
                <th style="width: 6%;">STATUS TINDAK LANJUT</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($records as $record)
                @php
                    $totalRowsRecord = 0;

                    foreach ($record->butirRawas as $butirHitung) {
                        $totalRowsRecord += max(1, $butirHitung->tindakLanjuts->count());
                    }

                    $isFirstRecordRow = true;
                @endphp

                @foreach ($record->butirRawas as $butir)
                    @php
                        $tindakLanjuts = $butir->tindakLanjuts
                            ->sortBy([
                                fn($tl) => $tl->butirPic?->unitKerja?->kode_unit ?? 'ZZZ',
                                fn($tl) => $tl->id,
                            ])
                            ->values();

                        if ($tindakLanjuts->count() === 0) {
                            $tindakLanjuts = collect([null]);
                        }

                        $jumlahBarisButir = max(1, $tindakLanjuts->count());
                        $review = $butir->reviewTindakLanjut;

                        $statusTl =
                            $review?->status ??
                            ($butir->tindakLanjuts->count() > 0
                                ? $butir->statusTindakLanjut()
                                : 'belum_ditindaklanjuti');

                        $statusTlClass = match ($statusTl) {
                            'belum_ditindaklanjuti', 'belum_ditanggapi', 'dalam_proses_tindak_lanjut' => 'status-belum',
                            'dalam_proses_reviu_dewan_pengawas' => 'status-reviu',
                            'selesai_tuntas', 'tuntas' => 'status-selesai',
                            default => 'status-belum',
                        };

                        $statusTlLabel = match ($statusTl) {
                            'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
                            'dalam_proses_tindak_lanjut' => 'Dalam Proses Tindak Lanjut',
                            'diusulkan_tuntas' => 'Diusulkan Tuntas',
                            'belum_ditanggapi' => 'Belum Direviu',
                            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu',
                            'selesai_tuntas' => 'Selesai Tuntas',
                            'tuntas' => 'Tuntas',
                            default => ucwords(str_replace('_', ' ', $statusTl)),
                        };
                    @endphp

                    @foreach ($tindakLanjuts as $i => $tl)
                        <tr>
                            @if ($isFirstRecordRow)
                                <td rowspan="{{ $totalRowsRecord }}" class="pre-line">
                                    {{ $record->nomor_surat ?? '-' }}
                                    {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d-M-Y') : '-' }}

                                    {{ $record->perihal_surat ?? '-' }}
                                    <br>
                                    @if ($record->dokumen_memo)
                                        <a href="{{ asset('storage/' . $record->dokumen_memo) }}">Dokumen Memo</a>
                                    @endif
                                </td>

                                @php
                                    $isFirstRecordRow = false;
                                @endphp
                            @endif

                            @if ($i === 0)
                                <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                    {{ $butir->tanggal_rawas ? \Carbon\Carbon::parse($butir->tanggal_rawas)->format('d-M-Y') : '-' }}

                                    {{ $butir->agenda_rawas ?? '-' }}
                                </td>

                                <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                    {{ $butir->keputusan_rawas ?? '-' }}
                                </td>

                                <td rowspan="{{ $jumlahBarisButir }}" class="center pre-line">
                                    Dewan Pengawas
                                </td>
                            @endif

                            <td class="center pre-line">
                                {{ $tl?->butirPic?->unitKerja?->kode_unit ?? '-' }}
                            </td>

                            <td class="pre-line">
                                {{ $tl?->tindak_lanjut ?? '-' }}
                            </td>

                            <td class="pre-line">
                                {{ $tl?->deliverables ?? '-' }}
                            </td>

                            <td class="center pre-line">
                                @if ($tl?->dokumen)
                                    <a href="{{ asset('storage/' . $tl->dokumen) }}">Dokumen TL</a>
                                @else
                                    -
                                @endif
                            </td>

                            <td class="center pre-line">
                                @if ($tl?->jth_tempo)
                                    {{ \Carbon\Carbon::parse($tl->jth_tempo)->format('d-M-Y') }}
                                @elseif ($record->jth_tempo)
                                    {{ \Carbon\Carbon::parse($record->jth_tempo)->format('d-M-Y') }}
                                @else
                                    -
                                @endif
                            </td>

                            @if ($i === 0)
                                <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                    {{ $review?->hasil_review ?? '-' }}
                                </td>

                                <td rowspan="{{ $jumlahBarisButir }}" class="center">
                                    <span class="status-badge {{ $statusTlClass }}">
                                        {{ $statusTlLabel }}
                                    </span>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="print-footer">
        Dokumen ini dicetak oleh {{ $printedBy ?? '-' }} pada {{ $printedAt ?? '-' }}
    </div>
</body>

</html>
