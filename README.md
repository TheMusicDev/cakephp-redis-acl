# TheMusicDev/RedisAcl

Auth to a Redis **6 ACL user** from a CakePHP 5 cache config or `redis://` URL. CakePHP's own `RedisEngine` has no
`username` key in its config, ignores the username in a DSN and calls `Redis::auth()` with a scalar password only — so a
Redis server running ACL users (username + password) cannot be used. This plugin ships one `RedisEngine` subclass and
one line of bootstrap; nothing else. It exists to be deleted the day CakePHP consumes the DSN username natively
([upstream issue](https://github.com/cakephp/cakephp/issues/19792)).

Requires phpredis **5.3.0+** (`ext-redis`), where `Redis::auth()` first accepts the array form `[username, password]`.

## Install

```
composer require themusicdev/redis-acl
```

Load the plugin (`config/plugins.php`: `'TheMusicDev/RedisAcl' => []`). That is the whole setup — the plugin remaps the
`redis://` URL scheme from the stock engine to the ACL-capable one, so DSNs and `className` configs both work.

## Use it

Either form reaches this plugin's engine:

```php
// In config/app.php — a DSN with the ACL user
'sessions' => [
    'url' => 'redis://acluser:aclpass@redis.internal:6379?prefix=myapp_sessions_&database=1',
    'duration' => 3600,
],

// Or discrete keys
'sessions' => [
    'className' => \TheMusicDev\RedisAcl\Cache\Engine\AclRedisEngine::class,
    'host' => 'redis.internal',
    'username' => 'acluser',
    'password' => 'aclpass',
    'prefix' => 'myapp_sessions_',
    'database' => 1,
    'duration' => 3600,
],
```

Do **not** set a discrete `className` alongside a `url`: CakePHP's DSN parsing wins its merge (`$parsed + $config`), so
the `className` you set next to a `url` is silently discarded — the remap in the plugin bootstrap is what makes the DSN
form work. A `username` set without a `password` throws (`CakeException`) — it can only authenticate as nobody.
`RedisCluster` config is inherited from the stock engine unchanged and its array-auth path is not tested here; on a
cluster, verify against your phpredis version.

### Sessions (the reason this plugin exists)

Sessions reach Redis through the cache config, so point `Session.defaults => 'cache'` at the config above:

```php
'Session' => [
    'defaults' => 'cache',
    'timeout' => 3600,          // minutes
    'cookie' => 'myapp',
],
```

Then `Cake\Http\Session\CacheSession` stores session data through the `sessions` (or renamed) cache config. Two things
to know — they are properties of cache-backed sessions, not of this plugin, and both bite in production:

- **No session locking.** PHP's native file handler locks the session file per request; `CacheSession` just calls
  `Cache::write`. Concurrent requests from one browser (parallel AJAX) can clobber each other's session writes — last
  write wins.
- **No `gc()`.** Session expiry is the Redis TTL, which comes from the cache config's `duration`. If `duration` is
  shorter than the session `timeout`, Redis evicts *live* sessions: users get logged out early and intermittently.
  **The rule: `duration` ≥ `timeout`.** (`timeout` is in minutes, `duration` in seconds: a two-hour session needs
  `timeout => 120` and `duration => 7200` or more.)

### Shared Redis between apps

A per-app `prefix`, and ideally a distinct `database=N`, or two apps on one Redis will read each other's keys.

## Tests

Unit tests need `ext-redis` on the runner (CI installs it via `shivammathur/setup-php`, `extensions: redis`). The
integration test — real auth to a real ACL user, with the **default user disabled**, so a scalar `auth()` cannot pass
by accident — runs only when `REDIS_TEST_URL` is set:

```
docker compose up -d --wait redistest
REDIS_TEST_URL='redis://acltest:aclpass@localhost:6381?prefix=int_' composer test
```

## Delete this plugin later

When CakePHP ships ACL username support, remove the composer package, drop the plugin from `config/plugins.php`, and
remove `username` from your cache configs (or leave them — the upstream engine will ignore what it does not consume,
exactly as it does today).
