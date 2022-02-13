<?php

namespace MatinUtils\ProcessManager;

class ProcessManager
{
    protected $workerPort;
    protected $workers = [], $pipes = [], $queue = [], $workerStatus = [], $workerConnections = [], $clientConnections = [];
    protected $descriptorspec = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];

    public function run()
    {
        $clientPort = $this->servePort('client');
        $workerPort = $this->servePort('worker');


        $workers = $pipes = $queue = $workerStatus = $workerConnections = $clientConnections = [];
        $numOfProcess = config('processManager.numOfProcess', 1);

        for ($i = 0; $i < $numOfProcess; $i++) {
            $workers[$i] = $this->startProcess($i);
            $workerConnections[$i] = socket_accept($workerPort);
        }

        while (true) {
            $read = array_merge($workerConnections, [$clientPort], $clientConnections);
            socket_select($read, $write, $except, null);

            if (in_array($clientPort, $read)) {
                $clientConnections[] = socket_accept($clientPort);
                dump('new client');
            }
        }

        foreach ($clientConnections as $clientKey => $clientConnection) {
            if (in_array($clientConnection, $read)) {
                $bytes = socket_recv($clientConnection, $input, 1000000, MSG_DONTWAIT);
                if ($bytes < 1) {
                    unset($clientConnections[$clientKey]);
                    continue;
                }
                $queue[] = [
                    'clientKey' => $clientKey,
                    'input' => $input
                ];
                dump("new request from client");
            }
        }

        foreach ($workerConnections as $workerKey => $workerConnection) {
            if (in_array($workerConnection, $read)) {
                $workerStatus[$workerKey] = socket_read($workerConnection, 1024);
                dump("Worker $workerKey status: $workerStatus[$workerKey]");
            }
        }

        foreach ($queue as $key => $item) {
            foreach ($workerStatus as $workerKey => $status) {
                if ($status == 'idle') {
                    socket_write($workerConnections[$workerKey], $item['input']);
                    dump("job given to worker $workerKey: " . strlen($item['input']) . " bytes");
                    $workerStatus[$workerKey] = 'busy';
                    unset($queue[$key]);
                    continue 2;
                }
            }
        }
        dump('queue count: ' . count($queue) . '. clint count: ' . count($clientConnections));
    }

    public function servePort($portName)
    {
        $portName = $portName . 'Port';
        try {
            $newPort = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_bind($newPort, "/tmp/$portName.sock");
        } catch (\Throwable $th) {
            unlink("/tmp/$portName.sock");
            socket_bind($newPort, "/tmp/$portName.sock");
        }

        socket_listen($newPort, 10);
        return $newPort;
    }

    public function startProcess($processNumber)
    {
        $worker = proc_open('php artisan process-manager:worker ' . $processNumber, $this->descriptorspec, $this->pipes[$processNumber]);
        stream_set_blocking($this->pipes[$processNumber][1], 0);
        return $worker;
    }
}
