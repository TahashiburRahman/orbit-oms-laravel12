<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Deliverable extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function brief()
    {
        return $this->belongsTo(Brief::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeVisibleTo(Builder $q, User $u): void
    {
        $q->whereHas('brief', fn ($q) => $q->visibleTo($u));
    }

    public static function canCreate(User $u): bool
    {
        return $u->active && $u->inTeam('admin', 'graphics');
    }

    public function canEdit(User $u): bool
    {
        return false;
    }
}
