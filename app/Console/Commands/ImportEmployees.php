<?php

namespace App\Console\Commands;

use App\Support\EmployeeImporter;
use App\Support\EmployeeRoster;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class ImportEmployees extends Command
{
    protected $signature = 'oms:import-employees {file? : Private UTF-8 roster CSV path} {--verify : Verify roster records without making changes}';

    protected $description = 'Import employees transactionally, preserving existing passwords and additional memberships';

    public function handle(EmployeeRoster $reader, EmployeeImporter $importer): int
    {
        try {
            $path = $this->argument('file') ?: base_path('.runtime/imports/employees.csv');
            $rows = $reader->read($path);
            $counts = $this->option('verify') ? $importer->verify($rows) : $importer->import($rows);
            foreach ($counts as $label => $count) {
                $this->line(ucfirst(str_replace('_', ' ', $label)).': '.$count);
            }
            $this->info($this->option('verify') ? 'Roster verification passed.' : 'Import committed. Initial passwords are never printed.');

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
        } catch (Throwable) {
            // Do not report SQL exceptions: their bindings may contain password hashes.
            $this->error('Import/verification failed. Import changes were rolled back. Check the local CSV, database connection, and migrations.');
        }

        return self::FAILURE;
    }
}
