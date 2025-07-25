<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Models\InvRequest;
use Illuminate\Support\Str;

class RequestPushed extends Notification
{
    use Queueable;

    // Change the properties to hold the data, not the model
    public string $notificationId;
    public int $itemCount;
    public string $url;

    /**
     * Create a new notification instance.
     * Pass the data directly, not the entire model.
     */
    public function __construct(string $notificationId, int $itemCount, string $url)
    {
        $this->notificationId = $notificationId;
        $this->itemCount = $itemCount;
        $this->url = $url;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $body = "New request with {$this->itemCount} item(s).";

        return (new WebPushMessage)
            ->title('New Part Request!')
            ->icon('/icon.png')
            ->body($body)
            // Use the data passed into the constructor
            ->data(['id' => $this->notificationId, 'url' => $this->url]);
    }
}
