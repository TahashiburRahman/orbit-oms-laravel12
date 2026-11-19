<?php

namespace App\Support;

use App\Notifications\WorkspaceNotification;

class Notify
{
    public static function send(iterable $users, string $title, string $url): void
    {
        $sent = [];
        foreach ($users as $user) {
            if (! $user || ! $user->active || in_array($user->id, $sent, true)) {
                continue;
            }
            $user->notify(new WorkspaceNotification($title, $url));
            $sent[] = $user->id;
        }
    }
}
