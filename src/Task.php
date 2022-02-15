<?php

namespace MatinUtils\ProcessManager;

class Task
{
    protected $tries = 0, $maxTries = 3;
    protected $workerKey = Null;
    public function __construct(protected int $clientKey, protected string $input)
    {
    }

    public function input()
    {
        return $this->input;
    }

    public function removeWorkerKey()
    {
        $this->workerKey = Null;
    }

    public function inProcess(int $workerKey)
    {
        $this->workerKey = $workerKey;
        $this->tries++;
    }

    public function isInProcess()
    {
        return $this->workerKey !== Null;
    }

    public function getProperties()
    {
        return [
            'input' => $this->input,
            'workerKey' => $this->workerKey,
            'clientKey' => $this->clientKey,
            'tries' => $this->tries
        ];
    }

    public function isGivenToWorker(int $workerKey)
    {
        return $this->workerKey === $workerKey;
    }

    public function MaxedTries()
    {
        return $this->tries  >= $this->maxTries;
    }
}
