<?php

namespace App\Models\Concerns;

use App\Support\Audit;

trait Audited
{
    protected static function bootAudited(): void
    {
        static::created(fn ($model) => Audit::record($model, 'created'));
        static::updated(function ($model) {
            $changes = array_diff_key($model->getChanges(), array_flip(['password', 'remember_token', 'phone', 'updated_at']));
            if ($changes) {
                Audit::record($model, 'updated', ['changes' => $changes, 'previous' => array_intersect_key($model->getRawOriginal(), $changes)]);
            }
        });
    }
}
