<?php

namespace App\Enums;

enum VisitStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case ONGOING = 'ONGOING';
    case WAITING_REPORT = 'WAITING_REPORT';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::ONGOING => 'Sedang Berlangsung',
            self::WAITING_REPORT => 'Belum Ada Laporan',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::REJECTED => 'danger',
            self::ONGOING => 'primary',
            self::WAITING_REPORT => 'orange',
            self::COMPLETED => 'success',
            self::CANCELLED => 'muted',
        };
    }
}
