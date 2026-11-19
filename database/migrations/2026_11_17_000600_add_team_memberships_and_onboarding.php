<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'team_id']);
        });

        DB::table('users')->whereNotNull('team_id')->orderBy('id')->chunkById(100, function ($users) {
            $now = now();
            foreach ($users as $user) {
                DB::table('team_user')->insertOrIgnore([
                    'user_id' => $user->id,
                    'team_id' => $user->team_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['phone', 'must_change_password', 'password_changed_at']));
    }
};
