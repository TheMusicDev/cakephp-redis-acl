# Decisions

Record of why the plugin looks the way it does (build brief, 2026-11-10, first consumer: a private app that needs
Redis-backed sessions on its dokploy staging environment, with a Redis server running ACL users).

- **A cache engine, not a `SessionHandlerInterface` implementation.** CakePHP sessions cannot name a Redis backend
  directly — `Cake\Http\Session` accepts exactly four `defaults`: `php`, `cake` (files), `database`, `cache`. With
  `defaults => 'cache'`, `Cake\Http\Session\CacheSession` stores sessions through a named **Cache** configuration,
  doing nothing but `Cache::read`/`write`/`delete` on its config. So the only extension point that reaches Redis
  sessions *is* a cache engine. A session handler would be more code, would help only sessions (not cache config),
  and would diverge from the upstream fix.
- **Reshape `password` in `init()`, do not override `connectRedis()`.** The stock engine passes
  `$this->_config['password']` to `Redis::auth()` verbatim, and `auth()` has accepted the array form
  `[username, password]` since phpredis 5.3.0. So the subclass only reshapes that one config key before
  `parent::init()` connects — connection logic (TLS/SSL/socket/persistent branching, several hundred lines) is
  never copied into this repo, where it would silently rot against upstream changes. The same passes through the
  persistent path unchanged (`pconnect` still runs the same `auth()` afterwards — covered by the spy tests).
- **Remap the `redis` URL scheme; do not tell hosts to set `className`.** `StaticConfigTrait::config()` parses a
  DSN with `url` and the parsed array wins the merge (`$parsed + $config`), so a discrete `className` next to a
  `url` is silently discarded — an ACL config would quietly fall back to the stock engine. `Cache::setDsnClassMap()`
  fixes the scheme itself, once, in the plugin bootstrap. No invented `redis-acl://` vocabulary.
- **The remap guards on the value, not the key's absence.** `Cache` ships `redis => RedisEngine::class` in its own
  default class map, so a key-existence check would never remap anything. The bootstrap remaps only while the map
  still holds the stock engine, so a host that mapped `redis` itself is never clobbered — the plugin-config
  convention (merge, never overwrite). (`setDsnClassMap()` itself is additive-and-overwriting: any key in the passed
  map wins, which is exactly why the guard is needed.)
- **`username` without `password` throws.** An empty-password array auth would reach Redis and fail per request, or
  authenticate as nobody — a misconfiguration must be loud at config time, not at first cache write. ACL users with
  `nopass` are therefore not supported; configure a fully authenticated user.
- **`password` already an array is not rewrapped**, so a host that builds the pair itself (e.g. from environment
  variables at runtime) keeps its value verbatim.
- **The DSN path leaves `database` and `prefix` as strings** (`database=3` arrives as `'3'`); the parent casts
  `database` to `int` where it is used (`select()`) exactly as it did for DSN-provided values before.
- **The RedisCluster path is inherited unchanged** — the engine only reshapes config, so whatever `new RedisCluster()`
  does with the array auth argument on a given phpredis version applies there too. Cluster ACL auth is untested here
  (no test cluster in CI); document it in the README rather than bend the one-class design around it.
- **The integration test's Redis has the default user disabled** (`user default off`) and exactly one ACL user, so a
  scalar `auth('aclpass')` cannot succeed by accident: a passing test proves the array form reached Redis. Locally it
  runs behind `REDIS_TEST_URL` against the container in `docker-compose.yml`; in CI against a `redis-server` started
  with the same directives.
- **Upstream gap filed** on `cakephp/cakephp` as [#19792](https://github.com/cakephp/cakephp/issues/19792):
  `RedisEngine` has no `username` key, ignores the DSN username captured by `StaticConfigTrait::parseDsn()`, and calls
  `Redis::auth()` with a scalar only — Redis 6 ACL users are unusable from stock config/DSNs (checked against cakephp
  5.4: `src/Cache/Engine/RedisEngine.php`, `_defaultConfig` and `connectRedis()`). Linked from the README.
