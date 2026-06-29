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
        'status',
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

            if (empty($butir->status)) {
                $butir->status = 'terbit';
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

    public function butirSubClusters()
    {
        return $this->hasMany(RagabButirSubCluster::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function subClusters()
    {
        return $this->belongsToMany(
            RagabSubCluster::class,
            'tb_butir_sub_cluster',
            'id_butir_ragab',
            'sub_cluster_id',
            'id_butir_ragab',
            'id'
        )->withTimestamps();
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
        if (! empty($this->status)) {
            return $this->status;
        }

        if ($this->tindakLanjutUnitKerjaIds() === []) {
            return 'terbit';
        }

        return $this->isTindakLanjutLengkap()
            ? 'diusulkan_tuntas'
            : 'dalam_proses';
    }

    public function statusTindakLanjutLabel(): string
    {
        return match ($this->statusTindakLanjut()) {
            'terbit' => 'Terbit',
            'dalam_proses' => 'Dalam Proses',
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            'selesai_tuntas' => 'Selesai Tuntas',
            default => 'Dalam Proses Tindak Lanjut',
        };
    }

    public function syncStatusFromTindakLanjut(?int $updatedBy = null): void
    {
        $this->loadMissing('butirPics', 'tindakLanjuts');

        $status = match (true) {
            $this->tindakLanjutUnitKerjaIds() === [] => 'terbit',
            $this->isTindakLanjutLengkap() => 'diusulkan_tuntas',
            default => 'dalam_proses',
        };

        $attributes = ['status' => $status];

        if ($updatedBy !== null) {
            $attributes['updated_by'] = $updatedBy;
        }

        $this->update($attributes);
    }

    public function markSelesaiTuntas(?int $updatedBy = null): void
    {
        $attributes = ['status' => 'selesai_tuntas'];

        if ($updatedBy !== null) {
            $attributes['updated_by'] = $updatedBy;
        }

        $this->update($attributes);
    }

    public function progressTindakLanjutLabel(): string
    {
        return $this->jumlahPicUnitSudahTindakLanjut() . ' dari ' . $this->jumlahPicUnit() . ' PIC Unit sudah TL';
    }
}
