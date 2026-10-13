<?php

declare(strict_types=1);
// Cobre UC-REQC-01, UC-REQC-02, UC-REQC-03, UC-REQC-04, UC-REQC-05 (PurchaseRequisition/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread compras/playbook/07, F3 — /purchase-requisition em Inertia atrás da flag useV2ComprasRequisicoes.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * O SQL é o mesmo da DataTable (PurchaseRequisitionController::consultaRequisicoes): RequisicoesCompraBaselineTest
 * trava a Blade. O "necessário até" é provado igual à coluna da DataTable para a mesma requisição.
 *
 * `withHeaders` persiste entre requisições do mesmo teste: antes de ler a DataTable depois de uma visita Inertia,
 * `flushHeaders()` (lição do run 38404610982, thread 06).
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). NUNCA biz=4.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-REQC');
    $this->user = $this->usuarioComPermissoes(['purchase_requisition.view_all', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    reqContratoSessao($this, $this->user);
});

/** Sessão como o SetSessionData monta: as datas leem `business.*`. */
function reqContratoSessao($teste, $user): void
{
    session([
        'user.id' => $user->id, 'user.business_id' => $teste->business->id,
        'business' => ['id' => $teste->business->id, 'quantity_precision' => 2, 'date_format' => 'd/m/Y', 'time_format' => 24],
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

function reqContratoRequisicao(int $businessId, int $localId, int $criadoPor, array $campos = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $businessId, 'location_id' => $localId, 'created_by' => $criadoPor,
        'type' => 'purchase_requisition', 'status' => 'ordered',
        'ref_no' => 'RQ-'.uniqid(), 'transaction_date' => now(), 'delivery_date' => null,
        'total_before_tax' => 0, 'final_total' => 0, 'tax_amount' => 0, 'discount_amount' => 0,
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

/** Prop deferida `requisicoes` (linhas por `ref` + kpis). */
function reqContratoLista($teste, string $query = ''): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'PurchaseRequisition/Index', 'X-Inertia-Partial-Data' => 'requisicoes',
    ])->get('/purchase-requisition'.($query !== '' ? '?'.$query : ''));
    $r->assertOk();
    $p = $r->json('props.requisicoes') ?? ['linhas' => [], 'kpis' => []];

    return ['linhas' => collect($p['linhas'])->keyBy('ref'), 'kpis' => $p['kpis']];
}

function reqContratoFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2ComprasRequisicoes' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-REQC-01 com a flag ligada, GET /purchase-requisition renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasRequisicoes']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/purchase-requisition');

    $r->assertOk();
    expect($r->json('component'))->toBe('PurchaseRequisition/Index');
});

test('UC-REQC-02 com a flag desligada, ou com ?legacy=1, GET /purchase-requisition segue na Blade', function () {
    reqContratoFlagDesligada();
    $this->get('/purchase-requisition')->assertOk()->assertViewIs('purchase_requisition.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2ComprasRequisicoes']);
    $this->get('/purchase-requisition?legacy=1')->assertOk()->assertViewIs('purchase_requisition.index');
});

test('UC-REQC-03 Tier 0 — minhas requisições, com o mesmo "necessário até" da Blade', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasRequisicoes']);
    $outro = $this->seededSupportClientTenant();
    $minha = 'RQ-MINHA-'.uniqid();
    $pedido = 'PO-'.uniqid();
    $alheia = 'RQ-ALHEIA-'.uniqid();
    reqContratoRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $minha, 'delivery_date' => '2031-03-10 14:30:00']);
    reqContratoRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $pedido, 'type' => 'purchase_order']);
    reqContratoRequisicao($outro->id, EstoqueFixture::locationId($outro->id, '-REQC'), $this->user->id, ['ref_no' => $alheia]);

    $linhas = reqContratoLista($this)['linhas'];

    expect($linhas->has($minha))->toBeTrue();     // controle positivo
    expect($linhas->has($pedido))->toBeFalse();
    expect($linhas->has($alheia))->toBeFalse();
    expect($linhas[$minha]['necessario_ate'])->toStartWith('10/03/2031');

    // Segundo caminho: a coluna da DataTable da Blade para a mesma requisição.
    $this->flushHeaders();
    $blade = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/purchase-requisition');
    $coluna = collect($blade->json('data'))->first(fn ($l) => strip_tags((string) $l['ref_no']) === $minha);
    expect($coluna)->not->toBeNull();
    expect($linhas[$minha]['necessario_ate'])->toBe(trim(strip_tags((string) $coluna['delivery_date'])));
});

test('UC-REQC-04 Tier 0 — sem acesso a todos os locais, só as requisições dos locais permitidos', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasRequisicoes']);
    $outroLocal = EstoqueFixture::locationId($this->business->id, '-REQC2');
    $permitida = 'RQ-LOC-OK-'.uniqid();
    $vetada = 'RQ-LOC-NAO-'.uniqid();
    reqContratoRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $permitida]);
    reqContratoRequisicao($this->business->id, $outroLocal, $this->user->id, ['ref_no' => $vetada]);

    // `permitted_locations()` lê as permissões DIRETAS do usuário, não as do papel.
    $restrito = $this->usuarioComPermissoes(['purchase_requisition.view_all'], $this->business);
    \Spatie\Permission\Models\Permission::findOrCreate("location.{$this->local}", 'web');
    $restrito->givePermissionTo("location.{$this->local}");
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $restrito = \App\User::findOrFail($restrito->id);
    $this->actingAs($restrito);
    reqContratoSessao($this, $restrito);

    $linhas = reqContratoLista($this)['linhas'];

    expect($linhas->has($permitida))->toBeTrue();
    expect($linhas->has($vetada))->toBeFalse();
});

test('UC-REQC-05 o filtro de status chega ao servidor e os KPIs contam pelo status', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasRequisicoes']);
    $ordenada = 'RQ-ORD-'.uniqid();
    $parcial = 'RQ-PARC-'.uniqid();
    $concluida = 'RQ-CONC-'.uniqid();
    reqContratoRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $ordenada, 'status' => 'ordered']);
    reqContratoRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $parcial, 'status' => 'partial']);
    reqContratoRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $concluida, 'status' => 'completed']);

    $filtrada = reqContratoLista($this, 'status=partial')['linhas'];
    expect($filtrada->has($parcial))->toBeTrue();
    expect($filtrada->has($ordenada))->toBeFalse();
    expect($filtrada->has($concluida))->toBeFalse();

    $toda = reqContratoLista($this);
    expect($toda['linhas']->has($concluida))->toBeTrue();
    expect($toda['kpis']['sem_pedido'])->toBe($toda['linhas']->where('status', 'ordered')->count());
    expect($toda['kpis']['parciais'])->toBe($toda['linhas']->where('status', 'partial')->count());
});
