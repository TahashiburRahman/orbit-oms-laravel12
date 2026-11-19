<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReleaseNote extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    public function targetTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('publish_at')->where('publish_at', '<=', now());
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->published()->whereHas('targetTeams', fn (Builder $teams) => $teams->whereIn('teams.id', $user->teamIds()));
    }

    public static function canCreate(User $user): bool
    {
        return $user->isAdmin();
    }

    public function canEdit(User $user): bool
    {
        return $user->isAdmin();
    }
}
