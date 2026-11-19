<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class SeedDemo extends Command
{
    protected $signature = 'oms:demo';

    protected $description = 'Seed repeatable Bangladeshi office examples; existing records/passwords are preserved';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo data is available only in local/testing environments.');

            return self::FAILURE;
        }
        $password = $this->secret('Password for new demo accounts (at least 12 characters)');
        $confirmation = $this->secret('Confirm demo password');
        $v = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], ['password' => ['required', 'confirmed', Password::min(12)]]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        app()->instance('oms.demo.password', $password);
        try {
            $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        } finally {
            app()->forgetInstance('oms.demo.password');
        }
        $this->info('Demo data ready. Accounts: admin, operations, marketing, graphics, developer @demo.example.test. Existing passwords were preserved.');

        return self::SUCCESS;
    }
}
