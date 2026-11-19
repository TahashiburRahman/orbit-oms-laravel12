<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_cannot_be_rewritten_even_with_a_query_builder_update(): void
    {
        User::factory()->create(['team_id' => Team::where('slug', 'admin')->value('id')]);
        $id = AuditLog::firstOrFail()->id;
        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $id)->update(['event' => 'rewritten']);
    }
}
