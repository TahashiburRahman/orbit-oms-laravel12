<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function edit(Request $request)
    {
        return response()->view('auth.change-password')->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['bail', 'required', 'string', 'confirmed', Password::min(12), function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The new password must not exceed 72 bytes.');
                }
            }],
        ]);

        try {
            DB::transaction(function () use ($request, $data) {
                $user = User::lockForUpdate()->findOrFail($request->user()->id);
                if (! Hash::check($data['current_password'], $user->password)) {
                    throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
                }
                if (Hash::check($data['password'], $user->password)) {
                    throw ValidationException::withMessages(['password' => 'Choose a new password different from your current password.']);
                }

                $user->forceFill([
                    'password' => Hash::make($data['password']),
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'remember_token' => null,
                ])->save();

                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
                PasswordResetOtp::where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                Audit::record($user, 'password_changed');
                $request->setUserResolver(fn () => $user);
                auth()->setUser($user);
            });
        } catch (QueryException) {
            report(new \RuntimeException('A password change could not be saved. Check database availability.'));

            return back()->withErrors(['password' => 'Your password could not be saved. Please try again or contact your administrator.']);
        }

        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('dashboard')->with('status', 'Your password has been changed.');
    }
}
