<?php

namespace Tests\Unit;

use App\Utils\Util;
use Tests\TestCase;

class FormatDateTimezoneTest extends TestCase
{
    public function test_format_date_does_not_shift_timezone_for_sao_paulo_business(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        date_default_timezone_set('America/Sao_Paulo');

        $util = new Util;

        $business = (object) [
            'date_format' => 'd/m/Y',
            'time_format' => 24,
        ];

        $this->assertSame(
            '24/04/2026 09:00',
            $util->format_date('2026-04-24 09:00:00', true, $business),
            'format_date empurrou 3 horas porque Carbon::createFromTimestamp foi chamado sem timezone (cria em UTC).'
        );
    }

    public function test_format_date_preserves_wall_clock_time_in_manaus(): void
    {
        config(['app.timezone' => 'America/Manaus']);
        date_default_timezone_set('America/Manaus');

        $util = new Util;
        $business = (object) ['date_format' => 'd/m/Y', 'time_format' => 24];

        $this->assertSame(
            '24/04/2026 14:30',
            $util->format_date('2026-04-24 14:30:00', true, $business)
        );
    }
}
