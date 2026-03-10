<?php

namespace App\Notifications;

use App\Models\ProgramApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReceivedNotification extends Notification
{
    use Queueable;

    protected ProgramApplication $application;

    public function __construct(ProgramApplication $application)
    {
        $this->application = $application;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fullName = trim(
            ($this->application->first_name ?? '') . ' ' . ($this->application->last_name ?? '')
        );

        $programTitle = $this->application->program_title
            ?: optional($this->application->program)->name
            ?: 'the internship program';

        $shiftName = $this->application->shift_name ?: 'To be communicated';

        return (new MailMessage)
            ->subject('Your Internship Application Has Been Received')
            ->view('emails.application-received', [
                'fullName' => $fullName,
                'programTitle' => $programTitle,
                'shiftName' => $shiftName,
                'application' => $this->application,
                'companyName' => config('app.name', 'AsyncAfrica'),
                'companyEmail' => env('MAIL_FROM_ADDRESS', 'async@africa.com'),
                'companyWebsite' => env('APP_URL', 'https://asyncafrica.com'),
            ]);
    }
}