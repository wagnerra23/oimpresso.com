<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * NfseCockpitController — bug 500 documentado + regression test.
 *
 * STATUS (2026-05-25): ROTA QUEBRADA EM PROD.
 *
 * Bug: schema race entre 2 migrations duplicadas pra `nfse_emissoes`:
 *   - Batch 69 (2026-05-01_000003_create_nfse_emissoes_table) — schema VELHO
 *     (`tomador_cnpj`, `valor_servicos`, `created_at`) — ATIVO em prod
 *   - Batch 106 (2026-05-11_150001_create_nfse_emissoes_table) — schema NOVO
 *     (`cpf_cnpj_tomador`, `value_servico`, `emitted_at`) — NÃO RODOU pq tabela já existia
 *
 * Controller `NfseCockpitController` + Model `NfseEmissao` foram escritos pro
 * schema NOVO. Query em prod retorna `SQLSTATE[42S22]: Column not found:
 * 'emitted_at'` (linha 55 NfseCockpitController::computeCounts).
 *
 * Fix opções (decisão Wagner, fora do escopo deste PR):
 *  - A) Reverter Controller/Model pro schema velho (compat)
 *  - B) Criar migration RENAME 13 colunas + add emitted_at (schema migration)
 *  - C) Drop + recreate (perda de dados nfse_emissoes em prod — risco)
 *
 * Tests aqui assertem o comportamento ESPERADO. Vão ser SKIPPED até bug ser
 * resolvido (task #12 MCP). Quando schema for unificado, remover markTestSkipped.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfseEmissao requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfse_emissoes')) {
        $this->markTestSkipped('nfse_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }
    if (! Schema::hasColumn('nfse_emissoes', 'emitted_at')) {
        $this->markTestSkipped(
            'BUG ATIVO (task #12): nfse_emissoes.emitted_at não existe (schema race ' .
            'entre migration batch 69 + batch 106). Controller/Model usam schema NOVO ' .
            'mas tabela em prod tem schema VELHO. Resolver antes de habilitar este test.'
        );
    }
});

it('GET /fiscal/nfse aborta 403 sem permission superadmin nem fiscal.nfse.view', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $this->actingAs($user);

    $response = $this->get('/fiscal/nfse');
    $response->assertStatus(403);
});

it('GET /fiscal/nfse renderiza Inertia Fiscal/Nfse com filters/counts canon', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 1, 'user.business_id' => 1]);

    $response = $this->get('/fiscal/nfse');
    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('Fiscal/Nfse')
            ->has('filters', fn ($f) => $f->hasAll(['search', 'status', 'mes']))
            ->has('counts')
    );
});

it('counts shape canon — 6 chaves obrigatorias', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 1, 'user.business_id' => 1]);

    $response = $this->get('/fiscal/nfse');
    $response->assertInertia(
        fn ($page) => $page->where(
            'counts',
            fn ($c) => collect(['total', 'autorizadas', 'rejeitadas', 'processando', 'canceladas', 'faturamento'])
                ->every(fn ($k) => array_key_exists($k, $c))
        )
    );
});

it('filtro mes invalido nao crasha (ignora silenciosamente)', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 1, 'user.business_id' => 1]);

    $response = $this->get('/fiscal/nfse?mes=INVALIDO');
    $response->assertStatus(200);
});
