<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use App\Support\EmployeeRoster;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class VerifyDashboardAccess extends Command
{
    protected $signature = 'oms:verify-dashboard-access {file? : Private roster CSV path}';

    protected $description = 'Read-only dashboard and optional password settings checks; no accounts or sessions are changed';

    public function handle(EmployeeRoster $reader, Kernel $kernel): int
    {
        $readOnly = false;
        $originalDriver = config('session.driver');
        $originalRequest = app('request');
        $originalUser = Auth::user();

        try {
            $rows = $this->argument('file') ? $reader->read($this->argument('file')) : User::where('active', true)->with(['team', 'teams'])->orderBy('id')->get()->map(fn (User $user) => ['email' => $user->email, 'primary_slug' => $user->team?->slug, 'team_slugs' => $user->teams->pluck('slug')->all()])->all();
            $connection = DB::connection();
            $connection->getPdo();
            // Guard every query made by the HTTP rendering checks before it reaches PDO.
            $connection->beforeExecuting(function (string $query) use (&$readOnly): void {
                if ($readOnly && ! preg_match('/^\s*(select|show)\b/i', $query)) {
                    throw new RuntimeException('Dashboard verification permits read-only SQL queries only.');
                }
            });
            $readOnly = true;

            config(['session.driver' => 'array']);
            app('session')->forgetDrivers();
            app()->forgetInstance('session.store');
            Facade::clearResolvedInstance('session');
            Auth::forgetGuards();

            $counts = ['accounts_checked' => 0, 'required_memberships_checked' => 0, 'password_settings_rendered' => 0, 'assigned_dashboards_rendered' => 0, 'dual_team_accounts_checked' => 0, 'unassigned_dashboards_denied' => 0];
            $allSlugs = Team::orderBy('id')->pluck('slug')->all();
            foreach ($rows as $row) {
                $user = User::where('email', $row['email'])->first();
                if (! $user || ! $user->active || $user->team?->slug !== $row['primary_slug']) {
                    throw new RuntimeException('An imported account is missing, inactive, or has a mismatched primary team.');
                }
                $membershipSlugs = $user->teams()->pluck('teams.slug')->all();
                $assignedSlugs = $user->isSuperAdmin() ? $allSlugs : $membershipSlugs;
                if (array_diff($row['team_slugs'], $membershipSlugs)) {
                    throw new RuntimeException('An imported account is missing a required membership.');
                }
                $counts['accounts_checked']++;
                $counts['required_memberships_checked'] += count($row['team_slugs']);

                $response = $this->request($kernel, $user, route('dashboard'));
                if ($response->getStatusCode() !== 302 || parse_url($response->headers->get('Location'), PHP_URL_PATH) !== parse_url(route('dashboard.team', $row['primary_slug']), PHP_URL_PATH)) {
                    throw new RuntimeException('An account does not default to its primary team dashboard.');
                }

                $response = $this->request($kernel, $user, route('password.change'));
                if ($response->getStatusCode() !== 200) {
                    throw new RuntimeException('An account could not open optional password settings.');
                }
                $counts['password_settings_rendered']++;

                foreach ($assignedSlugs as $slug) {
                    $response = $this->request($kernel, $user, route('dashboard.team', $slug));
                    if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->getContent(), 'Your task board')) {
                        throw new RuntimeException('An assigned dashboard did not render successfully.');
                    }
                    $counts['assigned_dashboards_rendered']++;
                }
                if (count($assignedSlugs) > 1) {
                    $counts['dual_team_accounts_checked']++;
                }
                foreach (array_diff($allSlugs, $assignedSlugs) as $slug) {
                    $response = $this->request($kernel, $user, route('dashboard.team', $slug));
                    if ($response->getStatusCode() !== 403) {
                        throw new RuntimeException('An unassigned dashboard was not denied.');
                    }
                    $counts['unassigned_dashboards_denied']++;
                }
            }

            foreach ($counts as $label => $count) {
                $this->line(ucfirst(str_replace('_', ' ', $label)).': '.$count);
            }
            $this->info('Dashboard access and optional password settings verified using read-only internal requests. Passwords, profiles, memberships, audit records, and database sessions were not changed.');

            return self::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        } catch (Throwable) {
            // Do not print exceptions, HTTP bodies, account identifiers, or SQL bindings.
            $this->error('Dashboard access verification failed. Check the roster import, assigned memberships, password settings, and application views. No account changes were made.');
        } finally {
            $readOnly = false;
            config(['session.driver' => $originalDriver]);
            app('session')->forgetDrivers();
            app()->forgetInstance('session.store');
            Facade::clearResolvedInstance('session');
            app()->instance('request', $originalRequest);
            Facade::clearResolvedInstance('request');
            Auth::forgetGuards();
            if ($originalUser) {
                Auth::setUser($originalUser);
            }
        }

        return self::FAILURE;
    }

    private function request(Kernel $kernel, User $user, string $url)
    {
        Auth::forgetGuards();
        // setUser authenticates this process only; it never creates a session login or remember token.
        Auth::setUser($user);
        $request = Request::create($url, 'GET');
        $request->setUserResolver(fn () => $user);

        return $kernel->handle($request);
    }
}
