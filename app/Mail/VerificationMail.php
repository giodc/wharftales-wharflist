<?php

namespace App\Mail;

use App\Models\Subscriber;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        $settings = app(SettingsService::class);

        return new Envelope(
            subject: 'Confirm your subscription to '.$settings->siteName(),
        );
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class);

        return new Content(
            markdown: 'mail.verification',
            with: [
                'siteName' => $settings->siteName(),
                'verifyUrl' => url('/verify/'.$this->subscriber->verification_token),
            ],
        );
    }
}
