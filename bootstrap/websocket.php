<?php

use Kyqo\WebSocket\WsServer;
use Kyqo\Core\Application;

/**
 * WebSocket server bootstrap.
 *
 * This file is loaded by `php kyqo ws:serve` automatically.
 * Return a callable that receives the WsServer instance.
 *
 * Available hooks:
 *   $server->onConnect(fn(int $clientId) => ...);
 *   $server->onDisconnect(fn(int $clientId) => ...);
 *   $server->onMessage(fn(string $channel, array $data, int $clientId) => ...);
 *
 * Available methods:
 *   $server->publish('channel', ['key' => 'value']);
 *   $server->sendTo($clientId, ['event' => 'hello']);
 *   $server->broadcast(['event' => 'announcement', 'data' => '...']);
 *
 * Example — chat room:
 *   $server->onMessage(function (string $channel, array $data, int $clientId) use ($server) {
 *       // Add sender info and broadcast to everyone in the channel
 *       $data['from'] = $clientId;
 *       $server->publish($channel, $data);
 *   });
 */
return function (WsServer $server, Application $app): void {
    $server->onConnect(function (int $clientId) use ($server) {
        $server->sendTo($clientId, ['event' => 'welcome', 'message' => 'Connected to Kyqo WebSocket server']);
    });

    $server->onMessage(function (string $channel, array $data, int $clientId) use ($server) {
        // Default: re-broadcast to channel, exclude sender
        $server->publish($channel, $data, $clientId);
    });

    $server->onDisconnect(function (int $clientId) {
        error_log("[Kyqo WS] Client {$clientId} disconnected");
    });
};
