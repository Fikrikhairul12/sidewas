<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Report SNP Dewas</title>

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

        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        td[rowspan] {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .record-group {
            page-break-inside: avoid;
            break-inside: avoid;
        }

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
        SIDEWAS SNP DEWAS
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">NOMOR, TANGGAL & PERIHAL SURAT</th>
                <th style="width: 6%;">ID BUTIR SNP</th>
                <th style="width: 12%;">ISI BUTIR SNP</th>
                <th style="width: 8%;">PIC UNIT KERJA</th>
                <th style="width: 12%;">TANGGAPAN & TINDAK LANJUT DIREKSI</th>
                <th style="width: 7%;">DELIVERABLE</th>
                <th style="width: 8%;">DOKUMEN PENDUKUNG</th>
                <th style="width: 9%;">TGL. JATUH TEMPO</th>
                <th style="width: 8%;">PIC KOMITE DEWAN PENGAWAS</th>
                <th style="width: 10%;">HASIL REVIU DEWAN PENGAWAS</th>
                <th style="width: 8%;">STATUS TINDAK LANJUT</th>
            </tr>
        </thead>

        @foreach ($records as $record)
            <tbody class="record-group">
                @php
                    $totalRowsRecord = 0;

                    foreach ($record->butirSnp as $butirHitung) {
                        $jumlahKompilasiTl = $butirHitung->kompilasiTindakLanjuts?->count() ?? 0;

                        // 1 baris tanggapan + minimal 1 baris TL
                        $totalRowsRecord += 1 + max(1, $jumlahKompilasiTl);
                    }

                    $isFirstRecordRow = true;
                @endphp

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

                        if ($kompilasiTindakLanjuts->count() === 0) {
                            $kompilasiTindakLanjuts = collect([null]);
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

                        $jumlahBarisButir = 1 + $kompilasiTindakLanjuts->count();
                    @endphp

                    {{-- Baris Tanggapan --}}
                    <tr>
                        @if ($isFirstRecordRow)
                            <td rowspan="{{ $totalRowsRecord }}" class="top pre-line">
                                {{ $record->nomor_surat }}
                                {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d-M-Y') : '-' }}

                                {{ $record->perihal_surat }}
                            </td>

                            @php
                                $isFirstRecordRow = false;
                            @endphp
                        @endif

                        <td class="center pre-line" rowspan="{{ $jumlahBarisButir }}">
                            {{ $butir->id_butir_snp }}
                        </td>

                        <td class="pre-line top" rowspan="{{ $jumlahBarisButir }}">
                            {{ $butir->butir_snp }}
                        </td>

                        <td class="pre-line top" rowspan="{{ $jumlahBarisButir }}">
                            PIC UNIT KERJA UTAMA:
                            {{ $picUtama?->unitKerja?->kode_unit ?? '-' }}

                            PIC UNIT KERJA PENDUKUNG:
                            @if ($picPendukung->count() > 0)
                                {{ $picPendukung->map(fn($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') }}
                            @else
                                -
                            @endif
                        </td>

                        <td class="pre-line top">
                            {{ $kompilasiTanggapan?->hasil_kompilasi ?? '-' }}
                        </td>

                        <td class="pre-line top">
                            {{ $kompilasiTanggapan?->deliverables ?? '-' }}
                        </td>

                        <td class="center">
                            @if ($kompilasiTanggapan?->dokumen)
                                <a href="{{ asset('storage/' . $kompilasiTanggapan->dokumen) }}" class="pre-line">
                                    Dokumen Kompilasi Tanggapan
                                </a>
                            @else
                                -
                            @endif
                        </td>

                        <td class="pre-line top">
                            {{ $jatuhTempoAwal ? $jatuhTempoAwal->format('d-M-Y') : '-' }}

                            @if ($kompilasiTanggapan?->ubah_tgl)
                                Pengajuan ubah tanggal:
                                {{ \Carbon\Carbon::parse($kompilasiTanggapan->ubah_tgl)->format('d-M-Y') }}

                                Status pengajuan:
                                {{ ucwords(str_replace('_', ' ', $kompilasiTanggapan->status_pengajuan_tgl ?? 'pending')) }}
                            @endif
                        </td>

                        <td class="center pre-line" rowspan="{{ $jumlahBarisButir }}">
                            {{ $komitePic?->komite?->kode_komite ?? '-' }}
                        </td>

                        <td class="pre-line top">
                            {{ $reviewTanggapan?->hasil_review ?? '-' }}
                        </td>

                        <td class="center" rowspan="{{ $jumlahBarisButir }}">
                            <span class="status-badge {{ $statusTerbaruButirClass }}">
                                {{ ucwords(str_replace('_', ' ', $statusTerbaruButir)) }}
                            </span>
                        </td>
                    </tr>

                    {{-- Baris Kompilasi Tindak Lanjut per Putaran --}}
                    @foreach ($kompilasiTindakLanjuts as $kompilasiTl)
                        @php
                            $reviewTl = $kompilasiTl
                                ? $butir->reviews
                                    ->where('tahap_review', 'tindak_lanjut')
                                    ->where('putaran_tl', $kompilasiTl->putaran_tl)
                                    ->sortByDesc('id')
                                    ->first()
                                : null;
                        @endphp

                        <tr>
                            <td class="pre-line top">
                                @if ($kompilasiTl)
                                    {{-- Putaran {{ $kompilasiTl->putaran_tl }}: --}}
                                    {{ $kompilasiTl->hasil_kompilasi ?? '-' }}
                                @else
                                    -
                                @endif
                            </td>

                            <td class="pre-line top">
                                @if ($kompilasiTl)
                                    {{-- Putaran {{ $kompilasiTl->putaran_tl }}: --}}
                                    {{ $kompilasiTl->deliverables ?? '-' }}
                                @else
                                    -
                                @endif
                            </td>

                            <td class="center pre-line">
                                @if ($kompilasiTl?->dokumen)
                                    Putaran {{ $kompilasiTl->putaran_tl }}:
                                    <a href="{{ asset('storage/' . $kompilasiTl->dokumen) }}">
                                        Dokumen Kompilasi TL
                                    </a>
                                @else
                                    -
                                @endif
                            </td>

                            <td class="center pre-line">
                                {{ $jatuhTempoFinal ? $jatuhTempoFinal->format('d-M-Y') : '-' }}
                            </td>

                            <td class="pre-line top">
                                {{ $reviewTl?->hasil_review ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        @endforeach
    </table>
    <div class="print-footer">
        Dokumen ini dicetak oleh {{ $printedBy ?? '-' }} pada {{ $printedAt ?? '-' }}
    </div>
</body>

</html>
