<?php

namespace Tests\Unit;

use App\Support\EventGrouping;
use Tests\TestCase;

class EventGroupingTest extends TestCase
{
    public function test_events_are_ordered_by_brand_type_and_date(): void
    {
        $groups = EventGrouping::group(collect([
            [
                'id' => 1,
                'brand_id' => 2,
                'brand_name' => 'MILO',
                'event_type_id' => 3,
                'event_type_name' => 'MILO School Sampling',
                'requires_school' => true,
                'event_date' => '2026-03-02',
                'event_name' => 'Event Kedua',
            ],
            [
                'id' => 2,
                'brand_id' => 1,
                'brand_name' => 'DANCOW',
                'event_type_id' => 2,
                'event_type_name' => 'DANCOW School Sampling',
                'requires_school' => true,
                'event_date' => '2026-03-01',
                'event_name' => 'Event Dancow',
            ],
            [
                'id' => 3,
                'brand_id' => 2,
                'brand_name' => 'MILO',
                'event_type_id' => 4,
                'event_type_name' => 'MILO InStore',
                'requires_school' => false,
                'event_date' => '2026-03-01',
                'event_name' => 'Event InStore',
            ],
            [
                'id' => 4,
                'brand_id' => 2,
                'brand_name' => 'MILO',
                'event_type_id' => 3,
                'event_type_name' => 'MILO School Sampling',
                'requires_school' => true,
                'event_date' => '2026-03-01',
                'event_name' => 'Event Pertama',
            ],
        ]));

        $this->assertSame(['DANCOW', 'MILO'], array_column($groups, 'brand_name'));
        $miloTypes = $groups[1]['event_types'];
        $this->assertSame(
            ['MILO InStore', 'MILO School Sampling'],
            array_column($miloTypes, 'event_type_name'),
        );
        $this->assertSame(
            ['2026-03-01', '2026-03-02'],
            array_column($miloTypes[1]['dates'], 'date'),
        );
        $this->assertSame([4], array_column($miloTypes[1]['dates'][0]['events'], 'id'));
    }
}
