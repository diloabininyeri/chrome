<?php

namespace Zeus\Chrome;

/**
 *
 */
class WebSocketFrameReader
{

    /**
     * @param $socket
     * @return string
     */
    public static function read($socket): string
    {

        $header = fread($socket, 2);
        if ($header === false || strlen($header) < 2) {
            throw new SocketException("Failed to read frame header");
        }

        $secondByte = ord($header[1]);
        $payloadLength = $secondByte & 0x7F;
        $isMasked = ($secondByte & 0x80) !== 0;

        $data = $header;


        if ($payloadLength === 126) {
            $lengthData = fread($socket, 2);
            if ($lengthData === false || strlen($lengthData) < 2) {
                throw new SocketException("Failed to read extended length");
            }
            $data .= $lengthData;
            $payloadLength = unpack('n', $lengthData)[1];
        } elseif ($payloadLength === 127) {
            $lengthData = fread($socket, 8);
            if ($lengthData === false || strlen($lengthData) < 8) {
                throw new SocketException("Failed to read extended length");
            }
            $data .= $lengthData;
            $payloadLength = unpack('J', $lengthData)[1];
        }


        if ($isMasked) {
            $maskKey = fread($socket, 4);
            if ($maskKey === false || strlen($maskKey) < 4) {
                throw new SocketException("Failed to read mask key");
            }
            $data .= $maskKey;
        }

        $remaining = $payloadLength;
        while ($remaining > 0) {
            $chunk = fread($socket, $remaining);
            if ($chunk === false) {
                throw new SocketException("Failed to read payload");
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }
}