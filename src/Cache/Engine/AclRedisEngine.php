<?php
declare(strict_types=1);

namespace TheMusicDev\RedisAcl\Cache\Engine;

use Cake\Cache\Engine\RedisEngine;
use Cake\Core\Exception\CakeException;

/**
 * RedisEngine that authenticates as a Redis 6 ACL user.
 *
 * The stock engine passes `password` to Redis::auth() as a scalar and has no `username` key at all, so a
 * Redis server running ACL users (username + password) cannot be used from a config or a `redis://` DSN.
 * phpredis has accepted the array form `auth([username, password])` since 5.3.0; this class reshapes the
 * `password` key into that array before `parent::init()` connects. Everything else — TLS, persistence,
 * clustering, DSN handling — is inherited unchanged, so it cannot rot against upstream.
 *
 * To be deleted the day cakephp/cakephp consumes the DSN username natively.
 */
class AclRedisEngine extends RedisEngine
{
    /**
     * Reshape ACL credentials before the parent connects.
     *
     * A `username` set without a `password` is a misconfiguration: an empty-password array auth would
     * reach Redis and fail per-request, or authenticate as nobody. It throws instead, so it is loud
     * at cache-config time, not at first cache write. (ACL users with `nopass` are not supported.)
     *
     * @param array<array-key, mixed> $config array of setting for the engine
     * @return bool True if the engine has been successfully initialized, false if not
     */
    public function init(array $config = []): bool
    {
        $username = $config['username'] ?? false;
        if ($username) {
            if (empty($config['password'])) {
                throw new CakeException(
                    'Redis ACL misconfiguration: `username` is set but `password` is missing or empty. ' .
                    'Configure a fully authenticated ACL user, or drop `username`.',
                );
            }
            if (!is_array($config['password'])) {
                $config['password'] = [(string)$username, (string)$config['password']];
            }
        }

        return parent::init($config);
    }
}
