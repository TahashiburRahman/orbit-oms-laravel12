<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;

class EmployeeImporter
{
    public function import(array $rows): array
    {
        return DB::transaction(function () use ($rows) {
            $teams = Team::whereIn('slug', ['admin', 'operations', 'marketing', 'graphics', 'developer'])->get()->keyBy('slug');
            if ($teams->count() !== 5) {
                throw new InvalidArgumentException('Required OMS teams are missing. Apply migrations first.');
            }
            $counts = ['accounts_created' => 0, 'accounts_updated' => 0, 'accounts_unchanged' => 0, 'profiles_created' => 0, 'profiles_updated' => 0, 'memberships_added' => 0, 'existing_passwords_preserved' => 0];
            foreach ($rows as $row) {
                $matches = User::whereRaw('LOWER(TRIM(email)) = ?', [$row['email']])->lockForUpdate()->get();
                if ($matches->count() > 1) {
                    throw new InvalidArgumentException('Existing accounts contain a duplicate normalized email. Resolve duplicates before importing.');
                }
                $user = $matches->first();
                $new = ! $user;
                $beforeIds = $user?->teamIds() ?? [];
                $originalHash = $user?->getRawOriginal('password');
                $user ??= new User;
                $user->fill(['name' => $row['name'], 'email' => $row['email'], 'phone' => $row['phone'], 'team_id' => $teams[$row['primary_slug']]->id]);
                if ($new) {
                    $user->fill(['password' => Hash::make($row['phone']), 'must_change_password' => false, 'active' => true, 'payroll_authorized' => false]);
                    $user->save();
                    $counts['accounts_created']++;
                } elseif ($user->isDirty()) {
                    $user->save();
                    $counts['accounts_updated']++;
                } else {
                    $counts['accounts_unchanged']++;
                }
                if (! $new) {
                    if (! hash_equals($originalHash, $user->getRawOriginal('password'))) {
                        throw new RuntimeException('Existing password preservation check failed.');
                    }
                    $counts['existing_passwords_preserved']++;
                }

                $requiredIds = array_map(fn ($slug) => (int) $teams[$slug]->id, $row['team_slugs']);
                $user->teams()->syncWithoutDetaching($requiredIds);
                $afterIds = $user->teamIds();
                $addedIds = array_values(array_diff($afterIds, $beforeIds));
                $counts['memberships_added'] += count($addedIds);
                if ($addedIds) {
                    Audit::record($user, 'team_memberships_imported', ['added_team_ids' => $addedIds, 'team_ids' => $afterIds]);
                }

                $profile = Employee::where('user_id', $user->id)->lockForUpdate()->first();
                if (! $profile) {
                    $code = 'OMS-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);
                    $candidate = $code;
                    $suffix = 0;
                    while (Employee::where('employee_code', $candidate)->exists()) {
                        $candidate = $code.'-'.(++$suffix);
                    }
                    $profile = Employee::create(['user_id' => $user->id, 'employee_code' => $candidate, 'job_title' => 'Team member', 'joined_on' => today(), 'phone' => $row['phone'], 'base_salary' => '0.00']);
                    $counts['profiles_created']++;
                } elseif ($profile->phone !== $row['phone']) {
                    $profile->update(['phone' => $row['phone']]);
                    $counts['profiles_updated']++;
                }
            }

            return $counts;
        });
    }

    public function verify(array $rows): array
    {
        $counts = ['accounts_verified' => 0, 'required_memberships_verified' => 0, 'profiles_verified' => 0, 'dual_team_accounts_verified' => 0, 'initial_password_hashes_verified' => 0];
        foreach ($rows as $index => $row) {
            $user = User::where('email', $row['email'])->first();
            $line = $index + 2;
            if (! $user || $user->name !== $row['name'] || $user->phone !== $row['phone'] || $user->team?->slug !== $row['primary_slug']) {
                throw new InvalidArgumentException("Row {$line}: account or primary-team verification failed.");
            }
            foreach ($row['team_slugs'] as $slug) {
                if (! $user->teams()->where('slug', $slug)->exists() || ! $user->inTeam($slug)) {
                    throw new InvalidArgumentException("Row {$line}: required membership is missing.");
                }
                $counts['required_memberships_verified']++;
            }
            if (! $user->employee || $user->employee->phone !== $row['phone']) {
                throw new InvalidArgumentException("Row {$line}: employee profile verification failed.");
            }
            if (count($row['team_slugs']) > 1) {
                $counts['dual_team_accounts_verified']++;
            }
            $counts['accounts_verified']++;
            $counts['profiles_verified']++;
            if (Hash::check($row['phone'], $user->password)) {
                $counts['initial_password_hashes_verified']++;
            }
        }

        return $counts;
    }
}
