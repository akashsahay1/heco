<?php

namespace Tests\Unit;

use App\Models\TripDay;
use App\Models\TripDayService;
use PHPUnit\Framework\TestCase;

/**
 * A day service is either one figure for the whole party or a figure per
 * head. These pin down which is which, since the calculator, the Trip
 * Manager and the PDF all lean on the same two methods.
 */
class TripDayServicePriceBasisTest extends TestCase
{
    public function test_a_flat_line_is_its_cost_whatever_the_party(): void
    {
        $service = new TripDayService(['cost' => 4500, 'price_basis' => 'flat']);

        $this->assertFalse($service->isPerPerson());
        $this->assertSame(4500.0, $service->groupCost(1.0));
        $this->assertSame(4500.0, $service->groupCost(4.0));
    }

    public function test_a_per_person_line_is_multiplied_by_the_people_factor(): void
    {
        $service = new TripDayService(['cost' => 1500, 'price_basis' => 'per_person']);

        $this->assertTrue($service->isPerPerson());
        $this->assertSame(6000.0, $service->groupCost(4.0));
        // Two adults and a child at 50%.
        $this->assertSame(3750.0, $service->groupCost(2.5));
    }

    public function test_rows_saved_before_the_column_existed_are_flat(): void
    {
        $service = new TripDayService(['cost' => 800]);

        $this->assertFalse($service->isPerPerson());
        $this->assertSame(800.0, $service->groupCost(3.0));
    }

    public function test_custom_is_a_day_type_hct_can_pick(): void
    {
        $this->assertContains(TripDay::TYPE_CUSTOM, TripDay::TYPES);
        $this->assertSame('custom', TripDay::TYPE_CUSTOM);
    }
}
