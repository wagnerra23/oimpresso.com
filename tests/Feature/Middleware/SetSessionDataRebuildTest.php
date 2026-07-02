<?php

declare(strict_types=1);

/**
 * SetSessionData — sessão meia-populada NUNCA pode operar como "business 0".
 *
 * Contrato (âncora externa, não derivado do código):
 *  - ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL: toda request opera com business_id real.
 *  - Incidente prod 2026-07-02: sessão com bloco `user` presente mas SEM `business_id`
 *    fazia `(int) session('user.business_id') = 0` — Financeiro vazio, extrato 404,
 *    shell caía no fallback "Oimpresso Matriz". O middleware só repopulava quando
 *    `!session()->has('user')`, então sessão stale passava batida pra sempre.
 *    Vetores: sessão stale pós-deploy, login social (Auth::login sem popular sessão
 *    UPOS), superadmin "Sign in as user".
 *
 * Comportamento exigido:
 *  1. Bloco `user` sem business_id → middleware reconstrói a partir de auth()
 *  2. Bloco `user` com business_id=0 → idem
 *  3. Reconstrução por sessão stale emite Log::warning (telemetria)
 *  4. Sessão saudável NÃO é tocada (sem rebuild espúrio)
 *  5. Comportamento original preservado: sessão sem `user` → popula
 *
 * Tier 0: businesses de teste próprios, NUNCA biz=4 (ROTA LIVRE — ADR 0101).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see app/Http/Middleware/SetSessionData.php
 */

use App\Business;
use App\Http\Middleware\SetSessionData;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function criarBizUser(): array
{
    $business = Business::create([
        'name' => 'Test Biz SetSessionData',
        'currency_id' => 1,
        'start_date' => '2026-01-01',
        'default_profit_percent' => 25.0,
        'owner_id' => 1,
        'fy_start_month' => 1,
        'accounting_method' => 'fifo',
        'time_zone' => 'America/Sao_Paulo',
    ]);

    $user = User::factory()->create(['business_id' => $business->id]);

    return [$business, $user];
}

function rodarSetSessionData(): void
{
    $request = Request::create('/financeiro');
    $request->setLaravelSession(app('session.store'));
    (new SetSessionData)->handle($request, fn ($r) => response('ok'));
}

it('reconstrói bloco user quando sessão tem user SEM business_id (incidente 2026-07-02)', function () {
    [$business, $user] = criarBizUser();
    $this->actingAs($user);

    // Sessão meia-populada: bloco `user` existe mas sem business_id
    session()->put('user', [
        'id' => $user->id,
        'email' => $user->email,
        // SEM business_id — estado do incidente
    ]);

    rodarSetSessionData();

    expect((int) session('user.business_id'))->toBe($business->id)
        ->and(session('business.id'))->toBe($business->id)
        ->and(session('business.name'))->toBe('Test Biz SetSessionData');
});

it('reconstrói bloco user quando business_id na sessão é 0', function () {
    [$business, $user] = criarBizUser();
    $this->actingAs($user);

    session()->put('user', [
        'id' => $user->id,
        'business_id' => 0,
    ]);

    rodarSetSessionData();

    expect((int) session('user.business_id'))->toBe($business->id);
});

it('emite Log::warning quando reconstrói sessão stale (telemetria)', function () {
    [, $user] = criarBizUser();
    $this->actingAs($user);

    session()->put('user', ['id' => $user->id]);

    Log::spy();

    rodarSetSessionData();

    Log::shouldHaveReceived('warning')->withArgs(
        fn ($message) => str_contains((string) $message, 'sem business_id')
    );
});

it('NÃO reconstrói sessão saudável (bloco user com business_id truthy fica intacto)', function () {
    [$business, $user] = criarBizUser();
    $this->actingAs($user);

    session()->put('user', [
        'id' => $user->id,
        'surname' => 'MARCADOR-NAO-TOCAR',
        'business_id' => $business->id,
    ]);

    rodarSetSessionData();

    // Se tivesse reconstruído, surname viria do User (factory = 'Mr'), não do marcador
    expect(session('user.surname'))->toBe('MARCADOR-NAO-TOCAR')
        ->and((int) session('user.business_id'))->toBe($business->id);
});

it('comportamento original preservado: sessão sem bloco user → popula tudo', function () {
    [$business, $user] = criarBizUser();
    $this->actingAs($user);

    expect(session()->has('user'))->toBeFalse();

    rodarSetSessionData();

    expect((int) session('user.business_id'))->toBe($business->id)
        ->and(session('business.id'))->toBe($business->id)
        ->and(session()->has('currency'))->toBeTrue();
});
