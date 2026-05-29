<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Report SNP Dewas Custom</title>

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

        .justify {
            text-align: justify;
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
        SIDEWAS SNP DEWAS
    </div>
    <table>
        <thead>
            <tr>
                @foreach ($selectedFields as $field)
                    <th>
                        {{ $fieldLabels[$field] ?? strtoupper($field) }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ($records as $record)
                @php
                    $totalRowsRecord = 0;

                    foreach ($record->butirSnp as $butirHitung) {
                        $totalRowsRecord += max(1, 1 + $butirHitung->tindakLanjuts->count());
                    }

                    $isFirstRecordRow = true;
                @endphp

                @foreach ($record->butirSnp as $butir)
                    @php
                        $picUtama = $butir->butirPics->where('jenis_pic', 'utama')->first();
                        $picPendukung = $butir->butirPics->where('jenis_pic', 'pendukung');
                        $komitePic = $butir->butirPics->where('jenis_pic', 'komite')->first();

                        $tanggapan = $butir->tanggapan;
                        $reviewTanggapan = $tanggapan?->review;

                        $tindakLanjuts = $butir->tindakLanjuts;

                        $jatuhTempoAwal = $record->tanggal_surat
                            ? \Carbon\Carbon::parse($record->tanggal_surat)->addDays(30)
                            : null;

                        $jatuhTempoFinal = $jatuhTempoAwal;

                        if (
                            $tanggapan &&
                            $tanggapan->status_pengajuan_tgl === 'disetujui' &&
                            !empty($tanggapan->ubah_tgl)
                        ) {
                            $jatuhTempoFinal = \Carbon\Carbon::parse($tanggapan->ubah_tgl);
                        }

                        $jumlahBarisButir = max(1, 1 + $tindakLanjuts->count());

                        $statusTanggapan = $reviewTanggapan?->status ?? 'belum_ditanggapi';

                        $statusTanggapanClass = match ($statusTanggapan) {
                            'belum_ditanggapi' => 'status-belum',
                            'dalam_proses_reviu_dewan_pengawas' => 'status-reviu',
                            'dalam_proses_tindak_lanjut_direksi' => 'status-tl',
                            'selesai_tuntas' => 'status-selesai',
                            default => 'status-belum',
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
                                <td rowspan="{{ $jumlahBarisButir }}" class="center pre-line">
                                    {{ $butir->id_butir_snp }}
                                </td>
                            @elseif ($field === 'isi_butir')
                                <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                    {{ $butir->butir_snp }}
                                </td>
                            @elseif ($field === 'pic_unit')
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
                            @elseif ($field === 'tanggapan_tl')
                                <td class="pre-line">
                                    @if ($tanggapan)
                                        [ISI TANGGAPAN SNP]
                                        {{ $tanggapan->tanggapan }}
                                    @else
                                        -
                                    @endif
                                </td>
                            @elseif ($field === 'deliverable')
                                <td class="pre-line">
                                    {{ $tanggapan?->deliverables ?? '-' }}
                                </td>
                            @elseif ($field === 'dokumen')
                                <td class="center">
                                    @if ($tanggapan?->dokumen)
                                        <a href="{{ asset('storage/' . $tanggapan->dokumen) }}" class="pre-line">
                                            Dokumen Tanggapan
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            @elseif ($field === 'jatuh_tempo')
                                <td class="pre-line">
                                    Jatuh tempo awal:
                                    {{ $jatuhTempoAwal ? $jatuhTempoAwal->format('d-M-Y') : '-' }}

                                    @if ($tanggapan?->ubah_tgl)
                                        Pengajuan ubah tanggal:
                                        {{ \Carbon\Carbon::parse($tanggapan->ubah_tgl)->format('d-M-Y') }}

                                        Status pengajuan:
                                        {{ ucwords(str_replace('_', ' ', $tanggapan->status_pengajuan_tgl ?? 'pending')) }}
                                    @endif
                                </td>
                            @elseif ($field === 'komite')
                                <td rowspan="{{ $jumlahBarisButir }}" class="center pre-line">
                                    {{ $komitePic?->komite?->kode_komite ?? '-' }}
                                </td>
                            @elseif ($field === 'hasil_reviu')
                                <td class="pre-line">
                                    {{ $reviewTanggapan?->hasil_review ?? '-' }}
                                </td>
                            @elseif ($field === 'status')
                                <td class="center">
                                    <span class="status-badge {{ $statusTanggapanClass }}">
                                        {{ ucwords(str_replace('_', ' ', $statusTanggapan)) }}
                                    </span>
                                </td>
                            @endif
                        @endforeach
                    </tr>

                    @foreach ($tindakLanjuts as $tl)
                        @php
                            $reviewTl = $tl->reviews->where('tahap_review', 'tindak_lanjut')->sortByDesc('id')->first();

                            $statusTl = $reviewTl?->status ?? '-';

                            $statusTlClass = match ($statusTl) {
                                'belum_ditanggapi' => 'status-belum',
                                'dalam_proses_reviu_dewan_pengawas' => 'status-reviu',
                                'dalam_proses_tindak_lanjut_direksi' => 'status-tl',
                                'selesai_tuntas' => 'status-selesai',
                                default => 'status-belum',
                            };
                        @endphp

                        <tr>
                            @foreach ($selectedFields as $field)
                                @if (in_array($field, ['surat', 'id_butir', 'isi_butir', 'pic_unit', 'komite']))
                                    @continue
                                @elseif ($field === 'tanggapan_tl')
                                    <td class="pre-line">
                                        [ISI TINDAK LANJUT SNP]
                                        {{ $tl->tindak_lanjut }}
                                    </td>
                                @elseif ($field === 'deliverable')
                                    <td class="pre-line">
                                        {{ $tl->deliverables ?? '-' }}
                                    </td>
                                @elseif ($field === 'dokumen')
                                    <td class="center">
                                        @if ($tl->dokumen)
                                            <a href="{{ asset('storage/' . $tl->dokumen) }}">
                                                Dokumen Tindak Lanjut
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                @elseif ($field === 'jatuh_tempo')
                                    <td class="center">
                                        {{ $jatuhTempoFinal ? $jatuhTempoFinal->format('d-M-Y') : '-' }}
                                    </td>
                                @elseif ($field === 'hasil_reviu')
                                    <td class="pre-line">
                                        {{ $reviewTl?->hasil_review ?? '-' }}
                                    </td>
                                @elseif ($field === 'status')
                                    <td class="center">
                                        @if ($statusTl !== '-')
                                            <span class="status-badge {{ $statusTlClass }}">
                                                {{ ucwords(str_replace('_', ' ', $statusTl)) }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                @endif
                            @endforeach
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
