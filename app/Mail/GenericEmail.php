<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $viewData;
    public $view;

    public function __construct(string $subject, array $viewData = [], string $view = 'emails.sample')
    {
        $this->subject = $subject;
        $this->viewData = $viewData;
        $this->view = $view;
    }

    public function build()
    {
        return $this->subject($this->subject)
            ->view($this->view)
            ->with($this->viewData);
    }
}
