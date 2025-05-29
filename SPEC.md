# Craft CMS Basic Authentication Plugin Specification

## Overview

A Craft CMS plugin that provides configurable HTTP Basic Authentication for websites based on flexible trigger conditions including environment, domain, and site matching.

## Core Features

- **Unified Rule System**: Single configuration structure for all authentication rules
- **Flexible Triggers**: Rules can be triggered by environment, domain patterns, or Craft sites
- **Wildcard Pattern Matching**: Support for domain and path pattern matching
- **Environment Variable Integration**: Password fields support Craft's environment variable syntax
- **IP Whitelisting**: Bypass authentication for specific IP addresses
- **User-Agent Exceptions**: Skip authentication for specific user agents (bots, crawlers)
- **Path Management**: Define excepted and protected paths per rule
- **Logged-in User Bypass**: Optional bypass for users already authenticated in Craft CMS

## Configuration Structure

### Settings Storage
Configuration is stored in Craft CMS `project.yaml` file through the plugin settings interface.

### Rule Configuration Schema

```yaml
plugins:
  your-basic-auth-plugin:
    settings:
      authRules:
        "rule-realm-name":
          enabled: boolean
          realm: string # Unique identifier and HTTP Basic Auth realm
          triggerConditions:
            environments: array # Case-insensitive environment names
            domains: array # Domain patterns with wildcard support
            sites: array # Craft site handles
          credentials:
            username: string
            password: string # Supports Craft environment variable syntax ($VAR_NAME)
          ipWhitelist: array # IP addresses/patterns that bypass auth
          userAgentExceptions: array # User-Agent patterns that bypass auth
          exceptedPaths: array # URI patterns that bypass auth
          protectedPaths: array # URI patterns that require auth even if environment/domain not protected
          customFailureMessage: string # Optional custom 401 message
          bypassForLoggedInUsers: boolean # Skip auth for Craft-authenticated users
```

### Example Configuration

```yaml
plugins:
  your-basic-auth-plugin:
    settings:
      authRules:
        "Development Environment":
          enabled: true
          realm: "Development Environment"
          triggerConditions:
            environments: ["dev", "development", "local"]
            domains: ["*.dev.local", "dev.example.com"]
            sites: []
          credentials:
            username: "dev"
            password: "$DEV_AUTH_PASSWORD"
          ipWhitelist: ["127.0.0.1", "192.168.1.*"]
          userAgentExceptions: ["*bot*", "*crawler*", "*monitor*"]
          exceptedPaths: ["/webhooks/*", "/api/health", "/system/status"]
          protectedPaths: []
          customFailureMessage: "Development access required"
          bypassForLoggedInUsers: false

        "Staging Sites":
          enabled: true
          realm: "Staging Sites"
          triggerConditions:
            environments: ["staging"]
            domains: []
            sites: ["site-1", "site-2"]
          credentials:
            username: "staging"
            password: "$STAGING_AUTH_PASSWORD"
          ipWhitelist: []
          userAgentExceptions: ["*bot*"]
          exceptedPaths: ["/webhooks/*"]
          protectedPaths: ["/admin/reports/*"]
          customFailureMessage: ""
          bypassForLoggedInUsers: true
```

## Rule Matching Logic

### Trigger Conditions
A rule applies when **any** of its trigger conditions match:
- Current environment matches any environment in `environments` array (case-insensitive)
- OR current domain matches any pattern in `domains` array
- OR current site handle matches any handle in `sites` array

### Environment Detection
- Uses Craft's `CRAFT_ENVIRONMENT` constant
- Case-insensitive matching
- No authentication applied if current environment has no matching rules

### Multiple Rule Handling
- Multiple rules can apply simultaneously
- Each applicable rule requires separate authentication
- Rules are processed in configuration order

### Path Processing Order
1. Check if current URI matches any `exceptedPaths` in applicable rules → Skip auth
2. Check if current URI matches any `protectedPaths` in any rule → Require auth
3. Apply standard rule matching logic

## Settings Interface

### Settings Screen Structure

