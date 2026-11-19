<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckWorkspace extends Command
{
    protected $signature = 'oms:check';

    protected $description = 'Verify the configured database and application prerequisites without printing credentials';

    public function handle(): int
    {
        $this->info('Laravel '.app()->version().' · PHP '.PHP_VERSION.' · '.config('app.timezone'));
        try {
            DB::connection()->getPdo();
            $this->info('Configured '.DB::getDriverName().' connection: OK');
            $tables = ['users', 'teams', 'team_user', 'clients', 'client_websites', 'projects', 'tasks', 'leads', 'campaigns', 'campaign_posts', 'briefs', 'deliverables', 'client_queries', 'employees', 'payroll_runs', 'payroll_entries', 'payroll_corrections', 'release_notes', 'notifications', 'audit_logs', 'password_reset_otps'];
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    $this->error('Missing table: '.$table);

                    return self::FAILURE;
                }
            }
            $this->info('All '.count($tables).' required module tables exist. Applied migrations: '.DB::table('migrations')->count());
            $this->info('Primary teams: '.DB::table('teams')->count().' · Active accounts: '.DB::table('users')->where('active', true)->count());
        } catch (\Throwable $e) {
            $this->error('Database verification failed. Check local .env and XAMPP MySQL. No credentials have been printed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
