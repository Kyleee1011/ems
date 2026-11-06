<?php

namespace Tests;

use App\Services\Payroll\OvertimeCalculator;
use PHPUnit\Framework\TestCase;

class OvertimeCalculatorTest extends TestCase
{
    private OvertimeCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new OvertimeCalculator();
    }

    public function testOrdinaryDayOvertimeMultiplier(): void
    {
        $this->assertSame(1.25, $this->calc->getMultiplier('REGULAR_DAY', false));
        $this->assertSame(1.25, $this->calc->getMultiplier('ORDINARY_DAY', false));
        // Calculation test: 2 hours at 100/hr = 2 * 100 * 1.25 = 250.00
        $this->assertEquals(250.00, $this->calc->calculate(2.0, 100.0, 'REGULAR_DAY', false));
    }

    public function testRestDayOvertimeMultiplier(): void
    {
        // Rest day OT: 130% * 1.30 = 169%
        $this->assertSame(1.69, $this->calc->getMultiplier('REGULAR_DAY', true));
        // Calculation test: 2 hours at 100/hr = 2 * 100 * 1.69 = 338.00
        $this->assertEquals(338.00, $this->calc->calculate(2.0, 100.0, 'REGULAR_DAY', true));
    }

    public function testSpecialHolidayOvertimeMultiplier(): void
    {
        // Special Non-Working Holiday OT: 130% * 1.30 = 169%
        $this->assertSame(1.69, $this->calc->getMultiplier('SPECIAL', false));
        $this->assertEquals(338.00, $this->calc->calculate(2.0, 100.0, 'SPECIAL', false));
    }

    public function testSpecialHolidayOnRestDayOvertimeMultiplier(): void
    {
        // Special Holiday falling on Rest Day OT: 150% * 1.30 = 195%
        $this->assertSame(1.95, $this->calc->getMultiplier('SPECIAL', true));
        // 2 hours at 100/hr = 2 * 100 * 1.95 = 390.00
        $this->assertEquals(390.00, $this->calc->calculate(2.0, 100.0, 'SPECIAL', true));
    }

    public function testRegularHolidayOvertimeMultiplier(): void
    {
        // Regular Holiday OT: 200% * 1.30 = 260%
        $this->assertSame(2.60, $this->calc->getMultiplier('REGULAR', false));
        // 2 hours at 100/hr = 2 * 100 * 2.60 = 520.00
        $this->assertEquals(520.00, $this->calc->calculate(2.0, 100.0, 'REGULAR', false));
    }

    public function testRegularHolidayOnRestDayOvertimeMultiplier(): void
    {
        // Regular Holiday falling on Rest Day OT: 260% * 1.30 = 338%
        $this->assertSame(3.38, $this->calc->getMultiplier('REGULAR', true));
        // 2 hours at 100/hr = 2 * 100 * 3.38 = 676.00
        $this->assertEquals(676.00, $this->calc->calculate(2.0, 100.0, 'REGULAR', true));
    }

    public function testDoubleHolidayOvertimeMultiplier(): void
    {
        // Double Regular Holiday OT: 300% * 1.30 = 390%
        $this->assertSame(3.90, $this->calc->getMultiplier('DOUBLE', false));
        // 2 hours at 100/hr = 2 * 100 * 3.90 = 780.00
        $this->assertEquals(780.00, $this->calc->calculate(2.0, 100.0, 'DOUBLE', false));
    }

    public function testDoubleHolidayOnRestDayOvertimeMultiplier(): void
    {
        // Double Regular Holiday on Rest Day OT: 390% * 1.30 = 507%
        $this->assertSame(5.07, $this->calc->getMultiplier('DOUBLE', true));
        // 2 hours at 100/hr = 2 * 100 * 5.07 = 1014.00
        $this->assertEquals(1014.00, $this->calc->calculate(2.0, 100.0, 'DOUBLE', true));
    }

    public function testZeroHoursOrRateReturnsZero(): void
    {
        $this->assertSame(0.0, $this->calc->calculate(0, 100, 'REGULAR', false));
        $this->assertSame(0.0, $this->calc->calculate(2, 0, 'REGULAR', false));
        $this->assertSame(0.0, $this->calc->calculate(-1, 100, 'REGULAR', false));
    }
}
