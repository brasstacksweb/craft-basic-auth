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

    /**
     * Test no active conditions means no authentication required.
     */
    public function testNoActiveConditions(): void
    {
        // Setup a request that doesn't match any conditions
        $this->request->method('getHostName')->willReturn('example.org');
        $this->request->method('getPathInfo')->willReturn('some/path');

        // Create conditions that won't match this request
        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'environments' => ['production'],
            'domains' => ['test.com'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Challenge callback
        $challengeCalled = false;
        $challenge = function() use (&$challengeCalled) {
            $challengeCalled = true;
        };

        // Test with environment that doesn't match
        $this->service->checkRequest('development', $this->request, [$condition], $challenge);

        // Verify challenge was not called
        $this->assertFalse($challengeCalled);
    }

    /**
     * Test environment match triggers authentication.
     */
    public function testEnvironmentMatch(): void
    {
        // Setup a request
        $this->request->method('getHostName')->willReturn('example.org');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = null;
        $this->mockAuthPassword = null;

        // Create condition that matches by environment
        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'environments' => ['development'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Challenge callback
        $challengeCalled = false;
        $challengedRealm = '';
        $challenge = function(Condition $c) use (&$challengeCalled, &$challengedRealm) {
            $challengeCalled = true;
            $challengedRealm = $c->realm;
        };

        // Test with matching environment
        $this->service->checkRequest('development', $this->request, [$condition], $challenge);

        // Verify challenge was called
        $this->assertTrue($challengeCalled);
        $this->assertEquals('Test Realm', $challengedRealm);
    }

    /**
     * Test domain match triggers authentication.
     */
    public function testDomainMatch(): void
    {
        // Setup a request
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = null;
        $this->mockAuthPassword = null;

        // Create condition that matches by domain
        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Challenge callback
        $challengeCalled = false;
        $challenge = function() use (&$challengeCalled) {
            $challengeCalled = true;
        };

        // Test with matching domain
        $this->service->checkRequest('production', $this->request, [$condition], $challenge);

        // Verify challenge was called
        $this->assertTrue($challengeCalled);
    }

    /**
     * Test protected path triggers authentication.
     */
    public function testProtectedPathMatch(): void
    {
        // Setup a request
        $this->request->method('getHostName')->willReturn('example.org');
        $this->request->method('getPathInfo')->willReturn('admin/reports');
        $this->mockAuthUser = null;
        $this->mockAuthPassword = null;

        // Create condition that matches by protected path
        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['test.com'], // Domain doesn't match
            'environments' => ['staging'], // Environment doesn't match
            'protectedPaths' => ['/admin/*'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Challenge callback
        $challengeCalled = false;
        $challenge = function() use (&$challengeCalled) {
            $challengeCalled = true;
        };

        // Test with matching protected path
        $this->service->checkRequest('production', $this->request, [$condition], $challenge);

        // Verify challenge was called
        $this->assertTrue($challengeCalled);
    }

    /**
     * Test excepted path bypasses authentication.
     */
    public function testExceptedPathBypass(): void
    {
        // Setup a request
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('api/health');

        // Create condition that would match by domain but has excepted path
        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'], // Domain matches
            'exceptedPaths' => ['/api/*'], // But path is excepted
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Challenge callback
        $challengeCalled = false;
        $challenge = function() use (&$challengeCalled) {
            $challengeCalled = true;
        };

        // Test with excepted path
        $this->service->checkRequest('production', $this->request, [$condition], $challenge);

        // Verify challenge was not called
        $this->assertFalse($challengeCalled);
    }

    /**
     * Test successful authentication.
     */
    public function testSuccessfulAuthentication(): void
    {
        // Setup a request with valid credentials
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = 'user';
        $this->mockAuthPassword = 'pass';

        // Create condition
        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Challenge callback (should not be called)
        $challengeCalled = false;
        $challenge = function($c) use (&$challengeCalled) {
            $challengeCalled = true;
        };

        // Test with valid credentials
        $this->service->checkRequest('production', $this->request, [$condition], $challenge);

        // Verify challenge was not called
        $this->assertFalse($challengeCalled);
    }

    /**
     * Test failed authentication.
     */
    public function testFailedAuthentication(): void
    {
        // Setup a request with invalid credentials
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = 'wrong';
        $this->mockAuthPassword = 'wrong';

        // Create condition
        $condition = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'],
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Challenge callback
        $challengeCalled = false;
        $challenge = function() use (&$challengeCalled) {
            $challengeCalled = true;
        };

        // Test with invalid credentials
        $this->service->checkRequest('production', $this->request, [$condition], $challenge);

        // Verify challenge was called
        $this->assertTrue($challengeCalled);
    }

    /**
     * Test multiple conditions with first one passing.
     */
    public function testMultipleConditionsFirstPasses(): void
    {
        // Setup a request with valid credentials for first condition
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = 'user1';
        $this->mockAuthPassword = 'pass1';

        // Create conditions
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

        // Challenge callback (should not be called)
        $challengeCalled = false;
        $challenge = function() use (&$challengeCalled) {
            $challengeCalled = true;
        };

        // Test with valid credentials for first condition
        $this->service->checkRequest('production', $this->request, [$condition1, $condition2], $challenge);

        // Verify challenge was not called
        $this->assertFalse($challengeCalled);
    }

    /**
     * Test multiple conditions with all failing.
     */
    public function testMultipleConditionsAllFail(): void
    {
        // Setup a request with invalid credentials
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');
        $this->mockAuthUser = 'wrong';
        $this->mockAuthPassword = 'wrong';

        // Create conditions
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

        // Challenge callback - should receive the first condition
        $challengedRealm = '';
        $challenge = function(Condition $c) use (&$challengedRealm) {
            $challengedRealm = $c->realm;
        };

        // Test with invalid credentials for all conditions
        $this->service->checkRequest('production', $this->request, [$condition1, $condition2], $challenge);

        // Verify first condition was used for challenge
        $this->assertEquals('Test Realm 1', $challengedRealm);
    }

    /**
     * Test disabled condition is ignored.
     */
    public function testDisabledCondition(): void
    {
        // Setup a request that would match by domain
        $this->request->method('getHostName')->willReturn('dev.test.com');
        $this->request->method('getPathInfo')->willReturn('some/path');

        // Create disabled condition
        $condition = new Condition([
            'enabled' => false, // Disabled
            'realm' => 'Test Realm',
            'domains' => ['*.test.com'], // Would match
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Challenge callback
        $challengeCalled = false;
        $challenge = function() use (&$challengeCalled) {
            $challengeCalled = true;
        };

        // Test with disabled condition
        $this->service->checkRequest('production', $this->request, [$condition], $challenge);

        // Verify challenge was not called
        $this->assertFalse($challengeCalled);
    }
}
