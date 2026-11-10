<?php
declare(strict_types=1);

namespace TheMusicDev\RedisAcl\Test\TestCase\Cache\Engine;

use Redis;
use TheMusicDev\RedisAcl\Cache\Engine\AclRedisEngine;

/**
 * Test double for AclRedisEngine that injects a spy \Redis through the parent's protected
 * `_createRedisInstance()` seam, so the auth() call can be observed without a server.
 */
final class SpyAclRedisEngine extends AclRedisEngine
{
    public Redis $redis;

    protected function _createRedisInstance(): Redis
    {
        return $this->redis;
    }
}
