<?php

namespace MatinUtils\ProcessManager\Commands;

use Illuminate\Console\Command;

class Worker extends Command
{
    protected $signature = 'process-manager:worker {processNumber}';

    protected $description = 'process-manager worker';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // $logfile = storage_path('logs/proccessManager/log-' . $this->argument('processNumber') . '.log');

        try {
            $pm = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_connect($pm, '/tmp/workerPort.sock');
        } catch (\Throwable $th) {
            app('log')->error("Worker " . $this->argument('processNumber') . ". PM connection unavailable. " . $th->getMessage());
            return;
        }
        app('log')->info("Worker " . $this->argument('processNumber') . ". Connected to PM");
    }
}
