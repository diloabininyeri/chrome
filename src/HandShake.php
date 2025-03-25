<?php

namespace Zeus\Chrome;

use Random\RandomException;

class HandShake
{
    /**
     * @var resource|null $socket
     */
    private $socket;

    private bool $isConnected = false;

    /**
     * @param string $wsUri
     */
    public function __construct(private string $wsUri)
    {
    }


    public function initiateConnection(): HandShake
    {
        $urlParts = parse_url($this->wsUri);
        $scheme = $urlParts['scheme'];
        $host = $urlParts['host'];
        $port = $urlParts['port'] ?? ($scheme === 'wss' ? 443 : 80);
        $path = $urlParts['path'] ?? '/';


        $context = stream_context_create([
            'socket' => [
                'timeout' => 10,
            ],
        ]);

        $this->socket = stream_socket_client(
            ($scheme === 'wss' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
            $errorNo,
            $errorString,
            timeout: 30,
            flags:STREAM_CLIENT_CONNECT,
           context:  $context
        );

        if (!$this->socket) {
            throw new SocketUnableConnectException("Unable to connect to WebSocket server: $errorString ($errorNo)");
        }


        $key = base64_encode(random_bytes(16));
        $headers =
            "GET $path HTTP/1.1\r\n" .
            "Host: $host\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Key: $key\r\n" .
            "Sec-WebSocket-Version: 13\r\n\r\n";


        fwrite($this->socket, $headers);


        $response = fread($this->socket, 1500);

        if (str_contains($response, ' 101 ')) {
            $this->isConnected = true;
        }

        return $this;
    }

    /***
     * @return bool
     */
    public function isConnectionSuccessful(): bool
    {
        return $this->isConnected;
    }

    /***
     * @return resource|null
     */
    public function getConnectionSocket()
    {
        return $this->socket;
    }
}
