<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetOtp;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use App\Support\BangladeshPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private function admin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function index(Request $r)
    {
        $this->admin();
        $users = User::with(['team', 'teams'])->when($r->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->search.'%')->orWhere('email', 'like', '%'.$r->search.'%')))->orderBy('name')->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $this->admin();

        return view('users.form', ['user' => new User(['active' => true]), 'teams' => Team::all()]);
    }

    public function store(Request $r)
    {
        $this->admin();
        $data = $this->data($r);
        $membershipIds = $this->requestedMemberships($r, $data);
        $data['payroll_authorized'] = $this->payrollAuthorization($r, $membershipIds);
        $user = DB::transaction(function () use ($data, $membershipIds) {
            $user = new User(collect($data)->except('is_superadmin')->all());
            $user->forceFill(['is_superadmin' => $data['is_superadmin'] ?? false])->save();
            $this->syncMemberships($user, $membershipIds, []);

            return $user;
        });

        return redirect()->route('users.show', $user)->with('status', 'Employee account created.');
    }

    public function show(User $user)
    {
        $this->admin();
        $user->load(['team', 'teams']);

        return view('users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->admin();
        abort_if($user->is_superadmin && ! auth()->user()->isSuperAdmin(), 403);
        $user->load(['team', 'teams']);

        return view('users.form', ['user' => $user, 'teams' => Team::all()]);
    }

    public function update(Request $r, User $user)
    {
        $this->admin();
        abort_if($user->is_superadmin && ! $r->user()->isSuperAdmin(), 403);
        $data = $this->data($r, $user);
        $membershipIds = $this->requestedMemberships($r, $data, $user);
        $data['payroll_authorized'] = $this->payrollAuthorization($r, $membershipIds);
        DB::transaction(function () use ($user, $data, $membershipIds) {
            $superadmins = User::where('is_superadmin', true)->where('active', true)->lockForUpdate()->get();
            $locked = User::lockForUpdate()->findOrFail($user->id);
            abort_if($locked->is_superadmin && ! auth()->user()->isSuperAdmin(), 403);
            if ($locked->is_superadmin && (! $data['active'] || ! ($data['is_superadmin'] ?? $locked->is_superadmin)) && $superadmins->count() <= 1) {
                throw ValidationException::withMessages(['is_superadmin' => 'Keep at least one active superadmin.']);
            }
            $admins = User::inTeams('admin')->where('active', true)->lockForUpdate()->get();
            $adminTeamId = Team::where('slug', 'admin')->value('id');
            $leaving = ! $data['active'] || ! in_array((int) $adminTeamId, $membershipIds, true);
            $retainsSuperadmin = $data['active'] && ($data['is_superadmin'] ?? $locked->is_superadmin);
            if ($admins->contains('id', $user->id) && $leaving && $admins->count() <= 1 && ! $retainsSuperadmin && ! $superadmins->where('id', '!=', $user->id)->count()) {
                throw ValidationException::withMessages(['active' => 'Keep at least one active administrator.']);
            }
            if ($user->id === auth()->id() && ! $data['active']) {
                throw ValidationException::withMessages(['active' => 'You cannot deactivate your own account.']);
            }
            $passwordChanged = isset($data['password']);
            $previousMemberships = $user->teamIds();
            $role = $data['is_superadmin'] ?? null;
            unset($data['is_superadmin']);
            $user->fill($data);
            if ($role !== null) $user->forceFill(['is_superadmin' => $role]);
            $user->save();
            $this->syncMemberships($user, $membershipIds, $previousMemberships);
            if (array_key_exists('phone', $data) && $user->employee) {
                $user->employee->update(['phone' => $data['phone']]);
            }
            if ($passwordChanged || ! $user->active || $user->wasChanged('is_superadmin')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                PasswordResetOtp::where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                $user->forceFill(['remember_token' => null])->save();
            }
            if ($passwordChanged) {
                Audit::record($user, 'password_reset');
            }
        });

        return redirect()->route('users.show', $user)->with('status', 'Account updated.');
    }

    private function data(Request $r, ?User $user = null): array
    {
        if ($r->has('is_superadmin') && ! $r->user()->isSuperAdmin()) {
            abort(403);
        }
        if (is_string($r->input('email'))) {
            $r->merge(['email' => mb_strtolower(trim($r->input('email')))]);
        }
        $data = $r->validate(['name' => 'required|string|max:160', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)], 'phone' => 'sometimes|nullable|string|max:40', 'team_id' => 'required|integer|exists:teams,id', 'team_ids' => 'sometimes|array', 'team_ids.*' => 'integer|distinct|exists:teams,id', 'active' => 'sometimes|boolean', 'payroll_authorized' => 'sometimes|boolean', 'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)]]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        unset($data['team_ids']);
        if (! empty($data['phone'])) {
            try {
                $data['phone'] = BangladeshPhone::normalize($data['phone']);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['phone' => 'Enter a valid Bangladesh mobile number.']);
            }
        }
        $data['active'] = $r->boolean('active');
        if ($r->user()->isSuperAdmin() && $r->has('is_superadmin')) {
            $r->validate(['is_superadmin' => 'required|boolean']);
            $data['is_superadmin'] = $r->boolean('is_superadmin');
        }

        return $data;
    }

    private function requestedMemberships(Request $request, array $data, ?User $user = null): array
    {
        $primaryId = (int) $data['team_id'];
        if ($request->has('team_ids') || $request->boolean('memberships_present')) {
            $memberships = $request->input('team_ids', []);
        } else {
            $memberships = $user ? array_values(array_diff($user->teamIds(), [(int) $user->team_id])) : [];
        }

        $memberships = array_values(array_unique(array_map('intval', [...$memberships, $primaryId])));
        sort($memberships);

        return $memberships;
    }

    private function payrollAuthorization(Request $request, array $memberships): bool
    {
        $operationsId = (int) Team::where('slug', 'operations')->value('id');

        return $request->boolean('payroll_authorized') && in_array($operationsId, $memberships, true);
    }

    private function syncMemberships(User $user, array $memberships, array $previous): void
    {
        sort($previous);
        $user->teams()->sync($memberships);
        $user->unsetRelation('team')->unsetRelation('teams');
        if ($previous !== $memberships) {
            Audit::record($user, 'team_memberships_changed', ['team_ids' => $memberships, 'previous' => $previous]);
        }
    }

    public function destroy(Request $r, User $user)
    {
        app(\App\Support\AccountRemoval::class)->remove($r->user(), $user);

        return redirect()->route('users.index')->with('status', 'Account and employee profile deleted.');
    }
}
