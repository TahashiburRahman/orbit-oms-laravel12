<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(Model $model, string $event, array $metadata = []): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'subject_type' => $model::class, 'subject_id' => $model->getKey(), 'event' => $event, 'metadata' => $metadata]);
    }
}
