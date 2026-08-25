<?php

namespace Modules\Ponto\Tests\Feature;

/**
 * Smoke test: todas as 11 telas React do PontoWR2 carregam 200 e renderizam
 * o component Inertia esperado.
 *
 * Não testa conteúdo — só verifica que rotas, controllers e montagem Inertia
 * estão saudáveis. Detecta imediatamente bugs de import, migration, etc.
 *
 * Cobre as telas de listagem/criação cujo render Inertia é aferido aqui
 * (status 200 + component exato):
 * @covers-us US-PONT-001
 * @covers-us US-PONT-002
 * @covers-us US-PONT-004
 * @covers-us US-PONT-005
 * @covers-us US-PONT-006
 * @covers-us US-PONT-007
 * @covers-us US-PONT-009
 * @covers-us US-PONT-010
 * @covers-us US-PONT-012
 */
class TelasNavegacaoTest extends PontoTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('rotasInertiaDataProvider')]
    public function rota_renderiza_component_inertia_esperado(string $url, string $component): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet($url);

        $response->assertStatus(200);
        $response->assertHeader('X-Inertia', 'true');
        $this->assertEquals($component, $response->json('component'), "Component errado em {$url}");
    }

    public static function rotasInertiaDataProvider(): array
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

    /**
     * `/modulos` NEGA admin-de-negocio — e isso e a REGRA, nao um defeito.
     *
     * O caso antes se chamava `modulos_admin_tambem_renderiza` e exigia 200 pro admin
     * do business. Isso e exatamente o furo que a decisao D2 (2026-08-19) FECHOU: o
     * `ModuleManagementController` passou a exigir a ability de superadmin
     * `manage_modules` e excluiu `Admin#<biz>` de proposito, porque a tela desliga
     * modulo do APP INTEIRO, pra todos os tenants — o comentario la registra o furo
     * como "medido: um user so com Admin#1 entrava".
     *
     * O teste seguia codificando o comportamento PRE-D2 e reprovava com 403. Ficou
     * assim porque o arquivo esta fora de qualquer lane; ninguem viu. Medido no CT100
     * em 2026-08-23. Agora ele prova a regra em vez de negar: admin de negocio NAO
     * entra. O caso positivo (superadmin entra) exige fixture de superadmin e fica
     * como decisao [W] — meio teste honesto vale mais que um inteiro que mente.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function modulos_nega_admin_de_negocio_regra_d2(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/modulos');

        $response->assertStatus(403);
    }
}
