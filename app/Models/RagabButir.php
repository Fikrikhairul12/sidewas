<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class RagabButir extends Model
{
    use TracksUser;

    protected $connection = 'mysql_ragab';

    protected $table = 'tb_butir_ragab';

    protected $fillable = [
        'id_butir_ragab',
        'id_ragab',
        'cluster_id',
        'sub_cluster_id',
        'tanggal_ragab',
        'agenda_ragab',
        'keputusan_ragab',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_ragab' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($butir) {
            if (empty($butir->id_butir_ragab)) {
                $butir->id_butir_ragab = static::generateIdButirRagab($butir->id_ragab);
            }
        });
    }

    public static function generateIdButirRagab(string $idRagab): string
    {
        $lastButir = static::where('id_ragab', $idRagab)->orderByDesc('id')->first();

        $nextNumber = $lastButir
            ? ((int) substr($lastButir->id_butir_ragab, strrpos($lastButir->id_butir_ragab, '.') + 1)) + 1
            : 1;

        return $idRagab . '.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    public function record()
    {
        return $this->belongsTo(RagabRecord::class, 'id_ragab', 'id_ragab');
    }

    public function cluster()
    {
        return $this->belongsTo(RagabCluster::class, 'cluster_id', 'id');
    }

    public function subCluster()
    {
        return $this->belongsTo(RagabSubCluster::class, 'sub_cluster_id', 'id');
    }

    public function butirPics()
    {
        return $this->hasMany(RagabButirPic::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function butirDirektorats()
    {
        return $this->hasMany(RagabButirDirektorat::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function tindakLanjuts()
    {
        return $this->hasMany(RagabTindakLanjut::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function reviews()
    {
        return $this->hasMany(RagabReview::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function reviewTindakLanjut()
    {
        return $this->hasOne(RagabReview::class, 'id_butir_ragab', 'id_butir_ragab')
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

    public function tindakLanjutUnitKerjaIds(): array
    {
        $this->loadMissing('tindakLanjuts');

        return $this->tindakLanjuts
            ->pluck('unit_kerja_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    public function jumlahPicUnit(): int
    {
        return count($this->picUnitKerjaIds());
    }

    public function jumlahPicUnitSudahTindakLanjut(): int
    {
        return count(array_intersect(
            $this->picUnitKerjaIds(),
            $this->tindakLanjutUnitKerjaIds()
        ));
    }

    public function isTindakLanjutLengkap(): bool
    {
        $picUnitIds = $this->picUnitKerjaIds();

        if (count($picUnitIds) === 0) {
            return false;
        }

        $tlUnitIds = $this->tindakLanjutUnitKerjaIds();

        return empty(array_diff($picUnitIds, $tlUnitIds));
    }

    public function statusTindakLanjut(): string
    {
        return $this->isTindakLanjutLengkap()
            ? 'diusulkan_tuntas'
            : 'dalam_proses_tindak_lanjut';
    }

    public function statusTindakLanjutLabel(): string
    {
        return match ($this->statusTindakLanjut()) {
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            default => 'Dalam Proses Tindak Lanjut',
        };
    }

    public function progressTindakLanjutLabel(): string
    {
        return $this->jumlahPicUnitSudahTindakLanjut() . ' dari ' . $this->jumlahPicUnit() . ' PIC Unit sudah TL';
    }
}
