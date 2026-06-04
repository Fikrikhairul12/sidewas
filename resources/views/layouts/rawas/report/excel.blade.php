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
            @foreach ($record->butirRawas as $butir)
                @php
                    $picUtama = $butir->butirPics->where('jenis_pic', 'utama')->first();
                    $picPendukung = $butir->butirPics->where('jenis_pic', 'pendukung');
                    $komitePic = $butir->butirPics->where('jenis_pic', 'komite')->first();

                    $tindakLanjuts = $butir->tindakLanjuts;

                    if ($tindakLanjuts->count() === 0) {
                        $tindakLanjuts = collect([null]);
                    }
                @endphp

                @foreach ($tindakLanjuts as $tl)
                    @php
                        $reviewTl = $tl
                            ? $tl->reviews
                                ->where('tahap_review', 'tindak_lanjut')
                                ->sortByDesc('id')
                                ->first()
                            : null;

                        $statusTl = $reviewTl?->status ?? ($tl ? 'belum_ditanggapi' : 'belum_ditindaklanjuti');

                        $statusTlLabel = match ($statusTl) {
                            'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
                            'belum_ditanggapi' => 'Belum Direviu',
                            'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu Dewan Pengawas',
                            'selesai_tuntas' => 'Selesai Tuntas',
                            'selesai' => 'Selesai',
                            default => ucwords(str_replace('_', ' ', $statusTl)),
                        };
                    @endphp

                    <tr>
                        @foreach ($selectedFields as $field)
                            @if ($field === 'surat')
                                <td>
                                    {{ $record->nomor_surat }}

                                    {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}

                                    {{ $record->perihal_surat }}
                                </td>

                            @elseif ($field === 'id_butir')
                                <td>
                                    {{ $butir->id_butir_rawas }}
                                </td>

                            @elseif ($field === 'isi_butir')
                                <td>
                                    {{ $butir->butir_rawas }}
                                </td>

                            @elseif ($field === 'pic_unit')
                                <td>
                                    PIC UNIT KERJA UTAMA:
                                    {{ $picUtama?->unitKerja?->kode_unit ?? '-' }}

                                    PIC UNIT KERJA PENDUKUNG:
                                    @if ($picPendukung->count() > 0)
                                        {{ $picPendukung->map(fn ($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') }}
                                    @else
                                        -
                                    @endif
                                </td>

                            @elseif ($field === 'pic_utama')
                                <td>
                                    {{ $picUtama?->unitKerja?->kode_unit ?? '-' }}
                                </td>

                            @elseif ($field === 'pic_pendukung')
                                <td>
                                    @if ($picPendukung->count() > 0)
                                        {{ $picPendukung->map(fn ($pic) => $pic->unitKerja?->kode_unit)->filter()->implode(', ') }}
                                    @else
                                        -
                                    @endif
                                </td>

                            @elseif ($field === 'tindak_lanjut')
                                <td>
                                    {{ $tl?->tindak_lanjut ?? '-' }}
                                </td>

                            @elseif ($field === 'deliverable')
                                <td>
                                    {{ $tl?->deliverables ?? '-' }}
                                </td>

                            @elseif ($field === 'dokumen')
                                <td>
                                    @if ($tl?->dokumen)
                                        {{ asset('storage/' . $tl->dokumen) }}
                                    @else
                                        -
                                    @endif
                                </td>

                            @elseif ($field === 'jatuh_tempo')
                                <td>
                                    @if ($tl?->jth_tempo)
                                        {{ \Carbon\Carbon::parse($tl->jth_tempo)->format('d/m/Y') }}
                                    @elseif ($record->jth_tempo)
                                        {{ \Carbon\Carbon::parse($record->jth_tempo)->format('d/m/Y') }}
                                    @else
                                        -
                                    @endif
                                </td>

                            @elseif ($field === 'komite')
                                <td>
                                    {{ $komitePic?->komite?->kode_komite ?? '-' }}
                                </td>

                            @elseif ($field === 'hasil_reviu')
                                <td>
                                    {{ $reviewTl?->hasil_review ?? '-' }}
                                </td>

                            @elseif ($field === 'status')
                                <td>
                                    {{ $statusTlLabel }}
                                </td>

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
