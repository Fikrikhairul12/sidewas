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

        .entry-block {
            border-bottom: 1px solid #999;
            margin-bottom: 4px;
            padding-bottom: 4px;
            white-space: pre-line;
        }

        .entry-block:last-child {
            border-bottom: 0;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .entry-title {
            display: block;
            font-weight: bold;
            margin-bottom: 2px;
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
                @foreach ($record->butirSnp as $butir)
                    @php
                        $picUtama = $butir->butirPics->where('jenis_pic', 'utama')->first();
                        $picPendukung = $butir->butirPics->where('jenis_pic', 'pendukung');
                        $komitePic = $butir->butirPics->where('jenis_pic', 'komite')->first();

                        $kompilasiTanggapan = $butir->kompilasiTanggapan;
                        $kompilasiTindakLanjut = $butir->kompilasiTindakLanjut;

                        $tanggapans = $butir->tanggapan ?? collect();
                        if ($tanggapans instanceof \Illuminate\Database\Eloquent\Model) {
                            $tanggapans = collect([$tanggapans]);
                        }

                        $tanggapanUnitKerjaIds = collect($tanggapanUnitKerjaIds ?? [])
                            ->map(fn($id) => (int) $id)
                            ->toArray();

                        if (!empty($tanggapanUnitKerjaIds)) {
                            $tanggapans = $tanggapans->filter(function ($item) use ($tanggapanUnitKerjaIds) {
                                return in_array((int) $item->butirPic?->unit_kerja_id, $tanggapanUnitKerjaIds, true);
                            });
                        }

                        $tindakLanjuts = $butir->tindakLanjuts ?? collect();

                        $tindakLanjutUnitKerjaIds = collect($tindakLanjutUnitKerjaIds ?? [])
                            ->map(fn($id) => (int) $id)
                            ->toArray();

                        if (!empty($tindakLanjutUnitKerjaIds)) {
                            $tindakLanjuts = $tindakLanjuts->filter(function ($item) use ($tindakLanjutUnitKerjaIds) {
                                return in_array((int) $item->butirPic?->unit_kerja_id, $tindakLanjutUnitKerjaIds, true);
                            });
                        }

                        $reviewTanggapan = $butir->reviews
                            ->where('tahap_review', 'tanggapan')
                            ->sortByDesc('id')
                            ->first();

                        $reviewTl = $butir->reviews->where('tahap_review', 'tindak_lanjut')->sortByDesc('id')->first();

                        $reviewTerbaruButir = $butir->reviews->sortByDesc('id')->first();

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

                        $status =
                            $reviewTerbaruButir?->status ??
                            ($reviewTl?->status ?? ($reviewTanggapan?->status ?? 'belum_ditanggapi'));
                    @endphp

                    <tr>
                        @foreach ($selectedFields as $field)
                            @if ($field === 'surat')
                                <td>
                                    {{ $record->nomor_surat }}
                                    {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d-M-Y') : '-' }}
                                    {{ $record->perihal_surat }}
                                </td>
                            @elseif ($field === 'id_butir')
                                <td>{{ $butir->id_butir_snp }}</td>
                            @elseif ($field === 'isi_butir')
                                <td>{{ $butir->butir_snp }}</td>
                            @elseif ($field === 'pic_utama')
                                <td>{{ $picUtama?->unitKerja?->kode_unit ?? '-' }}</td>
                            @elseif ($field === 'pic_pendukung')
                                <td>
                                    @if ($picPendukung->count() > 0)
                                        {{ $picPendukung->map(fn($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') }}
                                    @else
                                        -
                                    @endif
                                </td>
                            @elseif ($field === 'pic_unit')
                                <td>
                                    PIC UNIT KERJA UTAMA:
                                    {{ $picUtama?->unitKerja?->kode_unit ?? '-' }}

                                    PIC UNIT KERJA PENDUKUNG:
                                    @if ($picPendukung->count() > 0)
                                        {{ $picPendukung->map(fn($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') }}
                                    @else
                                        -
                                    @endif
                                </td>
                            @elseif ($field === 'tanggapan_unit' || $field === 'tanggapan')
                                <td>
                                    @forelse ($tanggapans as $tanggapan)
                                        <div class="entry-block">
                                            <span class="entry-title">
                                                {{ $tanggapan->butirPic?->unitKerja?->kode_unit ?? '-' }}
                                                -
                                                {{ $tanggapan->butirPic?->unitKerja?->nama_unit ?? '-' }}
                                            </span>

                                            {{ $tanggapan->tanggapan ?? '-' }}
                                        </div>
                                    @empty
                                        -
                                    @endforelse
                                </td>
                            @elseif ($field === 'tindak_lanjut_unit' || $field === 'tindak_lanjut')
                                <td>
                                    @forelse ($tindakLanjuts as $tl)
                                        <div class="entry-block">
                                            <span class="entry-title">
                                                {{ $tl->butirPic?->unitKerja?->kode_unit ?? '-' }}
                                                -
                                                {{ $tl->butirPic?->unitKerja?->nama_unit ?? '-' }}
                                            </span>

                                            {{ $tl->tindak_lanjut ?? '-' }}
                                        </div>
                                    @empty
                                        -
                                    @endforelse
                                </td>
                            @elseif ($field === 'kompilasi_tanggapan')
                                <td>{{ $kompilasiTanggapan?->hasil_kompilasi ?? '-' }}</td>
                            @elseif ($field === 'kompilasi_tindak_lanjut')
                                <td>{{ $kompilasiTindakLanjut?->hasil_kompilasi ?? '-' }}</td>
                            @elseif ($field === 'deliverable')
                                <td>
                                    Kompilasi Tanggapan:
                                    {{ $kompilasiTanggapan?->deliverables ?? '-' }}

                                    Kompilasi Tindak Lanjut:
                                    {{ $kompilasiTindakLanjut?->deliverables ?? '-' }}
                                </td>
                            @elseif ($field === 'dokumen')
                                <td>
                                    @if ($kompilasiTanggapan?->dokumen)
                                        Dokumen Kompilasi Tanggapan:
                                        {{ asset('storage/' . $kompilasiTanggapan->dokumen) }}
                                    @endif

                                    @if ($kompilasiTindakLanjut?->dokumen)
                                        Dokumen Kompilasi Tindak Lanjut:
                                        {{ asset('storage/' . $kompilasiTindakLanjut->dokumen) }}
                                    @endif

                                    @if (!$kompilasiTanggapan?->dokumen && !$kompilasiTindakLanjut?->dokumen)
                                        -
                                    @endif
                                </td>
                            @elseif ($field === 'jatuh_tempo')
                                <td>
                                    Jatuh tempo awal:
                                    {{ $jatuhTempoAwal ? $jatuhTempoAwal->format('d-M-Y') : '-' }}

                                    @if ($kompilasiTanggapan?->ubah_tgl)
                                        Pengajuan ubah tanggal:
                                        {{ \Carbon\Carbon::parse($kompilasiTanggapan->ubah_tgl)->format('d-M-Y') }}

                                        Status:
                                        {{ ucwords(str_replace('_', ' ', $kompilasiTanggapan->status_pengajuan_tgl ?? 'pending')) }}
                                    @endif

                                    Jatuh tempo final:
                                    {{ $jatuhTempoFinal ? $jatuhTempoFinal->format('d-M-Y') : '-' }}
                                </td>
                            @elseif ($field === 'komite')
                                <td>{{ $komitePic?->komite?->kode_komite ?? '-' }}</td>
                            @elseif ($field === 'hasil_reviu')
                                <td>
                                    Reviu Tanggapan:
                                    {{ $reviewTanggapan?->hasil_review ?? '-' }}

                                    Reviu Tindak Lanjut:
                                    {{ $reviewTl?->hasil_review ?? '-' }}
                                </td>
                            @elseif ($field === 'status')
                                <td>{{ ucwords(str_replace('_', ' ', $status)) }}</td>
                            @else
                                <td>-</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    <div class="print-footer">
        Dokumen ini dicetak oleh {{ $printedBy ?? '-' }} pada {{ $printedAt ?? '-' }}
    </div>
</body>

</html>
