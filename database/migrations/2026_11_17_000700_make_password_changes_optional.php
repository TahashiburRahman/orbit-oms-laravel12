<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $ids = DB::table('users')->where('must_change_password', true)->lockForUpdate()->pluck('id');
            DB::table('users')->whereIn('id', $ids)->update(['must_change_password' => false]);
            foreach ($ids as $id) {
                DB::table('audit_logs')->insert([
                    'user_id' => null,
                    'subject_type' => User::class,
                    'subject_id' => $id,
                    'event' => 'password_change_made_optional',
                    'metadata' => json_encode(['previous_required' => true]),
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // A rollback must not reinstate password-change requirements or alter credentials.
    }
};
