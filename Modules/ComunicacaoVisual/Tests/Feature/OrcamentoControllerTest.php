<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;

uses(Tests\TestCase::class);

beforeEach(function () {
    // CI SQLite :memory: sem migrate — controller persiste em comvis_orcamentos
    // + comvis_orcamento_itens; tests precisam schema MySQL UPos completo.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (Wagner Pest local segue mandatory — ADR 0101)');
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
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 * Multi-tenant Tier 0: biz=99 não deve ver orçamentos do biz=1.
 *
 * @see Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// ------------------------------------------------------------------
// Helper: bootstrap user autenticado no biz=1
// ------------------------------------------------------------------

function bootstrapComvisUser(): User
{
    try {
        $business = Business::find(1) ?? Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business_id=1.');
    }

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
        'is_admin'                 => true,
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

// ------------------------------------------------------------------
// POST /calcular — preview authoritative sem persistência
// ------------------------------------------------------------------

it('POST /calcular retorna 200 com JSON shape calculado', function () {
    $user = bootstrapComvisUser();

    $response = $this->actingAs($user)
        ->postJson('/comunicacao-visual/api/calcular', payloadCalculo());

    if ($response->status() === 403) {
        test()->markTestSkipped('Gate de módulo bloqueia neste env.');
    }

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

    // Valores calculados corretos
    $json = $response->json();
    expect($json['itens'][0]['area_m2'])->toBe(4.5);    // 3 × 1.5 × 1
    expect($json['itens'][0]['subtotal'])->toBe(270.0); // 4.5 × 60
    expect($json['subtotal'])->toBe(270.0);
    // total = 270 - 0 + 50 + 200 + 80 = 600
    expect($json['total'])->toBe(600.0);

    // NÃO deve ter persitido nada no banco
    expect(Orcamento::withoutGlobalScopes()->where('observacoes', 'banner externo teste')->count())->toBe(0);
});

// ------------------------------------------------------------------
// POST /orcamentos — persiste + retorna 201
// ------------------------------------------------------------------

it('POST /orcamentos persiste no DB e retorna 201 com Orcamento + itens', function () {
    $user = bootstrapComvisUser();

    $payload              = payloadCalculo();
    $payload['observacoes'] = 'teste-store-' . uniqid();

    $response = $this->actingAs($user)
        ->postJson('/comunicacao-visual/api/orcamentos', $payload);

    if ($response->status() === 403) {
        test()->markTestSkipped('Gate de módulo bloqueia neste env.');
    }

    $response->assertStatus(201);
    $json = $response->json();

    // Estrutura da resposta
    expect($json)->toHaveKey('id');
    expect($json)->toHaveKey('numero');
    expect($json)->toHaveKey('status');
    expect($json)->toHaveKey('itens');
    expect($json['status'])->toBe('rascunho');
    expect($json['numero'])->toMatch('/^ORC-\d{4}-\d{5}$/');

    // Persitiu no DB
    $orcDb = Orcamento::withoutGlobalScopes()->find($json['id']);
    expect($orcDb)->not->toBeNull();
    expect($orcDb->total)->toEqual(600.00);

    // Item persitiu também
    $itensDb = OrcamentoItem::withoutGlobalScopes()->where('orcamento_id', $json['id'])->get();
    expect($itensDb)->toHaveCount(1);
    expect((float) $itensDb->first()->area_m2)->toEqual(4.500);

    // Limpar
    OrcamentoItem::withoutGlobalScopes()->where('orcamento_id', $json['id'])->delete();
    Orcamento::withoutGlobalScopes()->where('id', $json['id'])->forceDelete();
});

// ------------------------------------------------------------------
// GET /orcamentos/{id} — retorna orçamento com itens
// ------------------------------------------------------------------

it('GET /orcamentos/{id} retorna Orcamento com itens', function () {
    $user       = bootstrapComvisUser();
    $businessId = session('user.business_id');

    // Criar orçamento no biz=1 via withoutGlobalScopes (SUPERADMIN: setup de teste)
    $orc = Orcamento::withoutGlobalScopes()->create([
        'business_id'      => $businessId,
        'numero'           => 'ORC-CTRL-TESTE-' . uniqid(),
        'data_emissao'     => '2026-05-10',
        'status'           => 'rascunho',
        'subtotal'         => 270.00,
        'desconto'         => 0.00,
        'extras'           => 50.00,
        'custo_instalacao' => 200.00,
        'custo_entrega'    => 80.00,
        'total'            => 600.00,
    ]);

    OrcamentoItem::withoutGlobalScopes()->create([
        'orcamento_id'     => $orc->id,
        'business_id'      => $businessId,
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

    if ($response->status() === 403) {
        test()->markTestSkipped('Gate de módulo bloqueia neste env.');
    }

    $response->assertStatus(200);
    $json = $response->json();
    expect($json['id'])->toBe($orc->id);
    expect($json['itens'])->toHaveCount(1);
    expect($json['itens'][0]['descricao'])->toBe('Banner 3x1.5');

    // Limpar
    OrcamentoItem::withoutGlobalScopes()->where('orcamento_id', $orc->id)->delete();
    Orcamento::withoutGlobalScopes()->where('id', $orc->id)->forceDelete();
});

// ------------------------------------------------------------------
// Multi-tenant Tier 0: biz=99 NÃO vê orçamentos do biz=1
// ADR 0093 — IRREVOGÁVEL
// ------------------------------------------------------------------

it('Multi-tenant Tier 0: GET /orcamentos/{id} retorna 404 para session biz diferente', function () {
    // Criar orçamento no biz=1 (SUPERADMIN: setup de teste)
    $orc = Orcamento::withoutGlobalScopes()->create([
        'business_id'      => 1,
        'numero'           => 'ORC-MT-TESTE-' . uniqid(),
        'data_emissao'     => '2026-05-10',
        'status'           => 'rascunho',
        'subtotal'         => 100.00,
        'desconto'         => 0.00,
        'extras'           => 0.00,
        'custo_instalacao' => 0.00,
        'custo_entrega'    => 0.00,
        'total'            => 100.00,
    ]);

    // Autenticar como usuário do biz=1 mas forçar sessão biz=99 pra simular outro tenant
    try {
        $user = User::where('business_id', 1)->first();
    } catch (\Throwable) {
        test()->markTestSkipped('User biz=1 indisponível.');
    }

    if (! $user) {
        test()->markTestSkipped('User biz=1 indisponível.');
    }

    // Sessão com biz=99 → global scope deve filtrar fora o orçamento do biz=1
    session([
        'user.business_id' => 99,
        'business.id'      => 99,
        'is_admin'         => true,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/comunicacao-visual/api/orcamentos/{$orc->id}");

    // findOrFail com global scope biz=99 deve lançar 404 (não encontra o biz=1)
    $response->assertStatus(404);

    // Limpar
    Orcamento::withoutGlobalScopes()->where('id', $orc->id)->forceDelete();
});
