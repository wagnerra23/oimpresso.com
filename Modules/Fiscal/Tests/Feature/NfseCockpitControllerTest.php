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
 * Tests aqui assertem o comportamento ESPERADO. Ficaram SKIPPED pelo guard de
 * `emitted_at` (task #12) até 2026-11-06: o controller passou a ler `created_at`
 * (schema velho, ver docblock de NfseCockpitController), então o guard saiu.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfseEmissao requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfse_emissoes')) {
        $this->markTestSkipped('nfse_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }
});

it('UC-FNFSE-02 · GET /fiscal/nfse aborta 403 sem permission superadmin nem fiscal.nfse.view', function () {
    $user = \App\User::factory()->create(['business_id' => 98]);
    $this->actingAs($user);

    $response = $this->get('/fiscal/nfse');
    $response->assertStatus(403);
});

it('UC-FNFSE-04 · GET /fiscal/nfse renderiza Inertia Fiscal/Nfse com filters/counts canon', function () {
    $user = \App\User::factory()->create(['business_id' => 98]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 98, 'user.business_id' => 98]);

    $response = $this->get('/fiscal/nfse');
    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('Fiscal/Nfse')
            ->has('filters', fn ($f) => $f->hasAll(['search', 'status', 'mes']))
            ->has('counts')
    );
});

it('UC-FNFSE-04 · counts shape canon — 6 chaves obrigatorias', function () {
    $user = \App\User::factory()->create(['business_id' => 98]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 98, 'user.business_id' => 98]);

    $response = $this->get('/fiscal/nfse');
    $response->assertInertia(
        fn ($page) => $page->where(
            'counts',
            fn ($c) => collect(['total', 'autorizadas', 'rejeitadas', 'processando', 'canceladas', 'faturamento'])
                ->every(fn ($k) => collect($c)->has($k)) // o Inertia entrega a prop como Collection
        )
    );
});

it('UC-FNFSE-03 · filtro mes invalido nao crasha (ignora silenciosamente)', function () {
    $user = \App\User::factory()->create(['business_id' => 98]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 98, 'user.business_id' => 98]);

    $response = $this->get('/fiscal/nfse?mes=INVALIDO');
    $response->assertStatus(200);
});

/**
 * UC-FNFSE-13..15 — o que a lista deferida devolve com cada filtro.
 * Lê o `buildRowsPayload` de verdade (reflection), com duas empresas: a da sessão (98) e a
 * vizinha (99), que nunca pode aparecer. Números 9_771_xxx reservados para este bloco.
 */
function nfseLinha(int $biz, string $numero, string $status, string $criadaEm, array $extra = []): void
{
    DB::table('nfse_emissoes')->insert($extra + [
        'business_id' => $biz, 'competencia' => substr($criadaEm, 0, 7) . '-01', 'numero' => $numero,
        'tomador_nome' => 'Tomador ' . $numero, 'descricao' => 'Serviço de teste', 'valor_servicos' => 100.00,
        'idempotency_key' => 'uc-fnfse-' . $numero, 'status' => $status,
        'created_at' => $criadaEm, 'updated_at' => $criadaEm,
    ]);
}

function nfseNumeros(array $filtros): array
{
    $m = new ReflectionMethod(Modules\Fiscal\Http\Controllers\NfseCockpitController::class, 'buildRowsPayload');
    $m->setAccessible(true);
    $payload = $m->invoke(new Modules\Fiscal\Http\Controllers\NfseCockpitController(), $filtros + ['search' => '', 'status' => 'todas', 'mes' => '2026-10']);

    return array_values(array_filter(
        array_map(fn ($r) => (string) $r['num'], $payload['data']),
        fn ($n) => str_starts_with($n, '9771'),
    ));
}

function nfseSemear(): void
{
    DB::table('nfse_emissoes')->where('numero', 'like', '9771%')->delete();
    nfseLinha(98, '9771001', 'emitida', '2026-10-05 10:00:00', ['tomador_cnpj' => '11222333000181', 'provider_codigo_verificacao' => 'ABC123XYZ']);
    nfseLinha(98, '9771002', 'erro', '2026-10-06 10:00:00', ['tomador_nome' => 'Grafica Alfa']);
    nfseLinha(98, '9771003', 'rascunho', '2026-10-07 10:00:00');
    nfseLinha(98, '9771004', 'processando', '2026-10-08 10:00:00', ['tomador_cpf' => '12345678909']);
    nfseLinha(98, '9771005', 'cancelada', '2026-10-09 10:00:00');
    nfseLinha(98, '9771006', 'emitida', '2026-09-20 10:00:00');
    nfseLinha(99, '9771007', 'emitida', '2026-10-10 10:00:00', ['tomador_nome' => 'Grafica Alfa']);

    $user = \App\User::factory()->create(['business_id' => 98]);
    test()->actingAs($user);
    session(['business.id' => 98, 'user.business_id' => 98]);
}

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfse_emissoes')) {
        DB::table('nfse_emissoes')->where('numero', 'like', '9771%')->delete();
    }
});

it('UC-FNFSE-13 · os chips filtram pela situação da nota de serviço, só da empresa', function () {
    nfseSemear();

    expect(nfseNumeros([]))->toBe(['9771005', '9771004', '9771003', '9771002', '9771001']); // mais nova primeiro
    expect(nfseNumeros(['status' => 'autorizadas']))->toBe(['9771001']);
    expect(nfseNumeros(['status' => 'rejeitadas']))->toBe(['9771002']);
    expect(nfseNumeros(['status' => 'processando']))->toBe(['9771004', '9771003']);       // rascunho + processando
    expect(nfseNumeros(['status' => 'canceladas']))->toBe(['9771005']);
});

it('UC-FNFSE-14 · a busca acha por número, código de verificação, nome e documento do tomador', function () {
    nfseSemear();

    expect(nfseNumeros(['search' => '9771001']))->toBe(['9771001']);
    expect(nfseNumeros(['search' => 'ABC123']))->toBe(['9771001']);
    expect(nfseNumeros(['search' => 'Grafica Alfa']))->toBe(['9771002']);   // a da vizinha, com o mesmo nome, não entra
    expect(nfseNumeros(['search' => '11222333000181']))->toBe(['9771001']);
    expect(nfseNumeros(['search' => '12345678909']))->toBe(['9771004']);
});

it('UC-FNFSE-15 · a competência escolhida restringe as linhas ao mês', function () {
    nfseSemear();

    expect(nfseNumeros(['mes' => '2026-09']))->toBe(['9771006']);
    expect(nfseNumeros(['mes' => '2026-10']))->not->toContain('9771006');
});
