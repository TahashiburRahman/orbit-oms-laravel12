<?php

namespace App\Support;

use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use RuntimeException;
use Throwable;

class OtpMailReadiness
{
    public function ensureAvailable(): void
    {
        if (! $this->available()) {
            throw new RuntimeException('Password reset email is unavailable. Please contact your administrator.');
        }
    }

    /** This checks configuration only; successful delivery still depends on the provider. */
    public function available(): bool
    {
        // Faked tests capture the code in memory without invoking any configured transport.
        if (app()->environment('testing') && Mail::getFacadeRoot() instanceof MailFake) {
            return true;
        }

        try {
            if (! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            $default = config('mail.driver') ?: config('mail.default');

            return is_string($default) && $this->delivers($default, []);
        } catch (Throwable) {
            // Configuration URLs can contain secrets. Never report their parsing exceptions.
            return false;
        }
    }

    private function delivers(string $name, array $visited): bool
    {
        if (in_array($name, $visited, true) || count($visited) >= 16) {
            return false;
        }
        $visited[] = $name;
        $config = config('mail.driver') ? config('mail') : config('mail.mailers.'.$name);
        if (! is_array($config)) {
            return false;
        }
        if (isset($config['url'])) {
            $config = array_merge($config, (new ConfigurationUrlParser)->parseConfiguration($config));
            $config['transport'] = $config['driver'] ?? null;
        }
        $transport = $config['transport'] ?? config('mail.driver');

        // Logging or memory-only fallbacks would disclose OTPs or claim a delivery that never happened.
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $mailers = $config['mailers'] ?? [];
            if (! is_array($mailers) || $mailers === []) {
                return false;
            }
            foreach ($mailers as $mailer) {
                if (! is_string($mailer) || ! $this->delivers($mailer, $visited)) {
                    return false;
                }
            }

            return true;
        }

        if ($transport === 'smtp') {
            $valid = is_string($config['host'] ?? null)
                && trim($config['host']) !== ''
                && filter_var($config['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) !== false
                && in_array($config['scheme'] ?? null, [null, 'smtp', 'smtps'], true);
            if (! $valid) {
                return false;
            }
            if (strtolower(rtrim(trim($config['host']), '.')) === 'smtp.gmail.com') {
                $port = (int) $config['port'];
                $scheme = $config['scheme'] ?? ($port === 465 ? 'smtps' : 'smtp');
                $password = $config['password'] ?? null;

                return filter_var($config['username'] ?? null, FILTER_VALIDATE_EMAIL) !== false
                    && is_string($password)
                    && ! in_array(strtolower(trim($password)), ['', 'null', 'password', 'your-app-password', 'your_app_password', 'app-password', 'app_password', 'change-me', 'changeme'], true)
                    && (($port === 587 && $scheme === 'smtp') || ($port === 465 && $scheme === 'smtps'));
            }

            return true;
        }

        return in_array($transport, ['sendmail', 'mailgun', 'ses', 'ses-v2', 'postmark', 'resend'], true);
    }
}
