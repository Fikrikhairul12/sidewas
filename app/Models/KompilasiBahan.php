<?php

namespace App\Models;

use Database\Factories\KompilasiBahanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class KompilasiBahan extends Model
{
    /** @use HasFactory<KompilasiBahanFactory> */
    use HasFactory;

    protected $table = 'tb_kompilasi_bahan';

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            $stage = $this->tahap()->firstOrFail();
            $stage->surat()->lockForUpdate()->firstOrFail();
            $compilation = $this->kompilasi()->lockForUpdate()->firstOrFail();
            if ($compilation->getAttribute('salinan_konteks') !== null) {
                throw new LogicException('Daftar bahan kompilasi yang sudah dibekukan tidak boleh ditambah atau diubah.');
            }

            return parent::save($options);
        });
    }

    protected $fillable = [
        'kompilasi_id', 'tahap_id', 'jenis_sumber', 'sumber_id', 'butir_key',
        'unit_kerja_id', 'pengisi_id', 'sumber_created_at', 'sumber_updated_at', 'salinan', 'bukti',
    ];

    protected function casts(): array
    {
        return ['salinan' => 'array', 'bukti' => 'array', 'sumber_created_at' => 'datetime', 'sumber_updated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $bahan): void {
            $module = SuratTahap::moduleFor($bahan->getConnectionName());
            $tahap = $bahan->tahap()->lockForUpdate()->firstOrFail();
            if ($tahap->status_pemetaan !== 'terpetakan' || $tahap->nomor_putaran === null) {
                throw new InvalidArgumentException('Identitas tahap harus direkonsiliasi sebelum menjadi bahan kompilasi resmi.');
            }
            $butir = $bahan->getConnection()->table('tb_butir_'.$module)
                ->where('id_butir_'.$module, $bahan->butir_key)->lockForUpdate()->first();
            $recordKey = $bahan->getConnection()->table('tb_record')->where('id', $tahap->record_id)->lockForUpdate()->first()?->{'id_'.$module};
            if ($butir === null || $butir->{'id_'.$module} !== $recordKey
                || $bahan->kompilasi()->where('tahap_id', $tahap->id)->where('id_butir_'.$module, $bahan->butir_key)->lockForUpdate()->first() === null) {
                throw new InvalidArgumentException('Bahan, kompilasi, butir, dan tahap harus berasal dari konteks surat yang sama.');
            }
            if (! in_array($bahan->jenis_sumber, ['tb_tanggapan', 'tb_tindak_lanjut'], true)
                || $bahan->getConnection()->table($bahan->jenis_sumber)->where('id', $bahan->sumber_id)
                    ->where('tahap_id', $tahap->id)->where('id_butir_'.$module, $bahan->butir_key)->lockForUpdate()->first() === null) {
                throw new InvalidArgumentException('Sumber bahan harus merupakan isian dari butir dan tahap kompilasi yang sama.');
            }
            if (empty($bahan->bukti) || $bahan->salinan === null) {
                throw new InvalidArgumentException('Salinan bahan harus memiliki isi dan bukti pencatatannya.');
            }
        });
        static::updating(function (): void {
            throw new LogicException('Salinan bahan kompilasi tidak boleh ditimpa.');
        });
        static::deleting(function (): void {
            throw new LogicException('Salinan bahan kompilasi tidak boleh dihapus.');
        });
    }

    public function tahap(): BelongsTo
    {
        return $this->belongsTo(SuratTahap::class, 'tahap_id');
    }

    public function kompilasi(): BelongsTo
    {
        $module = SuratTahap::moduleFor($this->getConnectionName());

        return $this->belongsTo(__NAMESPACE__.'\\'.ucfirst($module).'Kompilasi', 'kompilasi_id');
    }
}
