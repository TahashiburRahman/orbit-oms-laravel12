<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdmin extends Command
{
    protected $signature = 'oms:superadmin {--name= : Account name} {--email= : Login email}';

    protected $description = 'Create a new superadmin using hidden password prompts, with no default credentials';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = mb_strtolower(trim($this->option('email') ?: $this->ask('Email')));
        $data = ['name' => $name, 'email' => $email, 'password' => $this->secret('Password (at least 12 characters)'), 'password_confirmation' => $this->secret('Confirm password')];
        $validator = Validator::make($data, ['name' => 'required|string|max:160', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)]]);
        if ($validator->fails()) {
            $this->error('Account not created. Use a new valid email and matching passwords of at least 12 characters.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $team = Team::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
            $user = new User(collect($data)->except('password_confirmation')->all() + ['team_id' => $team->id, 'active' => true]);
            $user->forceFill(['is_superadmin' => true])->save();
            Audit::record($user, 'superadmin_created_locally');
        });
        $this->info('Superadmin created. Sign in at '.rtrim(config('app.url'), '/').'/login');

        return self::SUCCESS;
    }
}
