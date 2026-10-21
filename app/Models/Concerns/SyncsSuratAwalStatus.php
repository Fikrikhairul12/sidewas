<?php

namespace App\Models\Concerns;

use App\Models\DjsnRecord;
use App\Models\SnpRecord;
use App\Services\SuratAwalStatusService;
use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait SyncsSuratAwalStatus
{
    protected static function bootSyncsSuratAwalStatus(): void
    {
        $sync = static function (Model $model): void {
            $service = new SuratAwalStatusService;
            if (! $service->enabled($model->getConnectionName())) {
                return;
            }

            $record = match ($model->getTable()) {
                'tb_record' => $model->exists ? $model : null,
                'tb_tanggapan' => $model->butir()->first()?->record()->first(),
                default => $model->record()->first(),
            };
            if ($record instanceof SnpRecord || $record instanceof DjsnRecord) {
                $service->synchronizeRecord($record);
            }
        };

        static::saved($sync);
        static::deleted($sync);
    }
}
