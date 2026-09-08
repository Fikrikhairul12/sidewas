<?php

namespace App\Models\Kunjungan;

use Illuminate\Database\Eloquent\Relations\Pivot;

class VisitParticipant extends Pivot
{
    protected $connection = 'mysql_kunjungan';

    protected $table = 'visit_participants';

    public $incrementing = true;
}
