<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class ClientQuery extends WorkspaceModel
{
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(QueryMessage::class);
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
        return $u->active && ($u->isAdmin() || $this->assigned_to === $u->id || $this->created_by === $u->id);
    }
}
