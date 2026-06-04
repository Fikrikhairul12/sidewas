<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Report RAGAB Custom</title>

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

        .status-tl {
            background: #c8e079;
            color: #000;
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
            font-size: 55px;
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
        SIDEWAS RAGAB
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($selectedFields as $field)
                    <th>
                        {{ $fieldLabels[$field] ?? strtoupper(str_replace('_', ' ', $field)) }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ($records as $record)
                @php
                    $totalRowsRecord = 0;

                    foreach ($record->butirRagab as $butirHitung) {
                        $totalRowsRecord += max(1, $butirHitung->tindakLanjuts->count());
                    }

                    $isFirstRecordRow = true;
                @endphp

                @foreach ($record->butirRagab as $butir)
                    @php
                        $picUtama = $butir->butirPics->where('jenis_pic', 'utama')->first();
                        $picPendukung = $butir->butirPics->where('jenis_pic', 'pendukung');
                        $komitePic = $butir->butirPics->where('jenis_pic', 'komite')->first();

                        $tindakLanjuts = $butir->tindakLanjuts->values();
                        $jumlahBarisButir = max(1, $tindakLanjuts->count());
                    @endphp

                    @for ($i = 0; $i < $jumlahBarisButir; $i++)
                        @php
                            $tl = $tindakLanjuts[$i] ?? null;

                            $reviewTl = $tl
                                ? $tl->reviews
                                    ->where('tahap_review', 'tindak_lanjut')
                                    ->sortByDesc('id')
                                    ->first()
                                : null;

                            $statusTl = $reviewTl?->status ?? ($tl ? 'belum_ditanggapi' : 'belum_ditindaklanjuti');

                            $statusTlClass = match ($statusTl) {
                                'belum_ditindaklanjuti' => 'status-belum',
                                'belum_ditanggapi' => 'status-belum',
                                'dalam_proses_reviu_dewan_pengawas' => 'status-reviu',
                                'dalam_proses_tindak_lanjut_direksi' => 'status-tl',
                                'selesai_tuntas', 'selesai' => 'status-selesai',
                                default => 'status-belum',
                            };

                            $statusTlLabel = match ($statusTl) {
                                'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
                                'belum_ditanggapi' => 'Belum Direviu',
                                'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
                                'dalam_proses_tindak_lanjut_direksi' => 'Dalam Proses Tindak Lanjut Direksi',
                                'selesai_tuntas' => 'Selesai Tuntas',
                                'selesai' => 'Selesai',
                                default => ucwords(str_replace('_', ' ', $statusTl)),
                            };
                        @endphp

                        <tr>
                            @foreach ($selectedFields as $field)
                                @if ($field === 'surat')
                                    @if ($isFirstRecordRow)
                                        <td rowspan="{{ $totalRowsRecord }}" class="pre-line">
                                            {{ $record->nomor_surat }}
                                            {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d-M-Y') : '-' }}

                                            {{ $record->perihal_surat }}
                                        </td>

                                        @php
                                            $isFirstRecordRow = false;
                                        @endphp
                                    @endif

                                @elseif ($field === 'id_butir')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="center pre-line">
                                            {{ $butir->id_butir_ragab }}
                                        </td>
                                    @endif

                                @elseif ($field === 'isi_butir')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                            {{ $butir->butir_ragab }}
                                        </td>
                                    @endif

                                @elseif ($field === 'pic_unit')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                            PIC UNIT KERJA UTAMA:
                                            {{ $picUtama?->unitKerja?->kode_unit ?? '-' }}

                                            PIC UNIT KERJA PENDUKUNG:
                                            @if ($picPendukung->count() > 0)
                                                {{ $picPendukung->map(fn($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    @endif

                                @elseif ($field === 'pic_utama')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="center pre-line">
                                            {{ $picUtama?->unitKerja?->kode_unit ?? '-' }}
                                        </td>
                                    @endif

                                @elseif ($field === 'pic_pendukung')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                            @if ($picPendukung->count() > 0)
                                                {{ $picPendukung->map(fn($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    @endif

                                @elseif ($field === 'tindak_lanjut')
                                    <td class="pre-line">
                                        {{ $tl?->tindak_lanjut ?? '-' }}
                                    </td>

                                @elseif ($field === 'deliverable')
                                    <td class="pre-line">
                                        {{ $tl?->deliverables ?? '-' }}
                                    </td>

                                @elseif ($field === 'dokumen')
                                    <td class="center">
                                        @if ($tl?->dokumen)
                                            <a href="{{ asset('storage/' . $tl->dokumen) }}" class="pre-line center">
                                                Dokumen Tindak Lanjut
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>

                                @elseif ($field === 'jatuh_tempo')
                                    <td class="center pre-line">
                                        @if ($tl?->jth_tempo)
                                            {{ \Carbon\Carbon::parse($tl->jth_tempo)->format('d-M-Y') }}
                                        @elseif ($record->jth_tempo)
                                            {{ \Carbon\Carbon::parse($record->jth_tempo)->format('d-M-Y') }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                @elseif ($field === 'komite')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="center pre-line">
                                            {{ $komitePic?->komite?->kode_komite ?? '-' }}
                                        </td>
                                    @endif

                                @elseif ($field === 'hasil_reviu')
                                    <td class="pre-line">
                                        {{ $reviewTl?->hasil_review ?? '-' }}
                                    </td>

                                @elseif ($field === 'status')
                                    <td class="center">
                                        <span class="status-badge {{ $statusTlClass }}">
                                            {{ $statusTlLabel }}
                                        </span>
                                    </td>

                                @else
                                    <td>-</td>
                                @endif
                            @endforeach
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
