<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Cache\Engine\RedisEngine;
use TheMusicDev\RedisAcl\Cache\Engine\AclRedisEngine;

/**
 * Plugin config bootstrap. Runs twice, from two places, and is idempotent (see the guard below):
 *
 * 1. Composer's `autoload.files`, i.e. inside `require vendor/autoload.php`. **This is the one that
 *    matters.** `Cache::setConfig()` parses a `url` eagerly and `StaticConfigTrait::parseDsn()` resolves
 *    `className` from the DSN class map *at parse time*. A host application parses its Cache configs in
 *    `config/bootstrap.php` — which `BaseApplication::bootstrap()` requires **before** `pluginBootstrap()`
 *    runs. A remap done from the plugin's bootstrap hook alone therefore arrives after every `redis://`
 *    config has already baked in the stock engine, and an ACL DSN fails silently at the first cache write.
 *    Composer's autoload runs first of all, so the map is correct before any host config is parsed.
 * 2. `BasePlugin::bootstrap()`, which requires this file when the plugin is loaded. A no-op by then,
 *    kept so loading the plugin behaves as a reader expects.
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
