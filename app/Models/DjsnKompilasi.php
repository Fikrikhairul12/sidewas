<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSuratPutaran;
use App\Models\Concerns\HasKompilasiSalinan;
use App\Models\Concerns\TracksUser;
use Database\Factories\DjsnKompilasiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DjsnKompilasi extends Model
{
    use BelongsToSuratPutaran;
    /** @use HasFactory<DjsnKompilasiFactory> */
    use HasFactory;

    use HasKompilasiSalinan;

    use TracksUser;

    protected $connection = 'mysql_djsn';

    protected $table = 'tb_kompilasi';

    protected $fillable = [
        'id_butir_djsn', 'putaran_id', 'tahap_kompilasi', 'putaran_tl',
        'hasil_kompilasi', 'deliverables', 'dokumen', 'ubah_tgl',
        'status_pengajuan_tgl', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['ubah_tgl' => 'date'];
    }

    public function butir(): BelongsTo
    {
        return $this->belongsTo(DjsnButir::class, 'id_butir_djsn', 'id_butir_djsn');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DjsnReview::class, 'kompilasi_id');
    }

    public function bahan(): HasMany
    {
        return $this->hasMany(KompilasiBahan::class, 'kompilasi_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
