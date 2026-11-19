<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('company')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('source');
            $t->string('stage')->default('new')->index();
            $t->date('follow_up_at')->nullable()->index();
            $t->text('notes')->nullable();
            $t->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('converted_client_id')->nullable()->unique()->constrained('clients')->restrictOnDelete();
            $t->timestamp('converted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('status')->default('planning')->index();
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('campaign_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('channel');
            $t->text('content');
            $t->dateTime('scheduled_at')->nullable()->index();
            $t->string('status')->default('draft');
            $t->timestamps();
        });
        Schema::create('briefs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('type');
            $t->text('description');
            $t->date('due_date')->nullable()->index();
            $t->string('status')->default('open')->index();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('deliverables', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brief_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->text('notes')->nullable();
            $t->string('status')->default('draft');
            $t->text('review_notes')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['brief_id', 'version']);
        });
        Schema::create('client_queries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('subject');
            $t->text('description');
            $t->string('status')->default('open')->index();
            $t->string('priority')->default('normal');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('query_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_query_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['query_messages', 'client_queries', 'deliverables', 'briefs', 'campaign_posts', 'campaigns', 'leads'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
