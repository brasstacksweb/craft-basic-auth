<?php

namespace brasstacksweb\craftbasicauth\tests\unit;

use brasstacksweb\craftbasicauth\BasicAuth;
use brasstacksweb\craftbasicauth\models\Condition;
use brasstacksweb\craftbasicauth\models\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Simplified mock tests for the BasicAuth plugin.
 *
 * @internal
 *
 * @coversNothing
 */
class BasicAuthTest extends TestCase
{
    /**
     * Test creating a plugin mock.
     */
    public function testPluginMock(): void
    {
        // Create a mock of the plugin
        $plugin = $this->getMockBuilder(BasicAuth::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Set up a mock return value for getSettings
        $settings = new Settings();
        $plugin->method('getSettings')
            ->willReturn($settings);

        // Verify the mock works
        $this->assertInstanceOf(BasicAuth::class, $plugin);
        $this->assertInstanceOf(Settings::class, $plugin->getSettings());
    }

    /**
     * Test a condition with enabled flag.
     */
    public function testConditionEnabled(): void
    {
        $condition = new Condition();
        $this->assertFalse($condition->enabled);

        $condition->enabled = true;
        $this->assertTrue($condition->enabled);
    }

    /**
     * Test condition with environment settings.
     */
    public function testConditionEnvironments(): void
    {
        $condition = new Condition();
        $condition->environments = ['development', 'staging'];

        $this->assertCount(2, $condition->environments);
        $this->assertContains('development', $condition->environments);
        $this->assertContains('staging', $condition->environments);
    }

    /**
     * Test condition with domain settings.
     */
    public function testConditionDomains(): void
    {
        $condition = new Condition();
        $condition->domains = ['example.com', '*.test.com', 'staging.*.com', 'dev.*.example.com'];

        $this->assertCount(4, $condition->domains);
        $this->assertContains('example.com', $condition->domains);
        $this->assertContains('*.test.com', $condition->domains);
        $this->assertContains('staging.*.com', $condition->domains);
        $this->assertContains('dev.*.example.com', $condition->domains);
    }

    /**
     * Test condition with path settings.
     */
    public function testConditionPaths(): void
    {
        $condition = new Condition();
        $condition->protectedPaths = ['/admin/*', '/reports'];
        $condition->exceptedPaths = ['/admin/public', '/api/*'];

        $this->assertCount(2, $condition->protectedPaths);
        $this->assertCount(2, $condition->exceptedPaths);
        $this->assertContains('/admin/*', $condition->protectedPaths);
        $this->assertContains('/api/*', $condition->exceptedPaths);
    }

    /**
     * Test settings with conditions.
     */
    public function testSettingsWithConditions(): void
    {
        $settings = new Settings();

        // Create test conditions
        $condition1 = new Condition([
            'enabled' => true,
            'realm' => 'Test Realm 1',
            'environments' => ['development'],
            'username' => 'user1',
            'password' => 'pass1',
        ]);

        $condition2 = new Condition([
            'enabled' => false,
            'realm' => 'Test Realm 2',
            'domains' => ['test.com'],
            'username' => 'user2',
            'password' => 'pass2',
        ]);

        $settings->conditions = [$condition1, $condition2];

        // Verify conditions
        $this->assertCount(2, $settings->conditions);
        $this->assertTrue($settings->conditions[0]->enabled);
        $this->assertFalse($settings->conditions[1]->enabled);
        $this->assertEquals('Test Realm 1', $settings->conditions[0]->realm);
        $this->assertEquals('Test Realm 2', $settings->conditions[1]->realm);
    }
}
