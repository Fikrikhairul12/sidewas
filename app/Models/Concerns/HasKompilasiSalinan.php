<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @mixin Model */
trait HasKompilasiSalinan
{
    public function initializeHasKompilasiSalinan(): void
    {
        $this->mergeCasts(['salinan_konteks' => 'array', 'bahan_dicatat_at' => 'datetime']);
    }

    public static function bootHasKompilasiSalinan(): void
    {
        static::updating(function (Model $compilation): void {
            $stored = $compilation->newQuery()->lockForUpdate()->findOrFail($compilation->id);
            if ($stored->getAttribute('salinan_konteks') === null) {
                return;
            }
            foreach (['id_butir_snp', 'id_butir_djsn', 'putaran_id', 'tahap_id', 'putaran_tl', 'tahap_kompilasi',
                'hasil_kompilasi', 'deliverables', 'dokumen', 'ubah_tgl', 'alasan_pengajuan_tgl', 'created_by', 'created_at', 'status',
                'salinan_konteks', 'kunci_salinan', 'status_bahan', 'bahan_dicatat_at'] as $field) {
                if ($compilation->isDirty($field) && $compilation->getAttributes()[$field] !== $stored->getRawOriginal($field)) {
                    throw new LogicException('Hasil kompilasi dan salinan bahan resmi tidak boleh ditimpa. Perbaikan mengikuti putaran berikutnya.');
                }
            }
        });
        static::deleting(function (Model $compilation): void {
            if ($compilation->newQuery()->lockForUpdate()->find($compilation->id)?->getAttribute('salinan_konteks') !== null) {
                throw new LogicException('Kompilasi resmi harus tetap tersimpan sebagai riwayat.');
            }
        });
    }
}
