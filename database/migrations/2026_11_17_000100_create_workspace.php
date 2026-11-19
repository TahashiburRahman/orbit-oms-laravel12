<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->timestamps();
        });
        foreach (['admin' => 'Admin', 'operations' => 'Operations', 'marketing' => 'Digital Marketing', 'graphics' => 'Graphics', 'developer' => 'Developer'] as $slug => $name) {
            DB::table('teams')->insert(['slug' => $slug, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('active')->default(true)->index();
            $t->boolean('payroll_authorized')->default(false);
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('company')->nullable()->index();
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('status')->default('active')->index();
            $t->text('notes')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('status')->default('active')->index();
            $t->date('due_date')->nullable()->index();
            $t->text('notes')->nullable();
            $t->foreignId('team_id')->constrained()->restrictOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('project_user', function (Blueprint $t) {
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['project_id', 'user_id']);
        });
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('team_id')->constrained()->restrictOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('work_type')->default('general')->index();
            $t->string('priority')->default('normal');
            $t->string('status')->default('open')->index();
            $t->date('due_date')->nullable()->index();
            $t->text('blocker')->nullable();
            $t->text('handover_notes')->nullable();
            $t->text('configuration_summary')->nullable();
            $t->text('verification_results')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->index(['team_id', 'status']);
            $t->index(['assigned_to', 'due_date']);
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->morphs('commentable');
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('event');
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'audit_logs', 'comments', 'tasks', 'project_user', 'projects', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('team_id');
            $t->dropColumn(['active', 'payroll_authorized']);
        });
        Schema::dropIfExists('teams');
    }
};
