<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class CampaignPost extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function scopeVisibleTo(Builder $q, User $u): void
    {
        $q->whereHas('campaign', fn ($q) => $q->visibleTo($u));
    }

    public static function canCreate(User $u): bool
    {
        return Campaign::canCreate($u);
    }

    public function canEdit(User $u): bool
    {
        return $this->campaign->canEdit($u);
    }
}
