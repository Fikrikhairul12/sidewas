<table>
    <thead>
        <tr>
            @foreach ($selectedFields as $field)
                <th>{{ $fieldLabels[$field] ?? strtoupper($field) }}</th>
            @endforeach
        </tr>
    </thead>

    <tbody>
        @foreach ($records as $record)
            @foreach ($record->butirDjsn as $butir)
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
                        $tanggapan
                        && $tanggapan->status_pengajuan_tgl === 'disetujui'
                        && !empty($tanggapan->ubah_tgl)
                    ) {
                        $jatuhTempoFinal = \Carbon\Carbon::parse($tanggapan->ubah_tgl);
                    }

                    $statusTanggapan = $reviewTanggapan?->status ?? 'belum_ditanggapi';
                @endphp

                {{-- Baris tanggapan --}}
                <tr>
                    @foreach ($selectedFields as $field)
                        @if ($field === 'surat')
                            <td>
                                {{ $record->nomor_surat }}
                                {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d-M-Y') : '-' }}
                                {{ $record->perihal_surat }}
                            </td>

                        @elseif ($field === 'id_butir')
                            <td>{{ $butir->id_butir_djsn }}</td>

                        @elseif ($field === 'isi_butir')
                            <td>{{ $butir->butir_djsn }}</td>

                        @elseif ($field === 'pic_utama')
                            <td>{{ $picUtama?->unitKerja?->kode_unit ?? '-' }}</td>

                        @elseif ($field === 'pic_pendukung')
                            <td>
                                @if ($picPendukung->count() > 0)
                                    {{ $picPendukung->map(fn ($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') }}
                                @else
                                    -
                                @endif
                            </td>

                        @elseif ($field === 'tanggapan')
                            <td>{{ $tanggapan?->tanggapan ?? '-' }}</td>

                        @elseif ($field === 'tindak_lanjut')
                            <td>-</td>

                        @elseif ($field === 'deliverable')
                            <td>{{ $tanggapan?->deliverables ?? '-' }}</td>

                        @elseif ($field === 'dokumen')
                            <td>
                                @if ($tanggapan?->dokumen)
                                    {{ asset('storage/' . $tanggapan->dokumen) }}
                                @else
                                    -
                                @endif
                            </td>

                        @elseif ($field === 'jatuh_tempo')
                            <td>
                                Jatuh tempo awal:
                                {{ $jatuhTempoAwal ? $jatuhTempoAwal->format('d-M-Y') : '-' }}

                                @if ($tanggapan?->ubah_tgl)
                                    Pengajuan ubah tanggal:
                                    {{ \Carbon\Carbon::parse($tanggapan->ubah_tgl)->format('d-M-Y') }}

                                    Status:
                                    {{ ucwords(str_replace('_', ' ', $tanggapan->status_pengajuan_tgl ?? 'pending')) }}
                                @endif
                            </td>

                        @elseif ($field === 'komite')
                            <td>{{ $komitePic?->komite?->kode_komite ?? '-' }}</td>

                        @elseif ($field === 'hasil_reviu')
                            <td>{{ $reviewTanggapan?->hasil_review ?? '-' }}</td>

                        @elseif ($field === 'status')
                            <td>{{ ucwords(str_replace('_', ' ', $statusTanggapan)) }}</td>

                        @else
                            <td>-</td>
                        @endif
                    @endforeach
                </tr>

                {{-- Baris tindak lanjut --}}
                @foreach ($tindakLanjuts as $tl)
                    @php
                        $reviewTl = $tl->reviews
                            ->where('tahap_review', 'tindak_lanjut')
                            ->sortByDesc('id')
                            ->first();

                        $statusTl = $reviewTl?->status ?? '-';
                    @endphp

                    <tr>
                        @foreach ($selectedFields as $field)
                            @if ($field === 'surat')
                                <td></td>

                            @elseif ($field === 'id_butir')
                                <td></td>

                            @elseif ($field === 'isi_butir')
                                <td></td>

                            @elseif ($field === 'pic_utama')
                                <td></td>

                            @elseif ($field === 'pic_pendukung')
                                <td></td>

                            @elseif ($field === 'tanggapan')
                                <td></td>

                            @elseif ($field === 'tindak_lanjut')
                                <td>{{ $tl->tindak_lanjut }}</td>

                            @elseif ($field === 'deliverable')
                                <td>{{ $tl->deliverables ?? '-' }}</td>

                            @elseif ($field === 'dokumen')
                                <td>
                                    @if ($tl->dokumen)
                                        {{ asset('storage/' . $tl->dokumen) }}
                                    @else
                                        -
                                    @endif
                                </td>

                            @elseif ($field === 'jatuh_tempo')
                                <td>{{ $jatuhTempoFinal ? $jatuhTempoFinal->format('d-M-Y') : '-' }}</td>

                            @elseif ($field === 'komite')
                                <td></td>

                            @elseif ($field === 'hasil_reviu')
                                <td>{{ $reviewTl?->hasil_review ?? '-' }}</td>

                            @elseif ($field === 'status')
                                <td>{{ $statusTl !== '-' ? ucwords(str_replace('_', ' ', $statusTl)) : '-' }}</td>

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
