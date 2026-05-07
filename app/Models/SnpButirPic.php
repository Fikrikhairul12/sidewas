<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SnpButirPic extends Model
{
    use TracksUser;

    protected $connection = 'mysql_snp';
    protected $table = 'tb_butir_pic';

    protected $fillable = [
        'id_butir_snp',
        'unit_kerja_id',
        'komite_id',
        'jenis_pic',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saving(function ($pic) {
            $hasUnitKerja = ! empty($pic->unit_kerja_id);
            $hasKomite = ! empty($pic->komite_id);

            if ($hasUnitKerja === $hasKomite) {
                throw ValidationException::withMessages([
                    'pic' => 'PIC harus memilih salah satu: unit kerja atau komite.',
                ]);
            }
        });
    }

    public function butir()
    {
        return $this->belongsTo(SnpButir::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id', 'id');
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
