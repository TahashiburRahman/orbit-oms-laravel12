<?php

namespace Tests\Feature;

use App\Support\OtpMailReadiness;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpMailReadinessTest extends TestCase
{
    public function test_log_array_and_non_delivering_fallback_mailers_are_rejected_without_sending(): void
    {
        $guard = new OtpMailReadiness;
        config(['mail.from.address' => 'sender@example.test']);
        foreach (['log', 'array', 'failover'] as $mailer) {
            config(['mail.default' => $mailer]);
            $this->assertFalse($guard->available());
        }
        config(['mail.default' => 'nested', 'mail.mailers.nested' => ['transport' => 'roundrobin', 'mailers' => ['smtp', 'failover']]]);
        $this->assertFalse($guard->available());
    }

    public function test_usable_smtp_configuration_and_delivery_only_fallbacks_are_accepted_without_connecting(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'sender@example.test',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'smtp.example.test', 'port' => 587, 'scheme' => 'smtp'],
        ]);
        $guard = new OtpMailReadiness;
        $this->assertTrue($guard->available());
        config(['mail.default' => 'delivery-fallback', 'mail.mailers.delivery-fallback' => ['transport' => 'failover', 'mailers' => ['smtp', 'postmark']]]);
        $this->assertTrue($guard->available());
    }

    public function test_mail_url_cannot_override_a_delivering_transport_to_a_logging_transport(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'sender@example.test',
            'mail.mailers.smtp.url' => 'log://localhost',
        ]);
        $this->assertFalse((new OtpMailReadiness)->available());
    }

    public function test_cycles_missing_mailers_and_invalid_smtp_configuration_fail_closed(): void
    {
        config(['mail.from.address' => 'sender@example.test', 'mail.default' => 'cycle', 'mail.mailers.cycle' => ['transport' => 'failover', 'mailers' => ['cycle']]]);
        $guard = new OtpMailReadiness;
        $this->assertFalse($guard->available());
        config(['mail.default' => 'missing']);
        $this->assertFalse($guard->available());
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '', 'mail.mailers.smtp.url' => null]);
        $this->assertFalse($guard->available());
    }

    public function test_test_mail_fake_is_accepted_but_cannot_bypass_a_non_testing_environment(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $guard = new OtpMailReadiness;
        $this->assertTrue($guard->available());
        $this->app->instance('env', 'local');
        $this->assertFalse($guard->available());
    }

    public function test_configuration_failure_raises_a_safe_message_without_transport_details(): void
    {
        config(['mail.default' => 'log']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Password reset email is unavailable. Please contact your administrator.');
        (new OtpMailReadiness)->ensureAvailable();
    }

    public function test_gmail_requires_a_real_email_username_and_non_placeholder_app_password(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'sender@example.test',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'smtp.gmail.com', 'port' => 587, 'scheme' => 'smtp', 'username' => 'reset-fixture@example.test'],
        ]);
        $guard = new OtpMailReadiness;
        foreach ([null, '', ' ', 'null', 'your-app-password'] as $password) {
            config(['mail.mailers.smtp.password' => $password]);
            $this->assertFalse($guard->available());
        }
        config(['mail.mailers.smtp.password' => 'abcdefghijklmnop']);
        $this->assertTrue($guard->available());
        config(['mail.mailers.smtp.username' => 'not-an-email']);
        $this->assertFalse($guard->available());
    }

    public function test_gmail_requires_a_matching_tls_port_and_scheme(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'sender@example.test',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'smtp.gmail.com', 'port' => 587, 'scheme' => 'smtps', 'username' => 'reset-fixture@example.test', 'password' => 'abcdefghijklmnop'],
        ]);
        $guard = new OtpMailReadiness;
        $this->assertFalse($guard->available());
        config(['mail.mailers.smtp.port' => 465]);
        $this->assertTrue($guard->available());
        config(['mail.mailers.smtp.port' => 2525]);
        $this->assertFalse($guard->available());
    }
}
