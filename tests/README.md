# EMS Testing Guide

## Overview

This document explains how to run tests for the Employee Management System.

## Prerequisites

- PHP 8.0 or higher
- Composer dependencies installed
- PHPUnit 9.6+ (installed via Composer)

## Running Tests

### Run All Tests
```bash
php vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
# Unit tests only
php vendor/bin/phpunit --testsuite "Unit Tests"

# Integration tests only
php vendor/bin/phpunit --testsuite "Integration Tests"
```

### Run Specific Test File
```bash
php vendor/bin/phpunit tests/Unit/DashboardModelTest.php
```

### Run with Code Coverage (requires Xdebug)
```bash
php vendor/bin/phpunit --coverage-html tests/coverage
```

## Test Structure

```
tests/
├── bootstrap.php          # Test environment setup
├── Unit/                  # Unit tests (isolated, mocked dependencies)
│   ├── DashboardModelTest.php
│   └── ...
├── Integration/           # Integration tests (real database, full stack)
│   └── ...
├── coverage/             # Code coverage reports (generated)
└── results/              # Test results (generated)
```

## Writing Tests

### Unit Test Example

Unit tests should:
- Mock all external dependencies (PDO, file system, etc.)
- Test a single class in isolation
- Be fast and deterministic

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\YourModel;
use PDO;

class YourModelTest extends TestCase
{
    private $mockPdo;
    private $model;

    protected function setUp(): void
    {
        $this->mockPdo = $this->createMock(PDO::class);
        $this->model = new YourModel($this->mockPdo);
    }

    public function testYourMethod()
    {
        // Arrange
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchColumn')->willReturn(42);
        $this->mockPdo->method('query')->willReturn($mockStatement);

        // Act
        $result = $this->model->yourMethod();

        // Assert
        $this->assertEquals(42, $result);
    }
}
```

### Integration Test Example

Integration tests should:
- Use a test database
- Test multiple components together
- Verify end-to-end functionality

```php
<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class YourIntegrationTest extends TestCase
{
    public function testCompleteWorkflow()
    {
        // Test complete user workflow
        // Use real database connections (test DB)
    }
}
```

## Best Practices

1. **Follow AAA Pattern**: Arrange, Act, Assert
2. **One assertion per test** (when possible)
3. **Descriptive test names**: `testGetTotalEmployeesReturnsInteger()`
4. **Mock external dependencies** in unit tests
5. **Clean up in tearDown()** method
6. **Use data providers** for testing multiple scenarios

## Current Test Coverage

- ✅ DashboardModel: 100% (5 tests)
- ⚠️ Other models: Not yet tested

## Goals

- [ ] Achieve 80% code coverage for all Models
- [ ] Create integration tests for all Controllers
- [ ] Create integration tests for all API endpoints
- [ ] Set up CI/CD to run tests automatically

## Troubleshooting

### "No code coverage driver available"
Install Xdebug:
```bash
pecl install xdebug
```

### Tests fail with database errors
Ensure you're using mocked PDO in unit tests, not real database connections.

### Class not found errors
Run `composer dump-autoload` to regenerate autoloader.
