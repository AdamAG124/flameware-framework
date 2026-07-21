<?php

namespace App\Routing;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public readonly Http $method,
        public readonly string $path,
    ) {}
}
