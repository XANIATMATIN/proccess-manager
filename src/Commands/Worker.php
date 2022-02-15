<?php

namespace MatinUtils\ProcessManager\Commands;

use Exception;
use Illuminate\Console\Command;

class Worker extends Command
{
    protected $signature = 'process-manager:worker {processNumber}';

    protected $description = 'process-manager worker';

    protected $buffer = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $pm = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_connect($pm, base_path('bootstrap/easySocket/worker.sock'));
        } catch (\Throwable $th) {
            app('log')->error("Worker " . $this->argument('processNumber') . ". PM connection unavailable. " . $th->getMessage());
            return;
        }
        app('log')->info("Worker " . $this->argument('processNumber') . ". Connected to PM");

        socket_write($pm,  'idle');
        while (true) {
            $input = socket_read($pm, 5000);

            if (empty($input)) {
                socket_close($pm);
                break;
            }

            $this->buffer .= $input;

            $length = strlen($this->buffer);
            if ($this->buffer[$length - 1] == "\0") {
                app('log')->info("Worker " . $this->argument('processNumber') . ". Reveived " . strlen($this->buffer) . " bytes");
                $request = $this->makeRequest($this->buffer);
                $response = $this->makeResponse();
                $router = $this->makeRouter();
                $router->handle($request, $response);
                socket_write($pm, 'idle');
                $this->buffer = '';
            }
        }
    }

    protected function makeRouter()
    {
        $protocolAlias = ucfirst(config('easySocket.defaultProtocol', 'http'));
        $class = "App\\$protocolAlias\Protocol\Router";

        if (empty($class)) {
            throw new Exception("No Protocol", 1);
        }

        return new $class();
    }

    protected function makeRequest($input)
    {
        $protocolAlias = ucfirst(config('easySocket.defaultProtocol', 'http'));
        $class = "App\\$protocolAlias\Protocol\Request";

        if (empty($class)) {
            throw new Exception("No Protocol", 1);
        }

        return new $class($input);
    }

    protected function makeResponse()
    {
        $protocolAlias = ucfirst(config('easySocket.defaultProtocol', 'http'));
        $class = "App\\$protocolAlias\Protocol\Response";

        if (empty($class)) {
            throw new Exception("No Protocol", 1);
        }

        return new $class();
    }
}
