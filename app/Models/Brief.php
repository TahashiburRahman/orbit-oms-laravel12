<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Brief extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function deliverables()
    {
        return $this->hasMany(Deliverable::class);
    }

    public function scopeVisibleTo(Builder $q, User $u): void
    {
        if ($u->isAdmin()) {
            return;
        }
        $q->where(function ($q) use ($u) {
            $q->where('requested_by', $u->id)->orWhere('assigned_to', $u->id);
            if ($u->inTeam('graphics')) {
                $q->orWhere(fn ($q) => $q->whereNull('assigned_to')->where('status', 'open'));
            }
        });
    }

    public static function canCreate(User $u): bool
    {
        return $u->active && $u->inTeam('admin', 'marketing');
    }

    public function canEdit(User $u): bool
    {
        return $u->active && ($u->isAdmin() || ($this->requested_by === $u->id && $u->inTeam('marketing')));
    }

    public function canProduce(User $u): bool
    {
        return $u->active && ($u->isAdmin() || ($u->inTeam('graphics') && $this->assigned_to === $u->id));
    }
}
