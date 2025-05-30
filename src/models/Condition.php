<?php

namespace brasstacksweb\craftbasicauth\models;

use craft\base\Model;
use craft\helpers\ArrayHelper;
use craft\validators\ArrayValidator;

class Condition extends Model
{
    public bool $enabled = false;
    public string $realm = '';
    public array $environments = [];
    public array $domains = [];
    public string $username = '';
    public string $password = '';
    public array $exceptedPaths = [];
    public array $protectedPaths = [];
    public string $customFailureMessage = '';

    public function getEnvironments(): array
    {
        return ArrayHelper::flatten($this->environments);
    }

    public function rules(): array
    {
        return [
            [['realm'], 'required'],
            [['enabled'], 'boolean'],
            [['realm', 'username', 'password', 'customFailureMessage'], 'string'],
            [['environments', 'domains', 'exceptedPaths', 'protectedPaths'], ArrayValidator::class],
            [['environments', 'domains'], 'validateTriggers', 'skipOnEmpty' => false, 'when' => fn ($model) => $model->enabled],
            [['environments'], 'validateEnvironments'],
            [['domains'], 'validateDomains'],
            [['username', 'password'], 'required', 'when' => fn ($model) => $model->enabled],
            [['exceptedPaths', 'protectedPaths'], 'validatePaths'],
        ];
    }

    public function validateTriggers($attribute, $params): void
    {
        if (count($this->environments) === 0 && count($this->domains) === 0) {
            $this->addError('environments', 'At least one trigger (environment or domain) must be specified.');
            $this->addError('domains', 'At least one trigger (environment or domain) must be specified.');
        }
    }

    public function validateEnvironments($attribute, $params): void
    {
        foreach ($this->{$attribute} as $env) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{2,49}$/', $env)) {
                $this->addError($attribute, "Invalid environment name: {$env}. Must start with a letter, be 3-50 characters, and contain only letters, numbers, hyphens, and underscores.");
            }
        }
    }

    public function validateDomains($attribute, $params): void
    {
        foreach ($this->{$attribute} as $domain) {
            // Basic domain pattern validation
            // Allows patterns like example.com, *.example.com, sub.example.com
            if (!preg_match('/^(\*\.)?[a-zA-Z0-9][-a-zA-Z0-9]*(\.[a-zA-Z0-9][-a-zA-Z0-9]*)+$/', $domain)) {
                $this->addError($attribute, "Invalid domain pattern: {$domain}");
            }
        }
    }

    public function validatePaths($attribute, $params): void
    {
        foreach ($this->{$attribute} as $path) {
            if (substr($path, 0, 1) !== '/') {
                $this->addError($attribute, "Path must start with a forward slash: {$path}");
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
            'username' => 'Username',
            'password' => 'Password',
            'exceptedPaths' => 'Excepted Paths',
            'protectedPaths' => 'Protected Paths',
            'customFailureMessage' => 'Custom Failure Message',
        ];
    }

    public function attributeHints(): array
    {
        return [
            'realm' => 'Unique identifier and HTTP Basic Auth realm (e.g., Development Environment, Staging Sites)',
            'environments' => 'Case-insensitive environment names (e.g., dev, staging, production)',
            'domains' => 'Domain patterns with wildcard support (e.g., *.dev.local, staging.example.com)',
            'exceptedPaths' => 'URI patterns that bypass auth (e.g., /webhooks/*, /api/health, /system/status)',
            'protectedPaths' => 'URI patterns that require auth even if environment/domain not protected',
            'customFailureMessage' => 'Optional custom 401 message',
        ];
    }

    public function getAttributePlaceholder(string $name): string
    {
        return match ($name) {
            'realm' => 'e.g., Development Environment, Staging Sites',
            'environments' => 'e.g., dev, staging, production',
            'domains' => 'e.g., *.dev.local, staging.example.com',
            'username' => 'e.g., dev, staging',
            'password' => 'Password or $ENV_VARIABLE',
            'exceptedPaths' => 'e.g., /webhooks/*, /api/health, /system/',
            'protectedPaths' => 'e.g., /admin/reports/*',
            'customFailureMessage' => 'e.g., Development access required',
            default => '',
        };
    }

    // public function getAttributeWidth(string $name): string
    // {
    //     return match ($name) {
    //         default => '100%',
    //     };
    // }
}
