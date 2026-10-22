<x-report-butir-pdf :records="$records" :module="$workflowReport['module']" :prepared="true">
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report {{ $workflowReport['module'] === 'snp' ? 'SNP Dewas' : 'DJSN' }} - Seluruh Putaran</title>
    <style>
        @page { size: legal landscape; margin: 8mm 8mm 14mm; }
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
        .late-note { color:#854d0e; }
        a { color:#174d75; text-decoration:underline; }
        .report-butir-label { visibility:hidden; white-space:normal; }
        [data-snp-show-first="1"] { visibility:visible; }
        .report-continuation { visibility:hidden; color:#666; }
        .print-footer { position:fixed; left:0; bottom:-7mm; font-size:7px; color:#444; }
        .watermark { position:fixed; top:38%; width:100%; text-align:center; z-index:-1000; font-size:55px; font-weight:bold; color:rgba(0,0,0,.06); transform:rotate(-30deg); }
    </style>
</head>
<body>
    <div class="watermark">SIDEWAS {{ $workflowReport['module'] === 'snp' ? 'SNP DEWAS' : 'DJSN' }}</div>
    @php
        $fields = $workflowReport['fields'];
        $weights = ['surat' => 10, 'id_butir' => 8, 'isi_butir' => 32, 'pic_unit' => 8, 'tanggapan_tl' => 30, 'tanggapan_unit' => 24, 'kompilasi_tanggapan' => 22, 'hasil_reviu' => 21, 'deliverable' => 7, 'dokumen' => 7, 'jatuh_tempo' => 7, 'komite' => 6, 'status' => 10];
        $totalWeight = collect($fields)->sum(fn ($field) => $weights[$field] ?? 8);
        $module = $workflowReport['module'];
    @endphp
    <table>
        <thead><tr>
            @foreach ($fields as $field)
                <th style="width:{{ 100 * ($weights[$field] ?? 8) / max(1, $totalWeight) }}%;">{{ $workflowReport['labels'][$field] ?? $fieldLabels[$field] ?? strtoupper($field) }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @foreach ($workflowReport['butirs'] as $item)
                @php
                    $record = $item['record'];
                    $butir = $item['butir'];
                    $rounds = $workflowReport['round_field'] && $item['rounds']->isNotEmpty() ? $item['rounds'] : collect([null]);
                    $butirKey = $item['id'];
                @endphp
                @foreach ($rounds as $round)
                    @php
                        $firstRound = $loop->first;
                        $roundLabelKey = $butirKey.'-round-'.($round['id'] ?? 'initial');
                    @endphp
                    <tr data-report-butir="{{ $item['id'] }}" class="{{ $firstRound ? 'butir-start' : '' }} {{ $loop->last ? 'butir-end' : '' }}">
                        @foreach ($fields as $field)
                            <td>
                                @if ($field === $workflowReport['round_field'] && $round)
                                    @if (!in_array('id_butir', $fields, true) && !in_array('isi_butir', $fields, true))
                                        <div class="report-butir-label" data-snp-butir-label="{{ $roundLabelKey }}" data-snp-show-first="0">{{ $round['label'] }}<div class="report-continuation" data-snp-continuation>Lanjutan</div></div>
                                    @endif
                                    <div class="round-title">{{ $round['label'] }}</div>
                                    <div>Dibuka: {{ $round['opened_at']?->format('d/m/Y H:i') ?? 'Tanggal tidak tercatat' }}</div>
                                    @if ($round['archived_at'])<div>Diarsipkan: {{ $round['archived_at']->format('d/m/Y H:i') }}</div>@endif
                                @endif
                                @if ($field === 'surat' && $firstRound)
                                    <div>{{ $record->nomor_surat }}</div>
                                    <div>{{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}</div>
                                    <div>{{ $record->perihal_surat }}</div>
                                    @if (!$butir)<div>Belum ada butir.</div>@endif
                                @elseif ($field === 'id_butir')
                                    <div class="report-butir-label" data-snp-butir-label="{{ $roundLabelKey }}" data-snp-show-first="1">{{ $item['id'] }}@if($round)<div>{{ $round['label'] }}</div>@endif<div class="report-continuation" data-snp-continuation>Lanjutan</div></div>
                                @elseif ($field === 'isi_butir')
                                    @if (!in_array('id_butir', $fields, true))
                                        <div class="report-butir-label" data-snp-butir-label="{{ $roundLabelKey }}" data-snp-show-first="0">{{ $item['id'] }}@if($round)<div>{{ $round['label'] }}</div>@endif<div class="report-continuation" data-snp-continuation>Lanjutan</div></div>
                                    @endif
                                    @if ($firstRound)
                                    <x-rich-content data-report-rich :content="$butir?->getAttribute('butir_'.$module)" />
                                    @endif
                                @elseif ($field === 'pic_unit' && $firstRound && $butir)
                                    @foreach (app(\App\Services\SuratPutaranPenugasan::class)->units($butir->butirPics) as $unit)
                                        <div>{{ ucfirst($unit['role']) }}: {{ $unit['code'] ? $unit['code'].' - ' : '' }}{{ $unit['name'] }}</div>
                                    @endforeach
                                    <div class="muted">Penugasan terkini.</div>
                                @elseif ($field === 'komite' && $firstRound && $butir)
                                    @foreach ($butir->butirPics->where('jenis_pic', 'komite') as $pic)
                                        <div>{{ $pic->komite?->kode_komite ?? $pic->komite?->nama_komite ?? '-' }}</div>
                                    @endforeach
                                @elseif (in_array($field, ['tanggapan_tl', 'tanggapan_unit'], true) && $round)
                                    @include('layouts.partials.workflow-report-responses')
                                    @if ($field === 'tanggapan_tl')
                                        @foreach ($round['compilations']->concat($round['followups']) as $entry)
                                            @include('layouts.partials.workflow-report-entry')
                                        @endforeach
                                        @include('layouts.partials.workflow-report-late-responses')
                                    @endif
                                @elseif (in_array($field, ['kompilasi_tanggapan', 'kompilasi_tindak_lanjut', 'tindak_lanjut_unit', 'hasil_reviu'], true) && $round)
                                    @php
                                        $entries = match ($field) {
                                            'kompilasi_tanggapan' => $round['compilations']->where('stage', 'tanggapan'),
                                            'kompilasi_tindak_lanjut' => $round['compilations']->where('stage', 'tindak_lanjut'),
                                            'tindak_lanjut_unit' => $round['followups'],
                                            default => $round['reviews'],
                                        };
                                    @endphp
                                    @forelse ($entries as $entry)
                                        @include('layouts.partials.workflow-report-entry')
                                    @empty
                                        <div>{{ $field === 'hasil_reviu' ? 'Belum ada reviu pada putaran ini.' : 'Belum ada data pada putaran ini.' }}</div>
                                    @endforelse
                                @elseif (in_array($field, ['deliverable', 'dokumen', 'jatuh_tempo'], true))
                                    @if ($field === 'jatuh_tempo' && $firstRound)
                                        <div>Jatuh tempo tercatat: {{ $record->jth_tempo ? \Carbon\Carbon::parse($record->jth_tempo)->format('d/m/Y') : '-' }}</div>
                                        @if (!$butir && $record->batasStatusAwal())
                                            <div>Batas awal 14 hari kalender: {{ $record->batasStatusAwal()->format('d/m/Y') }}</div>
                                        @endif
                                    @endif
                                    @foreach ($round['all_entries'] ?? [] as $entry)
                                        @if (($field === 'deliverable' && filled($entry['deliverables'])) || ($field === 'dokumen' && $entry['documents']->isNotEmpty()) || ($field === 'jatuh_tempo' && $entry['proposed_date']))
                                            <div class="entry-title">{{ $entry['title'] }}{{ in_array($entry['field'], ['tanggapan', 'tindak_lanjut'], true) ? ' - '.$entry['unit'] : '' }}</div>
                                            @if ($entry['late_at'])<div class="late-note">Setelah penutupan {{ $entry['late_at']->format('d/m/Y H:i') }}; bukan bahan reviu.</div>@endif
                                            @if ($field === 'deliverable')
                                                <div class="pre-line">{{ $entry['deliverables'] }}</div>
                                            @elseif ($field === 'dokumen')
                                                @foreach ($entry['documents'] as $document)
                                                    <div><a href="{{ $document['url'] }}">{{ $document['label'] }}: {{ basename(parse_url($document['url'], PHP_URL_PATH)) }}</a></div>
                                                @endforeach
                                            @else
                                                <div>Pengajuan: {{ \Carbon\Carbon::parse($entry['proposed_date'])->format('d/m/Y') }}</div>
                                                <div>Status: {{ ucfirst($entry['date_status'] ?? 'pending') }}</div>
                                            @endif
                                        @endif
                                    @endforeach
                                @elseif ($field === 'status')
                                    <div><strong>{{ \App\Enums\SuratStatus::labelFor($record->status) }}</strong></div>
                                    @if ($record->closed_at)
                                        <div>Close: {{ $record->closed_at->format('d/m/Y H:i') }}</div>
                                        <div>Kelengkapan terlambat tidak mengubah keputusan.</div>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    <div class="print-footer">Dicetak oleh: {{ $printedBy ?? '-' }} | {{ $printedAt ?? now()->format('d/m/Y H:i') }}</div>
</body>
</html>
</x-report-butir-pdf>
