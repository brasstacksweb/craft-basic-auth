<?php

namespace brasstacksweb\craftbasicauth\models;

use craft\base\Model;
use craft\validators\ArrayValidator;

class Condition extends Model
{
    // Basic settings
    public bool $enabled = false;
    public string $realm = 'New Authentication Rule';
    
    // Trigger conditions
    public array $environments = [];
    public array $domains = [];
    public array $sites = [];
    
    // Authentication
    public string $username = '';
    public string $password = '';
    
    // Bypass settings
    public array $ipWhitelist = [];
    public array $userAgentExceptions = ['*bot*', '*crawler*'];
    public array $exceptedPaths = ['/webhooks/*'];
    public array $protectedPaths = [];
    public string $customFailureMessage = '';
    public bool $bypassForLoggedInUsers = false;

    public function rules(): array
    {
        return [
            [['realm'], 'required'],
            [['enabled', 'bypassForLoggedInUsers'], 'boolean'],
            [['realm', 'username', 'password', 'customFailureMessage'], 'string'],
            [['environments', 'domains', 'sites', 'ipWhitelist', 'userAgentExceptions', 'exceptedPaths', 'protectedPaths'], ArrayValidator::class],
            
            // Rule to validate that at least one trigger condition is specified when enabled
            [['environments', 'domains', 'sites'], 'validateTriggerConditions', 'when' => function($model) {
                return $model->enabled;
            }],
            
            // Validate credentials are provided when enabled
            [['username', 'password'], 'required', 'when' => function($model) {
                return $model->enabled;
            }],
            
            // Domain pattern validation
            ['domains', 'validateDomainPatterns'],
            
            // IP address validation
            ['ipWhitelist', 'validateIpAddresses'],
            
            // Path pattern validation
            [['exceptedPaths', 'protectedPaths'], 'validatePathPatterns'],
            
            // Environment name validation
            ['environments', 'validateEnvironmentNames'],
        ];
    }
    
    public function validateTriggerConditions($attribute, $params): void
    {
        if (empty($this->environments) && empty($this->domains) && empty($this->sites)) {
            $this->addError('triggerConditions', 'At least one trigger condition (environment, domain, or site) must be specified.');
        }
    }
    
    public function validateDomainPatterns($attribute, $params): void
    {
        foreach ($this->domains as $domain) {
            // Basic domain pattern validation
            // Allows patterns like example.com, *.example.com, sub.example.com
            if (!preg_match('/^(\*\.)?[a-zA-Z0-9][-a-zA-Z0-9]*(\.[a-zA-Z0-9][-a-zA-Z0-9]*)+$/', $domain)) {
                $this->addError($attribute, "Invalid domain pattern: {$domain}");
            }
        }
    }
    
    public function validateIpAddresses($attribute, $params): void
    {
        foreach ($this->ipWhitelist as $ip) {
            // Check for CIDR notation
            if (strpos($ip, '/') !== false) {
                list($base, $cidr) = explode('/', $ip, 2);
                if (!filter_var($base, FILTER_VALIDATE_IP) || !is_numeric($cidr) || $cidr < 0 || $cidr > 32) {
                    $this->addError($attribute, "Invalid CIDR IP address: {$ip}");
                }
            } 
            // Check for wildcard notation
            elseif (strpos($ip, '*') !== false) {
                $ipParts = explode('.', $ip);
                if (count($ipParts) !== 4 || array_filter($ipParts, function($part) {
                    return $part !== '*' && (!is_numeric($part) || $part < 0 || $part > 255);
                })) {
                    $this->addError($attribute, "Invalid wildcard IP address: {$ip}");
                }
            } 
            // Regular IP address
            elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->addError($attribute, "Invalid IP address: {$ip}");
            }
        }
    }
    
    public function validatePathPatterns($attribute, $params): void
    {
        foreach ($this->$attribute as $path) {
            if (substr($path, 0, 1) !== '/') {
                $this->addError($attribute, "Path must start with a forward slash: {$path}");
            }
        }
    }
    
    public function validateEnvironmentNames($attribute, $params): void
    {
        foreach ($this->environments as $env) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{2,49}$/', $env)) {
                $this->addError($attribute, "Invalid environment name: {$env}. Must start with a letter, be 3-50 characters, and contain only letters, numbers, hyphens, and underscores.");
            }
        }
    }
    
    public function attributeLabels(): array
    {
        return [
            'enabled' => 'Enable Basic Auth',
            'realm' => 'Realm Name',
            'environments' => 'Environments',
            'domains' => 'Domains',
            'sites' => 'Sites',
            'username' => 'Username',
            'password' => 'Password',
            'ipWhitelist' => 'IP Whitelist',
            'userAgentExceptions' => 'User-Agent Exceptions',
            'exceptedPaths' => 'Excepted Paths',
            'protectedPaths' => 'Protected Paths',
            'customFailureMessage' => 'Custom Failure Message',
            'bypassForLoggedInUsers' => 'Bypass for Logged-in Users',
        ];
    }

    public function attributeHints(): array
    {
        return [
            'realm' => 'Unique identifier and HTTP Basic Auth realm (e.g., Development Environment, Staging Sites)',
            'environments' => 'Case-insensitive environment names (e.g., dev, staging, production)',
            'domains' => 'Domain patterns with wildcard support (e.g., *.dev.local, staging.example.com)',
            'sites' => 'Craft site handles',
            'ipWhitelist' => 'IP addresses/patterns that bypass auth (e.g., 127.0.0.1, 192.168.1.*, 10.0.0.0/8)',
            'userAgentExceptions' => 'User-Agent patterns that bypass auth (e.g., *bot*, *crawler*, *monitor*)',
            'exceptedPaths' => 'URI patterns that bypass auth (e.g., /webhooks/*, /api/health, /system/status)',
            'protectedPaths' => 'URI patterns that require auth even if environment/domain not protected',
            'customFailureMessage' => 'Optional custom 401 message',
            'bypassForLoggedInUsers' => 'Skip authentication for users already logged in to Craft CMS',
        ];
    }

    public function getAttributeType(string $name): string
    {
        return match($name) {
            'enabled', 'bypassForLoggedInUsers' => 'lightswitch',
            'realm', 'username', 'customFailureMessage' => 'singleline',
            'password' => 'password',
            'environments', 'domains', 'ipWhitelist', 'userAgentExceptions', 'exceptedPaths', 'protectedPaths' => 'autosuggest',
            'sites' => 'multiselect',
            default => 'singleline',
        };
    }

    public function getAttributePlaceholder(string $name): string
    {
        return match($name) {
            'realm' => 'e.g., Development Environment, Staging Sites',
            'environments' => 'e.g., dev, staging, production',
            'domains' => 'e.g., *.dev.local, staging.example.com',
            'username' => 'e.g., dev, staging',
            'password' => 'Password or $ENV_VARIABLE',
            'ipWhitelist' => 'e.g., 127.0.0.1, 192.168.1.*, 10.0.0.0/8',
            'userAgentExceptions' => 'e.g., *bot*, *crawler*, *monitor*',
            'exceptedPaths' => 'e.g., /webhooks/*, /api/health, /system/',
            'protectedPaths' => 'e.g., /admin/reports/*',
            'customFailureMessage' => 'e.g., Development access required',
            default => '',
        };
    }

    public function getAttributeWidth(string $name): string
    {
        return match($name) {
            'realm' => '40%',
            'enabled', 'bypassForLoggedInUsers' => '10%',
            default => '100%',
        };
    }
}