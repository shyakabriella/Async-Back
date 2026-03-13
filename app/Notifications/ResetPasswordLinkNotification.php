<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLinkNotification extends Notification
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
            '/reset-password?token=' . urlencode($this->token) .
            '&email=' . urlencode($this->email);

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->view('emails.reset-password-link', [
                'user' => $notifiable,
                'resetUrl' => $resetUrl,
                'appName' => config('app.name', 'AsyncAfrica'),
            ]);
    }
}