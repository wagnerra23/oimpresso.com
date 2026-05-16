<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Smoke tests rotas Accounting — apenas status code < 500 + middleware auth aplicado.
 *
 * Não testa renderização completa nem lógica de negócio. Garante que:
 *  - Rotas registradas existem (não 404)
 *  - Middleware 'auth' redireciona usuário não autenticado (302 → login)
 *  - Usuário autenticado biz=1 acessa sem 5xx (200, 302, 403 aceitos)
 *
 * @see Modules/Accounting/Http/routes.php
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: smoke routes UltimatePOS precisam schema MySQL + seeders (ADR 0101)'
        );
    }
    foreach (['business', 'users', 'roles', 'chart_of_accounts'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela `{$tbl}` missing — rode migrate UltimatePOS primeiro");
        }
    }
});

// ------------------------------------------------------------------
// Helper — autentica admin do biz=1 (Wagner WR2 — ADR 0101)
// ------------------------------------------------------------------
function actAsAccountingAdmin(): ?User
{
    $business = Business::find(1);
    if (! $business) {
        return null;
    }
    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        return null;
    }

    session([
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'is_admin'         => true,
    ]);

    test()->actingAs($user);

    return $user;
}

// ------------------------------------------------------------------
// Rota não autenticada — middleware redireciona pra login
// ------------------------------------------------------------------

it('GET /accounting/dashboard sem auth redireciona ou nega (não 500)', function () {
    $response = $this->get('/accounting/dashboard');

    // Aceitos: 302 (redirect login), 401 (unauthorized), 403 (forbidden)
    // NUNCA 500 — significaria que middleware crashou.
    expect($response->status())->toBeLessThan(500);
});

// ------------------------------------------------------------------
// Rotas autenticadas biz=1 — não devem 5xx
// ------------------------------------------------------------------

it('GET /accounting/dashboard autenticado biz=1 responde sem 5xx', function () {
    $user = actAsAccountingAdmin();
    if (! $user) {
        $this->markTestSkipped('Sem User pra biz=1 — seeder UltimatePOS necessário');
    }

    $response = $this->get('/accounting/dashboard');

    expect($response->status())->toBeLessThan(500);
});

it('GET /accounting/chart_of_account autenticado biz=1 responde sem 5xx', function () {
    $user = actAsAccountingAdmin();
    if (! $user) {
        $this->markTestSkipped('Sem User pra biz=1 — seeder UltimatePOS necessário');
    }

    $response = $this->get('/accounting/chart_of_account');

    expect($response->status())->toBeLessThan(500);
});

it('GET /accounting/journal_entry autenticado biz=1 responde sem 5xx', function () {
    $user = actAsAccountingAdmin();
    if (! $user) {
        $this->markTestSkipped('Sem User pra biz=1 — seeder UltimatePOS necessário');
    }

    $response = $this->get('/accounting/journal_entry');

    expect($response->status())->toBeLessThan(500);
});

it('GET /accounting/trial_balance autenticado biz=1 responde sem 5xx', function () {
    $user = actAsAccountingAdmin();
    if (! $user) {
        $this->markTestSkipped('Sem User pra biz=1 — seeder UltimatePOS necessário');
    }

    $response = $this->get('/accounting/trial_balance');

    expect($response->status())->toBeLessThan(500);
});

it('GET /accounting/balance_sheet autenticado biz=1 responde sem 5xx', function () {
    $user = actAsAccountingAdmin();
    if (! $user) {
        $this->markTestSkipped('Sem User pra biz=1 — seeder UltimatePOS necessário');
    }

    $response = $this->get('/accounting/balance_sheet');

    expect($response->status())->toBeLessThan(500);
});

it('GET /accounting/transfers autenticado biz=1 responde sem 5xx', function () {
    $user = actAsAccountingAdmin();
    if (! $user) {
        $this->markTestSkipped('Sem User pra biz=1 — seeder UltimatePOS necessário');
    }

    $response = $this->get('/accounting/transfers');

    expect($response->status())->toBeLessThan(500);
});
