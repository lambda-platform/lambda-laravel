<?php

namespace Lambda\Dataform;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FormJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use FormEmail;

    public $data;
    public $schema;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $schema)
    {
        $this->data = $data;
        $this->schema = $schema;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        LOG::debug('FORM JOB EXECUTED');
        $this->sendEmail($this->data, $this->schema);
    }
}
