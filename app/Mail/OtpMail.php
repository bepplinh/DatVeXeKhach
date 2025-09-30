<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $phone;
    public $type;

    /**
     * Create a new message instance.
     */
    public function __construct(string $code, ?string $phone = null, string $type = 'email')
    {
        $this->code = $code;
        $this->phone = $phone;
        $this->type = $type;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->type === 'phone' 
            ? '📱 Mã xác thực SMS - DatVe'
            : '🔐 Mã xác thực OTP - DatVe';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = $this->type === 'phone' 
            ? 'emails.phone_otp'
            : 'emails.email_otp';

        return new Content(
            view: $template,
            with: [
                'code' => $this->code,
                'phone' => $this->phone,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
