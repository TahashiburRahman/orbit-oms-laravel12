<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Lead extends WorkspaceModel
{
    public const DEAL_STATUSES = ['in_progress' => 'In Progress', 'deal_approved' => 'Deal Approved', 'declined' => 'Declined'];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function canCorrectReferral(User $u): bool
    {
        return $u->active && $this->record_type === 'referral' && $this->created_by === $u->id && $this->deal_status === 'in_progress' && ! $this->converted_client_id;
    }

    protected function casts(): array
    {
        return ['follow_up_at' => 'date', 'converted_at' => 'datetime'];
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
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
