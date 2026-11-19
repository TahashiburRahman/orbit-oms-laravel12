<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_opt_in_repeatable_and_does_not_reset_existing_passwords(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->artisan('oms:demo')->expectsQuestion('Password for new demo accounts (at least 12 characters)', 'A-private-demo-password')->expectsQuestion('Confirm demo password', 'A-private-demo-password')->assertSuccessful();
        $hash = User::firstOrFail()->password;
        $counts = [User::count(), Client::count(), Task::count()];
        $this->artisan('oms:demo')->expectsQuestion('Password for new demo accounts (at least 12 characters)', 'A-different-demo-password')->expectsQuestion('Confirm demo password', 'A-different-demo-password')->assertSuccessful();
        $this->assertSame($counts, [User::count(), Client::count(), Task::count()]);
        $this->assertSame($hash, User::firstOrFail()->password);
        $this->assertSame([5, 1, 5], $counts);
    }
}
