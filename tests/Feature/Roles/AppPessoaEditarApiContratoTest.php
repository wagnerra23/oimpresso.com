<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * API de Editar cadastro do app das lojas — PATCH /api/app/pessoas/{id}.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §4.4. Parcial; sem tipo/papéis; sem
 * saldo nem limite. O ponto que mais importa: editar pelo app NÃO mexe no lançamento de saldo
 * inicial da pessoa (o ContactUtil::updateContact da web zeraria, porque o app não manda saldo).
 * NÃO derivado do controller.
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 da lane. Transação revertida.
 */

const APP_EDIT_BIZ = 98;
const APP_EDIT_OUTRO = 2;

function appEditUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => APP_EDIT_BIZ]);
    $papel = Role::create(['name' => 'AppEdit' . uniqid() . '#' . APP_EDIT_BIZ, 'business_id' => APP_EDIT_BIZ, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

function appEditContato(int $biz, int $criadoPor, array $extra = []): int
{
    return (int) DB::table('contacts')->insertGetId(array_merge([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Pessoa Antiga', 'is_customer' => 1,
        'contact_id' => 'APE' . random_int(10000, 99999), 'mobile' => '48911110000', 'city' => 'Tubarão',
        'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_EDIT_BIZ, APP_EDIT_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('edição parcial grava só o que veio e NÃO toca no saldo inicial da pessoa', function () {
    $u = appEditUsuario(['customer.update', 'customer.view']);
    $id = appEditContato(APP_EDIT_BIZ, (int) $u->id);
    $saldo = (int) DB::table('transactions')->insertGetId([
        'business_id' => APP_EDIT_BIZ, 'type' => 'opening_balance', 'status' => 'final',
        'payment_status' => 'due', 'contact_id' => $id, 'transaction_date' => now(),
        'final_total' => 150.00, 'created_by' => $u->id, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    Passport::actingAs($u, [], 'api');

    $this->patchJson("/api/app/pessoas/{$id}", [
        'nome' => 'Pessoa Nova', 'email' => 'nova@example.test', 'prazo_padrao_dias' => 30,
        'consentimento' => ['email_nfe' => true],
    ])->assertOk()->assertJsonPath('id', $id);

    $c = DB::table('contacts')->where('id', $id)->first();
    expect($c->name)->toBe('Pessoa Nova');
    expect($c->email)->toBe('nova@example.test');
    expect((int) $c->pay_term_number)->toBe(30);
    expect((int) $c->email_consent)->toBe(1);
    expect($c->consent_updated_at)->not->toBeNull();
    expect($c->city)->toBe('Tubarão');
    expect($c->mobile)->toBe('48911110000');
    expect((float) DB::table('transactions')->where('id', $saldo)->value('final_total'))->toBe(150.0);
});

it('telefone null grava vazio (coluna NOT NULL) e documento inválido responde 422 sem gravar', function () {
    $u = appEditUsuario(['customer.update']);
    $id = appEditContato(APP_EDIT_BIZ, (int) $u->id);
    Passport::actingAs($u, [], 'api');

    $this->patchJson("/api/app/pessoas/{$id}", ['telefone' => null])->assertOk();
    expect(DB::table('contacts')->where('id', $id)->value('mobile'))->toBe('');

    $r = $this->patchJson("/api/app/pessoas/{$id}", ['documento' => '11111111112', 'nome' => 'Não grava']) // pii-allowlist: dígitos repetidos, inválido de propósito
        ->assertStatus(422)->assertJsonPath('erro', 'validacao');
    expect(array_keys($r->json('campos')))->toContain('documento');
    expect(DB::table('contacts')->where('id', $id)->value('name'))->toBe('Pessoa Antiga');
});

it('sem customer.update responde 403 e não grava; com a permissão grava (controle)', function () {
    $sem = appEditUsuario(['customer.view']);
    $id = appEditContato(APP_EDIT_BIZ, (int) $sem->id);
    Passport::actingAs($sem, [], 'api');
    $this->patchJson("/api/app/pessoas/{$id}", ['nome' => 'X'])->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(DB::table('contacts')->where('id', $id)->value('name'))->toBe('Pessoa Antiga');

    Passport::actingAs(appEditUsuario(['customer.update']), [], 'api');
    $this->patchJson("/api/app/pessoas/{$id}", ['nome' => 'X'])->assertOk();
    expect(DB::table('contacts')->where('id', $id)->value('name'))->toBe('X');
});

it('Tier 0: pessoa de OUTRO business responde 404 e não muda', function () {
    $u = appEditUsuario(['customer.update']);
    $alheia = appEditContato(APP_EDIT_OUTRO, (int) $u->id);
    Passport::actingAs($u, [], 'api');

    $this->patchJson("/api/app/pessoas/{$alheia}", ['nome' => 'Invadido'])->assertNotFound();
    expect(DB::table('contacts')->where('id', $alheia)->value('name'))->toBe('Pessoa Antiga');
});
