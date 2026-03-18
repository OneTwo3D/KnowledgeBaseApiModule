<?php

namespace Modules\KnowledgeBase\Mail;

use Illuminate\Mail\Mailable;

class Login extends Mailable
{
    public $mailbox;
    public $customer;
    public $timestamp;
    public $url;
    
    /**
     * Create a new message instance.
     */
    public function __construct($mailbox, $customer, $timestamp, $url = '')
    {
        $this->mailbox = $mailbox;
        $this->customer = $customer;
        $this->timestamp = $timestamp;
        $this->url = $url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $auth_link = route('knowledgebase.customer_login_from_email', [
            'id' => \Kb::encodeMailboxId($this->mailbox->id),
            'customer_id' => encrypt($this->customer->id),
            'hash' => \Kb::customerHash($this->customer->created_at),
            'url' => $this->url,
            'timestamp' => $this->timestamp,
        ]);
        $portal_name = \Kb::getKbName($this->mailbox);

        $message = $this->subject(__('Authentication to :portal_name', ['portal_name' => $portal_name]))
                    ->view('knowledgebase::emails/customer_login', ['portal_name' => $portal_name, 'auth_link' => $auth_link])
                    ->text('knowledgebase::emails/customer_login_text', ['portal_name' => $portal_name, 'auth_link' => $auth_link]);

        return $message;
    }
}
