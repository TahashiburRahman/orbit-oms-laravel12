<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountRemoval
{
    public function remove(User $actor, User $user): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        try {
            DB::transaction(function () use ($actor, $user) {
                User::where('is_superadmin', true)->where('active', true)->lockForUpdate()->get();
                $user = User::lockForUpdate()->findOrFail($user->id);
                if ($user->id === $actor->id) {
                    throw ValidationException::withMessages(['account' => 'You cannot delete your own account.']);
                }
                if ($user->isSuperAdmin() && User::where('is_superadmin', true)->where('active', true)->count() <= 1) {
                    throw ValidationException::withMessages(['account' => 'Keep at least one active superadmin.']);
                }
                if ($user->employee) $this->deleteProfile($user->employee);
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                DB::table('password_reset_otps')->where('user_id', $user->id)->delete();
                DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $user->id)->delete();
                $user->teams()->detach();
                $user->projects()->detach();
                Audit::record($user, 'account_deleted', ['employee_profile_removed' => true]);
                // Restrictive foreign keys preserve authored records and immutable audit actors.
                $user->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['account' => 'This account is referenced by work or audit history. Reassign removable work or deactivate the account to preserve its history.']);
        }
    }

    public function removeProfile(User $actor, Employee $employee): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        DB::transaction(fn () => $this->deleteProfile($employee));
    }

    private function deleteProfile(Employee $employee): void
    {
        $employee = Employee::lockForUpdate()->findOrFail($employee->id);
        $runIds = PayrollEntry::where('employee_id', $employee->id)->pluck('payroll_run_id');
        PayrollRun::whereIn('id', $runIds)->orderBy('id')->lockForUpdate()->get();
        $entries = PayrollEntry::where('employee_id', $employee->id)->with('run')->lockForUpdate()->get();
        if ($entries->contains(fn ($entry) => $entry->run->status !== 'draft')) {
            throw ValidationException::withMessages(['employee' => 'Submitted, approved or paid payroll preserves this employee profile. Deactivate the login account instead.']);
        }
        foreach ($entries as $entry) {
            if ($entry->corrections()->exists()) {
                throw ValidationException::withMessages(['employee' => 'Payroll correction history preserves this employee profile.']);
            }
            Audit::record($entry, 'draft_entry_deleted_with_employee');
            $entry->delete();
        }
        Audit::record($employee, 'employee_deleted');
        $employee->delete();
    }
}
