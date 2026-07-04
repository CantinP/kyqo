<?php

namespace Kyqo\WebSocket;

/**
 * KyqoWsServer — native PHP WebSocket server (no Ratchet / Swoole required).
 *
 * Implements RFC 6455 (WebSocket) over raw PHP sockets.
 * Supports:
 *   - Multiple concurrent clients via stream_select()
 *   - Text frames (binary frames silently dropped)
 *   - Pub/sub channels: subscribe / unsubscribe / publish
 *   - Ping/Pong keepalive
 *   - Graceful disconnect on close frame or read error
 *
 * Usage — start from the CLI:
 *   php kyqo ws:serve
 *   php kyqo ws:serve --host=0.0.0.0 --port=8080
 *
 * JavaScript client:
 *   const ws = new WebSocket('ws://localhost:8080');
 *   ws.send(JSON.stringify({action:'subscribe', channel:'chat'}));
 *   ws.send(JSON.stringify({action:'message',   channel:'chat', data:{text:'Hello!'}}));
 *
 * Protocol — all messages are JSON:
 *   Client → Server:
 *     {"action":"subscribe",   "channel":"orders"}
 *     {"action":"unsubscribe", "channel":"orders"}
 *     {"action":"message",     "channel":"orders", "data":{...}}
 *     {"action":"ping"}
 *
 *   Server → Client:
 *     {"event":"subscribed",    "channel":"orders"}
 *     {"event":"message",       "channel":"orders", "data":{...}}
 *     {"event":"pong"}
 *     {"event":"error",         "message":"..."}
 */
class WsServer
{
    /** @var resource[] Connected sockets, keyed by resource id */
    private array $clients = [];

    /** @var array<int, string[]> channel => [client_ids] */
    private array $channels = [];

    /** @var array<int, bool> Tracks which clients completed the WS handshake */
    private array $handshook = [];

    /** @var array<int, string> Partial read buffers */
    private array $buffers = [];

    /** @var ?callable Event handler: fn(string $channel, array $data, int $clientId) */
    private $onMessage = null;

    /** @var ?callable Called when a client connects: fn(int $clientId) */
    private $onConnect = null;

    /** @var ?callable Called when a client disconnects: fn(int $clientId) */
    private $onDisconnect = null;

    private bool $running = false;

    public function __construct(
        private string $host = '0.0.0.0',
        private int    $port = 8080
    ) {}

    // ── Event hooks ───────────────────────────────────────────────────

    public function onMessage(callable $handler): static
    {
        $this->onMessage = $handler;
        return $this;
    }

    public function onConnect(callable $handler): static
    {
        $this->onConnect = $handler;
        return $this;
    }

    public function onDisconnect(callable $handler): static
    {
        $this->onDisconnect = $handler;
        return $this;
    }

    // ── Public API ────────────────────────────────────────────────────

    /**
     * Publish a message to all subscribers of a channel.
     *
     * @param string $channel
     * @param array  $data
     * @param int|null $excludeClientId  Exclude the sender
     */
    public function publish(string $channel, array $data, ?int $excludeClientId = null): void
    {
        $payload = json_encode(['event' => 'message', 'channel' => $channel, 'data' => $data]);

        foreach ($this->channels[$channel] ?? [] as $clientId) {
            if ($excludeClientId !== null && $clientId === $excludeClientId) continue;
            if (isset($this->clients[$clientId])) {
                $this->wsSend($this->clients[$clientId], $payload);
            }
        }
    }

    /**
     * Send a raw JSON payload to a specific client.
     */
    public function sendTo(int $clientId, array $payload): void
    {
        if (isset($this->clients[$clientId])) {
            $this->wsSend($this->clients[$clientId], json_encode($payload));
        }
    }

    /**
     * Broadcast to ALL connected clients.
     */
    public function broadcast(array $payload, ?int $excludeClientId = null): void
    {
        $json = json_encode($payload);
        foreach ($this->clients as $id => $socket) {
            if ($excludeClientId !== null && $id === $excludeClientId) continue;
            if ($this->handshook[$id] ?? false) {
                $this->wsSend($socket, $json);
            }
        }
    }

    public function getClientCount(): int  { return count($this->clients); }
    public function getChannels(): array   { return array_keys($this->channels); }
    public function stop(): void           { $this->running = false; }

    // ── Main loop ──────────────────────────────────────────────────────

    public function run(): void
    {
        $server = stream_socket_server(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
        );

        if (!$server) {
            throw new \RuntimeException("WebSocket: cannot bind to {$this->host}:{$this->port} — {$errstr} ({$errno})");
        }

        stream_set_blocking($server, false);

        echo "[Kyqo WS] Listening on ws://{$this->host}:{$this->port}\n";

        $this->running = true;

        while ($this->running) {
            $read   = array_merge([$server], $this->clients);
            $write  = null;
            $except = null;

            if (@stream_select($read, $write, $except, 0, 200000) === false) {
                break;
            }

            // New connection
            if (in_array($server, $read)) {
                $client = @stream_socket_accept($server, 0);
                if ($client) {
                    stream_set_blocking($client, false);
                    $id = (int) $client;
                    $this->clients[$id]   = $client;
                    $this->handshook[$id] = false;
                    $this->buffers[$id]   = '';
                }
                unset($read[array_search($server, $read)]);
            }

            // Read from clients
            foreach ($read as $socket) {
                $id = (int) $socket;
                if (!isset($this->clients[$id])) continue;

                $data = @fread($socket, 4096);

                if ($data === false || $data === '') {
                    $this->disconnect($id);
                    continue;
                }

                $this->buffers[$id] .= $data;

                if (!($this->handshook[$id] ?? false)) {
                    $this->tryHandshake($id);
                } else {
                    $this->processFrames($id);
                }
            }
        }

        foreach ($this->clients as $id => $socket) {
            @fclose($socket);
        }
        @fclose($server);
    }

