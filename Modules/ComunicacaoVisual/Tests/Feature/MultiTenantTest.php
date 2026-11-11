<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Modules\ComunicacaoVisual\Entities\Os;
use Tests\Support\WithSeededTenant;

uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

/**
 * Testa isolamento multi-tenant Tier 0 dos Models CV.
 *
 * ADR 0093: global scope business_id IRREVOGÁVEL.
 * Dados da empresa de teste 98 NÃO podem aparecer em queries com a sessão da empresa 99.
 *
 * Tenant 98 contra 99, as duas fictícias (ADR 0358). Nunca a 4 (cliente real) nem a 1 (empresa
 * real: no CT 100 a base é clone de prod e não se limpa entre runs). A transação desfaz o que
 * cada caso grava.
 *
 * Contrato de tela que este arquivo defende (trio · ADR 0264 G-2):
 *   UC-CV-05 — orçamento, OS e apontamento de outro business não aparecem (CU-CV-04) [T0]
 *
 * ⚠️ Aborta INTEIRO em SQLite: a lane do PR (`modules-pest.yml`) roda sqlite :memory: sem
 * migrate, então o verde dela NÃO prova este isolamento — prova que ele foi pulado. O veredito
 * real vem da full-suite noturna (MySQL). Ver SDD §6 (3 portas) e §9 D-7.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md §6 CU-CV-04
 * @see resources/js/Pages/ComunicacaoVisual/Index.casos.md
 */

// Guard SQLite: Models CV com BusinessScope + global scope requerem schema MySQL UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models ComunicacaoVisual com BusinessScope requerem schema MySQL UltimatePOS (Wagner Pest local segue mandatory — ADR 0101)');
    }
    if (! Schema::hasTable('comvis_materiais')) {
        $this->markTestSkipped('comvis_materiais table missing — rode Modules/ComunicacaoVisual migrate primeiro');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
});

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

it('UC-CV-05 · Material da empresa do teste não aparece na sessão da outra empresa', function () {
    // Criar material na empresa do teste
    setBizSession($this->biz->id);
    $material = Material::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'    => $this->biz->id,
        'nome'           => 'Lona Blackout Teste',
        'categoria'      => 'lona',
        'unidade'        => 'm2',
        'preco_custo_m2' => 12.50,
        'preco_venda_m2' => 25.00,
        'ativo'          => true,
    ]);

    // Consultar com a sessão da outra empresa — NÃO deve aparecer
    setBizSession($this->outra->id);
    $resultado = Material::where('id', $material->id)->get();

    expect($resultado)->toHaveCount(0);
});

it('UC-CV-05 · Material da empresa do teste aparece na sessão dela (controle positivo)', function () {
    setBizSession($this->biz->id);
    $material = Material::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'    => $this->biz->id,
        'nome'           => 'Vinil Adesivo Teste',
        'categoria'      => 'vinil_adesivo',
        'unidade'        => 'm2',
        'preco_custo_m2' => 8.00,
        'preco_venda_m2' => 18.00,
        'ativo'          => true,
    ]);

    setBizSession($this->biz->id);
    $resultado = Material::where('id', $material->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->nome)->toBe('Vinil Adesivo Teste');
});

// ------------------------------------------------------------------
// Orcamento
// ------------------------------------------------------------------

it('UC-CV-05 · Orcamento da empresa do teste não aparece na sessão da outra empresa', function () {
    setBizSession($this->biz->id);
    $orc = Orcamento::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => $this->biz->id,
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

    // Consultar com a sessão da outra empresa — NÃO deve aparecer
    setBizSession($this->outra->id);
    $resultado = Orcamento::where('id', $orc->id)->get();

    expect($resultado)->toHaveCount(0);
});

it('UC-CV-05 · Orcamento da empresa do teste aparece na sessão dela (controle positivo)', function () {
    setBizSession($this->biz->id);
    $orc = Orcamento::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => $this->biz->id,
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

    setBizSession($this->biz->id);
    $resultado = Orcamento::where('id', $orc->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->numero)->toBe('ORC-TESTE-99992');
});

// ------------------------------------------------------------------
// Os
// ------------------------------------------------------------------

it('UC-CV-05 · Os da empresa do teste não aparece na sessão da outra empresa', function () {
    setBizSession($this->biz->id);
    $os = Os::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => $this->biz->id,
        'numero'       => 'OS-TESTE-99991',
        'status_etapa' => 'arte',
        'valor_total'  => 0,
    ]);

    // Consultar com a sessão da outra empresa — NÃO deve aparecer
    setBizSession($this->outra->id);
    $resultado = Os::where('id', $os->id)->get();

    expect($resultado)->toHaveCount(0);
});

it('UC-CV-05 · Os da empresa do teste aparece na sessão dela (controle positivo)', function () {
    setBizSession($this->biz->id);
    $os = Os::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => $this->biz->id,
        'numero'       => 'OS-TESTE-99992',
        'status_etapa' => 'producao',
        'valor_total'  => 150.00,
    ]);

    setBizSession($this->biz->id);
    $resultado = Os::where('id', $os->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->numero)->toBe('OS-TESTE-99992');
    expect($resultado->first()->status_etapa)->toBe('producao');
});

// ------------------------------------------------------------------
// OrcamentoItem (isolamento via business_id redundante)
// ------------------------------------------------------------------

it('UC-CV-05 · OrcamentoItem da empresa do teste não aparece na sessão da outra empresa', function () {
    // Criar orçamento pai primeiro (sem global scope pra bypass do biz check)
    $orc = Orcamento::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => $this->biz->id,
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
        'business_id'      => $this->biz->id,
        'descricao'        => 'Banner Teste Isolamento',
        'quantidade'       => 1,
        'preco_unitario_m2' => 25.00,
        'subtotal'         => 25.00,
    ]);

    // Consultar com a sessão da outra empresa — NÃO deve aparecer
    setBizSession($this->outra->id);
    $resultado = OrcamentoItem::where('id', $item->id)->get();

    expect($resultado)->toHaveCount(0);
});
