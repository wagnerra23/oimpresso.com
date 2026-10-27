<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Superadmin\Http\Controllers\SubscriptionController;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-003

/**
 * Contrato da tela `/subscription/{id}/pay` (Pagar assinatura) — Blade → Inertia, decisão [W]
 * 2026-10-27: só Pix automático e pagamento offline.
 * UCs: Modules/Superadmin/Resources/js/Pages/superadmin/Pagar/Index.casos.md
 *
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /subscription/{id}/pay requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('packages')) {
        $this->markTestSkipped('Schema do Superadmin ausente — rode migrations primeiro.');
    }
    config(['constants.administrator_usernames' => 'ninguem_pg_test']);

    // Chaves de `system` que a tela lê. Semeia só o que falta e desfaz no fim (mesmo padrão
    // de SuperadminMinhaAssinaturaContratoTest).
    $this->pgSemeadas = [];
    $semear = function (string $chave, string $valor) {
        if (! DB::table('system')->where('key', $chave)->exists()) {
            DB::table('system')->insert(['key' => $chave, 'value' => $valor]);
            $this->pgSemeadas[] = $chave;
        }
    };
    $moeda = DB::table('currencies')->orderBy('id')->value('id');
    if ($moeda === null) {
        $this->markTestSkipped('Sem moeda em currencies — o payload não tem como formatar preço.');
    }
    $semear('app_currency_id', (string) $moeda);
    $semear('offline_payment_details', "Banco fictício\nAgência 0000");
    $this->pgOfflineAntes = DB::table('system')->where('key', 'enable_offline_payment')->value('value');
    DB::table('system')->updateOrInsert(['key' => 'enable_offline_payment'], ['value' => '1']);
});

afterEach(function () {
    DB::table('subscriptions')->where('business_id', BIZ_PG)->where('paid_via', 'offline')
        ->where('package_id', pgPacote())->delete();
    if (($this->pgOfflineAntes ?? null) === null) {
        DB::table('system')->where('key', 'enable_offline_payment')->delete();
    } else {
        DB::table('system')->where('key', 'enable_offline_payment')->update(['value' => $this->pgOfflineAntes]);
    }
    foreach ($this->pgSemeadas ?? [] as $chave) {
        DB::table('system')->where('key', $chave)->delete();
    }
});

/** Tenant fictício. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_PG = 98;

function pgUsuario(string $username, bool $comPermissao): User
{
    Business::firstOrCreate(['id' => BIZ_PG], ['name' => 'Tenant fictício pagar', 'currency_id' => 1]);

    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => BIZ_PG, 'first_name' => 'Pg', 'last_name' => 'Teste',
    ]);
    $user->syncRoles([]);
    $user->syncPermissions($comPermissao
        ? [Permission::firstOrCreate(['name' => 'superadmin.access_package_subscriptions', 'guard_name' => 'web'])]
        : []);

    return $user;
}

/** Pacote pago, público e repetível — idempotente por nome. Preço com centavos de propósito. */
function pgPacote(): int
{
    $id = DB::table('packages')->where('name', 'Pacote fictício pagar')->value('id');

    return (int) ($id ?? DB::table('packages')->insertGetId([
        'name' => 'Pacote fictício pagar', 'description' => 'Só para o contrato de Pagar.',
        'location_count' => 2, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 15, 'price' => 49.9,
        'created_by' => 1, 'sort_order' => 99, 'is_active' => 1, 'is_private' => 0, 'is_one_time' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

/** Pede a prop deferred — sem isso o payload nem é calculado. */
function pgPayload(User $user, string $rota): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get($rota, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'pagamento',
        'X-Inertia-Partial-Component' => 'superadmin/Pagar/Index',
    ]);
    $resposta->assertOk();

    return (array) $resposta->json('props.pagamento');
}

it('UC-SAPGR-01 · pagar e register-pay respondem Inertia com a tela nova', function () {
    $user = pgUsuario('pg_permitido_test', true);
    $id = pgPacote();

    foreach (["/subscription/{$id}/pay", "/subscription/{$id}/register-pay"] as $rota) {
        $this->actingAs($user)->get($rota)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('superadmin/Pagar/Index'));
    }
});

it('UC-SAPGR-02 · sem a permissão é barrado enquanto quem tem passa', function () {
    $id = pgPacote();

    $this->actingAs(pgUsuario('pg_barrado_test', false))->get("/subscription/{$id}/pay")->assertForbidden();
    $this->actingAs(pgUsuario('pg_permitido_test', true))->get("/subscription/{$id}/pay")->assertOk();
});

it('UC-SAPGR-03 · só Pix automático e offline aparecem, e o preço é o texto da Blade', function () {
    $user = pgUsuario('pg_permitido_test', true);
    $id = pgPacote();

    $dados = pgPayload($user, "/subscription/{$id}/pay");

    $chaves = array_column($dados['formas'], 'chave');
    expect(array_diff($chaves, SubscriptionController::GATEWAYS_DA_TELA))->toBe([]);
    expect($chaves)->toContain('offline');
    expect($dados['dados_offline'])->toBe(DB::table('system')->where('key', 'offline_payment_details')->value('value'));
    // Segundo caminho, independente do `moedaComoBlade`: o texto montado à mão com a moeda do
    // sistema e o negócio fictício (precisão 2, símbolo antes — os defaults da tabela business).
    $m = \App\System::getCurrency();
    expect($dados['pacote']['preco'])->toBe($m->symbol.' 49'.$m->decimal_separator.'90');
    expect($dados['pacote']['locais'])->toBe(2);
    expect($dados['pacote']['trial_dias'])->toBe(15);
});

it('UC-SAPGR-04 · confirmar por um gateway que saiu não cria assinatura', function () {
    $user = pgUsuario('pg_permitido_test', true);
    $id = pgPacote();
    $antes = DB::table('subscriptions')->where('business_id', BIZ_PG)->count();

    $this->actingAs($user)->post("/subscription/{$id}/confirm", ['gateway' => 'stripe'])
        ->assertRedirect('/subscription')
        ->assertSessionHas('status.success', 0);

    expect(DB::table('subscriptions')->where('business_id', BIZ_PG)->count())->toBe($antes);
});

it('UC-SAPGR-05 · confirmar offline cria a assinatura aguardando, com o preço do pacote', function () {
    $user = pgUsuario('pg_permitido_test', true);
    $id = pgPacote();

    $this->actingAs($user)->post("/subscription/{$id}/confirm", ['gateway' => 'offline'])
        ->assertRedirect('/subscription');

    $sub = DB::table('subscriptions')->where('business_id', BIZ_PG)->where('package_id', $id)
        ->where('paid_via', 'offline')->latest('id')->first();
    expect($sub)->not->toBeNull();
    expect($sub->status)->toBe('waiting');
    expect((float) $sub->package_price)->toBe(49.9);
});
