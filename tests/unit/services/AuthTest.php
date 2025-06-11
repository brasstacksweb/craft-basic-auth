<?php

namespace brasstacksweb\craftbasicauth\tests\unit\services;

use brasstacksweb\craftbasicauth\models\Condition;
use brasstacksweb\craftbasicauth\services\Auth;
use craft\web\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class AuthTest extends TestCase
{
    private Auth $service;
    private MockObject $request;
    private ?string $mockAuthUser = '';
    private ?string $mockAuthPassword = '';

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new Auth();

        // Create request mock that intercepts property access using __get
        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getHostName', 'getPathInfo', '__get'])
            ->getMock();

        // Set up __get to return our mock auth credentials
        $this->request->method('__get')
            ->willReturnCallback(function($name) {
                if ($name === 'authUser') {
                    return $this->mockAuthUser;
                }
                if ($name === 'authPassword') {
                    return $this->mockAuthPassword;
                }

                return null;
            });
    }

    public function testNoActiveConditions(): void
    {
        $this->request->method('getHostName')->willReturn('example.org');
        $this->request->method('getPathInfo')->willReturn('some/path');

        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'environments' => ['dev'],
            'domains' => ['test.com'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition]);

        $this->assertNull($activeCondition);
    }

    public function testEnvironmentMatchNoCredentials(): void
    {
        $this->request->method('getHostName')->willReturn('example.org');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = null;
        $this->mockAuthPassword = null;

        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'environments' => ['dev'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        $activeCondition = $this->service->getActiveCondition('dev', $this->request, [$condition]);

        $this->assertNotNull($activeCondition);
        $this->assertEquals('Test Realm', $activeCondition->realm);
    }

    public function testDomainMatchNoCredentials(): void
    {
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = null;
        $this->mockAuthPassword = null;

        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition]);

        $this->assertNotNull($activeCondition);
    }

    public function testProtectedPathMatchNoCredentials(): void
    {
        $this->request->method('getHostName')->willReturn('example.org');
        $this->request->method('getPathInfo')->willReturn('admin/reports');
        $this->mockAuthUser = null;
        $this->mockAuthPassword = null;

        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['test.com'], // Domain doesn't match
            'environments' => ['staging'], // Environment doesn't match
            'protectedPaths' => ['/admin/*'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition]);

        $this->assertNotNull($activeCondition);
    }

    public function testExceptedPathBypass(): void
    {
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('api/health');

        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'], // Domain matches
            'exceptedPaths' => ['/api/*'], // But path is excepted
            'username' => 'user',
            'password' => 'pass',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition]);

        $this->assertNull($activeCondition);
    }

    public function testSuccessfulAuthentication(): void
    {
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = 'user';
        $this->mockAuthPassword = 'pass';

        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition]);

        $this->assertNull($activeCondition);
    }

    public function testFailedAuthentication(): void
    {
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = 'wrong';
        $this->mockAuthPassword = 'wrong';

        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition]);

        $this->assertNotNull($activeCondition);
        $this->assertEquals('Test Realm', $activeCondition->realm);
    }

    public function testMultipleConditionsFirstPasses(): void
    {
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = 'user1';
        $this->mockAuthPassword = 'pass1';

        $condition1 = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm 1',
            'domains' => ['*.test.com'],
            'username' => 'user1',
            'password' => 'pass1',
        ]);

        $condition2 = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm 2',
            'domains' => ['*.test.com'],
            'username' => 'user2',
            'password' => 'pass2',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition1, $condition2]);

        $this->assertNull($activeCondition);
    }

    public function testMultipleConditionsAllFail(): void
    {
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = 'wrong';
        $this->mockAuthPassword = 'wrong';

        $condition1 = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm 1',
            'domains' => ['*.test.com'],
            'username' => 'user1',
            'password' => 'pass1',
        ]);

        $condition2 = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm 2',
            'domains' => ['*.test.com'],
            'username' => 'user2',
            'password' => 'pass2',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition1, $condition2]);

        $this->assertNotNull($activeCondition);
        $this->assertEquals('Test Realm 1', $activeCondition->realm);
    }

    public function testDisabledCondition(): void
    {
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');

        $condition = new Condition([
            'enabled' => false, // Disabled
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'], // Would match
            'username' => 'user',
            'password' => 'pass',
        ]);

        $activeCondition = $this->service->getActiveCondition('production', $this->request, [$condition]);

        $this->assertNull($activeCondition);
    }
}
