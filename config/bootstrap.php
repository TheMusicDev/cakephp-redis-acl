<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Cache\Engine\RedisEngine;
use TheMusicDev\RedisAcl\Cache\Engine\AclRedisEngine;

/**
 * Plugin config bootstrap (auto-required by BasePlugin::bootstrap()).
 *
 * Remaps the `redis://` URL scheme to the ACL-capable engine, so DSNs such as
 * `redis://user:pass@host:6379?prefix=...&database=3` reach this plugin's engine instead of the stock one.
 *
 * Why not `className` in the host's config: `StaticConfigTrait::config()` parses `url` and the parsed
 * array wins the merge (`$parsed + $config`), so `className` set next to a `url` is silently discarded.
 * Remapping the scheme means no second config vocabulary.
 *
 * `Cache` ships `redis => RedisEngine::class` in its own default map, and `Cache::setDsnClassMap()` is
 * additive (any `redis` key in the passed map wins over the current one). So the remap only happens while
 * the map still holds the stock engine: a host that mapped `redis` itself is never clobbered — the
 * TheMusicDev plugin-config convention.
 */
if ((Cache::getDsnClassMap()['redis'] ?? null) === RedisEngine::class) {
    Cache::setDsnClassMap(['redis' => AclRedisEngine::class]);
}
