<?php

namespace Modules\Ponto\Tests\Feature;

/**
 * @covers-us US-PONT-011
 */
class ImportacaoTest extends PontoTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_retorna_404_para_importacao_inexistente(): void
    {
        // ImportacaoController@show faz Importacao::findOrFail() → 404 pra id inexistente.
        $this->actAsAdmin();
        $this->inertiaGet('/ponto/importacoes/999999')->assertStatus(404);
    }
}
