<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Tests\Support\WithSeededTenant;

uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    // CI SQLite :memory: sem migrate — controller persiste em comvis_orcamentos
    // + comvis_orcamento_itens; tests precisam schema MySQL UPos completo.
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_orcamentos')) {
        $this->markTestSkipped('Requer MySQL com as tabelas comvis_* migradas.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
});

/**
 * Testes de integração do OrcamentoController — endpoints API JSON.
 *
 * US-COMVIS-001: cálculo m² authoritative + persistência.
 *
 * Endpoints cobertos:
 *   POST /comunicacao-visual/api/calcular       → preview sem persistência
 *   POST /comunicacao-visual/api/orcamentos     → persiste + 201
 *   GET  /comunicacao-visual/api/orcamentos/{id} → retorna com itens
 *
 * Tenant 98 contra 99, as duas fictícias (ADR 0358). O usuário é criado com exatamente as
 * permissões de orçamento: o seed do CI não dá papel nenhum ao usuário da empresa, e um 403
 * aqui é falha, não ambiente — até 2026-11-11 ele virava teste pulado.
 *
 * Contrato de tela que este arquivo defende (trio · ADR 0264 G-2):
 *   UC-CV-02 — medida inválida vira 422 em PT-BR                       (CU-CV-02 item 5)
 *   UC-CV-05 — orçamento de outro business responde 404, não 403       (CU-CV-04 item 3) [T0]
 *   UC-CV-11 — salvar grava os valores do servidor, atomicamente       (CU-CV-05)
 *
 * ⚠️ Pula em SQLite (beforeEach): roda na lane MySQL `verticais-pest.yml`.
 *
 * @covers-us US-COMVIS-001
 *
 * @see Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController
 * @see memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md §6 CU-CV-02/CU-CV-04/CU-CV-05
 * @see resources/js/Pages/ComunicacaoVisual/Index.casos.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// ------------------------------------------------------------------
// Helper: usuário da empresa com as permissões de orçamento, logado nela
// ------------------------------------------------------------------

function bootstrapComvisUser($test, ?Business $business = null): User
{
    $business ??= $test->biz;
    $user = $test->usuarioComPermissoes(['comvis.orcamento.view', 'comvis.orcamento.create'], $business);

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
    ]);

    return $user;
}

function payloadCalculo(): array
{
    return [
        'data_emissao'    => '2026-05-10',
        'data_validade'   => '2026-05-25',
        'contato_id'      => null,
        'vendedor_id'     => null,
        'desconto'        => 0.00,
        'extras'          => 50.00,
        'custo_instalacao' => 200.00,
        'custo_entrega'   => 80.00,
        'observacoes'     => 'banner externo teste',
        'itens'           => [
            [
                'descricao'        => 'Banner 3x1.5 lona front',
                'largura_m'        => 3.000,
                'altura_m'         => 1.500,
                'quantidade'       => 1,
                'preco_unitario_m2' => 60.00,
            ],
        ],
    ];
}

/** Orçamento gravado direto na empresa dada. `numero` cabe nos 20 caracteres da coluna. */
function orcamentoDeTeste(int $businessId, float $total = 100.00): Orcamento
{
    // SUPERADMIN: fixture criada para uma empresa escolhida pelo teste, sem depender da sessão.
    return Orcamento::withoutGlobalScopes()->create([
        'business_id'      => $businessId,
        'numero'           => 'ORC-T-' . uniqid(),
        'data_emissao'     => '2026-05-10',
        'status'           => 'rascunho',
        'subtotal'         => $total,
        'desconto'         => 0.00,
        'extras'           => 0.00,
        'custo_instalacao' => 0.00,
        'custo_entrega'    => 0.00,
        'total'            => $total,
    ]);
}

// ------------------------------------------------------------------
// POST /calcular — preview authoritative sem persistência
// ------------------------------------------------------------------

