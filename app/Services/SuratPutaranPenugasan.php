<?php

namespace App\Services;

use App\Models\SuratPutaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SuratPutaranPenugasan
{
    public function capture(SuratPutaran $round): void
    {
        if ($round->penugasan_unit !== null) {
            return;
        }
        $module = $round->getConnectionName() === 'mysql_snp' ? 'snp' : 'djsn';
        $record = $round->surat;
        $relation = 'butir'.ucfirst($module);
        $record->load($relation.'.butirPics.unitKerja');
        $assignments = [];
        foreach ($record->{$relation} as $butir) {
            $assignments[$butir->{'id_butir_'.$module}] = $this->units($butir->butirPics)->all();
        }
        $round->forceFill(['penugasan_unit' => $assignments, 'penugasan_dicatat_at' => now()])->save();
    }

    /**
     * @param  Collection<int, Model>  $pics
     * @return Collection<int, array{id: int, name: string, code: ?string, role: string}>
     */
    public function units(Collection $pics): Collection
    {
        return $pics->whereIn('jenis_pic', ['utama', 'pendukung'])->whereNotNull('unit_kerja_id')
            ->sortBy(fn ($pic) => $pic->jenis_pic === 'utama' ? 0 : 1)->unique('unit_kerja_id')
            ->map(fn ($pic) => [
                'id' => (int) $pic->unit_kerja_id,
                'name' => $pic->unitKerja?->nama_unit ?? 'Unit #'.$pic->unit_kerja_id.' (nama tidak tersedia)',
                'code' => $pic->unitKerja?->kode_unit, 'role' => $pic->jenis_pic,
            ])->values();
    }
}
