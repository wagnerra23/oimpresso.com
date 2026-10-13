<?php

declare(strict_types=1);
// Cobre UC-DEVC-01, UC-DEVC-02, UC-DEVC-03, UC-DEVC-04, UC-DEVC-05 (PurchaseReturn/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread compras/playbook/08, F3 — /purchase-return em Inertia atrás da flag useV2ComprasDevolucoes.
 *
 * TELA DE VALOR (regra mestre): o UC-DEVC-03 prova total e devido por DOIS caminhos — a conta à mão sobre o fixture e o
 * `data-orig-value` das colunas da DataTable da Blade para a mesma devolução — e o texto pelo `num_f` do mesmo número.
 * O SQL é o mesmo (PurchaseReturnController::consultaDevolucoes); DevolucoesCompraBaselineTest trava a Blade.
 *
 * `withHeaders` persiste entre requisições do mesmo teste: antes de ler a DataTable depois de uma visita Inertia,
 * `flushHeaders()` (lição do run 38404610982, thread 06).
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). NUNCA biz=4.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('transaction_payments') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-DEVC');
    $this->user = $this->usuarioComPermissoes(['purchase.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    devContratoSessao($this, $this->user);
});

function devContratoSessao($teste, $user): void
{
    session([
        'user.id' => $user->id, 'user.business_id' => $teste->business->id,
        'business' => ['id' => $teste->business->id, 'quantity_precision' => 2, 'currency_precision' => 2, 'date_format' => 'd/m/Y', 'time_format' => 24],
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

function devContratoTransacao(int $businessId, int $localId, int $criadoPor, array $campos = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $businessId, 'location_id' => $localId, 'created_by' => $criadoPor,
        'type' => 'purchase_return', 'status' => 'final', 'payment_status' => 'due',
        'ref_no' => 'DC-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => 0,
        'final_total' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function devContratoPagamento(int $transacaoId, int $businessId, float $valor): void
{
    DB::table('transaction_payments')->insert([
        'transaction_id' => $transacaoId, 'business_id' => $businessId, 'amount' => $valor,
        'method' => 'cash', 'is_return' => 0, 'paid_on' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Prop deferida `devolucoes` (linhas por `ref` + contagem por pagamento). */
function devContratoLista($teste): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'PurchaseReturn/Index', 'X-Inertia-Partial-Data' => 'devolucoes',
    ])->get('/purchase-return');
    $r->assertOk();
    $p = $r->json('props.devolucoes') ?? ['linhas' => [], 'por_pagamento' => []];

    return ['linhas' => collect($p['linhas'])->keyBy('ref'), 'por_pagamento' => collect($p['por_pagamento'])];
}

/** O número cru da célula da DataTable (`data-orig-value`) — o mesmo que o rodapé da Blade soma. */
function devContratoValorDaCelula(string $celula): float
{
    expect(preg_match('/data-orig-value="([^"]*)"/', $celula, $m))->toBe(1);

    return round((float) $m[1], 2);
}

function devContratoFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2ComprasDevolucoes' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-DEVC-01 com a flag ligada, GET /purchase-return renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasDevolucoes']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/purchase-return');

    $r->assertOk();
    expect($r->json('component'))->toBe('PurchaseReturn/Index');
});

test('UC-DEVC-02 com a flag desligada, ou com ?legacy=1, GET /purchase-return segue na Blade', function () {
    devContratoFlagDesligada();
    $this->get('/purchase-return')->assertOk()->assertViewIs('purchase_return.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2ComprasDevolucoes']);
    $this->get('/purchase-return?legacy=1')->assertOk()->assertViewIs('purchase_return.index');
});

