<?php

namespace Zeus\Chrome;


use Exception;

/**
 *
 */
class WebSocketCodec
{
    /**
     *
     */
    private const int OPCODE_TEXT = 0x1;

    /**
     * @param string $message
     * @param int $opcode
     * @return string
     */
    public function encode(string $message, int $opcode = self::OPCODE_TEXT): string
    {
        try {
            if ($opcode < 0 || $opcode > 0xF) {
                throw new SocketCodecException("Invalid opcode: $opcode");
            }


            $payload = $message;
            $payloadLength = strlen($payload);

            $frame = chr(0x80 | ($opcode & 0x0F));

            if ($payloadLength <= 125) {
                $frame .= chr(0x80 | $payloadLength);
            } elseif ($payloadLength <= 65535) {
                $frame .= chr(0x80 | 126) . pack('n', $payloadLength);
            } else {
                $frame .= chr(0x80 | 127) . pack('J', $payloadLength);
            }


            $maskKey = random_bytes(4);
            $frame .= $maskKey;


            $maskedPayload = '';
            for ($i = 0; $i < $payloadLength; $i++) {
                $maskedPayload .= $payload[$i] ^ $maskKey[$i % 4];
            }


            $frame .= $maskedPayload;

            return $frame;

        } catch (Exception $e) {
            throw new SocketCodecException("Encode failed: " . $e->getMessage());
        }
    }

    /**
     * @param string $data
     * @return array
     */
    public function decode(string $data): array
    {
        if (strlen($data) < 2) {
            throw new SocketCodecException('Invalid frame: too short');
        }

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);


        $isFinal = ($firstByte & 0x80) !== 0;
        $opcode = $firstByte & 0x0F;


        $isMasked = ($secondByte & 0x80) !== 0;
        $payloadLength = $secondByte & 0x7F;

        $offset = 2;


        if ($payloadLength === 126) {
            if (strlen($data) < 4) {
                throw new SocketCodecException('Invalid frame: missing extended length');
            }
            $payloadLength = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLength === 127) {
            if (strlen($data) < 10) {
                throw new SocketCodecException('Invalid frame: missing extended length');
            }
            $payloadLength = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }


        $maskKey = null;
        if ($isMasked) {
            if (strlen($data) < $offset + 4) {
                throw new SocketCodecException('Invalid frame: missing mask key');
            }
            $maskKey = substr($data, $offset, 4);
            $offset += 4;
        }


        if (strlen($data) < $offset + $payloadLength) {
            throw new SocketCodecException('Invalid frame: incomplete payload');
        }
        $payload = substr($data, $offset, $payloadLength);


        if ($isMasked && $maskKey) {
            $unmasked = '';
            for ($i = 0; $i < $payloadLength; $i++) {
                $unmasked .= $payload[$i] ^ $maskKey[$i % 4];
            }
            $payload = $unmasked;
        }

        return [
            'opcode' => $opcode,
            'isFinal' => $isFinal,
            'payload' => $payload,
            'length' => $payloadLength
        ];
    }

}

