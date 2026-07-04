<?php

namespace Kyqo\Broadcasting;

class PrivateChannel extends Channel
{
    public function __construct(string $name)
    {
        parent::__construct('private-' . $name);
    }
}
