<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetLink;
    public $expiresAt;

    /**
     * Create a new message instance.
     */
    public function __construct($resetLink, $expiresAt)
    {
        $this->resetLink = $resetLink;
        $this->expiresAt = $expiresAt;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Password Reset Request - HouseHunt-Kenya')
                    ->markdown('emails.password_reset')
                    ->with([
                        'resetLink' => $this->resetLink,
                        'expiresAt' => $this->expiresAt,
                    ]);
    }
}
