<?php
declare(strict_types=1);

namespace TheMusicDev\RedisAcl;

use Cake\Core\BasePlugin;

/**
 * TheMusicDev/RedisAcl: a RedisEngine subclass that authenticates as a Redis 6 ACL user.
 * The plugin's only configuration is the DSN scheme remap in config/bootstrap.php; the engine
 * is the single class. Meant to be deleted the day cakephp/cakephp ships ACL username support.
 */
final class RedisAclPlugin extends BasePlugin
{
}
