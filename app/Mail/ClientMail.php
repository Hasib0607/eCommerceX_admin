<?php

namespace App\Mail;

use App\Models\Design;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Orderitem;
use App\Models\Store;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData;
    public $data;
    public $store;
    public $invoice;
    public $order;
    public $orderitems;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($mailData, $data)
    {
        $this->mailData = $mailData;
        $this->data = $data;
        $this->store = $data['store'];
        $this->invoice = $data['invoice'];
        $this->order = $data['order'];
        $this->orderitems = $data['orderitems'];
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    // public function envelope()
    // {
    //         return new Envelope(
    //             from: new Address($this->mailData['fromMail'], $this->mailData['from_name']),
    //             subject: $this->mailData['subject'],
    //         );
    // }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    // public function content()
    // {
    //     // return new Content(
    //     //     view: 'admin.invoice.index'.$this->data['invoiceNo'],
    //     // );
    //     return new Content(
    //         view: 'clientOrderNotifyMail',
    //     );
    // }


public function build()
{
    return $this->from($this->mailData['fromMail'])
                ->view('clientOrderNotifyMail');
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
