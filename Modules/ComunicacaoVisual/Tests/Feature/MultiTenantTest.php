<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Modules\ComunicacaoVisual\Entities\Os;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 dos Models CV.
 *
 * ADR 0093: global scope business_id IRREVOGÁVEL.
 * Dados do biz=1 NÃO podem aparecer em queries com session biz=99 e vice-versa.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — conforme ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício, sem dados).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard SQLite: Models CV com BusinessScope + global scope requerem schema MySQL UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models ComunicacaoVisual com BusinessScope requerem schema MySQL UltimatePOS (Wagner Pest local segue mandatory — ADR 0101)');
    }
    if (! Schema::hasTable('comvis_materiais')) {
        $this->markTestSkipped('comvis_materiais table missing — rode Modules/ComunicacaoVisual migrate primeiro');
    }
});

// IDs usados nos testes — biz=1 (Wagner) e biz=99 (fictício isolamento)
defined('BIZ_WAGNER') || define('BIZ_WAGNER', 1);
defined('BIZ_FICTICIO') || define('BIZ_FICTICIO', 99);

// ------------------------------------------------------------------
// Helpers internos
// ------------------------------------------------------------------

/**
 * Simula sessão de um business sem autenticar usuário (suficiente pro global scope).
 */
function setBizSession(int $businessId): void
{
    session(['user.business_id' => $businessId]);
}

// ------------------------------------------------------------------
// Material
// ------------------------------------------------------------------

it('Material biz=1 não aparece com session biz=99', function () {
    // Criar material no biz=1
    setBizSession(BIZ_WAGNER);
    $material = Material::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'    => BIZ_WAGNER,
        'nome'           => 'Lona Blackout Teste',
        'categoria'      => 'lona',
        'unidade'        => 'm2',
        'preco_custo_m2' => 12.50,
        'preco_venda_m2' => 25.00,
        'ativo'          => true,
    ]);

    // Consultar com session biz=99 — NÃO deve aparecer
    setBizSession(BIZ_FICTICIO);
    $resultado = Material::where('id', $material->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Material::withoutGlobalScopes()->where('business_id', BIZ_WAGNER)
        ->where('nome', 'Lona Blackout Teste')
        ->forceDelete();
});

it('Material biz=1 aparece com session biz=1', function () {
    setBizSession(BIZ_WAGNER);
    $material = Material::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'    => BIZ_WAGNER,
        'nome'           => 'Vinil Adesivo Teste',
        'categoria'      => 'vinil_adesivo',
        'unidade'        => 'm2',
        'preco_custo_m2' => 8.00,
        'preco_venda_m2' => 18.00,
        'ativo'          => true,
    ]);

    setBizSession(BIZ_WAGNER);
    $resultado = Material::where('id', $material->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->nome)->toBe('Vinil Adesivo Teste');
})->afterEach(function () {
    Material::withoutGlobalScopes()->where('business_id', BIZ_WAGNER)
        ->where('nome', 'Vinil Adesivo Teste')
        ->forceDelete();
});

// ------------------------------------------------------------------
// Orcamento
// ------------------------------------------------------------------

it('Orcamento biz=1 não aparece com session biz=99', function () {
    setBizSession(BIZ_WAGNER);
    $orc = Orcamento::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_WAGNER,
        'numero'       => 'ORC-TESTE-99991',
        'data_emissao' => now()->toDateString(),
        'status'       => 'rascunho',
        'subtotal'     => 0,
        'desconto'     => 0,
        'extras'       => 0,
        'custo_instalacao' => 0,
        'custo_entrega'    => 0,
        'total'            => 0,
    ]);

    // Consultar com session biz=99 — NÃO deve aparecer
    setBizSession(BIZ_FICTICIO);
    $resultado = Orcamento::where('id', $orc->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Orcamento::withoutGlobalScopes()->where('numero', 'ORC-TESTE-99991')->forceDelete();
});

it('Orcamento biz=1 aparece com session biz=1', function () {
    setBizSession(BIZ_WAGNER);
    $orc = Orcamento::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_WAGNER,
        'numero'       => 'ORC-TESTE-99992',
        'data_emissao' => now()->toDateString(),
        'status'       => 'rascunho',
        'subtotal'     => 0,
        'desconto'     => 0,
        'extras'       => 0,
        'custo_instalacao' => 0,
        'custo_entrega'    => 0,
        'total'            => 0,
    ]);

    setBizSession(BIZ_WAGNER);
    $resultado = Orcamento::where('id', $orc->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->numero)->toBe('ORC-TESTE-99992');
})->afterEach(function () {
    Orcamento::withoutGlobalScopes()->where('numero', 'ORC-TESTE-99992')->forceDelete();
});

// ------------------------------------------------------------------
// Os
// ------------------------------------------------------------------

it('Os biz=1 não aparece com session biz=99', function () {
    setBizSession(BIZ_WAGNER);
    $os = Os::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_WAGNER,
        'numero'       => 'OS-TESTE-99991',
        'status_etapa' => 'arte',
        'valor_total'  => 0,
    ]);

    // Consultar com session biz=99 — NÃO deve aparecer
    setBizSession(BIZ_FICTICIO);
    $resultado = Os::where('id', $os->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Os::withoutGlobalScopes()->where('numero', 'OS-TESTE-99991')->forceDelete();
});

it('Os biz=1 aparece com session biz=1', function () {
    setBizSession(BIZ_WAGNER);
    $os = Os::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_WAGNER,
        'numero'       => 'OS-TESTE-99992',
        'status_etapa' => 'producao',
        'valor_total'  => 150.00,
    ]);

    setBizSession(BIZ_WAGNER);
    $resultado = Os::where('id', $os->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->numero)->toBe('OS-TESTE-99992');
    expect($resultado->first()->status_etapa)->toBe('producao');
})->afterEach(function () {
    Os::withoutGlobalScopes()->where('numero', 'OS-TESTE-99992')->forceDelete();
});

// ------------------------------------------------------------------
// OrcamentoItem (isolamento via business_id redundante)
// ------------------------------------------------------------------

it('OrcamentoItem biz=1 não aparece com session biz=99', function () {
    // Criar orçamento pai primeiro (sem global scope pra bypass do biz check)
    $orc = Orcamento::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_WAGNER,
        'numero'       => 'ORC-TESTE-99993',
        'data_emissao' => now()->toDateString(),
        'status'       => 'rascunho',
        'subtotal'     => 0,
        'desconto'     => 0,
        'extras'       => 0,
        'custo_instalacao' => 0,
        'custo_entrega'    => 0,
        'total'            => 0,
    ]);

    $item = OrcamentoItem::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'orcamento_id'     => $orc->id,
        'business_id'      => BIZ_WAGNER,
        'descricao'        => 'Banner Teste Isolamento',
        'quantidade'       => 1,
        'preco_unitario_m2' => 25.00,
        'subtotal'         => 25.00,
    ]);

    // Consultar com session biz=99 — NÃO deve aparecer
    setBizSession(BIZ_FICTICIO);
    $resultado = OrcamentoItem::where('id', $item->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    OrcamentoItem::withoutGlobalScopes()->whereHas('orcamento', function ($q) {
        $q->withoutGlobalScopes()->where('numero', 'ORC-TESTE-99993');
    })->delete();
    Orcamento::withoutGlobalScopes()->where('numero', 'ORC-TESTE-99993')->forceDelete();
});
