<style>
    .snp-content-appendix { page-break-before: always; font-size: 14px; line-height: 1.65; }
    .snp-content-appendix h2 { font-size: 18px; margin: 0 0 12px; }
    .snp-content-appendix p { margin: 0 0 10px; white-space: normal; }
    .snp-content-appendix ul, .snp-content-appendix ol { padding-left: 30px; }
    .snp-content-appendix img { max-width: 100%; }
    .snp-content-appendix .snp-letter { font-size: 12px; color: #444; margin-bottom: 18px; }
</style>
@foreach ($records as $record)
    @foreach ($record->butirSnp as $butir)
        <section class="snp-content-appendix">
            <h2>Isi Butir SNP — {{ $butir->id_butir_snp }}</h2>
            <div class="snp-letter">Surat: {{ $record->nomor_surat }} · {{ $record->perihal_surat }}</div>
            {!! app(\App\Services\SnpButirContent::class)->pdf($butir->butir_snp) !!}
        </section>
    @endforeach
@endforeach
