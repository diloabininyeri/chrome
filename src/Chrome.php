<?php

namespace Zeus\Chrome;

class Chrome
{
    private array $callbacks = [];

    public function __construct(private readonly SocketInterface $socket)
    {
    }

    public function connect(): bool
    {
        return $this->socket->connect();
    }
    public function disconnect(): bool
    {
        return $this->socket->disconnect();
    }
    public function send($message): bool
    {
        return $this->socket->send($message);
    }
    public function read(): array
    {
        return $this->socket->read();
    }
    public function on(string $event, callable $callback):void
    {
        $this->callbacks[$event][] = $callback;
    }

    public function trigger(string $event,mixed $data):void
    {
        if (isset($this->callbacks[$event])) {
            foreach ($this->callbacks[$event] as $callback) {
                $callback($data);
            }
        }
    }
}