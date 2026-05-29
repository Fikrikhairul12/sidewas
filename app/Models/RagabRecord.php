<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RagabRecord extends Model
{
    use TracksUser;

    protected $connection = 'mysql_ragab';

    protected $table = 'tb_record';

    protected $fillable = [
        'id_ragab',
        'cluster_id',
        'sub_cluster_id',
        'nomor_surat',
        'tanggal_surat',
        'perihal_surat',
        'dokumen',
        'jth_tempo',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'jth_tempo' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($record) {
            if (empty($record->id_ragab)) {
                $year = now()->format('Y');

                $lastNumber = static::whereYear('created_at', $year)->count() + 1;

                $record->id_ragab = $year . '-RAGAB.' . str_pad($lastNumber, 2, '0', STR_PAD_LEFT);
            }

            if (! empty($record->tanggal_surat) && empty($record->jth_tempo)) {
                $record->jth_tempo = Carbon::parse($record->tanggal_surat)->addDays(30);
            }

            if (empty($record->status)) {
                $record->status = 'draft';
            }
        });
    }

    public function cluster()
    {
        return $this->belongsTo(RagabCluster::class, 'cluster_id', 'id');
    }

    public function subCluster()
    {
        return $this->belongsTo(RagabSubCluster::class, 'sub_cluster_id', 'id');
    }

    public function butirRagab()
    {
        return $this->hasMany(RagabButir::class, 'id_ragab', 'id_ragab');
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
