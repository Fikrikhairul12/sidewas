<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @mixin Model */
trait HasKeputusanReviu
{
    private bool $savingWorkflowDecision = false;

    public function isSavingWorkflowDecision(): bool
    {
        return $this->savingWorkflowDecision;
    }

    public function saveWorkflowDecision(): bool
    {
        $this->savingWorkflowDecision = true;
        try {
            return $this->save();
        } finally {
            $this->savingWorkflowDecision = false;
        }
    }

    public static function bootHasKeputusanReviu(): void
    {
        static::updating(function (Model $review): void {
            $stored = $review->newQuery()->lockForUpdate()->findOrFail($review->id);
            if ($stored->tahap_id !== null && $stored->status !== 'belum_ditanggapi'
                && $stored->kompilasiPutaran?->salinan_konteks !== null) {
                throw new LogicException('Keputusan reviu resmi yang sudah disimpan tidak boleh ditimpa.');
            }
        });
        static::deleting(function (Model $review): void {
            if ($review->newQuery()->lockForUpdate()->find($review->id)?->kompilasiPutaran?->salinan_konteks !== null) {
                throw new LogicException('Reviu dan hubungan kompilasi resmi harus tetap tersimpan sebagai riwayat.');
            }
        });
    }
}
