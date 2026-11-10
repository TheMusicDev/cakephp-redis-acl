<?php
declare(strict_types=1);

namespace TheMusicDev\RedisAcl\Test\TestCase\Cache\Engine;

use Cake\Cache\Cache;
use Cake\Core\Exception\CakeException;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Redis;
use ReflectionProperty;
use TheMusicDev\RedisAcl\Cache\Engine\AclRedisEngine;

/**
 * The tests that justify the plugin: `auth()` must receive the ACL array form when `username` is
 * configured, and keep the stock scalar form when it is not.
 *
 * The engine is subclassed to inject a mock \Redis through the parent's protected
 * `_createRedisInstance()` seam, so no real server is needed. An integration test against a real
 * Redis with a real ACL user (and the default user disabled, so a scalar auth cannot succeed by
 * accident) runs when REDIS_TEST_URL is set — locally via `docker compose up -d --wait redistest`,
 * in CI via the redis step in the workflow.
 */
final class AclRedisEngineTest extends TestCase
{
    private Redis|MockObject $redis;

    /**
     * @var array<int, mixed>
     */
    private array $authArgs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->authArgs = [];
    }

    private function spy(): SpyAclRedisEngine
    {
        $this->redis = $this->getMockBuilder(Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->redis->method('pconnect')->willReturn(true);
        $this->redis->method('select')->willReturn(true);

        $engine = new SpyAclRedisEngine();
        $engine->redis = $this->redis;

        return $engine;
    }

    private function recordingAuth(): void
    {
        $this->redis->expects($this->once())->method('auth')->willReturnCallback(function (mixed ...$args): bool {
            $this->authArgs = $args;

            return true;
        });
    }

    /**
     * @param array<string, class-string> $map
     */
    private function resetDsnMap(array $map): void
    {
        $property = new ReflectionProperty(Cache::class, '_dsnClassMap');
        $property->setValue(null, $map);
    }

    public function testUsernameConfiguredReceivesArrayForm(): void
    {
        $engine = $this->spy();
        $this->recordingAuth();

        $this->assertSame(true, $engine->init([
            'prefix' => 'acl_',
            'username' => 'acluser',
            'password' => 'secretpass',
        ]));

        // One call, array form, [username, password] order.
        $this->assertSame([['acluser', 'secretpass']], $this->authArgs);
    }

    public function testNoUsernameKeepsScalarForm(): void
    {
        $engine = $this->spy();
        $this->recordingAuth();

        $this->assertSame(true, $engine->init([
            'prefix' => 'acl_',
            'password' => 'secretpass',
        ]));

        // One call, the stock scalar form — the regression guard for no-ACL deployments.
        $this->assertSame(['secretpass'], $this->authArgs);
    }

    public function testNoPasswordNoUsernameNeverCallsAuth(): void
    {
        $engine = $this->spy();
        $this->redis->expects($this->never())->method('auth');

        $this->assertSame(true, $engine->init(['prefix' => 'acl_']));
    }

    public function testPasswordPassedAsArrayIsNotRewrapped(): void
    {
        $engine = $this->spy();
        $this->recordingAuth();

        $this->assertSame(true, $engine->init([
            'prefix' => 'acl_',
            'username' => 'acluser',
            'password' => ['acluser', 'secretpass'],
        ]));

        $this->assertSame([['acluser', 'secretpass']], $this->authArgs);
    }

    public function testUsernameWithoutPasswordThrows(): void
    {
        $engine = $this->spy();
        $this->redis->expects($this->never())->method('auth');

        $this->expectException(CakeException::class);
        $this->expectExceptionMessage('ACL misconfiguration');

        $engine->init(['prefix' => 'acl_', 'username' => 'acluser']);
    }

    /**
     * Integration: a real Redis whose default user is disabled, reached only through the ACL user.
     *
     * The plugin's own bootstrap remap is applied first, exactly as a host load does, so the `redis://`
     * URL resolves to this plugin's engine instead of the stock one — with the stock engine the scalar
     * auth would fail on this server (default user disabled), which is the whole point of the test.
     */
    public function testRealServerAclAuth(): void
    {
        $url = env('REDIS_TEST_URL');
        if (!$url) {
            $this->markTestSkipped('REDIS_TEST_URL is not set.');
        }

        $originalMap = Cache::getDsnClassMap();
        require ROOT . '/config/bootstrap.php';

        try {
            Cache::setConfig('acl_test_integration', [
                'url' => $url,
                'duration' => 60,
            ]);

            $this->assertSame(
                AclRedisEngine::class,
                Cache::pool('acl_test_integration')::class,
            );
            $this->assertSame(true, Cache::write('probe', 'ok', 'acl_test_integration'));
            $this->assertSame('ok', Cache::read('probe', 'acl_test_integration'));
        } finally {
            $this->resetDsnMap($originalMap);
        }
    }
}
