<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $type;

    /**
     * Create a new message instance.
     */
    public function __construct(string $otpCode, string $type = 'registration')
    {
        $this->otpCode = $otpCode;
        $this->type = $type;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        if ($this->type === 'password_reset') {
            $subject = 'Kode OTP Reset Password - System Pakar';
        } elseif ($this->type === 'email_change') {
            $subject = 'Kode OTP Ubah Email - System Pakar';
        } else {
            $subject = 'Kode OTP Registrasi - System Pakar';
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'otpCode' => $this->otpCode,
                'type' => $this->type,
            ],
        );
    }
}
