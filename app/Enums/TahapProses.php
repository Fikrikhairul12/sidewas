<?php

namespace App\Enums;

enum TahapProses: string
{
    case TanggapanAwal = 'tanggapan_awal';
    case TindakLanjut = 'tindak_lanjut';

    public function label(): string
    {
        return match ($this) {
            self::TanggapanAwal => 'Tanggapan Awal',
            self::TindakLanjut => 'Tindak Lanjut',
        };
    }
}
