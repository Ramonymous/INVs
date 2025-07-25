<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintJobCompleted extends Notification
{
    use Queueable;

    public ?string $downloadUrl;
    public string $message;

    public function __construct(?string $downloadUrl, string $message = 'File PDF Anda telah siap.')
    {
        $this->downloadUrl = $downloadUrl;
        $this->message = $message;
    }

    public function via(object $notifiable): array
    {
        return ['database']; // Kita hanya butuh notifikasi di web untuk saat ini
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'url' => $this->downloadUrl,
        ];
    }
}
