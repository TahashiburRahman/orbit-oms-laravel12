<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceModel;
use Illuminate\Database\Eloquent\Model;

class WorkspacePolicy
{
    public function before(User $u, string $ability, mixed $record = null): ?bool
    {
        if (! $u->active) {
            return false;
        }
        if ($ability === 'create' && is_string($record) && is_subclass_of($record, WorkspaceModel::class)) {
            return $record::canCreate($u);
        }

        return null;
    }

    public function viewAny(User $u): bool
    {
        return $u->active;
    }

    public function view(User $u, Model $record): bool
    {
        return $record::visibleTo($u)->whereKey($record->getKey())->exists();
    }

    public function create(User $u): bool
    {
        return false;
    }

    public function update(User $u, Model $record): bool
    {
        return $this->view($u, $record) && $record->canEdit($u);
    }

    public function delete(User $u, Model $record): bool
    {
        return false;
    }
}
