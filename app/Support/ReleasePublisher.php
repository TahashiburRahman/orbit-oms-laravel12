<?php

namespace App\Support;

use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReleasePublisher
{
    public function notifyDue(): int
    {
        $count = 0;
        ReleaseNote::published()->whereNull('notified_at')->orderBy('id')->eachById(function (ReleaseNote $release) use (&$count) {
            $count += $this->notify($release) ? 1 : 0;
        });

        return $count;
    }

    public function notify(ReleaseNote $release): bool
    {
        return DB::transaction(function () use ($release) {
            $locked = ReleaseNote::query()->lockForUpdate()->findOrFail($release->id);
            if ($locked->status !== 'published' || ! $locked->publish_at || $locked->publish_at->isFuture() || $locked->notified_at) {
                return false;
            }

            $teamSlugs = $locked->targetTeams()->pluck('teams.slug')->all();
            Notify::send(User::query()->where('active', true)->inTeams(...$teamSlugs)->get(), 'Software update '.$locked->version.': '.$locked->summary, route('releases.show', $locked));
            $locked->update(['notified_at' => now()]);

            return true;
        });
    }
}
