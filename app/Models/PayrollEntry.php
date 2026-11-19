<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PayrollEntry extends WorkspaceModel
{
    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            if ($entry->run()->value('status') !== 'draft') {
                throw new LogicException('Entries can only be added to draft payroll.');
            }
        });
        static::updating(function (self $entry) {
            if ($entry->run()->value('status') !== 'draft') {
                throw new LogicException('Approved and submitted payroll entries cannot be overwritten.');
            }
        });
        static::deleting(function (self $entry) {
            if ($entry->run()->value('status') !== 'draft') {
                throw new LogicException('Submitted, approved and paid payroll entries are retained for history.');
            }
        });
    }

    protected function casts(): array
    {
        return ['base_salary' => 'decimal:2', 'allowances' => 'decimal:2', 'deductions' => 'decimal:2', 'net_pay' => 'decimal:2'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(PayrollCorrection::class)->orderBy('id');
    }

    public function effective(): self|PayrollCorrection
    {
        return $this->corrections->last() ?? $this;
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
        return $user->canPayroll() && $this->run->status === 'draft';
    }
}
