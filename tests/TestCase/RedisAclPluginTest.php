<?php
declare(strict_types=1);

namespace TheMusicDev\RedisAcl\Test\TestCase;

use Cake\Cache\Cache;
use Cake\Cache\Engine\RedisEngine;
use Cake\TestSuite\TestCase;
use ReflectionProperty;
use TestApp\Application;
use TheMusicDev\RedisAcl\Cache\Engine\AclRedisEngine;

/**
 * The plugin loads in an application and the `redis` DSN scheme ends up on the ACL engine,
 * without ever clobbering a host's own mapping.
 */
final class RedisAclPluginTest extends TestCase
{
    /**
     * @var array<string, class-string>
     */
    private array $originalMap;

    /**
     * @param array<string, class-string> $map
     * @return void
     */
    private function setMap(array $map): void
    {
        $property = new ReflectionProperty(Cache::class, '_dsnClassMap');
        $property->setValue(null, $map);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $property = new ReflectionProperty(Cache::class, '_dsnClassMap');
        $this->originalMap = $property->getValue();
    }

    protected function tearDown(): void
    {
        $this->setMap($this->originalMap);
        parent::tearDown();
    }

    private function boot(): void
    {
        $app = new Application(CONFIG);
        $app->bootstrap();
        $app->pluginBootstrap();
    }

    public function testBootstrapRemapsTheStockRedisScheme(): void
    {
        $this->boot();

        $this->assertSame(AclRedisEngine::class, Cache::getDsnClassMap()['redis']);
    }

    public function testBootstrapLeavesAHostMappingAlone(): void
    {
        $this->setMap(['redis' => 'Vendor\\CustomRedisEngine']);

        $this->boot();

        $this->assertSame('Vendor\\CustomRedisEngine', Cache::getDsnClassMap()['redis']);
    }

    public function testDsnUrlProducesTheAclEngine(): void
    {
        $this->boot();

        Cache::setConfig('acl_dsn_test', [
            'url' => 'redis://user:pass@localhost:6379?prefix=x&database=3',
        ]);

        $config = Cache::getConfig('acl_dsn_test');

        $this->assertSame(AclRedisEngine::class, $config['className']);
        $this->assertSame('user', $config['username']);
        $this->assertSame('pass', $config['password']);
        $this->assertSame('x', $config['prefix']);
        $this->assertSame('3', $config['database']);
    }

    public function testDiscreteClassNameNextToAUrlWouldHaveBeenDiscardedWithoutTheRemap(): void
    {
        // The reason the remap exists: without it, `className` next to a `url` is silently dropped.
        $parsed = Cache::parseDsn('redis://user:pass@localhost:6379');
        $this->assertSame(RedisEngine::class, $parsed['className']);
    }
}
