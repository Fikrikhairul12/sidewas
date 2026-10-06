<div class="snp-butir-label {{ $isFirst && $showFirst ? '' : 'snp-butir-label-hidden' }}"
    data-snp-butir-label="{{ $butirKey }}" data-snp-show-first="{{ $showFirst ? '1' : '0' }}">
    {{ $butir->id_butir_snp }}
    <div class="continuation" data-snp-continuation>Lanjutan</div>
</div>
