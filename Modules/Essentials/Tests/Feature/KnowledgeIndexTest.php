<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Tela /essentials/knowledge-base — base de conhecimento interna.
 *
 * Ponteiro podre fechado: o `@docvault` de `Knowledge/Index.tsx` citava
 * `KnowledgeIndexTest` desde a migração, e o arquivo nunca foi criado.
 *
 * A busca da tela é CLIENT-SIDE por desenho, e isso só é legítimo porque a árvore
 * inteira chega no payload. É essa pré-condição que se prova aqui — não a busca,
 * que não passa pelo servidor.
 *
 * @covers-us US-ESS-013
 */
class KnowledgeIndexTest extends EssentialsTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function index_exige_autenticacao(): void
    {
        $this->get('/essentials/knowledge-base')->assertRedirect('/login');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_retorna_o_component_inertia_da_tela(): void
    {
        $this->actAsAdmin();
        $this->assertInertiaComponent(
            $this->inertiaGet('/essentials/knowledge-base'),
            'Essentials/Knowledge/Index'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function arvore_chega_inteira_no_payload_que_e_a_pre_condicao_da_busca(): void
    {
        $this->actAsAdmin();

        // `books` é Inertia::defer — sem partial reload a prop nem existe.
        $props = $this->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $this->currentInertiaVersion() ?? '',
            'X-Inertia-Partial-Data'      => 'books',
            'X-Inertia-Partial-Component' => 'Essentials/Knowledge/Index',
            'Accept'                      => 'text/html',
        ])->get('/essentials/knowledge-base')->json('props');

        $this->assertArrayHasKey('books', $props);
        $this->assertIsArray($props['books']);

        // Lista, não paginator: buscar só na página carregada diria "nada encontrado"
        // com resultado na página 2. Se virar paginate(), a busca passa a mentir.
        $this->assertArrayNotHasKey('current_page', $props['books']);

        // Os 3 níveis chegam aninhados — a busca varre seção E artigo.
        foreach ($props['books'] as $livro) {
            $this->assertArrayHasKey('title', $livro);
            $this->assertArrayHasKey('children', $livro, 'Sem `children` a busca não alcança as seções');
            foreach ($livro['children'] as $secao) {
                $this->assertArrayHasKey(
                    'children',
                    $secao,
                    'Sem o 3º nível a busca não alcança os artigos — e é o nível que o usuário procura'
                );
            }
        }
    }
}
