<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DomainOrUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/[\s\x00-\x1f]/', $value)) {
            $fail('Enter a domain name or an HTTP/HTTPS website link.');

            return;
        }
        $url = preg_match('~^https?://~i', $value) ? $value : 'https://'.$value;
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || ! filter_var($url, FILTER_VALIDATE_URL) || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($host, '.')) {
            $fail('Enter a domain name or an HTTP/HTTPS website link.');
        }
    }
}
