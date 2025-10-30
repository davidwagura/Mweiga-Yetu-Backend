<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ImageUploadSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $imageUrl;

    public function __construct(string $imageUrl)
    {
        $this->imageUrl = $imageUrl;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'image_upload_success',
            'title' => 'Image Upload Successful',
            'message' => 'Your image was successfully uploaded.',
            'image_url' => $this->imageUrl
        ];
    }
}
