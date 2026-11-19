<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'oms:admin {--name= : Administrator name} {--email= : Administrator email}';

    protected $description = 'Create an administrator with a hidden password prompt';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->secret('Password (at least 12 characters)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], ['name' => 'required|string|max:160', 'email' => 'required|email|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        DB::transaction(function () use ($name, $email, $password) {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'team_id' => Team::where('slug', 'admin')->firstOrFail()->id, 'active' => true]);
            Audit::record($user, 'admin_created_locally');
        });
        $this->info('Administrator created. Sign in at '.config('app.url').'/login');

        return self::SUCCESS;
    }
}
