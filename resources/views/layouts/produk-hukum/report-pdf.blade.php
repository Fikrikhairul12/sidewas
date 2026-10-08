<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Produk Hukum</title>
    <style>
        @page { size: A4 landscape; margin: 14mm 12mm 17mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #253447; }
        h1 { margin: 0 0 5pt; font-size: 17pt; color: #2377b9; }
        .summary { margin: 0 0 14pt; font-size: 9pt; color: #526174; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th { background: #2377b9; color: white; text-align: left; font-size: 8pt; }
        th, td { padding: 7pt 6pt; border: 0.5pt solid #ccd8e4; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        td { line-height: 1.5; }
        tr { page-break-inside: avoid; }
        .shade td { background-color: rgba(237, 243, 248, 0.5); }
        .muted { font-size: 8pt; color: #607084; }
        .code { margin-top: 4pt; font-size: 8pt; color: #2377b9; }
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; z-index: -1000;
            font-family: Arial, Helvetica, sans-serif; font-size: 50px; font-weight: bold; color: rgba(0, 0, 0, 0.08);
            transform: rotate(-30deg); transform-origin: center; letter-spacing: 4px; text-transform: uppercase; }
        .print-footer { position: fixed; left: 0; bottom: -9mm; width: 82%; font-size: 7pt; color: #607084; }
    </style>
</head>
<body>
    <div class="watermark">SIDEWAS PRODUK HUKUM DEWAS</div>
    <div class="print-footer">Dokumen ini dicetak oleh {{ $printedBy }} pada {{ $printedAt }}</div>
    <h1>REKAP PRODUK HUKUM</h1>
    <p class="summary">{{ $scope }} &nbsp; | &nbsp; Total: {{ $products->count() }} peraturan &nbsp; | &nbsp; {{ $printedAt }}</p>
    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No.</th>
                <th style="width: 39%;">Produk Hukum</th>
                <th style="width: 16%;">Nomor / Tahun</th>
                <th style="width: 22%;">Jenis / Bidang</th>
                <th style="width: 8%;">Sifat</th>
                <th style="width: 11%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
                @php
                    $number = $loop->iteration;
                    $remainingTitle = $product->judul;
                    $titleParts = [];
                    while (mb_strlen($remainingTitle) > 700) {
                        $part = mb_substr($remainingTitle, 0, 700);
                        $space = mb_strrpos($part, ' ');
                        $length = $space !== false && $space > 350 ? $space + 1 : 700;
                        $titleParts[] = mb_substr($remainingTitle, 0, $length);
                        $remainingTitle = mb_substr($remainingTitle, $length);
                    }
                    $titleParts[] = $remainingTitle;
                @endphp
                @foreach ($titleParts as $part)
                    <tr class="{{ $number % 2 === 0 ? 'shade' : '' }}">
                        <td>{{ $number }}</td>
                        <td>
                            @if (! $loop->first)<div class="muted">Lanjutan judul</div>@endif
                            <strong>{{ $part }}</strong>
                            <div class="code">{{ $product->kode_produk_hukum }}</div>
                        </td>
                        <td>{{ $product->nomor_peraturan_keputusan ?: '-' }}<br><span class="muted">Tahun: {{ $product->tahun_peraturan ?: '-' }}</span></td>
                        <td>{{ $product->jenis_bentuk_peraturan ?: '-' }}<br><span class="muted">Bidang: {{ $product->bidang_pengaturan ?: '-' }}</span></td>
                        <td>{{ ucfirst($product->sifat_dokumen) }}</td>
                        <td>{{ $statusLabels[$product->status_peraturan] ?? 'Draf' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
