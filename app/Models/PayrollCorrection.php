<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollCorrection extends WorkspaceModel
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payroll corrections are append-only.'));
        static::deleting(fn () => throw new LogicException('Payroll corrections are append-only.'));
    }

    protected function casts(): array
    {
        return ['prior_values' => 'array', 'base_salary' => 'decimal:2', 'allowances' => 'decimal:2', 'deductions' => 'decimal:2', 'net_pay' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->canPayroll() ? $query : $query->whereRaw('1 = 0');
    }

    public static function canCreate(User $user): bool
    {
        return $user->isAdmin();
    }

    public function canEdit(User $user): bool
    {
        return false;
    }
}
