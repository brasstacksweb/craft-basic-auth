# Craft Basic Auth

A flexible and powerful Craft CMS plugin that adds HTTP Basic Authentication to your websites with intelligent rule-based configuration.

## Overview
This plugin allows you to protect your Craft CMS sites with HTTP Basic Authentication based on flexible trigger conditions including environment names and domain patterns. Perfect for protecting development, staging, or specific production environments while maintaining fine-grained control over what gets protected.

![Settings screenshot](/docs/images/settings.png "Settings")

### Key Features

- Flexible Rule System: Create authentication rules triggered by environment or domain patterns
- Environment-Aware: Automatically detects current environment using `CRAFT_ENVIRONMENT`
- Smart Protection: Define protected and excepted paths with wildcard pattern support
- Secure Credentials: Password fields support Craft's environment variable syntax

### Perfect For

- Development Teams: Protect staging environments while allowing webhook access
- Agencies: Manage authentication across multiple client sites from one interface
- Multi-site Setups: Different authentication rules for different sites in the same Craft installation
- Security-Conscious Sites: Add an extra layer of protection to specific admin areas

## Requirements

This plugin requires Craft CMS 5.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Basic Auth”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require brasstacksweb/craft-basic-auth

# tell Craft to install the plugin
./craft plugin/install craft-basic-auth
```

## Configuration

Configure authentication rules through the plugin's settings page in your Craft control panel. Each rule can be triggered by:

- Environment names (e.g., dev, staging, production)
- Domain patterns (e.g., \*.staging.com, dev.example.com)

Rules support wildcard patterns, path exceptions, and custom failure messages.

![New Condition screenshot](/docs/images/condition.png "New Condition")

## Testing

This plugin includes a test suite to verify its functionality.

### Running Tests

```bash
# Install dev dependencies if you haven't already
composer install

# Run all tests
composer test
```

### What's Covered

The test suite covers:

1. **Plugin Tests** - Uses PHPUnit mocks to simulate Craft dependencies:
   - Condition matching logic
   - Path protection and exception handling
   - Authentication functionality
