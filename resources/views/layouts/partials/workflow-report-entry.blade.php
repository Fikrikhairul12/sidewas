<div class="entry-block">
    <div class="entry-title">{{ $entry['title'] }}</div>
    @if (in_array($entry['field'], ['tanggapan', 'tindak_lanjut'], true))
        <div><strong>{{ $entry['unit_code'] ? $entry['unit_code'].' - ' : '' }}{{ $entry['unit'] }}</strong></div>
    @endif
    <div>Pengisi: {{ $entry['author'] }}</div>
    <div>Dicatat: {{ $entry['created_at']?->format('d/m/Y H:i') ?? 'Tanggal tidak tercatat' }}</div>
    @if ($entry['updated_at'] && $entry['updated_at'] != $entry['created_at'])
        <div>Diperbarui: {{ $entry['updated_at']->format('d/m/Y H:i') }}</div>
    @endif
    @if ($entry['late_at'])
        <div class="late-note">Tanggapan setelah penutupan: {{ $entry['late_at']->format('d/m/Y H:i') }}. Kelengkapan tambahan, bukan bahan keputusan reviu.</div>
    @endif
    @if ($entry['decision'])
        <div><strong>Keputusan reviu: {{ $entry['decision'] }}</strong></div>
    @endif
    @if (in_array($entry['field'], ['hasil_kompilasi', 'hasil_review'], true))
        <x-rich-content data-report-rich :content="$entry['content']" />
    @else
        <div class="pre-line">{{ filled($entry['content']) ? $entry['content'] : '-' }}</div>
    @endif
</div>
