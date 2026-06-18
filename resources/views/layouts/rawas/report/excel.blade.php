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
                    $tindakLanjuts = $butir->tindakLanjuts
                        ->sortBy([
                            fn($tl) => $tl->butirPic?->unitKerja?->kode_unit ?? 'ZZZ',
                            fn($tl) => $tl->id,
                        ])
                        ->values();

                    if ($tindakLanjuts->count() === 0) {
                        $tindakLanjuts = collect([null]);
                    }

                    $review = $butir->reviewTindakLanjut;
                    $statusTl =
                        $review?->status ??
                        ($butir->tindakLanjuts->count() > 0
                            ? $butir->statusTindakLanjut()
                            : 'belum_ditindaklanjuti');

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

                @foreach ($tindakLanjuts as $tl)
                    <tr>
                        @foreach ($selectedFields as $field)
                            @if ($field === 'surat')
                                <td>
                                    {{ $record->nomor_surat ?? '-' }}

                                    {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}

                                    {{ $record->perihal_surat ?? '-' }}
                                </td>
                            @elseif ($field === 'tgl_agenda')
                                <td>
                                    {{ $butir->tanggal_rawas ? \Carbon\Carbon::parse($butir->tanggal_rawas)->format('d/m/Y') : '-' }}

                                    {{ $butir->agenda_rawas ?? '-' }}
                                </td>
                            @elseif ($field === 'keputusan')
                                <td>
                                    {{ $butir->keputusan_rawas ?? '-' }}
                                </td>
                            @elseif ($field === 'direktorat')
                                <td>
                                    Dewan Pengawas
                                </td>
                            @elseif ($field === 'unit_pic')
                                <td>
                                    {{ $tl?->butirPic?->unitKerja?->kode_unit ?? '-' }}
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
                                    @php
                                        $dokumens = collect();

                                        if ($record->dokumen_memo) {
                                            $dokumens->push(asset('storage/' . $record->dokumen_memo));
                                        }

                                        if ($tl?->dokumen) {
                                            $dokumens->push(asset('storage/' . $tl->dokumen));
                                        }
                                    @endphp

                                    {{ $dokumens->count() > 0 ? $dokumens->implode("\n") : '-' }}
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
                            @elseif ($field === 'hasil_reviu')
                                <td>
                                    {{ $review?->hasil_review ?? '-' }}
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