it('POST /calcular retorna 200 com JSON shape calculado', function () {
    $user = bootstrapComvisUser($this);

    $response = $this->actingAs($user)
        ->postJson('/comunicacao-visual/api/calcular', payloadCalculo());

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data_emissao',
        'subtotal',
        'desconto',
        'extras',
        'custo_instalacao',
        'custo_entrega',
        'total',
        'itens' => [
            '*' => [
                'descricao',
                'area_m2',
                'preco_unitario_m2',
                'subtotal',
            ],
        ],
    ]);

    // Valores calculados corretos. O JSON tem um tipo numérico só: sem JSON_PRESERVE_ZERO_FRACTION,
    // 270.0 sai como 270 e volta int. O contrato é o VALOR, então compara em float.
    $json = $response->json();
    expect((float) $json['itens'][0]['area_m2'])->toBe(4.5);    // 3 × 1.5 × 1
    expect((float) $json['itens'][0]['subtotal'])->toBe(270.0); // 4.5 × 60
    expect((float) $json['subtotal'])->toBe(270.0);
    // total = 270 - 0 + 50 + 200 + 80 = 600
    expect((float) $json['total'])->toBe(600.0);

    // NÃO deve ter persitido nada no banco
    expect(Orcamento::withoutGlobalScopes()->where('observacoes', 'banner externo teste')->count())->toBe(0);
});

// ------------------------------------------------------------------
// POST /orcamentos — persiste + retorna 201
// ------------------------------------------------------------------

it('UC-CV-11 · POST /orcamentos persiste no DB e retorna 201 com Orcamento + itens', function () {
    $user = bootstrapComvisUser($this);

    $payload              = payloadCalculo();
    $payload['observacoes'] = 'teste-store-' . uniqid();

    $response = $this->actingAs($user)
        ->postJson('/comunicacao-visual/api/orcamentos', $payload);

    $response->assertStatus(201);
    $json = $response->json();

    // Estrutura da resposta
    expect($json)->toHaveKey('id');
    expect($json)->toHaveKey('numero');
    expect($json)->toHaveKey('status');
    expect($json)->toHaveKey('itens');
    expect($json['status'])->toBe('rascunho');
    expect($json['numero'])->toMatch('/^ORC-\d{4}-\d{5}$/');

    // Persitiu no DB, na empresa do usuário
    $orcDb = Orcamento::withoutGlobalScopes()->find($json['id']);
    expect($orcDb)->not->toBeNull();
    expect((int) $orcDb->business_id)->toBe($this->biz->id);
    expect($orcDb->total)->toEqual(600.00);

    // Item persitiu também
    $itensDb = OrcamentoItem::withoutGlobalScopes()->where('orcamento_id', $json['id'])->get();
    expect($itensDb)->toHaveCount(1);
    expect((float) $itensDb->first()->area_m2)->toEqual(4.500);
});

// ------------------------------------------------------------------
// GET /orcamentos/{id} — retorna orçamento com itens
// ------------------------------------------------------------------

it('GET /orcamentos/{id} retorna Orcamento com itens', function () {
    $user = bootstrapComvisUser($this);
    $orc  = orcamentoDeTeste($this->biz->id, 270.00);

    // SUPERADMIN: fixture criada para uma empresa escolhida pelo teste, sem depender da sessão.
    OrcamentoItem::withoutGlobalScopes()->create([
        'orcamento_id'     => $orc->id,
        'business_id'      => $this->biz->id,
        'descricao'        => 'Banner 3x1.5',
        'largura_m'        => 3.000,
        'altura_m'         => 1.500,
        'quantidade'       => 1,
        'area_m2'          => 4.500,
        'preco_unitario_m2' => 60.00,
        'subtotal'         => 270.00,
        'ordem'            => 1,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/comunicacao-visual/api/orcamentos/{$orc->id}");

    $response->assertStatus(200);
    $json = $response->json();
    expect($json['id'])->toBe($orc->id);
    expect($json['itens'])->toHaveCount(1);
    expect($json['itens'][0]['descricao'])->toBe('Banner 3x1.5');
});

// ------------------------------------------------------------------
// Multi-tenant Tier 0: a outra empresa NÃO vê o orçamento
// ADR 0093 — IRREVOGÁVEL
// ------------------------------------------------------------------

it('UC-CV-05 · Multi-tenant Tier 0: GET /orcamentos/{id} retorna 404 para session biz diferente', function () {
    $orc = orcamentoDeTeste($this->biz->id);

    // Controle positivo: a própria empresa enxerga o orçamento — o 404 abaixo não é rota quebrada.
    $dono = bootstrapComvisUser($this);
    $this->actingAs($dono)->getJson("/comunicacao-visual/api/orcamentos/{$orc->id}")->assertStatus(200);

    // Usuário da outra empresa, com as mesmas permissões, na sessão dela.
    $alheio = bootstrapComvisUser($this, $this->outra);

    // findOrFail com o escopo da empresa 99 deve dar 404 (não 403, que confirmaria a existência)
    $this->actingAs($alheio)
        ->getJson("/comunicacao-visual/api/orcamentos/{$orc->id}")
        ->assertStatus(404);
});
