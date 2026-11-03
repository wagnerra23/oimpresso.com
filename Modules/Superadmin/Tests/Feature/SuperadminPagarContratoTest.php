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
 * Contrato da tela `/subscription/{id}/pay` (Pagar assinatura) — Blade → Inertia atrás da flag
 * `useV2SuperadminPagar`. UCs: Modules/Superadmin/Resources/js/Pages/superadmin/Pagar/Index.casos.md
 *
 * A flag é forçada por stub do FeatureFlagService (mesmo desenho do CrmCampanhasContratoTest).
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /subscription/{id}/pay requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('packages')) {
        $this->markTestSkipped('Schema do Superadmin ausente — rode migrations primeiro.');
    }
    // Ninguém destes testes é superadmin por username (Gate::before).
    config(['constants.administrator_usernames' => 'ninguem_pg_test']);

    // Semeia o que falta para a tela renderizar e desfaz no fim: moeda do sistema (preço) e o
    // pagamento offline habilitado (forma oferecida).
    $this->pgSemeado = [];
    if (! DB::table('system')->where('key', 'app_currency_id')->exists()) {
        $moeda = DB::table('currencies')->orderBy('id')->value('id');
        if ($moeda === null) {
            $this->markTestSkipped('Sem moeda em currencies — o payload não tem como formatar preço.');
        }
        DB::table('system')->insert(['key' => 'app_currency_id', 'value' => (string) $moeda]);
        $this->pgSemeado[] = 'app_currency_id';
    }
    $this->pgOfflineAntes = DB::table('system')->where('key', 'enable_offline_payment')->value('value');
    DB::table('system')->updateOrInsert(['key' => 'enable_offline_payment'], ['value' => '1']);
});

afterEach(function () {
    foreach ($this->pgSemeado ?? [] as $chave) {
        DB::table('system')->where('key', $chave)->delete();
    }
    if (property_exists($this, 'pgOfflineAntes')) {
        $this->pgOfflineAntes === null
            ? DB::table('system')->where('key', 'enable_offline_payment')->delete()
            : DB::table('system')->where('key', 'enable_offline_payment')->update(['value' => $this->pgOfflineAntes]);
    }
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
});

/** Tenant fictício. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_PG = 98;

function pgFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2SuperadminPagar' && $this->ligada;
        }
    });
}

function pgUsuario(string $username, bool $comPermissao): User
{
    Business::firstOrCreate(['id' => BIZ_PG], ['name' => 'Tenant fictício pagar assinatura', 'currency_id' => 1]);

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

/** Pacote pago e ativo, marcado por nome; idempotente entre runs. */
function pgPacote(): int
{
    $nome = 'Pacote fictício contrato pagar';
    $id = DB::table('packages')->where('name', $nome)->value('id');

    return (int) ($id ?? DB::table('packages')->insertGetId([
        'name' => $nome, 'description' => 'Só para o contrato de Pagar assinatura.',
        'location_count' => 2, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 15, 'price' => 1234.5,
        'created_by' => 1, 'sort_order' => 99, 'is_active' => 1, 'is_private' => 0, 'is_one_time' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

function pgRota(int $pacote, string $sufixo = ''): string
{
    return "/subscription/{$pacote}/pay{$sufixo}";
}

it('UC-SAPG-01 · com a flag ligada, a tela responde Inertia com o componente novo', function () {
    pgFlag(true);
    $this->actingAs(pgUsuario('pg_permitido_test', true))
        ->get(pgRota(pgPacote()))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('superadmin/Pagar/Index', false));
});

it('UC-SAPG-02 · com a flag desligada, ou ?classico=1, segue a Blade', function () {
    $user = pgUsuario('pg_permitido_test', true);

    pgFlag(false);
    $blade = $this->actingAs($user)->get(pgRota(pgPacote()))->assertOk();
    expect($blade->headers->get('X-Inertia'))->toBeNull();
    $blade->assertViewIs('superadmin::subscription.pay');

    pgFlag(true);
    $this->actingAs($user)->get(pgRota(pgPacote(), '?classico=1'))
        ->assertOk()
        ->assertViewIs('superadmin::subscription.pay');
});

it('UC-SAPG-03 · só Pix automático e offline são oferecidos', function () {
    pgFlag(true);
    $this->actingAs(pgUsuario('pg_permitido_test', true))
        ->get(pgRota(pgPacote()))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $gateways = collect($page->toArray()['props']['pagamento']['formas'])->pluck('gateway')->all();
            expect(array_diff($gateways, ['paymentgateway_pix_automatico', 'offline']))->toBe([]);
            expect($gateways)->toContain('offline');
        });
});

it('UC-SAPG-04 · o preço é o texto de moedaComoBlade e o POST vai ao confirm do pacote', function () {
    pgFlag(true);
    $pacote = pgPacote();
    $user = pgUsuario('pg_permitido_test', true);

    $props = null;
    $this->actingAs($user)->get(pgRota($pacote))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$props) {
            $props = $page->toArray()['props']['pagamento'];
        });

    // O formatador lê a sessão do request; o GET acima a deixou montada para este usuário.
    $esperado = app(SubscriptionController::class)->moedaComoBlade(
        DB::table('packages')->where('id', $pacote)->value('price')
    );
    expect($props['pacote']['preco'])->toBe($esperado);
    expect($props['pacote']['id'])->toBe($pacote);
    expect($props['confirmar_url'])->toEndWith("/subscription/{$pacote}/confirm");
});

it('UC-SAPG-05 · sem a permissão é barrado ENQUANTO quem tem passa', function () {
    pgFlag(true);
    $this->actingAs(pgUsuario('pg_barrado_test', false))->get(pgRota(pgPacote()))->assertForbidden();
    $this->actingAs(pgUsuario('pg_permitido_test', true))->get(pgRota(pgPacote()))->assertOk();
});
