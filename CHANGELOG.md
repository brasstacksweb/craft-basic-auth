# Release Notes for Basic Auth

## 1.0.0
- Initial release

## 1.0.1 - 2025-06-10
- Enhanced domain validation pattern to support wildcards in different positions (e.g., `staging.*.com`)

## 1.0.2 - 2025-06-10
- Bug fix in domain pattern matching when checking active conditions against the current request domain.
- Updating authentication check to bypass subsequent matching conditions if any preceding conditions are met.

## 1.0.3 - 2025-06-11
- Refactoring condition matching logic to improve performance and maintainability.

## 1.0.4 - 2025-06-11
- Fixed a bug where the Auth service property was being accessed before initialization.

## 1.0.5 - 2025-06-11
- Fixed a bug in private class method being passed as a callable.

## 1.0.6 - 2025-06-18
- Simplified request and response handling for matching conditions.
