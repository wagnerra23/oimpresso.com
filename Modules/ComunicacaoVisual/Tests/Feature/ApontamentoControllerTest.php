<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\Os;

uses(Tests\TestCase::class);

/**
 * Testes de integração do ApontamentoController — endpoints API JSON.
 *
 * US-COMVIS-004: spool plotter / apontamento de produção.
 *
 * Endpoints cobertos:
 *   POST /comunicacao-visual/api/apontamentos/iniciar          → cria apontamento + 201
 *   POST /comunicacao-visual/api/apontamentos/{id}/finalizar   → finaliza + drift
 *   GET  /comunicacao-visual/api/apontamentos/em-andamento     → retorna ativo
 *   Multi-tenant: biz=1 não visível para session biz=99        → global scope
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 * Multi-tenant Tier 0 (ADR 0093): apontamento biz=1 não aparece para session biz=99.
 *
 * @see Modules\ComunicacaoVisual\Http\Controllers\ApontamentoController
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// ------------------------------------------------------------------
// Helper: bootstrap user autenticado no biz=1 + OS de teste
// ------------------------------------------------------------------

function bootstrapControllerApt(): array
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

    // OS de teste via withoutGlobalScopes (SUPERADMIN: setup de teste)
    $os = Os::withoutGlobalScopes()->create([
        'business_id'  => $business->id,
        'numero'       => 'OS-CTRL-TEST-' . uniqid(),
        'status_etapa' => 'producao',
        'valor_total'  => 0,
    ]);

    return [$business, $user, $os];
}

function limparCtrlAptTest(Os $os): void
{
    Apontamento::withoutGlobalScopes()->where('os_id', $os->id)->delete();
    Os::withoutGlobalScopes()->where('id', $os->id)->forceDelete();
}

// ------------------------------------------------------------------
// Teste 1: POST /apontamentos/iniciar persiste + retorna 201
// ------------------------------------------------------------------

it('POST /apontamentos/iniciar persiste apontamento no DB e retorna 201', function () {
    [$business, $user, $os] = bootstrapControllerApt();

    $response = $this->actingAs($user)
        ->postJson('/comunicacao-visual/api/apontamentos/iniciar', [
            'os_id'   => $os->id,
            'maquina' => 'plotter-roland-1',
        ]);

    if ($response->status() === 403) {
        test()->markTestSkipped('Gate de módulo bloqueia neste env.');
    }

    $response->assertStatus(201);
    $json = $response->json();

    expect($json)->toHaveKey('id');
    expect($json['os_id'])->toBe($os->id);
    expect($json['finalizado_em'])->toBeNull();
    expect($json['maquina'])->toBe('plotter-roland-1');

    // Persistiu no DB
    $db = Apontamento::withoutGlobalScopes()->find($json['id']);
    expect($db)->not->toBeNull();
    expect($db->finalizado_em)->toBeNull();

    limparCtrlAptTest($os);
});

// ------------------------------------------------------------------
// Teste 2: POST /apontamentos/{id}/finalizar atualiza row + calcula drift
// ------------------------------------------------------------------

it('POST /apontamentos/{id}/finalizar atualiza row e calcula drift corretamente', function () {
    [$business, $user, $os] = bootstrapControllerApt();

    // Criar apontamento inicial diretamente no banco
    $apontamento = Apontamento::withoutGlobalScopes()->create([
        'business_id'  => $business->id,
        'os_id'        => $os->id,
        'operador_id'  => $user->id,
        'iniciado_em'  => now()->subMinutes(5),
        'm2_orcado'    => 10.000, // orçado 10m²
    ]);

    $response = $this->actingAs($user)
        ->postJson("/comunicacao-visual/api/apontamentos/{$apontamento->id}/finalizar", [
            'm2_produzido' => 8.0,  // produzido 8m² → drift = -20%
            'observacoes'  => 'Produção ok com pequena sobra',
        ]);

    if ($response->status() === 403) {
        test()->markTestSkipped('Gate de módulo bloqueia neste env.');
    }

    $response->assertStatus(200);
    $json = $response->json();

    expect($json['finalizado_em'])->not->toBeNull();
    expect((float) $json['m2_produzido'])->toBe(8.0);
    expect((float) $json['drift_percent'])->toBe(-20.00); // ((8-10)/10)*100 = -20

    // Persistiu no DB
    $db = Apontamento::withoutGlobalScopes()->find($apontamento->id);
    expect($db->finalizado_em)->not->toBeNull();
    expect((float) $db->drift_percent)->toBe(-20.00);

    limparCtrlAptTest($os);
});

// ------------------------------------------------------------------
// Teste 3: GET /apontamentos/em-andamento retorna apontamento ativo
// ------------------------------------------------------------------

it('GET /apontamentos/em-andamento retorna apontamento ativo do usuário autenticado', function () {
    [$business, $user, $os] = bootstrapControllerApt();

    // Sem apontamento ativo → null
    $response = $this->actingAs($user)
        ->getJson('/comunicacao-visual/api/apontamentos/em-andamento');

    if ($response->status() === 403) {
        test()->markTestSkipped('Gate de módulo bloqueia neste env.');
    }

    $response->assertStatus(200);
    expect($response->json())->toBeNull();

    // Criar apontamento ativo
    $ativo = Apontamento::withoutGlobalScopes()->create([
        'business_id'   => $business->id,
        'os_id'         => $os->id,
        'operador_id'   => $user->id,
        'iniciado_em'   => now()->subMinutes(3),
        'finalizado_em' => null,
    ]);

    $response2 = $this->actingAs($user)
        ->getJson('/comunicacao-visual/api/apontamentos/em-andamento');

    $response2->assertStatus(200);
    $json2 = $response2->json();
    expect($json2['id'])->toBe($ativo->id);
    expect($json2['finalizado_em'])->toBeNull();

    limparCtrlAptTest($os);
});

// ------------------------------------------------------------------
// Teste 4: Multi-tenant Tier 0 — apontamento biz=1 não aparece para session biz=99
// ADR 0093 — IRREVOGÁVEL
// ------------------------------------------------------------------

it('Multi-tenant Tier 0: GET /apontamentos/em-andamento retorna null para session biz diferente', function () {
    // Criar user e OS no biz=1 (SUPERADMIN: setup de teste)
    try {
        $user = User::where('business_id', 1)->first();
    } catch (\Throwable) {
        test()->markTestSkipped('User biz=1 indisponível.');
    }

    if (! $user) {
        test()->markTestSkipped('User biz=1 indisponível.');
    }

    $os = Os::withoutGlobalScopes()->create([
        'business_id'  => 1,
        'numero'       => 'OS-MT-TEST-' . uniqid(),
        'status_etapa' => 'producao',
        'valor_total'  => 0,
    ]);

    // Apontamento ativo no biz=1
    Apontamento::withoutGlobalScopes()->create([
        'business_id'   => 1,
        'os_id'         => $os->id,
        'operador_id'   => $user->id,
        'iniciado_em'   => now()->subMinutes(5),
        'finalizado_em' => null,
    ]);

    // Autenticar com session biz=99 → global scope não deve ver o apontamento do biz=1
    session([
        'user.business_id' => 99,
        'business.id'      => 99,
        'user.id'          => $user->id,
        'is_admin'         => true,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/comunicacao-visual/api/apontamentos/em-andamento');

    if ($response->status() === 403) {
        test()->markTestSkipped('Gate de módulo bloqueia neste env.');
    }

    // Global scope biz=99 não deve encontrar o apontamento do biz=1
    $response->assertStatus(200);
    expect($response->json())->toBeNull();

    // Limpar
    Apontamento::withoutGlobalScopes()->where('os_id', $os->id)->delete();
    Os::withoutGlobalScopes()->where('id', $os->id)->forceDelete();
});
