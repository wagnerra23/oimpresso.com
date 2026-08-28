<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-JPERM-08 — `/ia/pro` e o preview admin são DUAS telas, não uma.
 *
 * ── O CONTRATO (derivado do routes.php + ADR 0140, não do .tsx — §5 2026-06-05)
 *   · `/ia/pro` (`jana.pro.index`) é a tela COMERCIAL do cliente. Todo usuário com
 *     `jana.access` entra; o business é o da sessão (UC-PRO-05 cobre esse eixo).
 *   · `/ia/admin/jana-pro/preview` (`jana.admin.jana_pro.preview`) é ADMIN: aceita
 *     `?business_id=N` DE PROPÓSITO, pra rodar o BriefDiarioService de outro tenant.
 *
 * Fundir as duas — ou reescrever "a tela Pro" sem separá-las — expõe o preview.
 *
 * ── ONDE ESTÁ A DEFESA REAL, e o comentário do controller diz o contrário
 * A rota declara `->middleware('can:jana.superadmin')`, e o docblock do
 * `JanaProController::preview` afirma "Middleware can:jana.superadmin já garante
 * mas defense-in-depth não custa".
 *
 * MEDIDO em 2026-08-28: é o INVERSO. O `Gate::before`
 * (app/Providers/AuthServiceProvider.php:34-47) devolve `true` em QUALQUER ability
 * fora de ['backup','superadmin','manage_modules'] pra quem tem `Admin#{business_id}`
 * — e `jana.superadmin` não está nessa allowlist. Logo o middleware NÃO barra o dono
 * de negócio; quem barra é a checagem de `user_type` em JanaProController:48, a
 * "defense-in-depth que não custa". É exatamente o buraco que vazou no
 * `SuperadminController` (P0 #6421), aqui tapado por acidente feliz.
 *
 * O 1º caso PINA essa verdade: se um dia o middleware passar a barrar sozinho, ele
 * quebra e alguém relê este bloco.
 *
 * TENANT: 98 canônico vs 99 adversário (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 *
 * @see Modules/Jana/Http/Controllers/Admin/JanaProController.php
 * @see Modules/Jana/Http/routes.php (linhas 120 e 240)
 */

const PROPREV_BIZ = 98;
const PROPREV_BIZ_ALHEIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }

    $business = Business::find(PROPREV_BIZ);
    if (! $business) {
        $this->markTestSkipped('business_id=98 (tenant canônico ADR 0358) ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', PROPREV_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98.');
    }
    $this->user = $user;

    if (! Business::find(PROPREV_BIZ_ALHEIO)) {
        Business::forceCreate([
            'id' => PROPREV_BIZ_ALHEIO,
            'name' => 'Test Biz Alheio#99 (Pro preview)',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => $user->id,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);
    }

    Permission::findOrCreate('jana.access', 'web');
    Permission::findOrCreate('jana.superadmin', 'web');

    // Dono do negócio: papel Admin#98 — é ele que dispara o `Gate::before`.
    $attrs = ['name' => 'Admin#'.PROPREV_BIZ, 'guard_name' => 'web'];
    if (Schema::hasColumn('roles', 'business_id')) {
        $attrs['business_id'] = PROPREV_BIZ;
    }
    $this->user->assignRole(Role::firstOrCreate($attrs));

    $this->user->givePermissionTo('jana.access');
    $this->user->user_type = 'user';          // dono comum, NÃO superadmin de plataforma
    $this->user->save();
    $this->user->forgetCachedPermissions();

    $this->actingAs($this->user);
    session([
        'user.business_id' => PROPREV_BIZ,
        'business' => ['id' => PROPREV_BIZ, 'name' => $business->name],
    ]);
});

it('CONTROLE: o middleware NÃO barra o dono — quem barra é o user_type', function () {
    // Se este assert virar false, o `Gate::before` mudou e TODA permissão do ERP
    // mudou de significado junto. Sem ele, o 403 do caso 3 poderia vir do
    // middleware e o teste estaria medindo a trava errada.
    expect($this->user->can('jana.superadmin'))->toBeTrue();
    expect($this->user->hasPermissionTo('jana.superadmin'))->toBeFalse();
})->group('tier0');

it('UC-JPERM-08 · a tela do CLIENTE abre pro dono do negócio', function () {
    $status = $this->get(route('jana.pro.index'))->status();

    expect($status)->not->toBe(403);
    expect($status)->toBeLessThan(500);   // anti-vácuo: 5xx não pode passar por "não é 403"
})->group('tier0');

it('UC-JPERM-08 · o PREVIEW admin de outro business é 403 pro dono do negócio', function () {
    $resp = $this->get(route('jana.admin.jana_pro.preview', ['business_id' => PROPREV_BIZ_ALHEIO]));

    $resp->assertStatus(403);
    // O 403 tem de ser o do CONTROLLER (tenant_violation), não um genérico:
    // é o que prova que a defesa que mordeu foi a de `user_type`, não outra trava.
    $resp->assertJsonPath('error', 'tenant_violation');
})->group('tier0');

it('UC-JPERM-08 · superadmin vê o preview alheio E a sessão NÃO muda de business', function () {
    $this->user->user_type = 'superadmin';
    $this->user->save();
    $this->user->forgetCachedPermissions();

    $status = $this->get(route('jana.admin.jana_pro.preview', ['business_id' => PROPREV_BIZ_ALHEIO]))->status();

    expect($status)->not->toBe(403);
    expect($status)->toBeLessThan(500);

    // O preview LÊ outro tenant; ele não pode TROCAR o tenant de quem olha.
    expect((int) session('user.business_id'))->toBe(PROPREV_BIZ);
})->group('tier0');
