<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class QueryMessage extends WorkspaceModel
{
    public function clientQuery()
    {
        return $this->belongsTo(ClientQuery::class, 'client_query_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeVisibleTo(Builder $q, User $u): void
    {
        $q->whereHas('clientQuery', fn ($q) => $q->visibleTo($u));
    }

    public static function canCreate(User $u): bool
    {
        return $u->active;
    }

    public function canEdit(User $u): bool
    {
        return false;
    }
}
