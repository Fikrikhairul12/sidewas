<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dokumen belum tersedia | SIDEWAS</title>
    <style>
        body { margin: 0; padding: 48px 24px; background: #f8fafc; color: #1e293b; font-family: Arial, sans-serif; }
        main { max-width: 600px; margin: 10vh auto; line-height: 1.6; }
        h1 { font-size: 26px; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
    <main>
        <p>SIDEWAS</p>
        <h1>Dokumen belum tersedia</h1>
        <p>{{ $verificationFailed ? 'Dokumen pendukung belum dapat diverifikasi. Hubungi pengelola untuk pemeriksaan berkas.' : 'Dokumen pendukung belum dapat dibuka. Hubungi pengelola untuk memeriksa ketersediaan berkas.' }}</p>
        <a href="{{ route('dashboard') }}">Kembali ke SIDEWAS</a>
    </main>
</body>
</html>
