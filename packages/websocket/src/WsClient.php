<?php

namespace Kyqo\WebSocket;

/**
 * WsClient — minimal PHP WebSocket client.
 *
 * Useful for server-to-server communication or testing the WS server
 * from within PHP (e.g. in queue workers or test suites).
 *
 * Usage:
 *   $client = new WsClient('127.0.0.1', 8080);
 *   $client->connect();
 *   $client->send(['action' => 'subscribe', 'channel' => 'chat']);
 *   $client->send(['action' => 'message',   'channel' => 'chat', 'data' => ['text' => 'Hi']]);
 *   $message = $client->receive();
 *   $client->close();
 */
class WsClient
{
    private mixed $socket = null;

    public function __construct(
        private string $host    = '127.0.0.1',
        private int    $port    = 8080,
        private string $path    = '/',
        private float  $timeout = 5.0
    ) {}

    public function connect(): void
    {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new \RuntimeException("WsClient: cannot connect to {$this->host}:{$this->port} — {$errstr} ({$errno})");
        }

        $key      = base64_encode(random_bytes(16));
        $request  = "GET {$this->path} HTTP/1.1\r\n"
                  . "Host: {$this->host}:{$this->port}\r\n"
                  . "Upgrade: websocket\r\n"
                  . "Connection: Upgrade\r\n"
                  . "Sec-WebSocket-Key: {$key}\r\n"
                  . "Sec-WebSocket-Version: 13\r\n"
                  . "\r\n";

        fwrite($this->socket, $request);

        // Read until end of HTTP response headers
        $response = '';
        while (!str_contains($response, "\r\n\r\n")) {
            $chunk = fread($this->socket, 1024);
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('WsClient: handshake failed — connection closed.');
            }
            $response .= $chunk;
        }

        if (!str_contains($response, '101')) {
            throw new \RuntimeException('WsClient: server rejected WebSocket upgrade.');
        }
    }

    /**
     * Send a JSON payload as a WebSocket text frame.
     */
    public function send(array $payload): void
    {
        $data   = json_encode($payload);
        $length = strlen($data);
        $mask   = random_bytes(4);

        $frame  = chr(0x81); // FIN + text opcode

        if ($length <= 125) {
            $frame .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $frame .= chr(0x80 | 126) . pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $length);
        }

        $frame .= $mask;

        for ($i = 0; $i < $length; $i++) {
            $frame .= chr(ord($data[$i]) ^ ord($mask[$i % 4]));
        }

        fwrite($this->socket, $frame);
    }

    /**
     * Read one WebSocket text frame and return the decoded JSON array.
     * Returns null on timeout or connection close.
     */
    public function receive(float $timeout = 2.0): ?array
    {
        stream_set_timeout($this->socket, (int) $timeout, (int) (($timeout - floor($timeout)) * 1_000_000));

        $header = fread($this->socket, 2);
        if (!$header || strlen($header) < 2) return null;

        $byte1  = ord($header[1]);
        $length = $byte1 & 0x7F;

        if ($length === 126) {
            $ext    = fread($this->socket, 2);
            $length = unpack('n', $ext)[1];
        } elseif ($length === 127) {
            $ext    = fread($this->socket, 8);
            $length = unpack('J', $ext)[1];
        }

        if ($length === 0) return [];

        $payload = '';
        while (strlen($payload) < $length) {
            $chunk = fread($this->socket, $length - strlen($payload));
            if ($chunk === false || $chunk === '') break;
            $payload .= $chunk;
        }

        return json_decode($payload, true);
    }

    public function close(): void
    {
        if ($this->socket) {
            // Send close frame
            fwrite($this->socket, chr(0x88) . chr(0x00));
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
