<?php

namespace App\Models\Concerns;

use App\Models\DjsnButir;
use App\Models\DjsnRecord;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Services\IsianUnitTahapService;
use App\Services\SuratAwalStatusService;
use App\Services\SuratTanggalService;
use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait SyncsSuratAwalStatus
{
    protected static function bootSyncsSuratAwalStatus(): void
    {
        static::created(function (Model $model): void {
            if ($model instanceof SnpButir || $model instanceof DjsnButir) {
                app(SuratTanggalService::class)->preparePublication($model);
            }
        });
        $sync = static function (Model $model): void {
            if ($model->getTable() === 'tb_butir_pic' && ! IsianUnitTahapService::enabled()) {
                return;
            }
            $service = new SuratAwalStatusService;
            if (! $service->enabled($model->getConnectionName())) {
                return;
            }
            if ($model->getTable() === 'tb_butir_pic') {
                app(SuratTanggalService::class)->publicationAssigned($model);
            }
            if (($model instanceof SnpButir || $model instanceof DjsnButir) && app(SuratTanggalService::class)->enabled($model->getConnectionName())) {
                $pic = $model->butirPics()->whereIn('jenis_pic', ['utama', 'pendukung'])->whereNotNull('unit_kerja_id')->first();
                if ($pic !== null) {
                    app(SuratTanggalService::class)->publicationAssigned($pic);
                }
            }

            $record = match ($model->getTable()) {
                'tb_record' => $model->exists ? $model : null,
                'tb_tanggapan', 'tb_butir_pic' => $model->butir()->first()?->record()->first(),
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
