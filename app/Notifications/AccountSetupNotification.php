<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AccountSetupNotification extends Notification
{
    use Queueable;

    protected string $token;
    protected string $email;

    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(
            env('FRONTEND_URL', config('app.url')),
            '/'
        );

        $resetUrl = $frontendUrl .
            '/reset-password?token=' . $this->token .
            '&email=' . urlencode($this->email);

        return (new MailMessage)
            ->subject('Set Up Your AsyncAfrica Account')
            ->view('emails.account-setup', [
                'user' => $notifiable,
                'resetUrl' => $resetUrl,
                'appName' => config('app.name', 'AsyncAfrica'),
            ]);
    }
}