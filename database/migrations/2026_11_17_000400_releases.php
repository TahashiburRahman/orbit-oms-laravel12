<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_notes', function (Blueprint $table) {
            $table->id();
            $table->string('version', 60)->unique();
            $table->string('summary');
            $table->text('details');
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('publish_at')->nullable()->index();
            $table->dateTime('notified_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('release_note_team', function (Blueprint $table) {
            $table->foreignId('release_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->primary(['release_note_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_note_team');
        Schema::dropIfExists('release_notes');
    }
};
