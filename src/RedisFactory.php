<?php

declare(strict_types=1);

namespace App;

final class RedisFactory
{
    public static function create(string $host, int $port): \Redis
    {
        $redis = new \Redis();
        $redis->connect($host, $port);

        return $redis;
    }
}
