<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-003

/**
 * Contrato do `POST /subscription/{id}/confirm` — o passo que cria a assinatura depois de pagar.
 *
 * Achado da auditoria 2026-11-05 (eixo 5, CRÍTICA): `gateway` vinha do request sem validação;
 * gateway desconhecido pulava o pagamento e `_add_subscription` aprovava o pacote pago. A rota
 * era `Route::any`, então um GET sem CSRF bastava. Estes casos travam o conserto.
 *
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /subscription/{id}/confirm requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('packages')) {
        $this->markTestSkipped('Schema do Superadmin ausente — rode migrations primeiro.');
    }
    config(['constants.administrator_usernames' => 'ninguem_scf_test']);
    Notification::fake();

    // `_payment_gateways()` lê `System::getCurrency()->country`: sem moeda do sistema ele quebra.
    $this->scfSemeado = [];
    if (! DB::table('system')->where('key', 'app_currency_id')->exists()) {
        $moeda = DB::table('currencies')->orderBy('id')->value('id');
        if ($moeda === null) {
            $this->markTestSkipped('Sem moeda em currencies — _payment_gateways() não tem país.');
        }
        DB::table('system')->insert(['key' => 'app_currency_id', 'value' => (string) $moeda]);
        $this->scfSemeado[] = 'app_currency_id';
    }

    $this->scfOfflineAntes = DB::table('system')->where('key', 'enable_offline_payment')->value('value');
    DB::table('system')->updateOrInsert(['key' => 'enable_offline_payment'], ['value' => '1']);
    scfLimpar();
});

afterEach(function () {
    scfLimpar();
    foreach ($this->scfSemeado ?? [] as $chave) {
        DB::table('system')->where('key', $chave)->delete();
    }
    if (property_exists($this, 'scfOfflineAntes')) {
        $this->scfOfflineAntes === null
            ? DB::table('system')->where('key', 'enable_offline_payment')->delete()
            : DB::table('system')->where('key', 'enable_offline_payment')->update(['value' => $this->scfOfflineAntes]);
    }
});

/** Tenant fictício. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_SCF = 98;

function scfUsuario(string $username, bool $comPermissao): User
{
    Business::firstOrCreate(['id' => BIZ_SCF], ['name' => 'Tenant fictício confirmar assinatura', 'currency_id' => 1]);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => BIZ_SCF, 'first_name' => 'Scf', 'last_name' => 'Teste',
    ]);
    $user->syncRoles([]);
    $user->syncPermissions($comPermissao
        ? [Permission::firstOrCreate(['name' => 'superadmin.access_package_subscriptions', 'guard_name' => 'web'])]
        : []);

    return $user;
}

/** Pacote pago e ativo, marcado por nome; idempotente entre runs. */
function scfPacote(string $nome = 'Pacote fictício contrato confirmar', int $privado = 0): int
{
    $id = DB::table('packages')->where('name', $nome)->value('id');
    if ($id !== null) {
        DB::table('packages')->where('id', $id)->update(['is_private' => $privado, 'is_active' => 1]);

        return (int) $id;
    }

    return (int) DB::table('packages')->insertGetId([
        'name' => $nome, 'description' => 'Só para o contrato do confirm de assinatura.',
        'location_count' => 1, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 0, 'price' => 99.9,
        'created_by' => 1, 'sort_order' => 99, 'is_active' => 1, 'is_private' => $privado, 'is_one_time' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function scfLimpar(): void
{
    $pacotes = DB::table('packages')->where('name', 'like', 'Pacote fictício contrato confirmar%')->pluck('id');
    DB::table('subscriptions')->where('business_id', BIZ_SCF)->whereIn('package_id', $pacotes)->delete();
}

function scfAssinaturas(int $pacote)
{
    return DB::table('subscriptions')->where('business_id', BIZ_SCF)->where('package_id', $pacote)->get();
}

it('UC-SACF-01 · gateway que não está configurado não cria assinatura', function () {
    $pacote = scfPacote();

    $this->actingAs(scfUsuario('scf_permitido_test', true))
        ->post("/subscription/{$pacote}/confirm", ['gateway' => 'qualquer'])
        ->assertRedirect();

    expect(scfAssinaturas($pacote))->toHaveCount(0);
});

it('UC-SACF-02 · GET no confirm é 405 e não cria assinatura', function () {
    $pacote = scfPacote();

    $this->actingAs(scfUsuario('scf_permitido_test', true))
        ->get("/subscription/{$pacote}/confirm?gateway=offline")
        ->assertStatus(405);

    expect(scfAssinaturas($pacote))->toHaveCount(0);
});

it('UC-SACF-03 · offline habilitado cria a assinatura em espera, sem datas', function () {
    $pacote = scfPacote();

    $this->actingAs(scfUsuario('scf_permitido_test', true))
        ->post("/subscription/{$pacote}/confirm", ['gateway' => 'offline'])
        ->assertRedirect();

    $linhas = scfAssinaturas($pacote);
    expect($linhas)->toHaveCount(1);
    expect($linhas->first()->status)->toBe('waiting');
    expect($linhas->first()->paid_via)->toBe('offline');
    expect($linhas->first()->start_date)->toBeNull();
});

it('UC-SACF-04 · pacote privado não é assinado por quem não é superadmin', function () {
    $pacote = scfPacote('Pacote fictício contrato confirmar privado', 1);

    $this->actingAs(scfUsuario('scf_permitido_test', true))
        ->post("/subscription/{$pacote}/confirm", ['gateway' => 'offline'])
        ->assertRedirect();

    expect(scfAssinaturas($pacote))->toHaveCount(0);
});

it('UC-SACF-05 · pacote inexistente não cria assinatura nem quebra a tela', function () {
    $inexistente = (int) DB::table('packages')->max('id') + 1000;

    $this->actingAs(scfUsuario('scf_permitido_test', true))
        ->post("/subscription/{$inexistente}/confirm", ['gateway' => 'offline'])
        ->assertRedirect();

    expect(DB::table('subscriptions')->where('business_id', BIZ_SCF)->where('package_id', $inexistente)->count())->toBe(0);
});

it('UC-SACF-06 · o retorno GET só aceita PayPal e Pesapal', function () {
    $pacote = scfPacote();

    $this->actingAs(scfUsuario('scf_permitido_test', true))
        ->get("/subscription/{$pacote}/confirm/retorno?gateway=offline")
        ->assertStatus(405);

    expect(scfAssinaturas($pacote))->toHaveCount(0);
});

it('UC-SACF-07 · sem a permissão é 403 ENQUANTO quem tem cria a assinatura', function () {
    $pacote = scfPacote();

    $this->actingAs(scfUsuario('scf_barrado_test', false))
        ->post("/subscription/{$pacote}/confirm", ['gateway' => 'offline'])
        ->assertForbidden();
    expect(scfAssinaturas($pacote))->toHaveCount(0);

    $this->actingAs(scfUsuario('scf_permitido_test', true))
        ->post("/subscription/{$pacote}/confirm", ['gateway' => 'offline'])
        ->assertRedirect();
    expect(scfAssinaturas($pacote))->toHaveCount(1);
});
