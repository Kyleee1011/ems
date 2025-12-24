<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\AppHelpers;

/**
 * Unit Test for AppHelpers Utility Class
 */
class AppHelpersTest extends TestCase
{
    public function testCleanNumReturnsNullForEmptyString()
    {
        $result = AppHelpers::cleanNum('');
        $this->assertNull($result);
    }

    public function testCleanNumReturnsNullForNull()
    {
        $result = AppHelpers::cleanNum(null);
        $this->assertNull($result);
    }

    public function testCleanNumReturnsValueForNonEmpty()
    {
        $result = AppHelpers::cleanNum(42);
        $this->assertEquals(42, $result);
        
        $result = AppHelpers::cleanNum('100');
        $this->assertEquals('100', $result);
    }

    public function testCleanDateReturnsNullForEmptyString()
    {
        $result = AppHelpers::cleanDate('');
        $this->assertNull($result);
    }

    public function testCleanDateReturnsNullForNull()
    {
        $result = AppHelpers::cleanDate(null);
        $this->assertNull($result);
    }

    public function testCleanDateReturnsValueForNonEmpty()
    {
        $result = AppHelpers::cleanDate('2025-01-01');
        $this->assertEquals('2025-01-01', $result);
    }

    public function testCalculateRatesFromMonthlyReturnsCorrectRates()
    {
        $monthly = 30000;
        $result = AppHelpers::calculateRatesFromMonthly($monthly);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('hourly', $result);
        
        // Expected: workdays_in_month = 313/12 ≈ 26.08
        // Daily = 30000 / 26.08 ≈ 1150.15
        // Hourly = 1150.15 / 8 ≈ 143.77
        $this->assertGreaterThan(1000, $result['daily']);
        $this->assertGreaterThan(100, $result['hourly']);
    }

    public function testCalculateRatesFromMonthlyReturnsZeroForZeroSalary()
    {
        $result = AppHelpers::calculateRatesFromMonthly(0);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['daily']);
        $this->assertEquals(0, $result['hourly']);
    }

    public function testCalculateRatesFromMonthlyReturnsZeroForNegativeSalary()
    {
        $result = AppHelpers::calculateRatesFromMonthly(-1000);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['daily']);
        $this->assertEquals(0, $result['hourly']);
    }

    public function testGenerateCutoffPeriodsReturnsArray()
    {
        $result = AppHelpers::generateCutoffPeriods();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        
        // The implementation seems to generate 16 periods (8 months or similar logic)
        $this->assertCount(16, $result);
    }

    public function testGenerateCutoffPeriodsHasCorrectStructure()
    {
        $result = AppHelpers::generateCutoffPeriods();

        foreach ($result as $cutoff) {
            $this->assertArrayHasKey('value', $cutoff);
            $this->assertArrayHasKey('label', $cutoff);
            
            // Value should be in format "YYYY-MM-DD|YYYY-MM-DD"
            $this->assertStringContainsString('|', $cutoff['value']);
        }
    }

    public function testGenerateCutoffPeriodsIncludesPeriod1()
    {
        $result = AppHelpers::generateCutoffPeriods();
        
        // At least one period should contain "08" (8th day)
        $found = false;
        foreach ($result as $cutoff) {
            if (strpos($cutoff['value'], '-08|') !== false) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found, 'Should include period starting on 8th');
    }

    public function testGenerateCutoffPeriodsIncludesPeriod2()
    {
        $result = AppHelpers::generateCutoffPeriods();
        
        // At least one period should contain "23" (23rd day)
        $found = false;
        foreach ($result as $cutoff) {
            if (strpos($cutoff['value'], '-23|') !== false) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found, 'Should include period starting on 23rd');
    }
}
