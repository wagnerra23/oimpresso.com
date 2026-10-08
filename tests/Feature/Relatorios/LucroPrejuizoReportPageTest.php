<?php

declare(strict_types=1);
// Cobre UC-RLP-01, UC-RLP-02 (resources/js/Pages/Relatorios/LucroPrejuizo/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor por três caminhos: HTML do partial da Blade × props da Page × soma direta em transactions, mais a fórmula do
// lucro líquido. Local próprio do teste e janela em 2099-06. Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['profit_loss_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    $this->periodo = ['start_date' => '2099-06-01', 'end_date' => '2099-06-30'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function rlpTransacao(int $businessId, int $local, int $criador, string $tipo, float $total, float $frete = 0): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => $tipo, 'status' => 'final', 'payment_status' => 'paid',
        'transaction_date' => '2099-06-10 10:00:00', 'final_total' => $total + $frete, 'total_before_tax' => $total,
        'shipping_charges' => $frete, 'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'RLP-'.uniqid(),
        'invoice_no' => 'RLP-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** O número que o partial da Blade escreve logo depois do rótulo (o primeiro span de moeda após ele). */
function rlpNaBlade(string $html, string $rotulo): float
{
    expect(preg_match('/'.preg_quote($rotulo, '/').'.*?<span class="display_currency"[^>]*>\s*(-?[0-9.]+)/s', $html, $m))->toBe(1);

    return (float) $m[1];
}

function rlpTela($teste, array $filtros): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/profit-loss?'.http_build_query(['tela' => 'nova'] + $filtros));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/LucroPrejuizo/Index');

    return $r->json('props');
}

test('UC-RLP-01 valor — os números da tela = HTML do partial da Blade = soma direta; lucro líquido pela fórmula', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RLP-'.uniqid());
    $venda = rlpTransacao($this->business->id, $local, $this->user->id, 'sell', 100, 10);
    $despesa = rlpTransacao($this->business->id, $local, $this->user->id, 'expense', 30);
    $filtros = ['location_id' => $local] + $this->periodo;

    // Caminho 1: o HTML que o partial da Blade devolve no ajax.
    $blade = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/reports/profit-loss?'.http_build_query($filtros));
    $blade->assertOk();
    $html = (string) $blade->getContent();

    // Caminho 2: a Page.
    $props = rlpTela($this, $filtros);
    $valor = fn (array $lista, string $rotulo) => (float) collect($lista)->firstWhere('rotulo', $rotulo)['valor'];
    $tela = [
        'vendas' => $valor($props['direita'], 'Total de vendas (sem imposto e desconto)'),
        'frete' => $valor($props['direita'], 'Frete das vendas'),
        'despesas' => $valor($props['esquerda'], 'Total de despesas'),
        'bruto' => (float) $props['resultado']['lucro_bruto'],
        'liquido' => (float) $props['resultado']['lucro_liquido'],
    ];
    expect($tela)->toEqual([
        'vendas' => rlpNaBlade($html, __('home.total_sell')),
        'frete' => rlpNaBlade($html, __('lang_v1.total_sell_shipping_charge')),
        'despesas' => rlpNaBlade($html, __('report.total_expense')),
        'bruto' => rlpNaBlade($html, __('lang_v1.gross_profit').':'),
        'liquido' => rlpNaBlade($html, __('report.net_profit').':'),
    ]);

    // Caminho 3: soma direta nas transações do local.
    expect([$tela['vendas'], $tela['frete'], $tela['despesas']])->toEqual([
        (float) DB::table('transactions')->where('id', $venda)->value('total_before_tax'),
        (float) DB::table('transactions')->where('id', $venda)->value('shipping_charges'),
        (float) DB::table('transactions')->where('id', $despesa)->value('final_total'),
    ]);

    // Fórmula do lucro líquido (getProfitLossDetails): lucro bruto + frete das vendas 10 − despesas 30.
    expect($tela['liquido'])->toEqual($tela['bruto'] + 10 - 30);
    expect([$tela['vendas'], $tela['frete'], $tela['despesas']])->toEqual([100.0, 10.0, 30.0]);
});

test('UC-RLP-02 Tier 0 — despesa do negócio 99 não entra; permissão da Blade', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RLP-'.uniqid());
    rlpTransacao($this->business->id, $local, $this->user->id, 'expense', 30);

    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $localAlheio = EstoqueFixture::locationId($alheio->id, '-RLP-'.uniqid());
    rlpTransacao($alheio->id, $localAlheio, $donoAlheio, 'expense', 777);

    // Sem filtro de local: só a despesa do 98 na janela (30), nunca a de 777 do 99.
    $props = rlpTela($this, $this->periodo);
    expect((float) collect($props['esquerda'])->firstWhere('rotulo', 'Total de despesas')['valor'])->toEqual(30.0);
    // Pedindo o local do 99: nada dele aparece.
    $props = rlpTela($this, ['location_id' => $localAlheio] + $this->periodo);
    expect((float) collect($props['esquerda'])->firstWhere('rotulo', 'Total de despesas')['valor'])->toEqual(0.0);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/profit-loss?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/profit-loss')->assertForbidden();
});
