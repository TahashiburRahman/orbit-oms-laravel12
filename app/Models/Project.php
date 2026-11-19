<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Project extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
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
        $q->where(fn ($q) => $q->whereIn('team_id', $u->teamIds())->orWhere('assigned_to', $u->id)->orWhere('created_by', $u->id)->orWhereHas('members', fn ($m) => $m->where('users.id', $u->id))->orWhereHas('tasks', fn ($t) => $t->where('assigned_to', $u->id)->orWhereIn('team_id', $u->teamIds())));
    }

    public static function canCreate(User $u): bool
    {
        return $u->inTeam('admin', 'operations');
    }

    public function canEdit(User $u): bool
    {
        return static::canCreate($u);
    }
}
