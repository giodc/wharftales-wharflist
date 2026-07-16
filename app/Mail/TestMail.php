<?php

namespace App\Mail;

use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        $settings = app(SettingsService::class);

        return new Envelope(
            subject: 'WharfList SMTP test — '.$settings->siteName(),
        );
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class);

        return new Content(
            view: 'mail.test',
            with: [
                'siteName' => $settings->siteName(),
                'logoUrl' => $settings->emailLogoUrl(),
                'logoAlign' => $settings->emailLogoPosition(),
                'footerCompany' => $settings->get('footer_company'),
                'footerAddress' => $settings->get('footer_address'),
                'footerCity' => $settings->get('footer_city'),
                'footerCountry' => $settings->get('footer_country'),
            ],
        );
    }
}
