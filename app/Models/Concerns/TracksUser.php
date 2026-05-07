<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait TracksUser
{
    protected static function bootTracksUser(): void
    {
        static::creating(function (Model $model) {
            if (Auth::check()) {
                if (empty($model->created_by)) {
                    $model->created_by = Auth::id();
                }

                if (empty($model->updated_by)) {
                    $model->updated_by = Auth::id();
                }
            }
        });

        static::updating(function (Model $model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
