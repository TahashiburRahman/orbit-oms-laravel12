<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('employee_code', 40)->unique();
            $table->string('job_title');
            $table->date('joined_on');
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7)->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('employee_name');
            $table->string('employee_code', 40);
            $table->decimal('base_salary', 12, 2);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2);
            $table->unique(['payroll_run_id', 'employee_id']);
            $table->timestamps();
        });
        Schema::create('payroll_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('base_salary', 12, 2);
            $table->decimal('allowances', 12, 2);
            $table->decimal('deductions', 12, 2);
            $table->decimal('net_pay', 12, 2);
            $table->json('prior_values');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_corrections');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employees');
    }
};
