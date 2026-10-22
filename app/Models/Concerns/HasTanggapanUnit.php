<?php

namespace App\Models\Concerns;

use App\Models\DjsnTanggapan;
use App\Models\SnpTanggapan;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTanggapanUnit
{
    public function tanggapanUnit(): HasMany
    {
        $module = $this->getConnectionName() === 'mysql_snp' ? 'snp' : 'djsn';

        return $this->hasMany($module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class, 'id_butir_'.$module, 'id_butir_'.$module);
    }

    public function tanggapanAktif(): HasMany
    {
        return $this->tanggapanUnit()->padaPutaranAktif();
    }
}
