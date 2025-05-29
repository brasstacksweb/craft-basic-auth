<?php

namespace brasstacksweb\craftbasicauth;

use brasstacksweb\craftbasicauth\models\Settings;
use craft\base\Model;
use craft\base\Plugin;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\web\Response;
use yii\base\Event;
use yii\web\UserEvent;

/**
 * Craft Basic Auth plugin.
 *
 * @method static BasicAuth getInstance()
 * @method        Settings  getSettings()
 *
 * @author Brass Tacks Web <help@brasstacksweb.com>
 * @copyright Brass Tacks Web
 * @license https://craftcms.github.io/license/ Craft License
 */
class BasicAuth extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $request = \Craft::$app->getRequest();

        // Skip auth check for console requests and CP requests
        if ($request->getIsConsoleRequest() || $request->getIsCpRequest()) {
            return;
        }

        // Check if any rules are configured
        $settings = $this->getSettings();
        $activeRules = $settings->getActiveRules();

        if (count($activeRules) === 0) {
            return;
        }

        // Process authentication on beginRequest
        \Craft::$app->onInit(function() use ($activeRules) {
            $this->processAuthentication($activeRules);
        });
    }

    /**
     * Process authentication for active rules
     */
    protected function processAuthentication(array $activeRules): void
    {
        $request = \Craft::$app->getRequest();
        $response = \Craft::$app->getResponse();
        $user = \Craft::$app->getUser();
        $currentPath = $request->getPathInfo();
        $userAgent = $request->getUserAgent();
        $clientIp = $request->getUserIP();

        foreach ($activeRules as $key => $rule) {
            // Skip if not enabled
            if (!$rule->enabled) {
                continue;
            }

            // Check bypass for logged-in users
            if ($rule->bypassForLoggedInUsers && !$user->getIsGuest()) {
                continue;
            }

            // Check IP whitelist
            foreach ($rule->ipWhitelist as $ip) {
                if ($this->matchIpAddress($ip, $clientIp)) {
                    continue 2; // Skip to next rule
                }
            }

            // Check User-Agent exceptions
            foreach ($rule->userAgentExceptions as $pattern) {
                if ($this->matchWildcardPattern($pattern, $userAgent)) {
                    continue 2; // Skip to next rule
                }
            }

            // Check excepted paths
            foreach ($rule->exceptedPaths as $path) {
                if ($this->matchWildcardPattern($path, '/' . $currentPath)) {
                    continue 2; // Skip to next rule
                }
            }

            // Check protected paths
            $requiresAuth = false;
            foreach ($rule->protectedPaths as $path) {
                if ($this->matchWildcardPattern($path, '/' . $currentPath)) {
                    $requiresAuth = true;
                    break;
                }
            }

            // If no protected path match, check if rule applies
            if (!$requiresAuth) {
                // Rule has already been determined to be active in getActiveRules()
                $requiresAuth = true;
            }

            // If authentication is required, check credentials
            if ($requiresAuth) {
                $this->requireAuthentication($rule, $response);
            }
        }
    }

    /**
     * Require HTTP Basic Authentication
     */
    protected function requireAuthentication($rule, $response): void
    {
        $username = $_SERVER['PHP_AUTH_USER'] ?? null;
        $password = $_SERVER['PHP_AUTH_PW'] ?? null;

        // Parse environment variables in password
        $expectedPassword = $rule->password;
        if (strpos($expectedPassword, '$') === 0) {
            $envVar = substr($expectedPassword, 1);
            $expectedPassword = App::env($envVar) ?? $expectedPassword;
        }

        // Check credentials
        if ($username === $rule->username && $password === $expectedPassword) {
            // Authentication successful
            return;
        }

        // Authentication failed, send 401 response
        $failureMessage = $rule->customFailureMessage ?: 'Authentication required';
        
        header('WWW-Authenticate: Basic realm="' . $rule->realm . '"');
        header('HTTP/1.0 401 Unauthorized');
        echo $failureMessage;
        exit;
    }

    /**
     * Match a wildcard pattern against a string
     */
    protected function matchWildcardPattern(string $pattern, string $string): bool
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        
        return (bool) preg_match('/^' . $pattern . '$/i', $string);
    }

    /**
     * Match an IP address against a pattern (including wildcards and CIDR)
     */
    protected function matchIpAddress(string $pattern, string $ip): bool
    {
        // Check for CIDR notation
        if (strpos($pattern, '/') !== false) {
            list($subnet, $bits) = explode('/', $pattern);
            $ip2long = ip2long($ip);
            $subnet2long = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnet2long &= $mask;
            
            return ($ip2long & $mask) === $subnet2long;
        }
        // Check for wildcard notation
        elseif (strpos($pattern, '*') !== false) {
            $ipParts = explode('.', $ip);
            $patternParts = explode('.', $pattern);
            
            if (count($ipParts) !== 4 || count($patternParts) !== 4) {
                return false;
            }
            
            for ($i = 0; $i < 4; $i++) {
                if ($patternParts[$i] !== '*' && $patternParts[$i] !== $ipParts[$i]) {
                    return false;
                }
            }
            
            return true;
        }
        // Exact match
        else {
            return $ip === $pattern;
        }
    }

    /**
     * Create the settings model
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * Render the settings HTML
     */
    protected function settingsHtml(): ?string
    {
        return \Craft::$app->view->renderTemplate('craft-basic-auth/_settings.twig', [
            'settings' => $this->getSettings(),
        ]);
    }
}