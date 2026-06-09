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
            @foreach ($record->butirRagab as $butir)
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

                    $review =
                        $butir->reviewTindakLanjut ?? $butir->reviews?->where('tahap_review', 'tindak_lanjut')->first();

                    $statusTl =
                        $review?->status ??
                        ($butir->tindakLanjuts->count() > 0 ? 'belum_ditanggapi' : 'belum_ditindaklanjuti');

                    $statusTlLabel = match ($statusTl) {
                        'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
                        'belum_ditanggapi' => 'Belum Direviu',
                        'dalam_proses_reviu_dewan_pengawas' => 'Dalam Proses Reviu',
                        'selesai_tuntas' => 'Selesai Tuntas',
                        'tuntas' => 'Tuntas',
                        default => ucwords(str_replace('_', ' ', $statusTl)),
                    };
                @endphp

                @foreach ($tindakLanjuts as $tl)
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
                                <td>
                                    {{ $record->nomor_surat ?? '-' }}

                                    {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}

                                    {{ $record->perihal_surat ?? '-' }}
                                </td>
                            @elseif ($field === 'tgl_agenda')
                                <td>
                                    {{ $butir->tanggal_ragab ? \Carbon\Carbon::parse($butir->tanggal_ragab)->format('d/m/Y') : '-' }}

                                    {{ $butir->agenda_ragab ?? '-' }}
                                </td>
                            @elseif ($field === 'keputusan')
                                <td>
                                    {{ $butir->keputusan_ragab ?? '-' }}
                                </td>
                            @elseif ($field === 'direktorat')
                                @if (!in_array($direktoratLabel, $printedDirektorats, true))
                                    <td rowspan="{{ $direktoratRowspans[$direktoratLabel] ?? 1 }}">
                                        {{ $direktoratLabel }}
                                    </td>

                                    @php
                                        $printedDirektorats[] = $direktoratLabel;
                                    @endphp
                                @endif
                            @elseif ($field === 'unit_pic')
                                <td>
                                    {{ $tl?->unitKerja?->kode_unit ?? '-' }}
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

                                        if ($record->dokumen) {
                                            $dokumens->push(asset('storage/' . $record->dokumen));
                                        }

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
