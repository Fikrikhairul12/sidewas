<?php

test('kompilasi snp tersedia sejak butir dibuat tanpa menunggu kelengkapan pic', function () {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Snp/KompilasiSnpController.php');

    expect($controller)
        ->toContain("'tahap' => 'tanggapan'")
        ->toContain('$reviuTanggapanSudahMembukaTindakLanjut')
        ->toContain("->where('status', 'dalam_proses_tindak_lanjut_direksi')")
        ->not->toContain('Kompilasi tanggapan belum bisa dilakukan karena tanggapan PIC Unit belum lengkap.')
        ->not->toContain('Kompilasi tindak lanjut belum bisa dilakukan karena tindak lanjut PIC Unit putaran ini belum lengkap.');
});

test('tanggapan dan tindak lanjut snp menyediakan update yang dikunci setelah direviu', function () {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $tanggapan = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Snp/TanggapanSnpController.php');
    $tindakLanjut = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Snp/TindakLanjutSnpController.php');

    expect($routes)
        ->toContain("->name('snp.tanggapan.update')")
        ->toContain("->name('snp.tindak-lanjut.update')");

    expect($tanggapan)
        ->toContain('public function update(Request $request, SnpTanggapan $tanggapan)')
        ->toContain("filled(\$review->hasil_review)")
        ->toContain("hasRoleType('admin_snp')");

    expect($tindakLanjut)
        ->toContain('public function update(Request $request, SnpTindakLanjut $tindakLanjut)')
        ->toContain('$kompilasiTindakLanjutSudahDireviu')
        ->toContain("->where('tahap_review', 'tindak_lanjut')")
        ->toContain("filled(\$review->hasil_review)")
        ->toContain("Kompilasi tindak lanjut putaran ini sudah direviu.")
        ->toContain("hasRoleType('admin_snp')");
});

test('admin snp tetap dapat mengisi tindak lanjut setelah kompilasi direviu', function () {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Snp/TindakLanjutSnpController.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/snp/tindak-lanjut.blade.php');

    expect($controller)
        ->toContain('private function canInputTindakLanjut($butir, User $user): bool')
        ->toContain("\$user->isSuperAdmin() || \$user->hasRoleType('admin_snp')")
        ->toContain('return blank($reviewTlTerakhir->hasil_review);');

    expect($view)
        ->not->toContain("\$butir?->kompilasiTindakLanjut?->status === 'dalam_proses_reviu_dewas'")
        ->toContain("\$availablePicUnits->count() > 0 && \$row['can_input_tl']")
        ->toContain('Sudah Direviu');
});
