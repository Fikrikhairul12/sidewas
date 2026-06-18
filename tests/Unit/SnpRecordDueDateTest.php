<?php

use App\Models\SnpRecord;

test('snp jatuh tempo uses fourteen business days excluding weekends', function () {
    $jatuhTempo = SnpRecord::hitungJatuhTempo('2026-06-19');

    expect($jatuhTempo->toDateString())->toBe('2026-07-09')
        ->and($jatuhTempo->isWeekend())->toBeFalse();
});

test('snp record create hook uses business day due date helper', function () {
    $source = file_get_contents(dirname(__DIR__, 2) . '/app/Models/SnpRecord.php');

    expect($source)
        ->toContain('$record->jth_tempo = static::hitungJatuhTempo($record->tanggal_surat);')
        ->not->toContain('addDays(30)');
});
