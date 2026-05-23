<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OPTSendMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $verificationUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: $this->data['subject'] ?? 'eCommerceX OTP Code',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
//    public function content()
//    {
//        return new Content(
//            view: 'view.name',
//        );
//    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $fromEmail = $this->data['formEmail'] ?: config('mail.from.address');
        $fromName = $this->data['fromName'] ?? config('mail.from.name', 'eCommerceX');

        return $this->from($fromEmail, $fromName)
            ->replyTo($fromEmail, $fromName)
            ->subject($this->data['subject'] ?? 'eCommerceX OTP Code')
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
                $headers->addTextHeader('X-eCommerceX-Email-Type', 'otp');
            })
            ->view('email.otp_send')
            ->text('email.otp_send_text');
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
