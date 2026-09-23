<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Contrato de SESSÃO das rotas do Financeiro.
 *
 * Até 2026-09-23 o grupo operacional (Modules/Financeiro/Routes/web.php) não passava
 * pelo `SetSessionData`. Medido: abrindo /financeiro/dre direto numa sessão ainda não
 * populada, a página recebeu meta.business_id = 0 e o DRE veio vazio; depois de visitar
 * /home veio business_id = 1. Os controllers leem `session('user.business_id')`, e quem
 * preenche essa chave é o middleware.
 *
 * Duas pernas:
 *   1. registro — toda rota autenticada `financeiro.*` carrega o middleware (oráculo =
 *      o registro de rotas vivo, não a leitura do arquivo);
 *   2. comportamento — uma requisição com sessão VAZIA sai com o business do usuário
 *      na sessão.
 *
 * ADR 0358: tenant canônico de teste é o fictício 98 (`seededTenant()`).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Rotas do Financeiro exigem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Tabelas business/permissions ausentes — rode migrate primeiro.');
    }
});

it('toda rota autenticada financeiro.* passa pelo SetSessionData', function () {
    $semMiddleware = [];
    $conferidas = 0;

    foreach (Route::getRoutes() as $rota) {
        $nome = (string) $rota->getName();
        if (! str_starts_with($nome, 'financeiro.')) {
            continue;
        }
        $middlewares = $rota->gatherMiddleware();
        if (! in_array('auth', $middlewares, true)) {
            continue;
        }
        $conferidas++;
        if (! in_array('SetSessionData', $middlewares, true)) {
            $semMiddleware[] = $nome;
        }
    }

    // Anti-vácuo: se o filtro não achar rota nenhuma, o "sem faltantes" seria verde vazio.
    expect($conferidas)->toBeGreaterThan(50);
    expect($semMiddleware)->toBe([]);
});

it('requisição com sessão vazia sai com o business do usuário na sessão', function () {
    $biz = $this->seededTenant();
    $user = User::factory()->create([
        'business_id' => (int) $biz->id,
        'username' => 'fin_sessao_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    try {
        Permission::firstOrCreate(['name' => 'financeiro.relatorios.view', 'guard_name' => 'web']);
        $user->givePermissionTo('financeiro.relatorios.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Pré-condição: a sessão começa SEM o bloco `user`.
        expect(session('user.business_id'))->toBeNull();

        $manifest = public_path('build-inertia/manifest.json');
        $resp = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
                'Accept' => 'text/html',
            ])
            ->get('/financeiro/dre');

        expect($resp->status())->toBe(200);
        expect((int) session('user.business_id'))->toBe((int) $biz->id);
    } finally {
        DB::table('model_has_permissions')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();
        $user->forceDelete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
});
