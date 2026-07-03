<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;

class Pipeline
{
    protected Request $request;
    protected array $middleware = [];

    public function send(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function through(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function then(\Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->carry(),
            $destination
        );

        return $pipeline($this->request);
    }

    protected function carry(): \Closure
    {
        return function (\Closure $next, $middleware) {
            return function (Request $request) use ($next, $middleware) {
                if (is_string($middleware)) {
                    $middleware = new $middleware();
                }

                return $middleware->handle($request, $next);
            };
        };
    }
}
