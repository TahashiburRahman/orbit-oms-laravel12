<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_websites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->string('domain');
            $t->string('normalized_domain');
            $t->string('website_name')->nullable();
            $t->timestamps();
            $t->unique(['client_id', 'normalized_domain']);
        });
        Schema::table('tasks', function (Blueprint $t) {
            $t->foreignId('client_website_id')->nullable()->constrained()->restrictOnDelete();
        });

        // Only explicit client links are trustworthy. Keep the historical text unchanged.
        DB::table('tasks')->whereNotNull('client_id')->whereNotNull('domain_name')->orderBy('id')->chunkById(200, function ($tasks) {
            foreach ($tasks as $task) {
                $domain = trim($task->domain_name);
                $validator = validator(['domain' => $domain], ['domain' => ['required', 'max:255', new \App\Rules\DomainOrUrl]]);
                if ($validator->fails()) {
                    continue;
                }
                $key = \App\Models\ClientWebsite::domainKey($domain);
                DB::table('client_websites')->insertOrIgnore(['client_id' => $task->client_id, 'domain' => $domain, 'normalized_domain' => $key, 'website_name' => $task->website_name, 'created_at' => now(), 'updated_at' => now()]);
                $id = DB::table('client_websites')->where('client_id', $task->client_id)->where('normalized_domain', $key)->value('id');
                DB::table('tasks')->where('id', $task->id)->update(['client_website_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $t) => $t->dropConstrainedForeignId('client_website_id'));
        Schema::dropIfExists('client_websites');
    }
};
