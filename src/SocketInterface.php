<?php

namespace Zeus\Chrome;

/**
 *
 */
interface SocketInterface
{

    /**
     * @return bool
     */
    public function connect(): bool;

    /**
     * @return bool
     */
    public function disconnect(): bool;

    /**
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * @param string $data
     * @return bool
     */
    public function send(string $data): bool;

    /**
     * @return array
     */
    public function read(): array;

    /**
     * @return string
     */
    public function getWsUri():string;
}
