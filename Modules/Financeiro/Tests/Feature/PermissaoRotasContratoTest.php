<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Contrato de PERMISSÃO (Camada 3 Spatie) das rotas do Financeiro que até
 * 2026-09-23 só exigiam login — medido em produção com
 * `php artisan route:list --path=financeiro --json` (sem `can:` na rota) e lendo
 * controller + FormRequest (sem can()/authorize).
 *
 * Os dados já eram restritos ao business da sessão; o buraco era dentro da empresa:
 * qualquer usuário logado cadastrava categoria, dava baixa em conta a pagar, emitia
 * boleto, conciliava extrato. Cada caso abaixo prova as DUAS pernas:
 *   - sem a permission → 403 exato (o gate morde);
 *   - com a permission → passa do gate (200/302/404 — ids inexistentes e payload
 *     vazio de propósito: nenhuma escrita acontece no tenant de teste).
 *
 * O usuário é comum (sem role `Admin#<biz>`) — Admin passa pelo Gate::before de
 * AuthServiceProvider e tornaria o 403 inalcançável.
 *
 * ADR 0358: tenant canônico de teste é o fictício 98 (`seededTenant()`).
 */

const PERM_ROTAS_ID_INEXISTENTE = 999999999;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Rotas do Financeiro exigem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Tabelas business/permissions ausentes — rode migrate primeiro.');
    }
});

/** [método, url, permission exigida, é GET Inertia?] */
function permRotasCasos(): array
{
    $id = PERM_ROTAS_ID_INEXISTENTE;

    return [
        'categorias.index'        => ['GET', '/financeiro/categorias', 'financeiro.dashboard.view', true],
        'categorias.store'        => ['POST', '/financeiro/categorias', 'financeiro.lancamentos.create', false],
        'categorias.update'       => ['PUT', "/financeiro/categorias/{$id}", 'financeiro.lancamentos.create', false],
        'categorias.destroy'      => ['DELETE', "/financeiro/categorias/{$id}", 'financeiro.lancamentos.create', false],
        'categorias.toggle'       => ['POST', "/financeiro/categorias/{$id}/toggle", 'financeiro.lancamentos.create', false],
        'plano-contas.index'      => ['GET', '/financeiro/plano-contas', 'financeiro.dashboard.view', true],
        'conciliacao.index'       => ['GET', '/financeiro/conciliacao', 'financeiro.conciliacao.manage', true],
        'conciliacao.upload'      => ['POST', '/financeiro/conciliacao/upload', 'financeiro.conciliacao.manage', false],
        'conciliacao.match'       => ['POST', "/financeiro/conciliacao/{$id}/match", 'financeiro.conciliacao.manage', false],
        'conciliacao.ignorar'     => ['POST', "/financeiro/conciliacao/{$id}/ignorar", 'financeiro.conciliacao.manage', false],
        'conciliacao.reabrir'     => ['POST', "/financeiro/conciliacao/{$id}/reabrir", 'financeiro.conciliacao.manage', false],
        'contas-bancarias.index'  => ['GET', '/financeiro/contas-bancarias', 'financeiro.contas_bancarias.manage', true],
        'contas-bancarias.upsert' => ['POST', "/financeiro/contas-bancarias/{$id}", 'financeiro.contas_bancarias.manage', false],
        'extrato.selecionar'      => ['GET', '/financeiro/extrato', 'financeiro.extrato.view', false],
        'extrato.index'           => ['GET', "/financeiro/extrato/{$id}", 'financeiro.extrato.view', true],
        'contas-pagar.index'      => ['GET', '/financeiro/contas-pagar', 'financeiro.contas_pagar.view', true],
        'contas-pagar.pagar'      => ['POST', "/financeiro/contas-pagar/{$id}/pagar", 'financeiro.contas_pagar.pagar', false],
        'contas-receber.index'    => ['GET', '/financeiro/contas-receber', 'financeiro.contas_receber.view', true],
        'contas-receber.boleto'   => ['POST', "/financeiro/contas-receber/{$id}/boleto", 'financeiro.contas_receber.create', false],
    ];
}

function permRotasUsuario(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'username' => 'fin_perm_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

function permRotasChamar($test, User $user, int $businessId, string $metodo, string $url, bool $inertia)
{
    $headers = [];
    if ($inertia) {
        $manifest = public_path('build-inertia/manifest.json');
        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
            'Accept' => 'text/html',
        ];
    }

    return $test->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user.id' => $user->id,
            'business.id' => $businessId,
        ])
        ->withHeaders($headers)
        ->call($metodo, $url);
}

function permRotasLimpar(User $user): void
{
    DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->where('model_id', $user->id)
        ->delete();
    $user->forceDelete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('sem a permission, cada rota do Financeiro devolve 403', function (string $metodo, string $url, string $permission, bool $inertia) {
    $biz = $this->seededTenant();
    $user = permRotasUsuario((int) $biz->id);

    try {
        // Pré-condição anti-vácuo: o 403 tem de vir da FALTA da permission — não de o
        // usuário ser Admin por acidente (aí nunca daria 403) nem de já ter a permission.
        expect($user->hasRole('Admin#'.$biz->id))->toBeFalse();
        expect($user->can($permission))->toBeFalse();

        $resp = permRotasChamar($this, $user, (int) $biz->id, $metodo, $url, $inertia);

        expect($resp->status())->toBe(403);
    } finally {
        permRotasLimpar($user);
    }
})->with(permRotasCasos());

it('com a permission, cada rota do Financeiro passa do gate', function (string $metodo, string $url, string $permission, bool $inertia) {
    $biz = $this->seededTenant();
    $user = permRotasUsuario((int) $biz->id);

    try {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $resp = permRotasChamar($this, $user, (int) $biz->id, $metodo, $url, $inertia);

        // 200 = tela · 302 = redirect/validação do payload vazio · 404 = id inexistente.
        // Todos provam que a requisição atravessou o gate; 403 provaria o contrário.
        expect($resp->status())->toBeIn([200, 302, 404]);
    } finally {
        permRotasLimpar($user);
    }
})->with(permRotasCasos());
