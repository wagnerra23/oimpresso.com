<?php

namespace Modules\Ponto\Tests\Feature;

/**
 * @covers-us US-PONT-008
 */
class EspelhoTest extends PontoTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_retorna_404_para_colaborador_inexistente(): void
    {
        // EspelhoController@show faz Colaborador::where('business_id', ...)->findOrFail()
        // → 404 pra id fora do tenant/inexistente (isolamento + not-found).
        $this->actAsAdmin();
        $this->inertiaGet('/ponto/espelho/999999')->assertStatus(404);
    }
}
