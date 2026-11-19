<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Task extends WorkspaceModel
{
    public const CLASSIFICATIONS = ['Support', 'Site Delivery', 'Landing Page', 'Meetings', 'Theme Customize', 'Pixel', 'Courier'];

    public const WORK_STATUSES = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed'];

    public function scopeWithWorkStatus(Builder $q, string $status): Builder
    {
        return match ($status) {
            'pending' => $q->where('status', 'open'),'completed' => $q->whereIn('status', ['completed', 'verified']),'in_progress' => $q->whereIn('status', ['in_progress', 'blocked', 'submitted']),default => $q->where('status', $status)
        };
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function getWorkStatusAttribute(): string
    {
        return match ($this->status) {
            'verified','completed' => 'completed','open' => 'pending','in_progress','blocked','submitted' => 'in_progress',default => $this->status ?? 'pending'
        };
    }

    public function getWorkStatusOptionsAttribute(): array
    {
        $options = self::WORK_STATUSES;
        if ($this->exists && ! $this->structured_fields_required && ! array_key_exists($this->work_status, $options)) {
            $options[$this->work_status] = 'Legacy: '.$this->status;
        }

        return $options;
    }

    protected function casts(): array
    {
        return ['due_date' => 'date', 'verified_at' => 'datetime', 'reported_at' => 'datetime', 'structured_fields_required' => 'boolean'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function clientWebsite()
    {
        return $this->belongsTo(ClientWebsite::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function scopeVisibleTo(Builder $q, User $u): void
    {
        if ($u->isAdmin()) {
            return;
        }
        $q->where(fn ($q) => $q->whereIn('team_id', $u->teamIds())->orWhere('assigned_to', $u->id)->orWhere('created_by', $u->id));
    }

    public static function canCreate(User $u): bool
    {
        return $u->active && ($u->isSuperAdmin() || count($u->teamIds()) > 0);
    }

    public function canEdit(User $u): bool
    {
        if ($u->isAdmin()) {
            return true;
        }
        if (! static::visibleTo($u)->whereKey($this->id)->exists()) {
            return false;
        }
        if ($this->isDevelopment()) {
            return $u->inTeam('operations') || ($u->inTeam('developer') && ($this->assigned_to === $u->id || ! $this->assigned_to));
        }

        return $this->assigned_to === $u->id || $this->created_by === $u->id || in_array($this->team_id, $u->teamIds(), true);
    }

    public function isDevelopment(): bool
    {
        return $this->work_type !== 'general';
    }
}
