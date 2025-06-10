# Release Notes for Basic Auth

## 1.0.0
- Initial release

## 2.0.0
- Adding support for Craft CMS v5

## 2.0.1 - 2025-06-09
- Updating new condition controller action and template to support `allowAdminChanges` config setting.

## 2.0.2 - 2025-06-10
- Enhanced domain validation pattern to support wildcards in different positions (e.g., `staging.*.com`)

## 2.0.3 - 2025-06-10
- Bug fix in domain pattern matching when checking active conditions against the current request domain.
- Updating authentication check to bypass subsequent matching conditions if any preceding conditions are met.
