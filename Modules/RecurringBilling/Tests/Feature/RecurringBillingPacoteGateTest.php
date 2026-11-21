<?php

declare(strict_types=1);

/*
 * Gate do pacote `recurringbilling_module` nas rotas web da Cobrança Recorrente.
 *
 * Auditoria RecurringBilling 2026-11-19, achado a5: o pacote só era conferido no menu
 * (DataController::modifyAdminMenu); a empresa sem o pacote abria /recurring-billing pela URL.
 *
 * O pacote é fixado por mock do ModuleUtil (mesmo padrão do ComprasPacoteGateTest): no CI a
 * tabela `system` não tem `superadmin_version`, e o ModuleUtil real libera qualquer pacote —
 * sem o mock o teste não provaria nada. Cada 403 tem o par 200 com o MESMO usuário e a MESMA
 * requisição, então o 403 vem do pacote e não de outra camada.
 *
 * Duas partes:
 *  1. sqlite — rota de prova com SÓ o gate (403 sem pacote, 200 com) + todas as rotas web do
 *     módulo carregam o gate, e os webhooks não.
 *  2. MySQL (CT 100) — a rota real GET /recurring-billing, com a pilha inteira de middleware.
 *
 * Tenant fictício 98 (ADR 0358). Nunca biz=4.
 */

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Http\Middleware\RecurringBillingLiberado;

uses(Tests\TestCase::class);

const RB_GATE_BIZ = 98;

/**
 * Fixa só o pacote da Cobrança Recorrente; qualquer outra consulta de pacote segue liberada.
 * Recebe o mock porque `partialMock` é protegido no TestCase: só o closure do teste o chama.
 */
function rbPacote($mock, bool $tem): void
{
    $mock->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $permissao = null, ...$resto) => $permissao === RecurringBillingLiberado::PACOTE ? $tem : true);
}

function rbUsuarioSqlite(): User
{
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('username')->unique();
            $t->string('password');
            $t->integer('business_id')->nullable();
            $t->rememberToken();
            $t->softDeletes();
            $t->timestamps();
        });
    }

    return User::forceCreate([
        'username'    => 'rb_gate_' . uniqid(),
        'password'    => bcrypt('x'),
        'business_id' => RB_GATE_BIZ,
    ]);
}

describe('rota de prova (sqlite)', function () {
    beforeEach(function () {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Parte sqlite — a rota real roda no bloco MySQL.');
        }
        Route::middleware(RecurringBillingLiberado::class)
            ->get('/__teste/rb-pacote-gate', fn () => 'ok');
    });

    it('empresa 98 sem o pacote recebe 403', function () {
        rbPacote($this->partialMock(ModuleUtil::class), false);

        $this->actingAs(rbUsuarioSqlite())->get('/__teste/rb-pacote-gate')->assertStatus(403);
    });

    it('controle positivo: a mesma empresa com o pacote recebe 200', function () {
        rbPacote($this->partialMock(ModuleUtil::class), true);

        $this->actingAs(rbUsuarioSqlite())->get('/__teste/rb-pacote-gate')
            ->assertStatus(200)
            ->assertSee('ok');
    });

    it('sem usuário logado o gate recusa (403)', function () {
        rbPacote($this->partialMock(ModuleUtil::class), true);

        $this->get('/__teste/rb-pacote-gate')->assertStatus(403);
    });
});

it('toda rota web nomeada do módulo carrega o gate de pacote, e os webhooks não', function () {
    $rotas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'recurring-billing.')
            || $r->getName() === 'rb-invoices.cancel');

    expect($rotas->count())->toBeGreaterThanOrEqual(20);

    foreach ($rotas as $rota) {
        expect($rota->gatherMiddleware())->toContain(RecurringBillingLiberado::class);
    }

    // Webhook chega sem usuário: com o gate ele daria 403 para o banco.
    expect(Route::getRoutes()->getByName('webhooks.inter.pix')->gatherMiddleware())
        ->not->toContain(RecurringBillingLiberado::class);
});

describe('rota real GET /recurring-billing (MySQL, CT 100)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Pilha completa de middleware exige o schema MySQL.');
        }
        $this->biz = $this->seededTenant();
        $this->user = $this->usuarioComPermissoes(['recurringbilling.access'], $this->biz);
    });

    it('empresa 98 sem o pacote recebe 403 na rota real', function () {
        rbPacote($this->partialMock(ModuleUtil::class), false);

        $this->actingAs($this->user)
            ->withSession(['user' => ['business_id' => $this->biz->id, 'id' => $this->user->id]])
            ->get('/recurring-billing')
            ->assertStatus(403);
    });

    it('controle positivo: com o pacote a rota real responde 200', function () {
        rbPacote($this->partialMock(ModuleUtil::class), true);

        $this->actingAs($this->user)
            ->withSession(['user' => ['business_id' => $this->biz->id, 'id' => $this->user->id]])
            ->get('/recurring-billing')
            ->assertStatus(200);
    });
});
