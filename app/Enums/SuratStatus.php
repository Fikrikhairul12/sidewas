<?php

namespace App\Enums;

enum SuratStatus: string
{
    case Draft = 'draft';
    case Terbit = 'terbit';
    case BelumDitindaklanjuti = 'belum_ditindaklanjuti';
    case Proses = 'proses';
    case TindakLanjutBelumSesuai = 'proses_tindak_lanjut_belum_sesuai';
    case TindakLanjutSesuai = 'tindak_lanjut_sesuai';
    case TidakDapatDitindaklanjuti = 'tidak_dapat_ditindaklanjuti';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Terbit => 'Terbit',
            self::BelumDitindaklanjuti => 'Belum Ditindaklanjuti',
            self::Proses => 'Proses',
            self::TindakLanjutBelumSesuai => 'Proses / Tindak Lanjut Belum Sesuai',
            self::TindakLanjutSesuai => 'Tindak Lanjut Sesuai',
            self::TidakDapatDitindaklanjuti => 'Tidak Dapat Ditindaklanjuti',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::TindakLanjutSesuai, self::TidakDapatDitindaklanjuti], true);
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
        return self::tryFrom($value ?? '')?->label() ?? match ($value) {
            'dalam_proses' => 'Proses',
            'tuntas' => 'Tuntas (Alur Lama)',
            default => str($value ?? '-')->replace('_', ' ')->title()->toString(),
        };
    }

    public static function colorFor(string $value): string
    {
        return match ($value) {
            'terbit' => '#2377b9',
            'belum_ditindaklanjuti' => '#b45309',
            'proses', 'dalam_proses' => '#c8e079',
            'proses_tindak_lanjut_belum_sesuai' => '#c2410c',
            'tindak_lanjut_sesuai', 'tuntas' => '#6bb17e',
            default => '#64748b',
        };
    }

    public static function textColorFor(string $value): string
    {
        return in_array($value, ['proses', 'dalam_proses'], true) ? '#0f172a' : '#ffffff';
    }
}
