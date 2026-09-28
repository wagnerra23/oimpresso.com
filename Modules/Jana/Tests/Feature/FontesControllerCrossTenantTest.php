<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaFonte;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Follow-up #4474 — gate EXPLÍCITO de tenant no FontesController::update
 * (Modules/KB, rota Jana PATCH /ia/metas/{id}/fonte, grupo /ia = só auth).
 *
 * IDOR CRÍTICO provado: `MetaFonte::updateOrCreate(['meta_id' => $metaId], $data)`
 * com $metaId cru gravava driver+config_json na fonte de apuração da meta de
 * OUTRO business (o backstop ScopeByBusinessViaParent não cobre o INSERT do
 * updateOrCreate) → injeção de `driver:sql` cross-tenant que roda depois no
 * ApuracaoService com o business_id da vítima.
 *
 * Fix: Meta::findOrFail($metaId) antes — espelha o gate que o show() já fazia.
 *
 * Este teste vive em Modules/Jana/Tests/Feature (registrado no phpunit.xml) —
 * Modules/KB não tem suite registrada, e a rota + entity Meta são de Jana.
 *
 * ADR 0093 Tier 0 IRREVOGÁVEL. ADR 0358: tenant 98 (fictício) vs biz=99. NUNCA biz=4, NUNCA biz=1.
 *
 * @see Modules/KB/Http/Controllers/FontesController.php
 */

const FONTES_BIZ_WAGNER = 98; // tenant canônico FICTÍCIO (ADR 0358)
const FONTES_BIZ_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
    foreach (['jana_metas', 'jana_meta_fontes'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Jana.");
        }
    }

    $business = Business::find(FONTES_BIZ_WAGNER);
    if (! $business) {
        $this->markTestSkipped('business_id=98 (tenant canônico ADR 0358) ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', FONTES_BIZ_WAGNER)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98.');
    }
    $this->wUser = $user;

    if (! Business::find(FONTES_BIZ_FICTICIO)) {
        Business::forceCreate([
            'id' => FONTES_BIZ_FICTICIO,
            'name' => 'Test Biz Adversario#99 (Fontes IDOR)',
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

    $this->metaFicticia = Meta::withoutGlobalScopes()->create([
        'business_id' => FONTES_BIZ_FICTICIO,
        'slug' => 'idor-fontes-biz99-'.uniqid(),
        'nome' => 'IDOR fonte meta biz99',
        'unidade' => 'BRL',
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ]);

    $this->metaWagner = Meta::withoutGlobalScopes()->create([
        'business_id' => FONTES_BIZ_WAGNER,
        'slug' => 'idor-fontes-biz1-'.uniqid(),
        'nome' => 'IDOR fonte meta biz1',
        'unidade' => 'BRL',
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ]);

    // Pré-condição do gate `can:jana.access` no grupo /ia (2026-07-27): sem a
    // permissão o middleware corta com 403 ANTES do controller, e o teste mediria
    // o gate em vez do isolamento cross-tenant que ele existe pra provar.
    // Conceder aqui NÃO enfraquece nada: o contrato sob teste é "usuário COM acesso
    // ao módulo ainda não alcança dado de outra empresa".
    //
    // Idem `can:jana.metas.manage` nas escritas de meta/período/fonte (#7895, UC-JPERM-03):
    // sem ela o 403 da trava chega ANTES do `Meta::findOrFail` e o 404 cross-tenant nunca
    // é exercitado — o teste de isolamento fica MUDO (medido 2026-09-27: 403 vs 404). O
    // 403 SEM a permissão é contrato separado, provado em `Http/MetasPermissaoTest.php`
    // e no `PeriodosControllerCrossTenantTest`.
    \Spatie\Permission\Models\Permission::findOrCreate('jana.access', 'web');
    \Spatie\Permission\Models\Permission::findOrCreate('jana.metas.manage', 'web');
    $this->wUser->givePermissionTo('jana.access', 'jana.metas.manage');
    $this->wUser->forgetCachedPermissions();

    $this->actingAs($this->wUser);
    session([
        'user.business_id' => FONTES_BIZ_WAGNER,
        'business' => ['id' => FONTES_BIZ_WAGNER, 'name' => $business->name],
    ]);
});

// A permissão criada na transação some no rollback; o cache do Spatie que sobrevive a
// ela vira PermissionDoesNotExist/FK no teste seguinte de OUTRO arquivo (#7895).
afterEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

function fontePayload(): array
{
    return [
        'driver' => 'sql',
        'config_json' => ['query' => 'SELECT 1'],
        'cadencia' => 'diaria',
    ];
}

it('update cross-tenant: PATCH fonte na meta biz=99 → 404 e NÃO grava MetaFonte', function () {
    $antes = MetaFonte::withoutGlobalScopes()->where('meta_id', $this->metaFicticia->id)->count();

    $resp = $this->patch("/ia/metas/{$this->metaFicticia->id}/fonte", fontePayload());

    $resp->assertNotFound();
    $depois = MetaFonte::withoutGlobalScopes()->where('meta_id', $this->metaFicticia->id)->count();
    expect($depois)->toBe($antes); // gate barrou a injeção de driver:sql cross-tenant
});

it('update positivo: PATCH fonte na própria meta biz=98 → 302 e grava', function () {
    $resp = $this->patch("/ia/metas/{$this->metaWagner->id}/fonte", fontePayload());

    $resp->assertRedirect();
    expect(MetaFonte::withoutGlobalScopes()->where('meta_id', $this->metaWagner->id)->count())->toBe(1);
});
