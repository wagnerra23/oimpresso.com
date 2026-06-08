<?php

namespace Tests\Feature\Modules\Financeiro;

use App\Business;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Tests\Feature\FinanceiroTestCase;

/**
 * CRUD livre de fin_categorias — pattern ADR 0024.
 *
 * Cobre:
 *  - store cria categoria scoped no business
 *  - index NÃO vaza categoria de outro business (BusinessScope)
 *  - validação nome único POR business
 *  - cor hex válida vs inválida (regex)
 *  - toggleAtivo flipa boolean
 *  - destroy é soft delete + index não lista após delete
 *
 * Roda contra DB dev real (mesmo pattern do MultiTenantIsolationTest).
 *
 * Note: classe PHPUnit (não Pest pure) pra contornar conflito de
 * uses() em Pest.php que já registra TestCase pra todo Feature/.
 */
class CategoriaCrudTest extends FinanceiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
        $this->cleanupTestRows();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestRows();
        parent::tearDown();
    }

    private function cleanupTestRows(): void
    {
        Categoria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('nome', 'like', 'TEST_CATEGORIA_%')
            ->forceDelete();
    }

    public function test_cria_categoria_scoped_no_business(): void
    {
        $businessId = $this->business->id;

        $response = $this->post('/financeiro/categorias', [
            'nome' => 'TEST_CATEGORIA_CRIAR',
            'cor' => '#FF6B6B',
            'plano_conta_id' => null,
            'tipo' => 'despesa',
            'ativo' => true,
        ]);

        $response->assertStatus(302);

        $count = Categoria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('nome', 'TEST_CATEGORIA_CRIAR')
            ->where('business_id', $businessId)
            ->count();

        $this->assertEquals(1, $count, 'Categoria deveria ter sido criada no business correto.');
    }

    public function test_nao_vaza_categoria_de_outro_business_via_scope(): void
    {
        $primary = $this->business;

        $other = Business::where('id', '!=', $primary->id)->first();
        if (! $other) {
            $this->markTestSkipped('Sem segundo business em DB pra testar isolamento real.');
        }

        // Cria 1 categoria no business primary (bypass scope pra direct insert)
        Categoria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $primary->id,
                'nome' => 'TEST_CATEGORIA_PRIMARY',
                'tipo' => 'receita',
                'ativo' => true,
            ]);

        // Cria 1 categoria no business OUTRO (bypass scope)
        Categoria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $other->id,
                'nome' => 'TEST_CATEGORIA_OUTRO',
                'tipo' => 'receita',
                'ativo' => true,
            ]);

        // Mesmo pattern do MultiTenantIsolationTest:
        // logout pra remover bypass de superadmin e forçar BusinessScope.
        // Setamos sessão pro business primary direto.
        auth()->logout();
        session(['user.business_id' => $primary->id]);

        // Query via Eloquent normal (com scope) — deve só achar a do primary.
        $nomesScope = Categoria::where('nome', 'like', 'TEST_CATEGORIA_%')
            ->pluck('nome')
            ->toArray();

        $this->assertContains('TEST_CATEGORIA_PRIMARY', $nomesScope, 'Scope deveria mostrar do primary.');
        $this->assertNotContains('TEST_CATEGORIA_OUTRO', $nomesScope, 'Scope NÃO deveria vazar do outro business.');

        // E quando trocamos a sessão pro outro business, vê só a do outro.
        session(['user.business_id' => $other->id]);
        $nomesOther = Categoria::where('nome', 'like', 'TEST_CATEGORIA_%')
            ->pluck('nome')
            ->toArray();

        $this->assertContains('TEST_CATEGORIA_OUTRO', $nomesOther);
        $this->assertNotContains('TEST_CATEGORIA_PRIMARY', $nomesOther);
    }

    /**
     * Extrai a lista de nomes de categorias da prop "categorias" da resposta Inertia.
     * Suporta tanto resposta JSON (X-Inertia: true) quanto HTML (data-page atributo).
     */
    private function extractCategoriaNomes($response): array
    {
        $payload = json_decode($response->getContent(), true);

        if (is_array($payload) && isset($payload['props']['categorias'])) {
            return collect($payload['props']['categorias'])->pluck('nome')->toArray();
        }

        // Fallback: parse data-page do HTML
        $html = $response->getContent();
        if (preg_match('/data-page="([^"]+)"/', $html, $m)) {
            $page = json_decode(html_entity_decode($m[1]), true);
            if (is_array($page) && isset($page['props']['categorias'])) {
                return collect($page['props']['categorias'])->pluck('nome')->toArray();
            }
        }

        return [];
    }

    public function test_valida_nome_unico_por_business(): void
    {
        Categoria::create([
            'nome' => 'TEST_CATEGORIA_DUP',
            'tipo' => 'ambos',
            'ativo' => true,
        ]);

        $response = $this->post('/financeiro/categorias', [
            'nome' => 'TEST_CATEGORIA_DUP',
            'tipo' => 'ambos',
            'ativo' => true,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('nome');
    }

    public function test_aceita_cor_hex_valida_e_rejeita_invalida(): void
    {
        // Válido
        $valid = $this->post('/financeiro/categorias', [
            'nome' => 'TEST_CATEGORIA_COR_OK',
            'cor' => '#ABCDEF',
            'tipo' => 'ambos',
            'ativo' => true,
        ]);
        $valid->assertStatus(302);
        $valid->assertSessionHasNoErrors();

        // Inválido
        $invalid = $this->post('/financeiro/categorias', [
            'nome' => 'TEST_CATEGORIA_COR_RUIM',
            'cor' => 'vermelho',
            'tipo' => 'ambos',
            'ativo' => true,
        ]);
        $invalid->assertStatus(302);
        $invalid->assertSessionHasErrors('cor');
    }

    public function test_toggle_ativo_flipa_boolean(): void
    {
        $categoria = Categoria::create([
            'nome' => 'TEST_CATEGORIA_TOGGLE',
            'tipo' => 'ambos',
            'ativo' => true,
        ]);

        $response = $this->post("/financeiro/categorias/{$categoria->id}/toggle");
        $response->assertStatus(302);

        $reloaded = Categoria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->find($categoria->id);

        $this->assertFalse((bool) $reloaded->ativo, 'Toggle deveria ter inativado.');

        // Toggle de novo → volta pra true
        $this->post("/financeiro/categorias/{$categoria->id}/toggle");
        $reloaded->refresh();

        $this->assertTrue((bool) $reloaded->ativo, 'Segundo toggle deveria ter reativado.');
    }

    public function test_destroy_faz_soft_delete(): void
    {
        $categoria = Categoria::create([
            'nome' => 'TEST_CATEGORIA_DEL',
            'tipo' => 'ambos',
            'ativo' => true,
        ]);
        $id = $categoria->id;

        $response = $this->delete("/financeiro/categorias/{$id}");
        $response->assertStatus(302);

        // deleted_at preenchido
        $row = DB::table('fin_categorias')->where('id', $id)->first();
        $this->assertNotNull($row, 'Linha não deveria sumir (soft delete).');
        $this->assertNotNull($row->deleted_at, 'deleted_at deveria estar preenchido.');

        // Não aparece em queries normais
        $found = Categoria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->find($id);
        $this->assertNull($found, 'Soft-deleted não deveria voltar em find().');

        // Index não lista após delete
        $response = $this->inertiaGet('/financeiro/categorias');
        $nomes = $this->extractCategoriaNomes($response);
        $this->assertNotContains('TEST_CATEGORIA_DEL', $nomes);
    }

    public function test_update_altera_campos_da_categoria(): void
    {
        $categoria = Categoria::create([
            'nome' => 'TEST_CATEGORIA_UP',
            'cor' => '#111111',
            'tipo' => 'receita',
            'ativo' => true,
        ]);

        $response = $this->put("/financeiro/categorias/{$categoria->id}", [
            'nome' => 'TEST_CATEGORIA_UP_NOVO',
            'cor' => '#222222',
            'tipo' => 'despesa',
            'ativo' => false,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $reloaded = Categoria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->find($categoria->id);

        $this->assertEquals('TEST_CATEGORIA_UP_NOVO', $reloaded->nome);
        $this->assertEquals('#222222', $reloaded->cor);
        $this->assertEquals('despesa', $reloaded->tipo);
        $this->assertFalse((bool) $reloaded->ativo);
    }
}
