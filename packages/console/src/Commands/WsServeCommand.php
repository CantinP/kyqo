<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Kyqo\WebSocket\WsServer;
use Symfony\Component\Console\Input\InputOption;

/**
 * php kyqo ws:serve
 * php kyqo ws:serve --host=0.0.0.0 --port=8080
 */
class WsServeCommand extends Command
{
    protected string $signature   = 'ws:serve';
    protected string $description = 'Start the Kyqo WebSocket server';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host to listen on', '0.0.0.0');
        $this->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen on', '8080');
    }

    protected function handle(): int
    {
        $host = $this->option('host') ?? '0.0.0.0';
        $port = (int) ($this->option('port') ?? 8080);

        $server = new WsServer($host, $port);

        // Allow the application to hook into the WS server
        // by defining a bootstrap/websocket.php file
        $bootstrap = $this->app->basePath('bootstrap/websocket.php');
        if (file_exists($bootstrap)) {
            $configure = require $bootstrap;
            if (is_callable($configure)) {
                $configure($server, $this->app);
            }
        } else {
            // Default: re-broadcast messages to the channel
            $server->onMessage(function (string $channel, array $data, int $clientId) use ($server) {
                $server->publish($channel, $data, $clientId);
            });
        }

        $this->info("WebSocket server starting on ws://{$host}:{$port}");
        $this->info('Press Ctrl+C to stop.');

        $server->run();

        return self::SUCCESS;
    }
}
