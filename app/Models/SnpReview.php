<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SnpReview extends Model
{
    use TracksUser;

    protected $connection = 'mysql_snp';
    protected $table = 'tb_review';

    protected $fillable = [
        'id_butir_snp',
        'id_tanggapan',
        'id_tindak_lanjut',
        'tahap_review',
        'komite_id',
        'hasil_review',
        'deliverables',
        'status',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saving(function ($review) {
            $hasTanggapan = ! empty($review->id_tanggapan);
            $hasTindakLanjut = ! empty($review->id_tindak_lanjut);

            if ($hasTanggapan === $hasTindakLanjut) {
                throw ValidationException::withMessages([
                    'review' => 'Review harus memiliki salah satu: id_tanggapan atau id_tindak_lanjut.',
                ]);
            }

            if ($review->tahap_review === 'tanggapan' && ! $hasTanggapan) {
                throw ValidationException::withMessages([
                    'id_tanggapan' => 'Review tahap tanggapan wajib memiliki id_tanggapan.',
                ]);
            }

            if ($review->tahap_review === 'tindak_lanjut' && ! $hasTindakLanjut) {
                throw ValidationException::withMessages([
                    'id_tindak_lanjut' => 'Review tahap tindak lanjut wajib memiliki id_tindak_lanjut.',
                ]);
            }
        });
    }

    public function butir()
    {
        return $this->belongsTo(SnpButir::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function tanggapan()
    {
        return $this->belongsTo(SnpTanggapan::class, 'id_tanggapan', 'id');
    }

    public function tindakLanjut()
    {
        return $this->belongsTo(SnpTindakLanjut::class, 'id_tindak_lanjut', 'id');
    }

    public function komite()
    {
        return $this->belongsTo(Komite::class, 'komite_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
