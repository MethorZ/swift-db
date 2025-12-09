# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

We take security vulnerabilities seriously. If you discover a security issue, please report it responsibly.

### How to Report

**Do NOT report security vulnerabilities through public GitHub issues.**

Instead, please send an email to: **security@methorz.dev**

Include the following information:

1. **Description**: A clear description of the vulnerability
2. **Steps to Reproduce**: Detailed steps to reproduce the issue
3. **Impact**: What an attacker could achieve by exploiting this vulnerability
4. **Affected Versions**: Which versions are affected
5. **Suggested Fix**: If you have one (optional)

### What to Expect

- **Acknowledgment**: We will acknowledge receipt within 48 hours
- **Initial Assessment**: We will provide an initial assessment within 7 days
- **Resolution Timeline**: We aim to resolve critical issues within 30 days
- **Disclosure**: We will coordinate disclosure timing with you

### Security Best Practices

When using SwiftDb, follow these security practices:

#### 1. Database Credentials

```php
// ❌ BAD: Hardcoded credentials
$config = [
    'username' => 'root',
    'password' => 'mypassword',
];

// ✅ GOOD: Environment variables
$config = [
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
];
```

#### 2. Query Parameters

SwiftDb uses parameterized queries by default, protecting against SQL injection:

```php
// ✅ GOOD: Parameterized (automatic)
$repository->query()
    ->where('product_name', $userInput)
    ->get();

// ❌ BAD: Don't use raw user input in raw queries
$repository->query()
    ->whereRaw("product_name = '{$userInput}'")  // SQL injection risk!
    ->get();

// ✅ GOOD: Use bindings with raw queries
$repository->query()
    ->whereRaw('product_name = ?', [$userInput])
    ->get();
```

#### 3. Connection Security

```php
// ✅ GOOD: Use SSL for remote connections
$config = [
    'dsn' => 'mysql:host=remote;dbname=app;charset=utf8mb4',
    'options' => [
        PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca-cert.pem',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ],
];
```

#### 4. Principle of Least Privilege

```sql
-- Create a limited user for your application
CREATE USER 'app_user'@'%' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON myapp.* TO 'app_user'@'%';

-- Don't use root in production!
```

## Security Features

SwiftDb includes several built-in security features:

- **Parameterized Queries**: All queries use prepared statements
- **Type Casting**: Entity hydration includes type conversion
- **Connection Isolation**: Master/slave separation for read/write operations
- **Deadlock Protection**: Automatic retry prevents information leakage

## Acknowledgments

We appreciate the security research community's efforts in responsibly disclosing vulnerabilities.

Contributors who report valid security issues will be acknowledged (with permission) in our release notes.

