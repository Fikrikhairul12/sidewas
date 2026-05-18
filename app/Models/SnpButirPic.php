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
            if (in_array($pic->jenis_pic, ['utama', 'pendukung'])) {
                if (empty($pic->unit_kerja_id) || !empty($pic->komite_id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'pic' => 'PIC utama/pendukung wajib menggunakan unit kerja.',
                    ]);
                }
            }

            if ($pic->jenis_pic === 'komite') {
                if (empty($pic->komite_id) || !empty($pic->unit_kerja_id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'komite' => 'PIC komite wajib menggunakan komite.',
                    ]);
                }
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
