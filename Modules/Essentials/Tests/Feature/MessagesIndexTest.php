<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Tela /essentials/messages — mural interno por localidade.
 *
 * Existia um ponteiro podre: o `@docvault` de `Messages/Index.tsx` citava
 * `MessagesIndexTest` desde a migração, e o arquivo nunca foi criado. Este é ele.
 *
 * O recorte por localidade é CLIENT-SIDE por desenho (o mural chega inteiro, não é
 * paginado, e o polling repõe a lista completa) — então o que se prova aqui é o
 * contrato de props que torna esse recorte possível, não uma query de filtro.
 *
 * @covers-us US-ESS-012
 */
class MessagesIndexTest extends EssentialsTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function index_exige_autenticacao(): void
    {
        $this->get('/essentials/messages')->assertRedirect('/login');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_retorna_inertia_com_o_contrato_de_props_da_tela(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/messages');

        $this->assertInertiaComponent($response, 'Essentials/Messages/Index');

        $props = $response->json('props');
        // `can` e `me` são eager: a tela decide com eles ANTES do defer resolver.
        $this->assertArrayHasKey('can', $props);
        $this->assertArrayHasKey('me', $props);
        $this->assertArrayHasKey('view', $props['can']);
        $this->assertArrayHasKey('create', $props['can']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mural_e_localidades_chegam_por_partial_reload_e_o_mural_nao_e_paginado(): void
    {
        $this->actAsAdmin();

        // `messages` e `locations` são Inertia::defer — sem partial reload a prop
        // nem existe no payload (mesmo padrão do ComprasContratoFiltrosTest).
        $props = $this->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $this->currentInertiaVersion() ?? '',
            'X-Inertia-Partial-Data'      => 'messages,locations',
            'X-Inertia-Partial-Component' => 'Essentials/Messages/Index',
            'Accept'                      => 'text/html',
        ])->get('/essentials/messages')->json('props');

        $this->assertArrayHasKey('messages', $props);
        $this->assertArrayHasKey('locations', $props);

        // O recorte por localidade da tela é client-side, e só pode ser porque o
        // mural vem como LISTA — não como paginator. Se um dia virar paginate(),
        // este assert quebra e o filtro do cabeçalho passa a mentir (mostraria
        // "nenhuma desta localidade" com resultado na página 2).
        $this->assertIsArray($props['messages']);
        $this->assertArrayNotHasKey('current_page', $props['messages']);

        // Cada mensagem carrega a localidade que o recorte usa.
        foreach ($props['messages'] as $m) {
            $this->assertArrayHasKey('location_id', $m, 'Sem location_id o filtro do cabeçalho não tem o que recortar');
        }
    }
}
