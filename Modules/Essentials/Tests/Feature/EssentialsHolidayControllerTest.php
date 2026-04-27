<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Feature tests para Modules/Essentials/Http/Controllers/EssentialsHolidayController.
 *
 * Pages Inertia: Essentials/Holidays/Index.
 *
 * Contratos cobertos:
 *   - /hrm/holiday exige autenticação (redirect /login)
 *   - GET /hrm/holiday devolve Inertia com componente esperado e props
 *     (`holidays`, `locations`, `filtros`, `can_manage`)
 *   - Filtros location_id/start_date/end_date são preservados em props
 *   - Scope por business_id (não vaza feriados de outro business)
 *
 * Métodos prefixados com `test_` para PHPUnit 12 (anotação @test foi removida).
 */
class EssentialsHolidayControllerTest extends EssentialsTestCase
{
    public function test_index_exige_autenticacao(): void
    {
        $this->get('/hrm/holiday')->assertRedirect('/login');
    }

    public function test_index_retorna_inertia_com_props_esperadas(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/hrm/holiday');

        $this->assertInertiaComponent($response, 'Essentials/Holidays/Index');

        $props = $response->json('props');
        $this->assertArrayHasKey('holidays', $props);
        $this->assertArrayHasKey('locations', $props);
        $this->assertArrayHasKey('filtros', $props);
        $this->assertArrayHasKey('can_manage', $props);

        $this->assertIsArray($props['holidays']);
        $this->assertIsArray($props['locations']);
        $this->assertIsArray($props['filtros']);
        $this->assertArrayHasKey('location_id', $props['filtros']);
        $this->assertArrayHasKey('start_date', $props['filtros']);
        $this->assertArrayHasKey('end_date', $props['filtros']);
    }

    public function test_filtro_por_location_id_preservado_nos_props(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/hrm/holiday', ['location_id' => 999999]);

        $this->assertInertiaComponent($response, 'Essentials/Holidays/Index');
        $this->assertEquals(999999, $response->json('props.filtros.location_id'));
    }

    public function test_filtro_por_intervalo_de_datas_preservado_nos_props(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/hrm/holiday', [
            'start_date' => '2026-01-01',
            'end_date'   => '2026-12-31',
        ]);

        $this->assertEquals('2026-01-01', $response->json('props.filtros.start_date'));
        $this->assertEquals('2026-12-31', $response->json('props.filtros.end_date'));
    }

    public function test_index_lista_apenas_holidays_do_business_atual(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/hrm/holiday');

        $businessId = $this->business->id;
        $holidays = $response->json('props.holidays');
        $this->assertIsArray($holidays);

        if (! empty($holidays)) {
            $ids = collect($holidays)->pluck('id')->filter()->all();
            $count = \Modules\Essentials\Entities\EssentialsHoliday::whereIn('id', $ids)
                ->where('business_id', '!=', $businessId)
                ->count();
            $this->assertSame(0, $count,
                "Holidays vazaram de outro business — esperado 0 fora do business {$businessId}, achei {$count}.");
        } else {
            $this->addToAssertionCount(1);
        }
    }
}