    // ── Handshake (RFC 6455) ────────────────────────────────────────

    private function tryHandshake(int $id): void
    {
        $buffer = $this->buffers[$id];

        if (!str_contains($buffer, "\r\n\r\n")) return; // incomplete headers

        if (!preg_match('/Sec-WebSocket-Key:\s*(\S+)/i', $buffer, $m)) {
            $this->disconnect($id);
            return;
        }

        $key      = $m[1];
        $accept   = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n"
                  . "Upgrade: websocket\r\n"
                  . "Connection: Upgrade\r\n"
                  . "Sec-WebSocket-Accept: {$accept}\r\n"
                  . "\r\n";

        fwrite($this->clients[$id], $response);

        $this->handshook[$id] = true;
        $this->buffers[$id]   = '';

        if ($this->onConnect) {
            ($this->onConnect)($id);
        }
    }

    // ── Frame processing (RFC 6455) ───────────────────────────────

    private function processFrames(int $id): void
    {
        $buffer = &$this->buffers[$id];

        while (strlen($buffer) >= 2) {
            $byte0 = ord($buffer[0]);
            $byte1 = ord($buffer[1]);

            $fin    = ($byte0 & 0x80) !== 0;
            $opcode = $byte0 & 0x0F;
            $masked = ($byte1 & 0x80) !== 0;
            $length = $byte1 & 0x7F;

            $offset = 2;

            if ($length === 126) {
                if (strlen($buffer) < 4) return;
                $length = unpack('n', substr($buffer, 2, 2))[1];
                $offset = 4;
            } elseif ($length === 127) {
                if (strlen($buffer) < 10) return;
                $length = unpack('J', substr($buffer, 2, 8))[1];
                $offset = 10;
            }

            $maskLen   = $masked ? 4 : 0;
            $totalSize = $offset + $maskLen + $length;

            if (strlen($buffer) < $totalSize) return; // wait for more data

            $payload = substr($buffer, $offset + $maskLen, $length);

            if ($masked) {
                $mask = substr($buffer, $offset, 4);
                for ($i = 0; $i < $length; $i++) {
                    $payload[$i] = chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
                }
            }

            $buffer = substr($buffer, $totalSize);

            match ($opcode) {
                0x1 => $this->handleText($id, $payload),   // text frame
                0x8 => $this->disconnect($id),              // close frame
                0x9 => $this->handlePing($id),              // ping
                0xA => null,                                  // pong — no-op
                default => null,
            };
        }
    }

    private function handleText(int $id, string $payload): void
    {
        $msg = json_decode($payload, true);
        if (!is_array($msg)) return;

        $action  = $msg['action'] ?? '';
        $channel = $msg['channel'] ?? '';

        switch ($action) {
            case 'subscribe':
                $this->channels[$channel][] = $id;
                $this->channels[$channel]   = array_unique($this->channels[$channel]);
                $this->wsSend($this->clients[$id], json_encode(['event' => 'subscribed', 'channel' => $channel]));
                break;

            case 'unsubscribe':
                $this->channels[$channel] = array_filter(
                    $this->channels[$channel] ?? [],
                    fn ($c) => $c !== $id
                );
                $this->wsSend($this->clients[$id], json_encode(['event' => 'unsubscribed', 'channel' => $channel]));
                break;

            case 'message':
                $data = $msg['data'] ?? [];
                if ($this->onMessage) {
                    ($this->onMessage)($channel, $data, $id);
                } else {
                    // Default: re-broadcast to channel
                    $this->publish($channel, $data, $id);
                }
                break;

            case 'ping':
                $this->wsSend($this->clients[$id], json_encode(['event' => 'pong']));
                break;

            default:
                $this->wsSend($this->clients[$id], json_encode(['event' => 'error', 'message' => 'Unknown action']));
        }
    }

    private function handlePing(int $id): void
    {
        // Send Pong frame (opcode 0xA)
        fwrite($this->clients[$id], chr(0x8A) . chr(0x00));
    }

    // ── Frame encoding ────────────────────────────────────────────────

    private function wsSend(mixed $socket, string $payload): void
    {
        $length = strlen($payload);
        $frame  = chr(0x81); // FIN + text opcode

        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }

        $frame .= $payload;

        @fwrite($socket, $frame);
    }

    // ── Disconnect ────────────────────────────────────────────────────

    private function disconnect(int $id): void
    {
        if (isset($this->clients[$id])) {
            @fclose($this->clients[$id]);
            unset($this->clients[$id], $this->handshook[$id], $this->buffers[$id]);
        }

        foreach ($this->channels as $channel => &$subscribers) {
            $subscribers = array_filter($subscribers, fn ($c) => $c !== $id);
        }

        if ($this->onDisconnect) {
            ($this->onDisconnect)($id);
        }
    }
}
