<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Report EKSTERNAL Custom</title>

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
        SIDEWAS EKSTERNAL
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($selectedFields as $field)
                    <th>{{ $fieldLabels[$field] ?? strtoupper(str_replace('_', ' ', $field)) }}</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ($records as $record)
                @php
                    $totalRowsRecord = 0;

                    foreach ($record->butirEksternal as $butirHitung) {
                        $totalRowsRecord += max(1, $butirHitung->tindakLanjuts->count());
                    }

                    $isFirstRecordRow = true;
                @endphp

                @foreach ($record->butirEksternal as $butir)
                    @php
                        $allowedDirektoratIds = ($butir->butirDirektorats ?? collect())
                            ->pluck('direktorat_id')
                            ->map(fn($id) => (int) $id)
                            ->toArray();

                        $getDirektoratLabel = function ($tl) use ($allowedDirektoratIds) {
                            $tlDirektoratId = $tl?->unitKerja?->direktorat_id;

                            return $tlDirektoratId && in_array((int) $tlDirektoratId, $allowedDirektoratIds, true)
                                ? $tl?->unitKerja?->direktorat?->nama_direktorat ?? '-'
                                : '-';
                        };

                        $tindakLanjuts = $butir->tindakLanjuts
                            ->sortBy(function ($tl) use ($getDirektoratLabel) {
                                $direktoratLabel = $getDirektoratLabel($tl);

                                $direktoratSortKey = $direktoratLabel === '-' ? 'ZZZZZZ' : $direktoratLabel;

                                return $direktoratSortKey .
                                    '|' .
                                    ($tl->unitKerja?->kode_unit ?? 'ZZZ') .
                                    '|' .
                                    str_pad((string) $tl->id, 10, '0', STR_PAD_LEFT);
                            })
                            ->values();

                        if ($tindakLanjuts->count() === 0) {
                            $tindakLanjuts = collect([null]);
                        }

                        $direktoratRowspans = $tindakLanjuts->map(fn($tl) => $getDirektoratLabel($tl))->countBy();

                        $printedDirektorats = [];

                        if ($tindakLanjuts->count() === 0) {
                            $tindakLanjuts = collect([null]);
                        }

                        $jumlahBarisButir = max(1, $tindakLanjuts->count());

                        $review =
                            $butir->reviewTindakLanjut ??
                            $butir->reviews?->where('tahap_review', 'tindak_lanjut')->first();

                        $statusTl =
                            $review?->status ??
                            ($butir->tindakLanjuts->count() > 0 ? 'belum_ditanggapi' : 'belum_ditindaklanjuti');

                        $statusTlClass = match ($statusTl) {
                            'belum_ditindaklanjuti', 'belum_ditanggapi' => 'status-belum',
                            'dalam_proses_reviu_dewan_pengawas' => 'status-reviu',
                            'selesai_tuntas', 'tuntas' => 'status-selesai',
                            default => 'status-belum',
                        };

                        $statusTlLabel = match ($statusTl) {
                            'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
                            'belum_ditanggapi' => 'Belum Direviu',
                            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu',
                            'selesai_tuntas' => 'Selesai Tuntas',
                            'tuntas' => 'Tuntas',
                            default => ucwords(str_replace('_', ' ', $statusTl)),
                        };
                    @endphp

                    @foreach ($tindakLanjuts as $i => $tl)
                        @php
                            $allowedDirektoratIds = ($butir->butirDirektorats ?? collect())
                                ->pluck('direktorat_id')
                                ->map(fn($id) => (int) $id)
                                ->toArray();

                            $tlDirektoratId = $tl?->unitKerja?->direktorat_id;

                            $direktoratLabel = $getDirektoratLabel($tl);
                        @endphp

                        <tr>
                            @foreach ($selectedFields as $field)
                                @if ($field === 'surat')
                                    @if ($isFirstRecordRow)
                                        <td rowspan="{{ $totalRowsRecord }}" class="pre-line">
                                            {{ $record->nomor_surat ?? '-' }}
                                            {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d-M-Y') : '-' }}

                                            Instansi:
                                            {{ $record->nama_instansi_pengundang ?? '-' }}

                                            {{ $record->perihal_surat ?? '-' }}
                                            <br>
                                            @if ($record->dokumen)
                                                <a href="{{ asset('storage/' . $record->dokumen) }}">Dokumen Surat</a>
                                            @endif

                                            @if ($record->dokumen_memo)
                                                @if ($record->dokumen)
                                                    
                                                @endif
                                                <a href="{{ asset('storage/' . $record->dokumen_memo) }}">Dokumen Memo</a>
                                            @endif
                                        </td>

                                        @php
                                            $isFirstRecordRow = false;
                                        @endphp
                                    @endif
                                @elseif ($field === 'tgl_agenda')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                            {{ $butir->tanggal_eksternal ? \Carbon\Carbon::parse($butir->tanggal_eksternal)->format('d-M-Y') : '-' }}

                                            {{ $butir->agenda_eksternal ?? '-' }}
                                        </td>
                                    @endif
                                @elseif ($field === 'keputusan')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                            {{ $butir->keputusan_eksternal ?? '-' }}
                                        </td>
                                    @endif
                                @elseif ($field === 'direktorat')
                                    @if (!in_array($direktoratLabel, $printedDirektorats, true))
                                        <td rowspan="{{ $direktoratRowspans[$direktoratLabel] ?? 1 }}"
                                            class="center pre-line">
                                            {{ $direktoratLabel }}
                                        </td>

                                        @php
                                            $printedDirektorats[] = $direktoratLabel;
                                        @endphp
                                    @endif
                                @elseif ($field === 'unit_pic')
                                    <td class="center pre-line">
                                        {{ $tl?->unitKerja?->kode_unit ?? '-' }}
                                    </td>
                                @elseif ($field === 'tindak_lanjut')
                                    <td class="pre-line">
                                        {{ $tl?->tindak_lanjut ?? '-' }}
                                    </td>
                                @elseif ($field === 'deliverable')
                                    <td class="pre-line">
                                        {{ $tl?->deliverables ?? '-' }}
                                    </td>
                                @elseif ($field === 'dokumen')
                                    <td class="center pre-line">


                                        @if ($tl?->dokumen)
                                            @if ($record->dokumen || $record->dokumen_memo)
                                                <br>
                                            @endif
                                            <a href="{{ asset('storage/' . $tl->dokumen) }}">Dokumen TL</a>
                                        @endif

                                        @if (!$record->dokumen && !$record->dokumen_memo && !$tl?->dokumen)
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
                                @elseif ($field === 'hasil_reviu')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="pre-line">
                                            {{ $review?->hasil_review ?? '-' }}
                                        </td>
                                    @endif
                                @elseif ($field === 'status')
                                    @if ($i === 0)
                                        <td rowspan="{{ $jumlahBarisButir }}" class="center">
                                            <span class="status-badge {{ $statusTlClass }}">
                                                {{ $statusTlLabel }}
                                            </span>
                                        </td>
                                    @endif
                                @else
                                    <td>-</td>
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
