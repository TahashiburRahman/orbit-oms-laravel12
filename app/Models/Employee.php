<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['joined_on' => 'date', 'base_salary' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->canPayroll() ? $query : $query->whereRaw('1 = 0');
    }

    public static function canCreate(User $user): bool
    {
        return $user->canPayroll();
    }

    public function canEdit(User $user): bool
    {
        return $user->canPayroll();
    }
}
