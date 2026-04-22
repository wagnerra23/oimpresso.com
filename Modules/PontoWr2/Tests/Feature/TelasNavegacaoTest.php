<?php

namespace Modules\PontoWr2\Tests\Feature;

/**
 * Smoke test: todas as 11 telas React do PontoWR2 carregam 200 e renderizam
 * o component Inertia esperado.
 *
 * Não testa conteúdo — só verifica que rotas, controllers e montagem Inertia
 * estão saudáveis. Detecta imediatamente bugs de import, migration, etc.
 */
class TelasNavegacaoTest extends PontoTestCase
{
    /**
     * @test
     * @dataProvider rotasInertiaDataProvider
     */
    public function rota_renderiza_component_inertia_esperado(string $url, string $component): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet($url);

        $response->assertStatus(200);
        $response->assertHeader('X-Inertia', 'true');
        $this->assertEquals($component, $response->json('component'), "Component errado em {$url}");
    }

    public function rotasInertiaDataProvider(): array
    {
        return [
            'Dashboard'                => ['/ponto', 'Ponto/Dashboard/Index'],
            'Welcome React'            => ['/ponto/react', 'Ponto/Welcome'],
            'Relatórios'               => ['/ponto/relatorios', 'Ponto/Relatorios/Index'],
            'Aprovações'               => ['/ponto/aprovacoes', 'Ponto/Aprovacoes/Index'],
            'Intercorrências index'    => ['/ponto/intercorrencias', 'Ponto/Intercorrencias/Index'],
            'Intercorrências create'   => ['/ponto/intercorrencias/create', 'Ponto/Intercorrencias/Create'],
            'Banco de Horas'           => ['/ponto/banco-horas', 'Ponto/BancoHoras/Index'],
            'Espelho'                  => ['/ponto/espelho', 'Ponto/Espelho/Index'],
            'Escalas'                  => ['/ponto/escalas', 'Ponto/Escalas/Index'],
            'Escala create'            => ['/ponto/escalas/create', 'Ponto/Escalas/Form'],
            'Importações'              => ['/ponto/importacoes', 'Ponto/Importacoes/Index'],
            'Importação create'        => ['/ponto/importacoes/novo', 'Ponto/Importacoes/Create'],
            'Colaboradores'            => ['/ponto/colaboradores', 'Ponto/Colaboradores/Index'],
            'Configurações'            => ['/ponto/configuracoes', 'Ponto/Configuracoes/Index'],
            'REPs'                     => ['/ponto/configuracoes/reps', 'Ponto/Configuracoes/Reps'],
        ];
    }

    /** @test */
    public function modulos_admin_tambem_renderiza(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/modulos');

        $response->assertStatus(200);
        $this->assertEquals('Modules/Index', $response->json('component'));

        $modules = $response->json('props.modules');
        $this->assertIsArray($modules);
        $this->assertGreaterThan(10, count($modules));
    }
}
