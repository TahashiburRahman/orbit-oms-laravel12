<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetOtp extends Mailable
{
    public function __construct(public string $code, public int $expiresMinutes = 10) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Orbit Office password reset code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-otp');
    }
}