```
Authentication Rules
├── [Current Environment: staging] [Current Site: site-1] ← Context indicators
├── Add New Rule [+]
├── "Development Environment" (enabled) ▼
│   ├── Enable Basic Auth [toggle]
│   ├── Realm Name: [text input] ← Unique identifier
│   ├── Trigger Conditions:
│   │   ├── Environments: [tag field] (autocomplete: dev, staging, production)
│   │   ├── Domains: [tag field] (supports wildcards)
│   │   └── Sites: [checkboxes] (populated from Craft sites)
│   ├── Credentials:
│   │   ├── Username: [text input]
│   │   └── Password: [Craft environment variable field]
│   ├── IP Whitelist: [tag field] (one per tag)
│   ├── User-Agent Exceptions: [tag field] (supports wildcards)
│   ├── Excepted Paths: [tag field] (supports wildcards)
│   ├── Protected Paths: [tag field] (supports wildcards)
│   ├── Custom Failure Message: [text input]
│   └── Bypass for Logged-in Users: [toggle]
└── [Delete Rule] (for each rule)
```

### Field Placeholders and Help Text

- **Realm Name**: "e.g., Development Environment, Staging Sites"
- **Environments**: "e.g., dev, staging, production"
- **Domains**: "e.g., \*.dev.local, staging.example.com"
- **IP Whitelist**: "e.g., 127.0.0.1, 192.168.1.\*, 10.0.0.0/8"
- **User-Agent Exceptions**: "e.g., bot*, *crawler*, *monitor"
- **Paths**: "e.g., /webhooks/, /api/health, /system/"

## Validation Rules

### On Save Validation

#### Required Fields (when enabled = true)
- `realm`: Non-empty string, must be unique across all rules
- At least one trigger condition must be specified
- `credentials.username`: Non-empty string
- `credentials.password`: Non-empty string

#### Environment Names
- Pattern: `/^[a-zA-Z][a-zA-Z0-9_-]{2,49}$/`
- Must start with letter
- 3-50 characters
- Letters, numbers, hyphens, underscores only

#### Domain Patterns
- Valid domain format with optional wildcards
- Wildcard (`*`) only at subdomain level
- Examples: `example.com`, `*.example.com`, `staging.example.com`

#### Site Handles
- Must match existing Craft site handles
- Validated against current site configuration

#### IP Addresses
- Support individual IPs: `192.168.1.1`
- Support wildcards: `192.168.1.*`
- Support CIDR notation: `10.0.0.0/8`

#### Path Patterns
- Must start with `/`
- Support wildcards: `/api/*`, `/webhooks/*/callback`
- Validate as reasonable URI patterns

### Default Values for New Rules

```yaml
enabled: false
realm: "New Authentication Rule"
triggerConditions:
  environments: []
  domains: []
  sites: []
credentials:
  username: ""
  password: ""
ipWhitelist: []
userAgentExceptions: ["*bot*", "*crawler*"]
exceptedPaths: ["/webhooks/*"]
protectedPaths: []
customFailureMessage: ""
bypassForLoggedInUsers: false
```

## Technical Implementation Notes

### Authentication Flow
1. Request received
2. Check if any rules apply based on trigger conditions
3. For each applicable rule:
   - Check IP whitelist → bypass if matched
   - Check user-agent exceptions → bypass if matched
   - Check excepted paths → bypass if matched
   - Check protected paths → require auth if matched
   - Apply standard authentication requirement
4. If authentication required:
   - Check if user already logged into Craft (if bypass enabled)
   - Validate HTTP Basic Auth credentials
   - Return 401 with custom message if authentication fails

### Pattern Matching
- Use fnmatch or similar for wildcard pattern matching
- Case-insensitive for environments
- Case-sensitive for paths and domains
- Support `*` and `?` wildcards where specified

### Settings Controller
- Custom controller for AJAX form validation
- Validates settings model before save
- Returns JSON response with validation errors
- Integrates with Craft's settings system

### Environment Variable Integration
- Use Craft's existing environment variable field type
- Support `$VARIABLE_NAME` syntax
- Validate environment variables exist at runtime, not save time

## Security Considerations

- Store credentials securely in project configuration
- Support environment variables for sensitive data
- Implement proper HTTP Basic Auth headers
- Validate all input patterns to prevent injection
- Log authentication attempts (optional future feature)
- Rate limiting (optional future feature)

## Future Enhancement Ideas

- Rate limiting per IP/rule
- Time-based authentication rules
- Integration with Craft user permissions
- Detailed logging and monitoring
- Emergency bypass mechanisms
- Multiple credential sets per rule
