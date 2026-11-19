<?php

namespace App\Console\Commands;

use App\Support\ReleasePublisher;
use Illuminate\Console\Command;

class PublishReleaseNotifications extends Command
{
    protected $signature = 'releases:notify';

    protected $description = 'Notify target teams about software updates whose publication date has arrived';

    public function handle(ReleasePublisher $publisher): int
    {
        $this->info($publisher->notifyDue().' release notification batches sent.');

        return self::SUCCESS;
    }
}
