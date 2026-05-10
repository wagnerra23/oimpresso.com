<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Services\ApontamentoTracker;

uses(Tests\TestCase::class);

/**
 * Testes do ApontamentoTracker — spool plotter / apontamento de produção.
 *
 * US-COMVIS-004: iniciar/finalizar/cancelar + drift detection.
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 * Multi-tenant Tier 0 (ADR 0093): global scope obrigatório.
 *
 * @see Modules\ComunicacaoVisual\Services\ApontamentoTracker
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-004
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// ------------------------------------------------------------------
// Helper: setup de sessão biz=1 + OS fake via withoutGlobalScopes
// ------------------------------------------------------------------

function bootstrapTrackerTest(): array
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
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'is_admin'         => true,
    ]);

    // Criar OS de teste via withoutGlobalScopes (SUPERADMIN: setup de teste)
    $os = Os::withoutGlobalScopes()->create([
        'business_id'  => $business->id,
        'numero'       => 'OS-TEST-' . uniqid(),
        'status_etapa' => 'producao',
        'valor_total'  => 0,
    ]);

    return [$business, $user, $os];
}

function limparApontamentosTest(Os $os): void
{
    Apontamento::withoutGlobalScopes()->where('os_id', $os->id)->delete();
    Os::withoutGlobalScopes()->where('id', $os->id)->forceDelete();
}

// ------------------------------------------------------------------
// Teste 1: iniciar() cria apontamento com finalizado_em null
// ------------------------------------------------------------------

it('iniciar() cria apontamento com finalizado_em null e em_andamento=true', function () {
    [$business, $user, $os] = bootstrapTrackerTest();
    $tracker = new ApontamentoTracker();

    $apontamento = $tracker->iniciar(
        osId:       $os->id,
        operadorId: $user->id,
    );

    expect($apontamento->id)->toBeGreaterThan(0);
    expect($apontamento->os_id)->toBe($os->id);
    expect($apontamento->operador_id)->toBe($user->id);
    expect($apontamento->finalizado_em)->toBeNull();
    expect($apontamento->duracao_segundos)->toBeNull();
    expect($apontamento->m2_produzido)->toBeNull();
    expect($apontamento->esta_em_andamento)->toBeTrue();

    limparApontamentosTest($os);
});

// ------------------------------------------------------------------
// Teste 2: finalizar() calcula duracao_segundos correto
// ------------------------------------------------------------------

it('finalizar() calcula duracao_segundos correto', function () {
    [$business, $user, $os] = bootstrapTrackerTest();
    $tracker = new ApontamentoTracker();

    // Criar apontamento com iniciado_em fixo no passado (5 minutos e 30 segundos atrás)
    $iniciado = now()->subMinutes(5)->subSeconds(30); // ~330 segundos atrás
    $apontamento = Apontamento::create([
        'business_id'  => $business->id,
        'os_id'        => $os->id,
        'operador_id'  => $user->id,
        'iniciado_em'  => $iniciado,
        'm2_orcado'    => null,
    ]);

    $result = $tracker->finalizar($apontamento->id, 5.0);

    expect($result->finalizado_em)->not->toBeNull();
    // Tolerância de 3 segundos pra execução do teste
    expect($result->duracao_segundos)->toBeGreaterThanOrEqual(328);
    expect($result->duracao_segundos)->toBeLessThanOrEqual(335);
    expect($result->m2_produzido)->toEqual('5.000'); // cast decimal:3

    limparApontamentosTest($os);
});

// ------------------------------------------------------------------
// Teste 3: finalizar() calcula drift_percent quando m2_orcado > 0
// Orçado=10, Produzido=8 → drift = (8-10)/10 × 100 = -20%
// ------------------------------------------------------------------

it('finalizar() calcula drift_percent correto (orcado=10 prod=8 → drift=-20%)', function () {
    [$business, $user, $os] = bootstrapTrackerTest();
    $tracker = new ApontamentoTracker();

    $apontamento = Apontamento::create([
        'business_id'  => $business->id,
        'os_id'        => $os->id,
        'operador_id'  => $user->id,
        'iniciado_em'  => now()->subMinutes(10),
        'm2_orcado'    => 10.000,
    ]);

    $result = $tracker->finalizar($apontamento->id, 8.0);

    // drift = ((8 - 10) / 10) × 100 = -20.00
    expect((float) $result->drift_percent)->toBe(-20.00);
    expect((float) $result->m2_produzido)->toBe(8.0);

    limparApontamentosTest($os);
});

// ------------------------------------------------------------------
// Teste 4: finalizar() drift null quando m2_orcado = 0
// Impossível dividir por zero — drift fica null
// ------------------------------------------------------------------

it('finalizar() drift null quando m2_orcado=0 (divisão por zero evitada)', function () {
    [$business, $user, $os] = bootstrapTrackerTest();
    $tracker = new ApontamentoTracker();

    $apontamento = Apontamento::create([
        'business_id'  => $business->id,
        'os_id'        => $os->id,
        'operador_id'  => $user->id,
        'iniciado_em'  => now()->subMinutes(3),
        'm2_orcado'    => 0.000,
    ]);

    $result = $tracker->finalizar($apontamento->id, 5.0);

    expect($result->drift_percent)->toBeNull();

    limparApontamentosTest($os);
});

// ------------------------------------------------------------------
// Teste 5: cancelar() seta finalizado_em + m2_produzido=0 + prefixo [CANCELADO]
// ------------------------------------------------------------------

it('cancelar() define finalizado_em + m2_produzido=0 + observacoes "[CANCELADO]"', function () {
    [$business, $user, $os] = bootstrapTrackerTest();
    $tracker = new ApontamentoTracker();

    $apontamento = Apontamento::create([
        'business_id'  => $business->id,
        'os_id'        => $os->id,
        'operador_id'  => $user->id,
        'iniciado_em'  => now()->subMinutes(2),
    ]);

    $result = $tracker->cancelar($apontamento->id, 'Plotter travou no meio da impressão');

    expect($result->finalizado_em)->not->toBeNull();
    expect((float) $result->m2_produzido)->toBe(0.0);
    expect($result->observacoes)->toStartWith('[CANCELADO]');
    expect($result->observacoes)->toContain('Plotter travou no meio da impressão');
    expect($result->drift_percent)->toBeNull();

    limparApontamentosTest($os);
});

// ------------------------------------------------------------------
// Teste 6: iniciar() throw se operador já tem apontamento em andamento
// ------------------------------------------------------------------

it('iniciar() lança RuntimeException se operador já tem apontamento ativo', function () {
    [$business, $user, $os] = bootstrapTrackerTest();
    $tracker = new ApontamentoTracker();

    // Primeiro apontamento ativo
    Apontamento::create([
        'business_id'   => $business->id,
        'os_id'         => $os->id,
        'operador_id'   => $user->id,
        'iniciado_em'   => now()->subMinutes(5),
        'finalizado_em' => null,
    ]);

    // Segundo deve lançar
    expect(fn () => $tracker->iniciar($os->id, $user->id))
        ->toThrow(RuntimeException::class, 'já possui apontamento');

    limparApontamentosTest($os);
});

// ------------------------------------------------------------------
// Teste 7: finalizar() throw se apontamento já finalizado
// ------------------------------------------------------------------

it('finalizar() lança RuntimeException se apontamento já finalizado', function () {
    [$business, $user, $os] = bootstrapTrackerTest();
    $tracker = new ApontamentoTracker();

    $apontamento = Apontamento::create([
        'business_id'      => $business->id,
        'os_id'            => $os->id,
        'operador_id'      => $user->id,
        'iniciado_em'      => now()->subMinutes(10),
        'finalizado_em'    => now()->subMinutes(5),
        'duracao_segundos' => 300,
        'm2_produzido'     => 5.0,
    ]);

    expect(fn () => $tracker->finalizar($apontamento->id, 5.0))
        ->toThrow(RuntimeException::class, 'já foi finalizado');

    limparApontamentosTest($os);
});

// ------------------------------------------------------------------
// Teste 8: emAndamento() retorna apontamento ativo correto
// ------------------------------------------------------------------

it('emAndamento() retorna apontamento ativo do operador', function () {
    [$business, $user, $os] = bootstrapTrackerTest();
    $tracker = new ApontamentoTracker();

    // Sem apontamento ativo → null
    $ativo = $tracker->emAndamento($user->id);
    expect($ativo)->toBeNull();

    // Criar apontamento ativo
    $criado = Apontamento::create([
        'business_id'   => $business->id,
        'os_id'         => $os->id,
        'operador_id'   => $user->id,
        'iniciado_em'   => now()->subMinutes(3),
        'finalizado_em' => null,
    ]);

    $ativo = $tracker->emAndamento($user->id);
    expect($ativo)->not->toBeNull();
    expect($ativo->id)->toBe($criado->id);

    limparApontamentosTest($os);
});
