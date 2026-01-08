<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    protected $target;
    protected $message;

    /**
     * Create a new job instance.
     */
    public function __construct($target, $message)
    {
        $this->target = $target;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \App\Services\WhatsAppService::send($this->target, $this->message);
    }
}
