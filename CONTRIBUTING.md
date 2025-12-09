# Contributing to SwiftDb

Thank you for your interest in contributing to SwiftDb!

## Development Setup

```bash
# Clone the repository
git clone https://github.com/methorz/swift-db.git
cd swift-db

# Install dependencies
composer install

# Start test database (optional, for integration tests)
docker-compose up -d

# Run tests
composer test
```

## Code Quality Standards

All contributions must pass our quality checks:

```bash
# Run all checks
composer cs-check   # Code style (PSR-12 + Slevomat)
composer analyze    # Static analysis (PHPStan Level 9)
composer test       # Unit tests (PHPUnit)

# Auto-fix code style
composer cs-fix
```

### Requirements

- **Zero PHPCS errors**: PSR-12 + Slevomat Coding Standard
- **Zero PHPStan errors**: Level 9 strictness
- **All tests passing**: Unit tests must pass
- **Strict types**: `declare(strict_types=1)` in all files
- **Type hints**: Full type coverage on all methods

## Pull Request Process

1. **Fork** the repository
2. **Create a branch** from `main`: `git checkout -b feature/your-feature`
3. **Make your changes** following our coding standards
4. **Add tests** for new functionality
5. **Run quality checks**: `composer cs-check && composer analyze && composer test`
6. **Commit** with clear messages
7. **Push** to your fork
8. **Open a Pull Request** against `main`

### Commit Messages

Use clear, descriptive commit messages:

```
Add bulk delete operation to AbstractRepository

- Implement deleteMany() method for bulk deletions
- Add support for chunked deletion for large datasets
- Add unit tests for new functionality
```

## Architecture Guidelines

### Adding New Features

1. **Follow existing patterns**: Check how similar features are implemented
2. **Keep it focused**: One class, one responsibility
3. **Use final classes**: Unless inheritance is intended
4. **Inject dependencies**: Via constructor

### Entity Changes

- Extend `AbstractEntity`
- Implement `EntityInterface`
- Use traits for common functionality (`TimestampsTrait`, `UuidTrait`, `VersionTrait`)

### Repository Changes

- Extend `AbstractRepository`
- Implement `RepositoryInterface`
- Use generics for type safety

## Testing

### Unit Tests

```bash
# Run unit tests only
vendor/bin/phpunit --testsuite=unit
```

### Integration Tests

Requires a running MySQL database:

```bash
# Start database
docker-compose up -d

# Run integration tests
vendor/bin/phpunit --testsuite=integration
```

### Test Structure

```
tests/
├── Unit/           # Fast, no external dependencies
└── Integration/    # Requires database
```

## Questions?

Open an issue for:

- Bug reports
- Feature requests
- Questions about the codebase

Thank you for contributing!