test('UC-DEVC-03 Tier 0 e valor — minhas devoluções, com o mesmo total e o mesmo devido da Blade', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasDevolucoes']);
    $outro = $this->seededSupportClientTenant();
    $paga = 'DC-PAGA-'.uniqid();
    $semPagamento = 'DC-SEMPG-'.uniqid();
    $compra = 'COMPRA-'.uniqid();
    $alheia = 'DC-ALHEIA-'.uniqid();
    $id = devContratoTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $paga, 'final_total' => 1770.50, 'payment_status' => 'partial']);
    devContratoPagamento($id, $this->business->id, 500.25);
    devContratoPagamento($id, $this->business->id, 270.25);
    devContratoTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $semPagamento, 'final_total' => 640.00]);
    devContratoTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $compra, 'type' => 'purchase', 'status' => 'received']);
    devContratoTransacao($outro->id, EstoqueFixture::locationId($outro->id, '-DEVC'), $this->user->id, ['ref_no' => $alheia]);

    $linhas = devContratoLista($this)['linhas'];

    expect($linhas->has($paga))->toBeTrue();      // controle positivo
    expect($linhas->has($semPagamento))->toBeTrue();
    expect($linhas->has($compra))->toBeFalse();
    expect($linhas->has($alheia))->toBeFalse();

    // Caminho 1: à mão. Devido = 1770,50 − (500,25 + 270,25) = 1000,00; sem pagamento, devido = total.
    expect(round((float) $linhas[$paga]['total'], 2))->toBe(1770.5);
    expect(round((float) $linhas[$paga]['devido'], 2))->toBe(round(1770.50 - (500.25 + 270.25), 2));
    expect(round((float) $linhas[$semPagamento]['devido'], 2))->toBe(640.0);

    // Caminho 2: o data-orig-value das colunas da DataTable da Blade para as mesmas devoluções.
    $this->flushHeaders();
    $blade = collect($this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/purchase-return')->json('data'))->keyBy(fn ($l) => strip_tags((string) $l['ref_no']));
    foreach ([$paga, $semPagamento] as $ref) {
        expect($blade->has($ref))->toBeTrue();
        expect(round((float) $linhas[$ref]['total'], 2))->toBe(devContratoValorDaCelula((string) $blade[$ref]['final_total']));
        expect(round((float) $linhas[$ref]['devido'], 2))->toBe(devContratoValorDaCelula((string) $blade[$ref]['payment_due']));
    }

    // Somas do rodapé: a Blade soma o data-orig-value das linhas. A tela React não mostra soma (decisão [W]), mas os
    // números que ela recebe somam o mesmo — à mão, na Blade e na React: total 2410,50 e devido 1640,00.
    $somaReact = fn (string $campo) => round(collect([$paga, $semPagamento])->sum(fn ($r) => (float) $linhas[$r][$campo]), 2);
    $somaBlade = fn (string $coluna) => round(collect([$paga, $semPagamento])->sum(fn ($r) => devContratoValorDaCelula((string) $blade[$r][$coluna])), 2);
    expect($somaReact('total'))->toBe(round(1770.50 + 640.00, 2));
    expect($somaReact('total'))->toBe($somaBlade('final_total'));
    expect($somaReact('devido'))->toBe(round(1000.00 + 640.00, 2));
    expect($somaReact('devido'))->toBe($somaBlade('payment_due'));

    // O texto é o num_f do mesmo número — a tela não formata valor.
    $util = new \App\Utils\Util;
    expect($linhas[$paga]['total_texto'])->toBe($util->num_f(1770.50, true));
    expect($linhas[$paga]['devido_texto'])->toBe($util->num_f(1000.00, true));
    expect($linhas[$paga]['devido_texto'])->toContain('1.000,00');
});

test('UC-DEVC-04 Tier 0 — sem acesso a todos os locais, só as devoluções dos locais permitidos', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasDevolucoes']);
    $outroLocal = EstoqueFixture::locationId($this->business->id, '-DEVC2');
    $permitida = 'DC-LOC-OK-'.uniqid();
    $vetada = 'DC-LOC-NAO-'.uniqid();
    devContratoTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $permitida]);
    devContratoTransacao($this->business->id, $outroLocal, $this->user->id, ['ref_no' => $vetada]);

    // `permitted_locations()` lê as permissões DIRETAS do usuário, não as do papel.
    $restrito = $this->usuarioComPermissoes(['purchase.view'], $this->business);
    \Spatie\Permission\Models\Permission::findOrCreate("location.{$this->local}", 'web');
    $restrito->givePermissionTo("location.{$this->local}");
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $restrito = \App\User::findOrFail($restrito->id);
    $this->actingAs($restrito);
    devContratoSessao($this, $restrito);

    $linhas = devContratoLista($this)['linhas'];

    expect($linhas->has($permitida))->toBeTrue();
    expect($linhas->has($vetada))->toBeFalse();
});

test('UC-DEVC-05 a contagem por status de pagamento bate com a lista', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasDevolucoes']);
    devContratoTransacao($this->business->id, $this->local, $this->user->id, ['payment_status' => 'due']);
    devContratoTransacao($this->business->id, $this->local, $this->user->id, ['payment_status' => 'paid']);
    devContratoTransacao($this->business->id, $this->local, $this->user->id, ['payment_status' => 'paid']);

    $lista = devContratoLista($this);
    $porRotulo = $lista['por_pagamento']->keyBy('rotulo');
    $recebido = __('lang_v1.received');

    expect($porRotulo->has($recebido))->toBeTrue();
    expect($porRotulo[$recebido]['quantidade'])->toBe($lista['linhas']->where('pagamento', 'paid')->count());
    expect($lista['por_pagamento']->sum('quantidade'))->toBe($lista['linhas']->count());
});
