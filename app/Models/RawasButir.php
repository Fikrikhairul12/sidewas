<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class RawasButir extends Model
{
    use TracksUser;

    protected $connection = 'mysql_rawas';

    protected $table = 'tb_butir_rawas';

    protected $fillable = [
        'id_butir_rawas',
        'id_rawas',
        'cluster_id',
        'sub_cluster_id',
        'tanggal_rawas',
        'agenda_rawas',
        'keputusan_rawas',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_rawas' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($butir) {
            if (empty($butir->id_butir_rawas)) {
                $butir->id_butir_rawas = static::generateIdButirRawas($butir->id_rawas);
            }
        });
    }

    public static function generateIdButirRawas(string $idRawas): string
    {
        $lastButir = static::where('id_rawas', $idRawas)->orderByDesc('id')->first();
        $nextNumber = $lastButir
            ? ((int) substr($lastButir->id_butir_rawas, strrpos($lastButir->id_butir_rawas, '.') + 1)) + 1
            : 1;

        return $idRawas . '.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    public function record()
    {
        return $this->belongsTo(RawasRecord::class, 'id_rawas', 'id_rawas');
    }

    public function cluster()
    {
        return $this->belongsTo(RawasCluster::class, 'cluster_id', 'id');
    }

    public function subCluster()
    {
        return $this->belongsTo(RawasSubCluster::class, 'sub_cluster_id', 'id');
    }

    public function butirPics()
    {
        return $this->hasMany(RawasButirPic::class, 'id_butir_rawas', 'id_butir_rawas');
    }

    public function tindakLanjuts()
    {
        return $this->hasMany(RawasTindakLanjut::class, 'id_butir_rawas', 'id_butir_rawas');
    }

    public function reviews()
    {
        return $this->hasMany(RawasReview::class, 'id_butir_rawas', 'id_butir_rawas');
    }

    public function reviewTindakLanjut()
    {
        return $this->hasOne(RawasReview::class, 'id_butir_rawas', 'id_butir_rawas')
            ->where('tahap_review', 'tindak_lanjut');
    }

    public function picUnitKerjaIds(): array
    {
        $this->loadMissing('butirPics');

        return $this->butirPics
            ->where('jenis_pic', 'unit')
            ->pluck('unit_kerja_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    public function picUnitButirPicIds(): array
    {
        $this->loadMissing('butirPics');

        return $this->butirPics
            ->where('jenis_pic', 'unit')
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    public function tindakLanjutButirPicIds(): array
    {
        $this->loadMissing('tindakLanjuts');

        return $this->tindakLanjuts
            ->pluck('butir_pic_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    public function jumlahPicUnit(): int
    {
        return count($this->picUnitButirPicIds());
    }

    public function jumlahPicUnitSudahTindakLanjut(): int
    {
        return count(array_intersect(
            $this->picUnitButirPicIds(),
            $this->tindakLanjutButirPicIds()
        ));
    }

    public function isTindakLanjutLengkap(): bool
    {
        $picUnitIds = $this->picUnitButirPicIds();

        if (count($picUnitIds) === 0) {
            return false;
        }

        return empty(array_diff($picUnitIds, $this->tindakLanjutButirPicIds()));
    }

    public function statusTindakLanjut(): string
    {
        if (count($this->tindakLanjutButirPicIds()) === 0) {
            return 'belum_ditindaklanjuti';
        }

        return $this->isTindakLanjutLengkap()
            ? 'diusulkan_tuntas'
            : 'dalam_proses_tindak_lanjut';
    }

    public function statusTindakLanjutLabel(): string
    {
        return match ($this->statusTindakLanjut()) {
            'belum_ditindaklanjuti' => 'Belum Ditindaklanjuti',
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            default => 'Dalam Proses Tindak Lanjut',
        };
    }

    public function progressTindakLanjutLabel(): string
    {
        return $this->jumlahPicUnitSudahTindakLanjut() . ' dari ' . $this->jumlahPicUnit() . ' PIC Unit sudah TL';
    }
}
