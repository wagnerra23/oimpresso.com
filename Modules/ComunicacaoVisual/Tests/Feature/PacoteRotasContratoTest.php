<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\ComunicacaoVisual\Http\Middleware\ComunicacaoVisualLiberado;
use Spatie\Permission\Models\Role;
use Tests\Support\WithSeededTenant;

/**
 * Pacote `comunicacao_visual_module` nas rotas do módulo (UC-CVPAC-01..03).
 *
 * Âncora: Camada 1 de memory/reference/feedback-habilitar-modulo-por-business.md (o pacote é da
 * empresa, nunca hardcode de business_id) + auditoria 2026-11-05 I-04/S8: só o menu e o app conferiam.
 *
 * O pacote é fixado por mock do ModuleUtil (padrão do ComprasPacoteGateTest): no CI a tabela `system`
 * não tem `superadmin_version`, e o ModuleUtil real liberaria qualquer pacote. Cada negativo tem o
 * par positivo com o MESMO usuário e a MESMA requisição: o 403 vem do pacote, não de outra camada.
 *
 * Tenant 98 (ADR 0358). ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_orcamentos')) {
        $this->markTestSkipped('Requer MySQL com o schema do módulo.');
    }
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: '.$e->getMessage());
    }

    $this->pacote = function (bool $tem): void {
        $this->partialMock(ModuleUtil::class)
            ->shouldReceive('hasThePermissionInSubscription')
            ->andReturnUsing(fn ($biz, $permissao = null, ...$resto) => $permissao === ComunicacaoVisualLiberado::PACOTE ? $tem : true);
    };

    $this->como = function (\App\User $user) {
        return $this->actingAs($user)->withSession([
            'user.business_id' => $this->biz->id, 'business.id' => $this->biz->id, 'user.id' => $user->id,
        ]);
    };

    $this->operador = $this->usuarioComPermissoes(['comvis.orcamento.view', 'comvis.orcamento.create'], $this->biz);
});

it('UC-CVPAC-01 · empresa sem o pacote recebe 403 no hub, na agenda e na API', function () {
    ($this->pacote)(false);

    ($this->como)($this->operador)->get('/comunicacao-visual')->assertStatus(403);
    ($this->como)($this->operador)->get('/comunicacao-visual/agenda')->assertStatus(403);
    ($this->como)($this->operador)->getJson('/comunicacao-visual/api/orcamentos')->assertStatus(403);
    ($this->como)($this->operador)->getJson('/comunicacao-visual/api/orcamentos/999999999')->assertStatus(403);
    ($this->como)($this->operador)->postJson('/comunicacao-visual/api/orcamentos/999999999/aprovar')->assertStatus(403);
});

it('UC-CVPAC-01 · o admin da empresa, que passa em todo can(), também é barrado sem o pacote', function () {
    $papel = Role::firstOrCreate(['name' => 'Admin#'.$this->biz->id, 'guard_name' => 'web', 'business_id' => $this->biz->id]);
    $admin = \App\User::factory()->create(['business_id' => $this->biz->id, 'allow_login' => 1]);
    $admin->assignRole($papel);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $admin = \App\User::findOrFail($admin->id);
    expect($admin->can('comvis.orcamento.view'))->toBeTrue();

    ($this->pacote)(false);
    ($this->como)($admin)->getJson('/comunicacao-visual/api/orcamentos/999999999')->assertStatus(403);

    // Par positivo: com o pacote, a mesma requisição chega ao controller (404 = não achou).
    ($this->pacote)(true);
    ($this->como)($admin)->getJson('/comunicacao-visual/api/orcamentos/999999999')->assertStatus(404);
});

it('UC-CVPAC-02 · empresa com o pacote segue usando, e a permissão continua valendo', function () {
    ($this->pacote)(true);

    ($this->como)($this->operador)->get('/comunicacao-visual')
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('ComunicacaoVisual/Index'));
    ($this->como)($this->operador)->getJson('/comunicacao-visual/api/orcamentos/999999999')->assertStatus(404);

    $semPermissao = \App\User::factory()->create(['business_id' => $this->biz->id, 'allow_login' => 1]);
    ($this->como)($semPermissao)->getJson('/comunicacao-visual/api/orcamentos/999999999')->assertStatus(403);
});

it('UC-CVPAC-03 · o link público do cliente não depende do pacote', function () {
    ($this->pacote)(false);

    // 404 = chegou ao controller e não achou o token; o gate daria 403.
    $this->get('/orcamento-cv/'.str_repeat('a', 48))->assertStatus(404);
});

it('UC-CVPAC-03 · toda rota operacional carrega o gate; a pública e a instalação não', function () {
    $rotas = collect(Route::getRoutes()->getRoutes());
    $operacionais = $rotas->filter(fn ($r) => str_starts_with((string) $r->getName(), 'comvis.api.')
        || str_starts_with((string) $r->getName(), 'comunicacao-visual.'));

    expect($operacionais->count())->toBeGreaterThanOrEqual(15);
    foreach ($operacionais as $rota) {
        expect($rota->gatherMiddleware())->toContain(ComunicacaoVisualLiberado::class);
    }

    $foraDoGate = $rotas->filter(fn ($r) => str_starts_with((string) $r->getName(), 'comvis.aceite.')
        || str_starts_with($r->uri(), 'comunicacao-visual/install'));
    expect($foraDoGate->count())->toBeGreaterThanOrEqual(6);
    foreach ($foraDoGate as $rota) {
        expect($rota->gatherMiddleware())->not->toContain(ComunicacaoVisualLiberado::class);
    }
});
