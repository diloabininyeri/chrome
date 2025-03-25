<?php

namespace Zeus\Chrome;

use JsonException;

/**
 *
 */
class WebSocketClient implements SocketInterface
{
    /**
     * @var HandShake
     */
    private HandShake $handshake;

    /**
     * @var WebSocketCodec
     */
    private WebSocketCodec $codec;

    /**
     * @param string $wsUri
     */
    public function __construct(string $wsUri)
    {
        $this->handshake = new HandShake($wsUri);
        $this->codec = new WebSocketCodec();
    }

    /**
     * @return bool
     */
    public function connect(): bool
    {
        $this->handshake->initiateConnection();
        $isConnect = $this->handshake->isConnectionSuccessful();
        if ($isConnect) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function disconnect(): bool
    {
        return fclose($this->handshake->getConnectionSocket());
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->handshake->isConnectionSuccessful();
    }

    /**
     * @param string $data
     * @return bool
     */
    public function send(string $data): bool
    {
        if (!$this->handshake->isConnectionSuccessful()) {
            return false;
        }

        return fwrite($this->handshake->getConnectionSocket(), $this->codec->encode($data));
    }

    /**
     * @throws JsonException
     */
    public function read(): array
    {
        if (!$this->handshake->isConnectionSuccessful()) {
            return [];
        }

        $data = WebSocketFrameReader::read($this->handshake->getConnectionSocket());

        if ($data === '') {
            return [];
        }

        $decode = $this->codec->decode($data)['payload'];
        return json_decode(
            $decode, true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE
        );
    }

}
