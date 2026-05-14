<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendPseVisitorReport extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $visitors;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $visitors)
    {
        $this->name = $name;
        $this->visitors = $visitors;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject('Product খুঁজো Visitor Report')
            ->view('email.pse_visitor_report');
    }
}
