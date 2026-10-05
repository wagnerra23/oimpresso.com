<?php

declare(strict_types=1);

use App\Business;
use App\System;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Superadmin\Http\Controllers\SubscriptionController;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-003

/**
 * Contrato da tela `/subscription` (Minha assinatura) — thread Superadmin/07 (Blade → Inertia).
 * UCs: Modules/Superadmin/Resources/js/Pages/superadmin/MinhaAssinatura/Index.casos.md
 *
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /subscription requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('packages')) {
        $this->markTestSkipped('Schema do Superadmin ausente — rode migrations primeiro.');
    }
    // Ninguém destes testes é superadmin por username (Gate::before).
    config(['constants.administrator_usernames' => 'ninguem_ma_test']);

    // O payload formata preço com a moeda do sistema (`System::getCurrency()`), como a Blade.
    // Produção tem `app_currency_id`; a lane MySQL não semeia. Sem a chave a tela dava 500 e
    // UC-SAMA-03/05 caíam por isso, não pelo contrato. Semeia só quando falta, e desfaz no fim.
    $this->maMoedaSemeada = false;
    if (! DB::table('system')->where('key', 'app_currency_id')->exists()) {
        $moeda = DB::table('currencies')->orderBy('id')->value('id');
        if ($moeda === null) {
            $this->markTestSkipped('Sem moeda em currencies — o payload não tem como formatar preço.');
        }
        DB::table('system')->insert(['key' => 'app_currency_id', 'value' => (string) $moeda]);
        $this->maMoedaSemeada = true;
    }
});

afterEach(function () {
    if ($this->maMoedaSemeada ?? false) {
        DB::table('system')->where('key', 'app_currency_id')->delete();
    }
});

/** Tenant fictício. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_MA = 98;

const ROTA_MA = '/subscription';

function maUsuario(string $username, bool $comPermissao): User
{
    Business::firstOrCreate(['id' => BIZ_MA], ['name' => 'Tenant fictício minha assinatura', 'currency_id' => 1]);

    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => BIZ_MA, 'first_name' => 'Ma', 'last_name' => 'Teste',
    ]);
    $user->syncRoles([]);
    $user->syncPermissions($comPermissao
        ? [Permission::firstOrCreate(['name' => 'superadmin.access_package_subscriptions', 'guard_name' => 'web'])]
        : []);

    return $user;
}

/** Um pacote ativo marcado por nome; `privado` decide o `is_private`. */
function maPacote(string $nome, bool $privado = false): int
{
    $id = DB::table('packages')->where('name', $nome)->value('id');

    return (int) ($id ?? DB::table('packages')->insertGetId([
        'name' => $nome, 'description' => 'Só para o contrato de Minha assinatura.',
        'location_count' => 1, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 0, 'price' => 10,
        'created_by' => 1, 'sort_order' => 99, 'is_active' => 1, 'is_private' => $privado ? 1 : 0,
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

/** Assinatura marcada pelo `payment_transaction_id` — idempotente entre runs. */
function maAssinatura(int $biz, string $marca): void
{
    if (DB::table('subscriptions')->where('payment_transaction_id', $marca)->exists()) {
        return;
    }
    DB::table('subscriptions')->insert([
        'business_id' => $biz, 'package_id' => maPacote('Pacote fictício thread 07'),
        'start_date' => now()->subDays(5)->toDateString(), 'end_date' => now()->addDays(25)->toDateString(),
        'package_price' => 10, 'package_details' => json_encode(['name' => 'Pacote fictício thread 07']),
        'created_id' => 1, 'paid_via' => 'fixture', 'payment_transaction_id' => $marca,
        'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Pede a prop deferred — sem isso o payload nem é calculado. */
function maPayload(User $user): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get(ROTA_MA, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'assinatura',
        'X-Inertia-Partial-Component' => 'superadmin/MinhaAssinatura/Index',
    ]);
    $resposta->assertOk();

    return (array) $resposta->json('props.assinatura');
}

it('UC-SAMA-01 · a tela responde Inertia com o componente novo', function () {
    $this->actingAs(maUsuario('ma_permitido_test', true))
        ->get(ROTA_MA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('superadmin/MinhaAssinatura/Index', false));
});

it('UC-SAMA-02 · sem a permissão é barrado ENQUANTO quem tem passa', function () {
    $this->actingAs(maUsuario('ma_barrado_test', false))->get(ROTA_MA)->assertForbidden();
    $this->actingAs(maUsuario('ma_permitido_test', true))->get(ROTA_MA)->assertOk();
});

it('UC-SAMA-03 · o histórico só traz assinatura do próprio negócio', function () {
    $outro = (int) DB::table('business')->whereNotIn('id', [BIZ_MA, 4])->orderBy('id')->value('id');
    if ($outro === 0) {
        $this->markTestSkipped('Sem segundo negócio semeado — o caso cross-tenant não tem o que provar.');
    }
    maAssinatura(BIZ_MA, 'thread07-proprio');
    maAssinatura($outro, 'thread07-alheio');

    $transacoes = collect(maPayload(maUsuario('ma_permitido_test', true))['historico'])->pluck('transacao');

    expect($transacoes)->toContain('thread07-proprio')
        ->and($transacoes)->not->toContain('thread07-alheio');
});

it('UC-SAMA-04 · o valor sai no mesmo texto que a Blade mostrava', function () {
    if (! DB::table('system')->where('key', 'app_currency_id')->exists()) {
        $this->markTestSkipped('Sem app_currency_id semeado — sem moeda do sistema para formatar.');
    }
    $m = System::getCurrency();
    $ctrl = app(SubscriptionController::class);

    session(['business' => ['currency_precision' => 2, 'currency_symbol_placement' => 'before']]);
    // 1.005 é o discriminante: accounting.js dá "1.00" (1.005*100 = 100.4999…); o round() do PHP daria "1.01".
    expect($ctrl->moedaComoBlade(1.005))->toBe($m->symbol.' 1'.$m->decimal_separator.'00');
    expect($ctrl->moedaComoBlade('1234567.8950'))
        ->toBe($m->symbol.' 1'.$m->thousand_separator.'234'.$m->thousand_separator.'567'.$m->decimal_separator.'90');

    session(['business' => ['currency_precision' => 0, 'currency_symbol_placement' => 'after']]);
    expect($ctrl->moedaComoBlade(99.5))->toBe('100 '.$m->symbol);
});

it('UC-SAMA-05 · pacote privado só aparece para o superadmin', function () {
    maPacote('Pacote privado thread 07', true);

    $nomes = collect(maPayload(maUsuario('ma_permitido_test', true))['pacotes'])->pluck('nome');

    expect($nomes)->not->toContain('Pacote privado thread 07');
});
