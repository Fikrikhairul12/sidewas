<x-report-butir-pdf :records="$records" :module="$workflowReport['module']" :prepared="true">
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report {{ strtoupper($workflowReport['module']) }} - Seluruh Kompilasi</title>
    <style>
        @page { size:legal landscape; margin:8mm 8mm 14mm; }
        body { width:1283px; margin:0; font-family:Arial, Helvetica, sans-serif; font-size:8px; color:#000; }
        table { width:100%; border-collapse:collapse; table-layout:fixed; }
        th, td { border:1px solid #000; padding:4px; vertical-align:top; word-wrap:break-word; overflow-wrap:break-word; font-size:8px; }
        th { background:#f2f2f2; text-align:center; font-weight:bold; }
        thead { display:table-header-group; }
        tbody td { border-top:0; border-bottom:0; }
        .butir-start td { border-top:1px solid #000; }
        .butir-end td { border-bottom:1px solid #000; }
        .entry-block { margin-bottom:5px; padding-bottom:3px; border-bottom:1px solid #bbb; }
        .entry-title, .round-title { font-weight:bold; margin-bottom:3px; }
        .round-title { color:#174d75; }
        .pre-line { white-space:pre-line; }
        .muted { color:#555; }
        a { color:#174d75; text-decoration:underline; }
        .report-butir-label { visibility:hidden; white-space:normal; }
        [data-snp-show-first="1"] { visibility:visible; }
        .report-continuation { visibility:hidden; color:#666; }
        .print-footer { position:fixed; left:0; bottom:-7mm; font-size:7px; color:#444; }
        .watermark { position:fixed; top:38%; width:100%; text-align:center; z-index:-1000; font-size:55px; font-weight:bold; color:rgba(0,0,0,.06); transform:rotate(-30deg); }
    </style>
</head>
<body>
    <div class="watermark">SIDEWAS {{ strtoupper($workflowReport['module']) }}</div>
    @php
        $fields = $workflowReport['fields'];
        $weights = ['surat' => 10, 'id_butir' => 8, 'isi_butir' => 25, 'pic_unit' => 8, 'tanggapan_tl' => 30, 'tanggapan_unit' => 24, 'tindak_lanjut_unit' => 24, 'kompilasi_tanggapan' => 22, 'kompilasi_tindak_lanjut' => 22, 'hasil_reviu' => 21, 'deliverable' => 7, 'dokumen' => 7, 'jatuh_tempo' => 9, 'komite' => 6, 'status' => 10];
        $totalWeight = collect($fields)->sum(fn ($field) => $weights[$field] ?? 8);
        $module = $workflowReport['module'];
    @endphp
    <table>
        <thead><tr>
            @foreach ($fields as $field)
                <th style="width:{{ 100 * ($weights[$field] ?? 8) / max(1, $totalWeight) }}%;">{{ $workflowReport['labels'][$field] ?? strtoupper($field) }}</th>
            @endforeach
        </tr></thead>
        <tbody>
        @foreach ($workflowReport['butirs'] as $item)
            @php
                $record = $item['record'];
                $butir = $item['butir'];
                $rounds = $workflowReport['round_field'] ? $item['rounds'] : $item['rounds']->take(1);
                if ($rounds->isEmpty()) { $rounds = collect([null]); }
            @endphp
            @foreach ($rounds as $round)
                @php
                    $snapshot = $round['snapshot'] ?? [];
                    $labelKey = $item['id'].'-comp-'.($round['id'] ?? 'none');
                    $noteField = $workflowReport['round_field'] ?? $fields[0];
                @endphp
                <tr data-report-butir="{{ $item['id'] }}" class="{{ $loop->first ? 'butir-start' : '' }} {{ $loop->last ? 'butir-end' : '' }}">
                @foreach ($fields as $field)
                    <td>
                        @if ($field === $noteField)
                            @if ($round)
                                <div class="round-title">{{ $round['label'] }}</div>
                                <div>Salinan kompilasi #{{ $round['id'] }}: {{ $round['captured_at']?->format('d/m/Y H:i') ?? '-' }} WIB</div>
                                @if ($round['unverified_reviews'])<div class="muted">Hubungan reviu perlu rekonsiliasi; reviu tersebut tidak disertakan.</div>@endif
                            @else
                                <div class="muted">Belum tersedia bahan proses resmi untuk dicetak. Isian yang belum dikompilasi tidak disertakan.</div>
                            @endif
                            @if ($loop->parent->first && $item['unverified'])
                                <div class="muted">{{ $item['unverified'] }} kompilasi belum memiliki salinan bahan terverifikasi dan memerlukan rekonsiliasi. Arsip lama tetap tersedia di aplikasi.</div>
                            @endif
                        @endif
                        @if ($field === 'surat')
                            <div>{{ $round ? data_get($snapshot, 'surat.nomor_surat') : $record->nomor_surat }}</div>
                            <div>{{ ($date = $round ? data_get($snapshot, 'surat.tanggal_surat') : $record->tanggal_surat) ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-' }}</div>
                            <div>{{ $round ? data_get($snapshot, 'surat.perihal') : $record->perihal_surat }}</div>
                            @if (!$butir)<div>Belum ada butir.</div>@endif
                        @elseif ($field === 'id_butir' || ($field === 'isi_butir' && !in_array('id_butir', $fields, true)))
                            <div class="report-butir-label" data-snp-butir-label="{{ $labelKey }}" data-snp-show-first="{{ $field === 'id_butir' ? 1 : 0 }}">{{ $item['id'] }}@if($round)<div>{{ $round['label'] }}</div>@endif<div class="report-continuation" data-snp-continuation>Lanjutan</div></div>
                        @endif
                        @if ($field === 'isi_butir')
                            <x-rich-content data-report-rich :content="$round ? $snapshot['butir']['isi'] : $butir?->getAttribute('butir_'.$module)" />
                        @elseif ($field === 'pic_unit' && $round)
                            @foreach ($round['assignments']->whereIn('jenis_pic', ['utama', 'pendukung']) as $assignment)
                                <div>{{ ucfirst($assignment['jenis_pic']) }}: {{ data_get($assignment, 'unit.kode') ? data_get($assignment, 'unit.kode').' - ' : '' }}{{ data_get($assignment, 'unit.nama') ?? 'Unit #'.data_get($assignment, 'unit.id') }}</div>
                            @endforeach
                            <div class="muted">Penugasan saat kompilasi.</div>
                        @elseif ($field === 'komite' && $round)
                            @foreach ($round['assignments']->where('jenis_pic', 'komite') as $assignment)
                                <div>{{ data_get($assignment, 'komite.nama') ?? 'Komite #'.data_get($assignment, 'komite.id') }}</div>
                            @endforeach
                        @elseif ($round && in_array($field, ['tanggapan_tl', 'tanggapan_unit', 'tindak_lanjut_unit', 'kompilasi_tanggapan', 'kompilasi_tindak_lanjut', 'hasil_reviu'], true))
                            @php
                                $entries = match ($field) {
                                    'tanggapan_tl' => $round['sources']->concat($round['compilations']),
                                    'tanggapan_unit' => $round['responses'], 'tindak_lanjut_unit' => $round['followups'],
                                    'kompilasi_tanggapan' => $round['compilations']->where('stage', 'tanggapan'),
                                    'kompilasi_tindak_lanjut' => $round['compilations']->where('stage', 'tindak_lanjut'),
                                    default => $round['reviews'],
                                };
                                $showMissing = $field === 'tanggapan_tl' || $field === 'tanggapan_unit' && $round['stage_type'] === 'tanggapan'
                                    || $field === 'tindak_lanjut_unit' && $round['stage_type'] === 'tindak_lanjut';
                            @endphp
                            @foreach ($entries as $entry)
                                @include('layouts.partials.workflow-report-entry', ['officialSnapshot' => true])
                            @endforeach
                            @if ($showMissing)
                                @foreach ($round['missing'] as $unit)
                                    <div class="entry-block"><strong>{{ $unit['code'] ? $unit['code'].' - ' : '' }}{{ $unit['name'] }}</strong><div>Belum mengisi saat kompilasi.</div></div>
                                @endforeach
                                @if ($entries->isEmpty() && $round['missing']->isEmpty())<div>Tidak ada bahan unit dalam cakupan kompilasi ini.</div>@endif
                            @elseif ($entries->isEmpty())
                                <div>{{ $field === 'hasil_reviu' ? 'Belum ada reviu yang terhubung.' : '-' }}</div>
                            @endif
                        @elseif ($round && in_array($field, ['deliverable', 'dokumen'], true))
                            @foreach ($round['all_entries'] as $entry)
                                @if ($field === 'deliverable' ? filled($entry['deliverables']) : $entry['documents']->isNotEmpty())
                                    <div class="entry-title">{{ $entry['title'] }}{{ $entry['unit'] ? ' - '.$entry['unit'] : '' }}</div>
                                    @if ($field === 'deliverable')<div class="pre-line">{{ $entry['deliverables'] }}</div>
                                    @else
                                        @foreach ($entry['documents'] as $document)<div><a href="{{ $document['url'] }}">{{ $document['label'] }}: {{ basename(parse_url($document['url'], PHP_URL_PATH)) }}</a></div>@endforeach
                                    @endif
                                @endif
                            @endforeach
                        @elseif ($round && $field === 'jatuh_tempo')
                            <div>Batas saat kompilasi: {{ data_get($snapshot, 'tahap.jatuh_tempo') ? \Carbon\Carbon::parse($snapshot['tahap']['jatuh_tempo'])->format('d/m/Y').' 23.59 WIB' : 'Belum terverifikasi' }}</div>
                            @if (data_get($snapshot, 'kompilasi.ubah_tgl'))
                                <div>Pengajuan: {{ \Carbon\Carbon::parse($snapshot['kompilasi']['ubah_tgl'])->format('d/m/Y') }} ({{ ucfirst($round['compilations'][0]['date_status'] ?? 'pending') }})</div>
                                <div class="pre-line">{{ data_get($snapshot, 'kompilasi.alasan_pengajuan_tgl') }}</div>
                            @endif
                            @foreach ($round['date_history'] as $event)
                                <div class="entry-block">{{ ucfirst(str_replace('_', ' ', $event->jenis)) }}: {{ $event->tanggal_baru?->format('d/m/Y') ?? '-' }}
                                    @if ($event->jenis === 'pengajuan_ditolak')<div>Tanggal tetap: {{ $event->tanggal_lama?->format('d/m/Y') ?? '-' }}</div>@endif
                                    <div class="pre-line">{{ $event->alasan }}</div>
                                    <div>{{ $event->bukti['pengguna_nama'] ?? 'Pengguna #'.$event->pengguna_id }} · {{ $event->diputuskan_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</div>
                                </div>
                            @endforeach
                        @elseif ($field === 'status')
                            <div><strong>{{ \App\Enums\SuratStatus::labelFor($record->status) }}</strong></div>
                            @if ($record->closed_at)<div>Close: {{ $record->closed_at->format('d/m/Y H:i') }} WIB</div>@endif
                        @endif
                    </td>
                @endforeach
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
    <div class="print-footer">Dicetak oleh: {{ $printedBy ?? '-' }} | {{ $printedAt ?? now()->format('d/m/Y H:i') }} WIB</div>
</body>
</html>
</x-report-butir-pdf>
