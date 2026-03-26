# Testing

Fennectra provides built-in support for PHPUnit testing in your application. The framework includes CLI commands to scaffold tests and run them, plus a base `TestCase` class with database helpers.

## Quick Start

```bash
# Create your first test
./forge make:test UserService

# Run all tests
./forge test

# Run only unit tests
./forge test --unit

# Run a specific test
./forge test --filter=UserServiceTest
```

## Test Structure

```
your-app/
├── phpunit.xml              ← PHPUnit configuration
├── tests/
│   ├── TestCase.php         ← Base test class (DB helpers, .env loading)
│   ├── Unit/                ← Pure unit tests (no DB, no HTTP)
│   │   └── UserServiceTest.php
│   └── Feature/             ← Feature tests (with DB, full stack)
│       └── Auth/
│           └── LoginTest.php
```

### Unit vs Feature Tests

| | Unit | Feature |
|---|---|---|
| **Extends** | `PHPUnit\Framework\TestCase` | `Tests\TestCase` |
| **Database** | No | Yes (via `query()` helpers) |
| **Speed** | Fast | Slower |
| **Use for** | Services, DTOs, helpers, pure logic | API endpoints, DB queries, workflows |

## Scaffolding Tests

### `./forge make:test`

```bash
# Unit test (default)
./forge make:test UserService
# → tests/Unit/UserServiceTest.php

# Feature test
./forge make:test Auth/Login --feature
# → tests/Feature/Auth/LoginTest.php

# Explicit unit test
./forge make:test Calculator --unit
# → tests/Unit/CalculatorTest.php
```

The command automatically:
- Creates `phpunit.xml` if missing
- Creates `tests/TestCase.php` if missing
- Creates `tests/Unit/` and `tests/Feature/` directories
- Adds the `Test` suffix if you forget it

### Subdirectory Support

Organize tests by domain using slashes:

```bash
./forge make:test Auth/Registration --feature
./forge make:test Auth/PasswordReset --feature
./forge make:test Billing/InvoiceCalculator
```

## Running Tests

### `./forge test`

```bash
# Run all tests
./forge test

# Run only unit tests
./forge test --unit

# Run only feature tests
./forge test --feature

# Filter by test name
./forge test --filter=testUserCanLogin

# Filter by class name
./forge test --filter=UserServiceTest

# Code coverage (requires Xdebug or PCOV)
./forge test --coverage
```

### Quality Check

`./forge quality` includes tests as step 3/3. If your app has a `phpunit.xml`, tests run automatically during quality checks:

```bash
./forge quality        # Style + Static analysis + Tests
./forge quality --fix  # Auto-fix style, then run analysis + tests
```

## Writing Tests

### Unit Test Example

```php
<?php

namespace Tests\Unit;

use App\Services\PriceCalculator;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PriceCalculator();
    }

    public function testCalculatesTotalWithTax(): void
    {
        $total = $this->calculator->withTax(100.00, 0.20);

        $this->assertEquals(120.00, $total);
    }

    public function testRejectsNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->withTax(-10, 0.20);
    }
}
```

### Feature Test Example

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class UserCreationTest extends TestCase
{
    public function testCreatesUserInDatabase(): void
    {
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT),
        ]);

        $row = $this->queryOne(
            'SELECT * FROM users WHERE email = :email',
            ['email' => 'john@example.com']
        );

        $this->assertNotNull($row);
        $this->assertEquals('John Doe', $row['name']);
    }
}
```

## Base TestCase

The `Tests\TestCase` class provides:

| Method | Description |
|--------|-------------|
| `query(string $sql, array $params): array` | Execute SQL, return all rows |
| `queryOne(string $sql, array $params): ?array` | Execute SQL, return first row |

It also:
- Loads `.env` before each test
- Resets DB connections after each test (prevents state leaks)

## Configuration

### phpunit.xml

The default `phpunit.xml` at your project root:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
```

### Autoloading

Your `composer.json` already includes test autoloading:

```json
{
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

After creating tests, run `composer dump-autoload` to ensure the `Tests\` namespace is registered.

## Tips

- **Keep unit tests fast**: no DB, no HTTP, no filesystem. Mock external dependencies.
- **Use feature tests for integration**: test the full stack with a real database.
- **One assertion per concept**: test one behavior per test method, use descriptive names.
- **Run tests before committing**: `./forge quality` does this automatically.
- **Use `--filter` during development**: run only the test you're working on for faster feedback.
