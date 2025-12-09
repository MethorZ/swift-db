---
name: Bug Report
about: Report a bug to help us improve SwiftDb
title: '[BUG] '
labels: bug
assignees: ''
---

## Bug Description

A clear and concise description of what the bug is.

## Steps to Reproduce

1. Configure '...'
2. Call method '...'
3. Pass parameters '...'
4. See error

## Expected Behavior

A clear and concise description of what you expected to happen.

## Actual Behavior

What actually happened, including any error messages.

## Code Example

```php
// Minimal code example that reproduces the issue
$repository = new ProductRepository($connection);
$repository->query()
    ->where('product_id', 1)
    ->get();
```

## Error Output

```
// Paste any error messages or stack traces here
```

## Environment

- **PHP Version**: [e.g., 8.3.0]
- **SwiftDb Version**: [e.g., 1.0.0]
- **MySQL Version**: [e.g., 8.0.35]
- **Operating System**: [e.g., Ubuntu 22.04, macOS 14]

## Additional Context

Add any other context about the problem here, such as:
- Related configuration
- Whether this worked in a previous version
- Any workarounds you've tried

