<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSetupNotification extends Notification
{
    use Queueable;

    protected string $setupUrl;

    public function __construct(string $setupUrl)
    {
        $this->setupUrl = $setupUrl;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Set Up Your Password')
            ->view('emails.reset-password-link', [
                'user' => $notifiable,
                'resetUrl' => $this->setupUrl,
                'appName' => config('app.name', 'AsyncAfrica'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'setup_url' => $this->setupUrl,
        ];
    }
}