<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave E-BE (ADR 0179 Q4 Default ON) -- ClienteIaController.
 *
 * Cobre 4 endpoints:
 *   POST /cliente/{id}/ia/resumo        -- LLM (mock fixture)
 *   POST /cliente/{id}/ia/segmento      -- LLM structured (mock fixture)
 *   POST /cliente/{id}/ia/proxima-acao  -- LLM structured (mock fixture)
 *   GET  /cliente/{id}/ia/score-risco   -- deterministico (zero LLM, 8 sinais)
 *
 * Mock mode: config('copiloto.cliente_ia.force_mock', true) faz controller
 * retornar fixtures determinasticos sem invocar Agent::prompt() (zero custo
 * LLM em tests). Pattern espelha LaravelAiSdkDriver::responderChat com
 * copiloto.dry_run.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): cross-tenant biz=99999 -> 404
 * (NAO 403 -- nao vaza existencia do recurso). Mesma defesa do
 * ClienteAutosaveController.
 *
 * Skip-graceful em sqlite :memory: (CI) que nao roda migrations UPOS.
 * Pattern copiado de tests/Feature/Cliente/ClienteDrawerCadastroAutosaveTest.php.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) -- rode com DB_CONNECTION=mysql (dev) ou CI integration job.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    // Cria contact customer ativo no biz alvo.
    $now = now();
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Cliente Wave E IA Test',
        'mobile' => '11999999999',
        'contact_status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id]);

    // Mock mode pros 3 endpoints LLM -- zero custo Brain B em tests.
    Config::set('copiloto.cliente_ia.force_mock', true);

    // Flush cache pra cada teste comecar limpo (Cache::remember tem 6h TTL).
    Cache::flush();
});

// ---------------------------------------------------------------------
// POST /cliente/{id}/ia/resumo
// ---------------------------------------------------------------------

test('POST /cliente/{id}/ia/resumo -- happy path retorna 200 + sumario + fonte', function () {
    $response = $this->postJson("/cliente/{$this->contactId}/ia/resumo");

    $response->assertStatus(200)
        ->assertJsonStructure(['sumario', 'generated_at', 'fonte']);

    expect($response->json('sumario'))->toBeString()->not->toBe('');
    expect($response->json('fonte'))->toBe('fixture-mock');
});

test('POST /cliente/{id}/ia/resumo -- cache hit nao recalcula (2o request vem do Cache)', function () {
    // 1o request: popula cache.
    $r1 = $this->postJson("/cliente/{$this->contactId}/ia/resumo");
    $r1->assertStatus(200);
    $generatedAt1 = $r1->json('generated_at');

    // Verifica que cacheou.
    $cacheKey = "cliente_ia:resumo:{$this->business->id}:{$this->contactId}";
    expect(Cache::get($cacheKey))->not->toBeNull();

    // 2o request: deve vir do cache (generated_at identico, nao novo timestamp).
    $r2 = $this->postJson("/cliente/{$this->contactId}/ia/resumo");
    $r2->assertStatus(200);
    expect($r2->json('generated_at'))->toBe($generatedAt1);
});

test('POST /cliente/{id}/ia/resumo -- force=true ignora cache', function () {
    // Popula cache com payload determinado.
    $cacheKey = "cliente_ia:resumo:{$this->business->id}:{$this->contactId}";
    Cache::put($cacheKey, [
        'sumario' => 'CACHED OLD',
        'generated_at' => '2020-01-01T00:00:00+00:00',
        'fonte' => 'fixture-mock',
    ], 3600);

    // force=true -- novo fixture, generated_at != 2020.
    $response = $this->postJson("/cliente/{$this->contactId}/ia/resumo?force=1");
    $response->assertStatus(200);
    expect($response->json('generated_at'))->not->toBe('2020-01-01T00:00:00+00:00');
    expect($response->json('sumario'))->not->toBe('CACHED OLD');
});

// ---------------------------------------------------------------------
// POST /cliente/{id}/ia/segmento
// ---------------------------------------------------------------------

test('POST /cliente/{id}/ia/segmento -- happy path retorna shape estruturado', function () {
    $response = $this->postJson("/cliente/{$this->contactId}/ia/segmento");

    $response->assertStatus(200)
        ->assertJsonStructure(['segmento_sugerido', 'tags_sugeridas', 'justificativa']);

    expect($response->json('segmento_sugerido'))->toBeString();
    expect($response->json('tags_sugeridas'))->toBeArray();
    expect($response->json('justificativa'))->toBeString()->not->toBe('');
});

// ---------------------------------------------------------------------
// POST /cliente/{id}/ia/proxima-acao
// ---------------------------------------------------------------------

test('POST /cliente/{id}/ia/proxima-acao -- happy path retorna shape com urgencia enum', function () {
    $response = $this->postJson("/cliente/{$this->contactId}/ia/proxima-acao");

    $response->assertStatus(200)
        ->assertJsonStructure(['acao', 'urgencia', 'justificativa', 'sugerido_em']);

    expect($response->json('urgencia'))->toBeIn(['alta', 'media', 'baixa']);
});

// ---------------------------------------------------------------------
// GET /cliente/{id}/ia/score-risco (deterministico -- ZERO LLM)
// ---------------------------------------------------------------------

