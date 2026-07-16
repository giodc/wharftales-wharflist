<?php

namespace App\Mail;

use App\Models\EmailCampaign;
use App\Models\Subscriber;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmailCampaign $campaign,
        public Subscriber $subscriber,
        public string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
        );
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class);
        $body = str_replace('{{UNSUBSCRIBE_LINK}}', $this->unsubscribeUrl, $this->campaign->body);

        return new Content(
            view: 'mail.campaign',
            with: [
                'bodyHtml' => $body,
                'siteName' => $settings->siteName(),
                'logoUrl' => $settings->emailLogoUrl(),
                'logoAlign' => $settings->emailLogoPosition(),
                'footerCompany' => $settings->get('footer_company'),
                'footerAddress' => $settings->get('footer_address'),
                'footerCity' => $settings->get('footer_city'),
                'footerCountry' => $settings->get('footer_country'),
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }
}
