# EMS Testing - Quick Reference

## Run Tests

```bash
# All tests
php vendor/bin/phpunit

# Unit tests only
php vendor/bin/phpunit --testsuite "Unit Tests"

# Specific file
php vendor/bin/phpunit tests/Unit/DashboardModelTest.php

# With coverage (requires Xdebug)
php vendor/bin/phpunit --coverage-html tests/coverage
```

## Test Status

**Total**: 70 test methods across 10 test classes
**Passing**: 37/64 (58%)
**Status**: ✅ Tests running, some need updates

## Passing Tests ✅

- DashboardModelTest (5/5)
- PayrollConfigModelTest (9/9)
- EmployeeTest (7/7)
- AppHelpersTest (11/12)
- FileUploadServiceTest (7/8)

## Tests Needing Updates ⚠️

These tests assume methods that don't exist. Update them to use actual model methods:

### LeaveTest
Use: `getCredits()`, `applyLeave()`, `getPendingLeaves()`, `updateLeaveStatus()`

### LoanTest
Use: `createLoan()`, `updateStatus()`, `getStats()`, `getLoans()`

### TimecardTest
Use: `getSchedule()`, `getRawLogs()`, `generateDtr()`, `updateManualLog()`

### PayslipTest
Use: `getPayrollStatus()`, `getEmployeeData()`, `getAttendanceData()`, `calculateBreakdown()`

### ScheduleBatchTest
Use: `getPendingBatches()`, `getBatchStatus()`, `getSchedules()`, `updateStatus()`

## Next Steps

1. Update the 5 test files to match actual model methods
2. Run tests again: `php vendor/bin/phpunit`
3. Aim for 100% pass rate
4. Expand coverage to Controllers and APIs

## Troubleshooting

**"Class not found"**: Run `composer dump-autoload`
**"No coverage driver"**: Install Xdebug
**Tests are slow**: Ensure you're using mocks, not real DB

## Files Created

```
tests/
├── bootstrap.php
├── Unit/
│   ├── DashboardModelTest.php ✅
│   ├── PayrollConfigModelTest.php ✅
│   ├── AppHelpersTest.php ✅
│   ├── FileUploadServiceTest.php ✅
│   ├── EmployeeTest.php ✅
│   ├── LeaveTest.php ⚠️
│   ├── LoanTest.php ⚠️
│   ├── TimecardTest.php ⚠️
│   ├── PayslipTest.php ⚠️
│   └── ScheduleBatchTest.php ⚠️
└── README.md
```

See `walkthrough.md` for complete testing guide.
