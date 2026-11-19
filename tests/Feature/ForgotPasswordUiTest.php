<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgotPasswordUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_and_request_page_expose_the_email_code_recovery_flow(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Forgot password?')->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee('Work email')
            ->assertSee('Email reset code')
            ->assertSee(route('password.email'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('Back to sign in');
    }

    public function test_reset_form_has_one_time_code_controls_and_recovery_navigation(): void
    {
        $this->withSession(['reset_email' => 'employee@example.test', 'reset_challenge' => 'ui-fixture-challenge'])
            ->get(route('password.reset'))->assertOk()
            ->assertSee('Reset your password.')
            ->assertSee('email code with 6 digits')
            ->assertSee('10 minutes')
            ->assertSee('name="email" value="employee@example.test" readonly', false)
            ->assertSee('name="otp" value=""', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('pattern="[0-9]{6}"', false)
            ->assertSee('name="password" type="password" value=""', false)
            ->assertSee('name="password_confirmation" type="password" value=""', false)
            ->assertSee('at least 12 characters')
            ->assertSee('Resend code')
            ->assertSee('Change email')
            ->assertSee('Back to sign in')
            ->assertSee(route('password.reset.store'), false);
    }

    public function test_mail_contains_the_code_expiry_and_expected_subject(): void
    {
        $mail = new PasswordResetOtp('123456', 10);
        $this->assertSame('Orbit Office password reset code', $mail->envelope()->subject);
        $this->assertSame('123456', $mail->code);
        $html = $mail->render();
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('10 minutes', $html);
        $this->assertStringContainsString('Requesting a new code replaces the previous one.', $html);
        $this->assertStringContainsString('Do not share this code with anyone.', $html);
    }
}
