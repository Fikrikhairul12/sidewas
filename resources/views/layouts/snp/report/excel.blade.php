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

                    $reviewTanggapan = $butir->reviews->where('tahap_review', 'tanggapan')->sortByDesc('id')->first();

                    $reviewTl = $butir->reviews->where('tahap_review', 'tindak_lanjut')->sortByDesc('id')->first();

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

                    $status = $reviewTl?->status ?? ($reviewTanggapan?->status ?? 'belum_ditanggapi');
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
                                    @if (!$loop->first)
                                        --------------------

                                    @endif

                                    {{ $tanggapan->butirPic?->unitKerja?->kode_unit ?? '-' }}
                                    -
                                    {{ $tanggapan->butirPic?->unitKerja?->nama_unit ?? '-' }}

                                    {{ $tanggapan->tanggapan ?? '-' }}

                                @empty
                                    -
                                @endforelse
                            </td>
                        @elseif ($field === 'tindak_lanjut_unit' || $field === 'tindak_lanjut')
                            <td>
                                @forelse ($tindakLanjuts as $tl)
                                    @if (!$loop->first)
                                        --------------------

                                    @endif

                                    {{ $tl->butirPic?->unitKerja?->kode_unit ?? '-' }}
                                    -
                                    {{ $tl->butirPic?->unitKerja?->nama_unit ?? '-' }}

                                    {{ $tl->tindak_lanjut ?? '-' }}

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
