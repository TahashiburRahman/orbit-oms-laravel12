<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared('CREATE TRIGGER oms_audit_no_'.strtolower($operation)." BEFORE {$operation} ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit entries are append-only'");
            }
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared('CREATE TRIGGER oms_audit_no_'.strtolower($operation)." BEFORE {$operation} ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit entries are append-only'); END");
            }
        }
    }

    public function down(): void
    {
        foreach (['update', 'delete'] as $operation) {
            DB::unprepared("DROP TRIGGER IF EXISTS oms_audit_no_{$operation}");
        }
    }
};
