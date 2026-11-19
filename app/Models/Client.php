<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Client extends WorkspaceModel
{
    public function websites()
    {
        return $this->hasMany(ClientWebsite::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeVisibleTo(Builder $q, User $u): void
    {
        if ($u->inTeam('admin', 'operations')) {
            return;
        }
        $q->where(function ($q) use ($u) {
            $q->where('assigned_to', $u->id)->orWhere('created_by', $u->id)
                ->orWhereHas('projects', fn ($p) => $p->visibleTo($u))
                ->orWhereHas('tasks', fn ($t) => $t->visibleTo($u));
        });
    }

    public static function canCreate(User $u): bool
    {
        return $u->active && ($u->isSuperAdmin() || count($u->teamIds()) > 0);
    }

    public function canEdit(User $u): bool
    {
        return $u->inTeam('admin', 'operations');
    }
}
