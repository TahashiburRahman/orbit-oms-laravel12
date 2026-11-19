<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordSettingsUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_every_team_dashboard_links_to_optional_password_settings(): void
    {
        foreach (['admin', 'operations', 'marketing', 'graphics', 'developer'] as $slug) {
            $user = User::factory()->create([
                'team_id' => Team::where('slug', $slug)->value('id'),
                'must_change_password' => false,
            ]);
            $response = $this->actingAs($user)->get(route('dashboard.team', $slug))
                ->assertOk()
                ->assertSee('Change password')
                ->assertSee('Account menu for '.$user->name);
            $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), route('password.change')));
        }
    }

    public function test_password_page_is_optional_account_settings_for_both_legacy_flag_states(): void
    {
        foreach ([false, true] as $legacyFlag) {
            $user = User::factory()->create([
                'team_id' => Team::where('slug', 'marketing')->value('id'),
                'must_change_password' => $legacyFlag,
            ]);
            $page = $this->actingAs($user)->get(route('password.change'))
                ->assertOk()
                ->assertSee('Main navigation')
                ->assertSee('Current password')
                ->assertSee('New password')
                ->assertSee('Confirm new password')
                ->assertSee('Save password')
                ->assertSee('Cancel')
                ->assertSee('Back to dashboard')
                ->assertSee('at least 12 characters')
                ->assertSee(route('password.update'), false)
                ->assertSee('name="_token"', false)
                ->assertSee('value="put"', false)
                ->assertDontSee('FINISH ACCOUNT SETUP')
                ->assertDontSee('before opening the company workspace')
                ->assertDontSee('Current temporary password');
            $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
            $this->get(route('dashboard'))->assertRedirect(route('dashboard.team', 'marketing'));
            $this->assertSame($legacyFlag, $user->fresh()->must_change_password);
        }
    }
}
