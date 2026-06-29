<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class EksternalButir extends Model
{
    use TracksUser;

    protected $connection = 'mysql_eksternal';

    protected $table = 'tb_butir_eksternal';

    protected $fillable = [
        'id_butir_eksternal',
        'id_eksternal',
        'cluster_id',
        'sub_cluster_id',
        'tanggal_eksternal',
        'agenda_eksternal',
        'keputusan_eksternal',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_eksternal' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($butir) {
            if (empty($butir->id_butir_eksternal)) {
                $butir->id_butir_eksternal = static::generateIdButirEksternal($butir->id_eksternal);
            }

            if (empty($butir->status)) {
                $butir->status = 'terbit';
            }
        });
    }

    public static function generateIdButirEksternal(string $idEksternal): string
    {
        $lastButir = static::where('id_eksternal', $idEksternal)->orderByDesc('id')->first();

        $nextNumber = $lastButir
            ? ((int) substr($lastButir->id_butir_eksternal, strrpos($lastButir->id_butir_eksternal, '.') + 1)) + 1
            : 1;

        return $idEksternal . '.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    public function record()
    {
        return $this->belongsTo(EksternalRecord::class, 'id_eksternal', 'id_eksternal');
    }

    public function cluster()
    {
        return $this->belongsTo(EksternalCluster::class, 'cluster_id', 'id');
    }

    public function subCluster()
    {
        return $this->belongsTo(EksternalSubCluster::class, 'sub_cluster_id', 'id');
    }

    public function butirPics()
    {
        return $this->hasMany(EksternalButirPic::class, 'id_butir_eksternal', 'id_butir_eksternal');
    }

    public function butirDirektorats()
    {
        return $this->hasMany(EksternalButirDirektorat::class, 'id_butir_eksternal', 'id_butir_eksternal');
    }

    public function tindakLanjuts()
    {
        return $this->hasMany(EksternalTindakLanjut::class, 'id_butir_eksternal', 'id_butir_eksternal');
    }

    public function reviews()
    {
        return $this->hasMany(EksternalReview::class, 'id_butir_eksternal', 'id_butir_eksternal');
    }

    public function reviewTindakLanjut()
    {
        return $this->hasOne(EksternalReview::class, 'id_butir_eksternal', 'id_butir_eksternal')
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
            default => 'Dalam Proses',
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
