<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->string('record_type')->default('legacy')->index();
            $t->boolean('structured_fields_required')->default(false);
            $t->dateTime('reported_at')->nullable();
            $t->foreignId('reported_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('website_name')->nullable();
            $t->string('domain_name')->nullable();
            $t->string('classification')->nullable()->index();
            $t->string('client_name')->nullable();
            $t->text('actions')->nullable();
        });
        foreach (['developer', 'operations'] as $slug) {
            DB::table('tasks')->whereIn('team_id', DB::table('teams')->where('slug', $slug)->select('id'))->update(['record_type' => $slug]);
        }
        foreach (['pixel' => 'Pixel', 'courier' => 'Courier', 'landing_page' => 'Landing Page'] as $type => $classification) {
            DB::table('tasks')->where('record_type', 'developer')->where('work_type', $type)->update(['classification' => $classification]);
        }
        Schema::table('leads', function (Blueprint $t) {
            $t->string('record_type')->default('lead')->index();
            $t->string('domain_name')->nullable();
            $t->string('deal_status')->nullable()->index();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->dropConstrainedForeignId('submitted_by');
            $t->dropColumn(['record_type', 'domain_name', 'deal_status']);
        });
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reported_by');
            $t->dropColumn(['record_type', 'structured_fields_required', 'reported_at', 'website_name', 'domain_name', 'classification', 'client_name', 'actions']);
        });
    }
};
