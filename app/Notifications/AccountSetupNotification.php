<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSetupNotification extends Notification
{
    use Queueable;

    protected string $setupUrl;
    protected ?string $temporaryPassword;

    public function __construct(string $setupUrl, ?string $temporaryPassword = null)
    {
        $this->setupUrl = $setupUrl;
        $this->temporaryPassword = $temporaryPassword;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Set Up Your Account')
            ->view('emails.account-setup', [
                'user' => $notifiable,
                'setupUrl' => $this->setupUrl,
                'temporaryPassword' => $this->temporaryPassword,
                'appName' => config('app.name', 'AsyncAfrica'),
            ]);
    }
}