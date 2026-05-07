<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserKomite extends Model
{
    protected $table = 'tb_user_komite';

    protected $fillable = [
        'user_id',
        'komite_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function komite()
    {
        return $this->belongsTo(Komite::class, 'komite_id', 'id');
    }
}