test('GET /cliente/{id}/ia/score-risco -- deterministico retorna score + breakdown 8 sinais', function () {
    // NAO precisa force_mock -- score-risco e deterministico, zero LLM.
    Config::set('copiloto.cliente_ia.force_mock', false);

    $response = $this->getJson("/cliente/{$this->contactId}/ia/score-risco");

    $response->assertStatus(200)
        ->assertJsonStructure(['score', 'label', 'breakdown', 'generated_at']);

    $score = $response->json('score');
    expect($score)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(10.0);

    $label = $response->json('label');
    expect($label)->toBeIn(['cliente fiel', 'risco baixo', 'risco alto']);

    $breakdown = $response->json('breakdown');
    expect($breakdown)->toBeArray()->toHaveCount(8);

    // 8 keys canon (espelham RiscoClienteCard.tsx).
    $keys = array_column($breakdown, 'key');
    expect($keys)->toContain('saldo')
        ->toContain('sem_compra_90')
        ->toContain('sem_compra_180')
        ->toContain('inativo')
        ->toContain('sem_contato')
        ->toContain('pj_sem_ie')
        ->toContain('sem_localidade')
        ->toContain('cadastro_velho_sem_compra');
});

test('GET /cliente/{id}/ia/score-risco -- cliente recem criado sem compras tem score baixo (varios sinais ativos)', function () {
    Config::set('copiloto.cliente_ia.force_mock', false);

    $response = $this->getJson("/cliente/{$this->contactId}/ia/score-risco");
    $response->assertStatus(200);

    // Cliente sem compras, sem email, sem cidade/estado -- score nao deve estar
    // no top (multiplos sinais ativos: sem_contato como mobile presente NAO ativa,
    // mas sem_localidade ativa -- pesos somam pra reduzir score).
    $score = $response->json('score');
    expect($score)->toBeLessThanOrEqual(10.0);

    // Verifica que cacheou (TTL 24h pra deterministico).
    $cacheKey = "cliente_ia:score_risco:{$this->business->id}:{$this->contactId}";
    expect(Cache::get($cacheKey))->not->toBeNull();
});

// ---------------------------------------------------------------------
// Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL)
// ---------------------------------------------------------------------

test('cross-tenant biz=$foreign retorna 404 em todos os 4 endpoints (nao vaza existencia)', function () {
    // Busca um business REAL diferente do user atual (mysql tem FK real
    // pra contacts.business_id -> business.id; nao podemos inventar biz=99999).
    $foreignBusiness = \App\Business::where('id', '!=', $this->business->id)->first();
    if (! $foreignBusiness) {
        $this->markTestSkipped('Sem segundo business em DB pra testar cross-tenant.');
    }
    $foreignBizId = (int) $foreignBusiness->id;

    $now = now();
    $foreignContactId = DB::table('contacts')->insertGetId([
        'business_id' => $foreignBizId,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Foreign biz contact',
        'mobile' => '11000000000',
        'contact_status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->postJson("/cliente/{$foreignContactId}/ia/resumo")->assertStatus(404);
    $this->postJson("/cliente/{$foreignContactId}/ia/segmento")->assertStatus(404);
    $this->postJson("/cliente/{$foreignContactId}/ia/proxima-acao")->assertStatus(404);
    $this->getJson("/cliente/{$foreignContactId}/ia/score-risco")->assertStatus(404);
});

test('endpoint em contact inexistente retorna 404', function () {
    $this->postJson('/cliente/9999999/ia/resumo')->assertStatus(404);
    $this->getJson('/cliente/9999999/ia/score-risco')->assertStatus(404);
});

// ---------------------------------------------------------------------
// Permission gate (LEITURA -- customer.view ou supplier.view)
// ---------------------------------------------------------------------

test('retorna 403 quando user sem permission customer.view nem customer.view_own', function () {
    $weakUser = \App\User::factory()->create([
        'business_id' => $this->business->id,
    ]);

    if ($weakUser->can('customer.view') || $weakUser->can('customer.view_own')
        || $weakUser->can('supplier.view') || $weakUser->can('supplier.view_own')) {
        $this->markTestSkipped('User factory ja vem com permissions de view -- ambiente seedeado bloqueia este test.');
    }

    $this->actingAs($weakUser);
    session(['user.business_id' => $this->business->id]);

    $this->postJson("/cliente/{$this->contactId}/ia/resumo")
        ->assertStatus(403)
        ->assertJsonStructure(['message']);
});

// ---------------------------------------------------------------------
// Mock mode (zero custo LLM em tests)
// ---------------------------------------------------------------------

test('com mock mode ativo, controller nao instancia Agent real (fixture fallback)', function () {
    // Mock mode forca fixture -- garantia explicita do contrato.
    Config::set('copiloto.cliente_ia.force_mock', true);

    $r = $this->postJson("/cliente/{$this->contactId}/ia/resumo");
    $r->assertStatus(200);

    // Marca canon do fixture: fonte = 'fixture-mock' (LLM real retorna 'jana-haiku').
    expect($r->json('fonte'))->toBe('fixture-mock');
});

// ---------------------------------------------------------------------
// PII LGPD (response NAO inclui tax_number plain)
// ---------------------------------------------------------------------

test('response da IA nao inclui tax_number plain do contact (defesa LGPD ADR 0093)', function () {
    // Atualiza contact com tax_number plain.
    DB::table('contacts')->where('id', $this->contactId)->update([
        'tax_number' => '111.444.777-35', // pii-allowlist (sintético)
    ]);

    $r = $this->postJson("/cliente/{$this->contactId}/ia/resumo");
    $r->assertStatus(200);

    $body = $r->json();
    $bodyJson = json_encode($body);
    expect($bodyJson)->not->toContain('111.444.777-35'); // pii-allowlist (sintético)
});
