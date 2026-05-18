<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeleteRequest extends Model
{
    protected $table = 'tb_delete_requests';

    protected $fillable = [
        'type_code',
        'database_name',
        'table_name',
        'record_key',
        'record_label',
        'reason',
        'requested_by',
        'verified_by',
        'approved_by',
        'rejected_by',
        'status',
        'requested_at',
        'verified_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by', 'id');
    }
}
