<?php

namespace Kyqo\Broadcasting;

/**
 * BroadcastManager
 *
 * Resolves and delegates to the configured broadcaster.
 *
 * Supported drivers: 'pusher', 'log', 'null'
 *
 * Usage:
 *   broadcast(new OrderShipped($order));
 *
 *   // Or:
 *   app('broadcast')->channel('orders.' . $order->id, new OrderShipped($order));
 */
class BroadcastManager
{
    /** @var array<string, BroadcasterInterface> */
    private array $drivers = [];

    public function __construct(private array $config = []) {}

    public function driver(string $driver = null): BroadcasterInterface
    {
        $driver ??= $this->config['default'] ?? 'log';

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    private function createDriver(string $driver): BroadcasterInterface
    {
        $cfg = $this->config['connections'][$driver] ?? [];

        return match ($driver) {
            'pusher' => new Broadcasters\PusherBroadcaster(
                $cfg['key']     ?? '',
                $cfg['secret']  ?? '',
                $cfg['app_id']  ?? '',
                $cfg['cluster'] ?? 'mt1',
                $cfg['useTLS']  ?? true
            ),
            'log'  => new Broadcasters\LogBroadcaster(),
            'null' => new Broadcasters\NullBroadcaster(),
            default => throw new \InvalidArgumentException("Broadcast driver [{$driver}] not supported."),
        };
    }

    public function event(ShouldBroadcast $event): void
    {
        $channels = $event->broadcastOn();
        $channels = is_array($channels) ? $channels : [$channels];

        $payload = array_merge(
            method_exists($event, 'broadcastWith') ? $event->broadcastWith() : [],
            ['event' => method_exists($event, 'broadcastAs') ? $event->broadcastAs() : get_class($event)]
        );

        foreach ($channels as $channel) {
            $channelName = $channel instanceof Channel ? $channel->name : (string) $channel;
            $this->driver()->broadcast([$channelName], $payload['event'], $payload);
        }
    }

    public function __call(string $method, array $args): mixed
    {
        return $this->driver()->{$method}(...$args);
    }
}
