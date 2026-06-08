<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Repair\Entities\JobSheet;
use Tests\TestCase;

uses(TestCase::class);

/**
 * US-REPA-002 — Pest snapshot pós-refactor Caminho A (vocabulário shared).
 *
 * Risco principal: mudança de keys (`plate` → `code`, `vehicle` → `item`,
 * `km` → `usage_meter`, `mecanico` → `executor`, `box` → `slot`,
 * `elevador` → `area`, `aprovacao_pendente` → `pending_approval`,
 * `orcamento_*` → `quote_*`) é silenciosa pra TypeScript se sub-componente
 * acessar key legacy. Este snapshot é a rede de proteção.
 *
 * @see memory/decisions/proposals/drafts/repair-shared-refactor/MIGRATION_NOTES.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P8
 *
 * IMPORTANTE Felipe: rode local com `vendor/bin/pest Modules/Repair/Tests/Feature/ProducaoOficinaRefactorTest.php`.
 * Wagner regra 2026-05-09: mudanças tenancy/scope/Controller/Model/migration multi-tenant
 * exigem Pest verde local antes do merge — esta sessão IA NÃO executa Pest.
 */

// Guard SQLite: beforeEach usa App\User::where('business_id',1)->first() e todos os tests
// fazem $this->get('/repair/producao-oficina') que requer schema MySQL UltimatePOS completo.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Repair/ProducaoOficina requer schema MySQL UltimatePOS (Wagner Pest local segue mandatory — ADR 0101)');
    }

    // ADR 0101 — biz=1 (Wagner WR2), NUNCA biz=4 (cliente ROTA LIVRE).
    // Se actingAsBusinessUser não existir como helper, substituir por:
    //   $user = \App\User::where('business_id', 1)->first();
    //   $this->actingAs($user);
    if (method_exists($this, 'actingAsBusinessUser')) {
        $this->actingAsBusinessUser(businessId: 1);
    } else {
        $user = \App\User::where('business_id', 1)->first();
        if ($user) {
            $this->actingAs($user);
        }
    }
});

it('returns shared-vocabulary keys in mock columns', function () {
    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->component('Repair/ProducaoOficina/Index')
        ->has('columns.0.cards.0', fn ($card) => $card
            ->has('code')              // antes: 'plate'
            ->has('item')              // antes: 'vehicle'
            ->has('brand')             // mantido (já genérico no BD)
            ->has('usage_meter')       // antes: 'km'
            ->has('usage_unit')        // novo: complemento de usage_meter
            ->has('executor')          // antes: 'mecanico'
            ->has('executor_initials') // antes: 'mecanico_initials'
            ->etc()
        )
        ->has('slot_config')
        ->has('label_overrides')
    );
});

it('returns pending_approval (renamed from aprovacao_pendente)', function () {
    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->where('totals.pending_approval', 3) // antes: 'totals.aguardando_aprovacao'
    );
});

it('exposes default slot_config when business.repair_settings is null', function () {
    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->where('slot_config.0.label', 'Box')
        ->where('slot_config.0.options.0', 'B1')
        ->where('slot_config.1.label', 'Elevador')
        ->where('slot_config.1.options.0', 'E1')
    );
});

it('uses configured slot_config when business.repair_settings has slots', function () {
    \App\Business::where('id', 1)->update([
        'repair_settings' => json_encode([
            'slots' => [
                ['key' => 'slot', 'label' => 'Bancada', 'options' => ['BC1', 'BC2']],
            ],
            'labels' => ['executor' => 'Designer'],
        ]),
    ]);

    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->where('slot_config.0.label', 'Bancada')
        ->where('slot_config.0.options.0', 'BC1')
        ->where('label_overrides.executor', 'Designer')
    );

    // Cleanup pro próximo test
    \App\Business::where('id', 1)->update(['repair_settings' => null]);
});

it('move endpoint preserves business_id scope (multi-tenant Tier 0)', function () {
    // Cria JobSheet em business diferente — endpoint /move NÃO pode permitir
    // mutação cross-business (ADR 0093 Tier 0 IRREVOGÁVEL).
    if (! class_exists(\Database\Factories\Modules\Repair\Entities\JobSheetFactory::class)
        && ! method_exists(JobSheet::class, 'factory')) {
        // Sem factory configurada, skip — Felipe configura depois.
        $this->markTestSkipped('JobSheetFactory pendente — config Felipe local.');
        return;
    }

    $jobSheetOtherBiz = JobSheet::factory()->create(['business_id' => 99]);
    $originalStatusId = $jobSheetOtherBiz->status_id;

    $response = $this->post("/repair/producao-oficina/{$jobSheetOtherBiz->id}/move", [
        'column' => 'em-execucao',
    ]);

    // Esperado: 403 OU redirect com error session
    expect(in_array($response->status(), [403, 302, 404], true))->toBeTrue();
    expect($jobSheetOtherBiz->fresh()->status_id)->toBe($originalStatusId);
});

/**
 * GUARD anti-regressão vocabulário automotivo.
 * Se alguém reintroduzir 'plate'/'vehicle'/'km'/'mecanico' no shape Card,
 * este test falha em CI antes do merge.
 */
it('mock columns NÃO contém vocabulário automotivo legacy', function () {
    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->has('columns.0.cards.0', fn ($card) => $card
            ->missing('plate')
            ->missing('vehicle')
            ->missing('km')
            ->missing('mecanico')
            ->missing('mecanico_initials')
            ->missing('aprovacao_pendente')
            ->missing('orcamento_total')
            ->missing('orcamento_pecas')
            ->missing('orcamento_status')
            ->missing('box')
            ->missing('elevador')
            ->etc()
        )
    );
});
