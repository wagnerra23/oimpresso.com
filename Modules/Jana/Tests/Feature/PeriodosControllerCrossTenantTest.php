<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaPeriodo;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Follow-up #4474 (fix fail-open ScopeByBusinessViaParent) — gate EXPLÍCITO
 * de tenant no PeriodosController. NÃO confia só no backstop de scope, que
 * NÃO cobre INSERT (só SELECT).
 *
 * IDOR provado: resource /ia/metas/{meta}/periodos (grupo /ia = só auth) deixava
 *   - store:   criar MetaPeriodo na meta de OUTRO business (INSERT não escopado)
 *   - update:  alterar período aninhado em meta de outro business
 *   - destroy: apagar período aninhado em meta de outro business
 *
 * Fix: Meta::findOrFail($metaId) (Meta tem HasBusinessScope) → id cross-tenant
 * 404 antes de qualquer escrita no filho. É o gate que os FormRequests já
 * documentavam mas o controller nunca executava.
 *
 * ADR 0093 Tier 0 IRREVOGÁVEL. ADR 0101: biz=1 (Wagner WR2) vs biz=99. NUNCA biz=4.
 *
 * @see Modules/Jana/Http/Controllers/PeriodosController.php
 * @see Modules/Jana/Tests/Feature/EntitiesFilhasMultiTenantViaParentTest.php (harness base)
 */

const PERIODOS_BIZ_WAGNER = 1;
const PERIODOS_BIZ_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
    foreach (['jana_metas', 'jana_meta_periodos'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Jana.");
        }
    }

    $business = Business::find(PERIODOS_BIZ_WAGNER);
    if (! $business) {
        $this->markTestSkipped('business_id=1 (Wagner WR2) não encontrado — semear DB.');
    }
    $user = User::where('business_id', PERIODOS_BIZ_WAGNER)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=1.');
    }
    $this->wUser = $user;

    // biz=99 stub (FK jana_metas.business_id → business). Rollback via DatabaseTransactions.
    if (! Business::find(PERIODOS_BIZ_FICTICIO)) {
        Business::forceCreate([
            'id' => PERIODOS_BIZ_FICTICIO,
            'name' => 'Test Biz Adversario#99 (Periodos IDOR)',
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
        'business_id' => PERIODOS_BIZ_FICTICIO,
        'slug' => 'idor-periodos-biz99-'.uniqid(),
        'nome' => 'IDOR meta biz99',
        'unidade' => 'BRL',
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ]);

    $this->metaWagner = Meta::withoutGlobalScopes()->create([
        'business_id' => PERIODOS_BIZ_WAGNER,
        'slug' => 'idor-periodos-biz1-'.uniqid(),
        'nome' => 'IDOR meta biz1',
        'unidade' => 'BRL',
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ]);

    $this->actingAs($this->wUser);
    session([
        'user.business_id' => PERIODOS_BIZ_WAGNER,
        'business' => ['id' => PERIODOS_BIZ_WAGNER, 'name' => $business->name],
    ]);
});

function periodoPayload(): array
{
    return [
        'tipo_periodo' => 'mes',
        'data_ini' => now()->startOfMonth()->toDateString(),
        'data_fim' => now()->endOfMonth()->toDateString(),
        'valor_alvo' => 1000,
        'trajetoria' => 'linear',
    ];
}

function periodoCru(int $metaId): MetaPeriodo
{
    return MetaPeriodo::withoutGlobalScopes()->create([
        'meta_id' => $metaId,
        'tipo_periodo' => 'mes',
        'data_ini' => now()->startOfMonth(),
        'data_fim' => now()->endOfMonth(),
        'valor_alvo' => 1000,
        'trajetoria' => 'linear',
    ]);
}

it('store cross-tenant: POST período na meta biz=99 (autenticado biz=1) → 404 e NÃO cria', function () {
    $antes = MetaPeriodo::withoutGlobalScopes()->where('meta_id', $this->metaFicticia->id)->count();

    $resp = $this->post("/ia/metas/{$this->metaFicticia->id}/periodos", periodoPayload());

    $resp->assertNotFound();
    $depois = MetaPeriodo::withoutGlobalScopes()->where('meta_id', $this->metaFicticia->id)->count();
    expect($depois)->toBe($antes); // gate barrou o INSERT cross-tenant
});

it('store positivo: POST período na própria meta biz=1 → 302 e CRIA', function () {
    $resp = $this->post("/ia/metas/{$this->metaWagner->id}/periodos", periodoPayload());

    $resp->assertRedirect();
    expect(MetaPeriodo::withoutGlobalScopes()->where('meta_id', $this->metaWagner->id)->count())->toBe(1);
});

it('update cross-tenant: PUT período de meta biz=99 → 404 e NÃO altera', function () {
    $periodo = periodoCru($this->metaFicticia->id);

    $resp = $this->put(
        "/ia/metas/{$this->metaFicticia->id}/periodos/{$periodo->id}",
        array_merge(periodoPayload(), ['valor_alvo' => 999999])
    );

    $resp->assertNotFound();
    $fresh = MetaPeriodo::withoutGlobalScopes()->find($periodo->id);
    expect((float) $fresh->valor_alvo)->toBe(1000.0);
});

it('destroy cross-tenant: DELETE período de meta biz=99 → 404 e período SOBREVIVE', function () {
    $periodo = periodoCru($this->metaFicticia->id);

    $resp = $this->delete("/ia/metas/{$this->metaFicticia->id}/periodos/{$periodo->id}");

    $resp->assertNotFound();
    expect(MetaPeriodo::withoutGlobalScopes()->find($periodo->id))->not->toBeNull();
});
