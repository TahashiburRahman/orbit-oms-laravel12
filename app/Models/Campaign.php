<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Campaign extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function posts()
    {
        return $this->hasMany(CampaignPost::class);
    }

    public function briefs()
    {
        return $this->hasMany(Brief::class);
    }

    public function scopeVisibleTo(Builder $q, User $u): void
    {
        if (! $u->isAdmin()) {
            $q->where(fn ($q) => $q->where('assigned_to', $u->id)->orWhere('created_by', $u->id));
        }
    }

    public static function canCreate(User $u): bool
    {
        return $u->active && $u->inTeam('admin', 'marketing');
    }

    public function canEdit(User $u): bool
    {
        return self::canCreate($u) && ($u->isAdmin() || $this->assigned_to === $u->id || $this->created_by === $u->id);
    }
}
