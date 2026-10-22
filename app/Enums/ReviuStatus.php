<?php

namespace App\Enums;

enum ReviuStatus: string
{
    case BelumDitanggapi = 'belum_ditanggapi';
    case TindakLanjutBelumSesuai = 'tindak_lanjut_belum_sesuai';
    case TindakLanjutSesuai = 'tindak_lanjut_sesuai';
    case TidakDapatDitindaklanjuti = 'tidak_dapat_ditindaklanjuti';

    public function label(): string
    {
        return match ($this) {
            self::BelumDitanggapi => 'Belum Ditanggapi',
            self::TindakLanjutBelumSesuai => 'Tindak Lanjut Belum Sesuai',
            self::TindakLanjutSesuai => 'Tindak Lanjut Sesuai',
            self::TidakDapatDitindaklanjuti => 'Tidak Dapat Ditindaklanjuti',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    public static function labelFor(?string $value): string
    {
        return self::tryFrom($value ?? '')?->label()
            ?? str($value ?? '-')->replace('_', ' ')->title()->toString().' (Alur Lama)';
    }
}
