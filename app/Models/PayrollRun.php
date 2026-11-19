<?php

namespace App\Models;

use App\Support\DecimalMoney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
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
        return $user->canPayroll() && $this->status === 'draft';
    }

    public function total(): string
    {
        return DecimalMoney::decimal($this->entries->sum(fn (PayrollEntry $entry) => DecimalMoney::minor($entry->effective()->net_pay)));
    }
}
