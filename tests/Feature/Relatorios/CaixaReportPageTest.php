<?php

declare(strict_types=1);
// Cobre UC-RCX-01, UC-RCX-02, UC-RCX-03 (resources/js/Pages/Relatorios/Caixa/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor por três caminhos: JSON do DataTable da Blade × props da Page × soma direta em
// cash_register_transactions, mais a conta à mão. Operador e janela (2031) próprios do teste —
// NÃO 2099: cash_registers.created_at é TIMESTAMP, que no MySQL vai só até 2038 (2099 vira
// 0000-00-00 e some do filtro; o MariaDB do CT 100 aceita, por isso lá passava),
// filtrados por user_id como a Blade faz. Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

const RCX_INICIO = '2031-10-01';
const RCX_FIM = '2031-10-31';

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['register_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    // As colunas de valor do DataTable da Blade formatam com Util::num_f, que lê a moeda da sessão.
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
});

/** Um caixa do operador, aberto em 2031-10-02 às 10:$minuto, com os recebimentos dados. */
function rcxCaixa(int $businessId, int $operador, int $minuto, array $recebimentos): int
{
    $caixa = DB::table('cash_registers')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'user_id' => $operador,
        'status' => 'close', 'closed_at' => sprintf('2031-10-02 18:%02d:00', $minuto), 'total_card_slips' => 0, 'total_cheques' => 0,
        'created_at' => sprintf('2031-10-02 10:%02d:00', $minuto), 'updated_at' => now(),
    ]);
    foreach ($recebimentos as [$forma, $valor, $tipo]) {
        DB::table('cash_register_transactions')->insert([
            'cash_register_id' => $caixa, 'amount' => $valor, 'pay_method' => $forma, 'type' => 'credit',
            'transaction_type' => $tipo, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $caixa;
}

function rcxPage($teste, int $operador, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/register-report?'.http_build_query([
        'tela' => 'nova', 'user_id' => $operador, 'start_date' => RCX_INICIO, 'end_date' => RCX_FIM, 'page' => $pagina,
    ]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Caixa/Index');

    return $r->json('props');
}

test('UC-RCX-01 valor — valores por forma e total = JSON do DataTable da Blade = soma direta', function () {
    $operador = \App\User::factory()->create(['business_id' => $this->business->id])->id;
    $caixa = rcxCaixa($this->business->id, $operador, 0, [
        ['cash', 100, 'sell'], ['card', 50, 'sell'], ['custom_pay_1', 30, 'sell'],
        ['cash', 999, 'refund'], // controle: só recebimento de venda entra
    ]);

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas do report.js.
    $nomes = ['created_at', 'closed_at', 'location_name', 'user_name', 'total_card_payment', 'total_cheque_payment', 'total_cash_payment',
        'total_bank_transfer_payment', 'total_advance_payment', 'total_custom_pay_1', 'total_custom_pay_2', 'total_custom_pay_3',
        'total_custom_pay_4', 'total_custom_pay_5', 'total_custom_pay_6', 'total_custom_pay_7', 'total_other_payment', 'total', 'action'];
    $colunas = [];
    foreach ($nomes as $i => $c) {
        $colunas[$i] = ['data' => $c, 'name' => $c === 'location_name' ? 'bl.name' : $c, 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/register-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'],
            'user_id' => $operador, 'start_date' => RCX_INICIO, 'end_date' => RCX_FIM]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    $linhaBlade = collect($dt->json('data'))->firstWhere('id', $caixa);
    expect($linhaBlade)->not->toBeNull();
    $orig = fn (string $campo) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $linhaBlade[$campo], $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $props = rcxPage($this, $operador);
    $linhaPage = collect($props['linhas'])->firstWhere('id', $caixa);
    expect((float) $linhaPage['valores']['cash'])->toEqual($orig('total_cash_payment'));
    expect((float) $linhaPage['valores']['card'])->toEqual($orig('total_card_payment'));
    expect((float) $linhaPage['valores']['custom_pay_1'])->toEqual($orig('total_custom_pay_1'));
    expect((float) $linhaPage['total'])->toEqual($orig('total'));

    // Caminho 3: soma direta dos recebimentos de venda do caixa.
    $direto = (float) DB::table('cash_register_transactions')->where('cash_register_id', $caixa)->where('transaction_type', 'sell')->sum('amount');
    expect((float) $linhaPage['total'])->toEqual($direto);

    // Conta à mão: 100 + 50 + 30 = 180 (os 999 de devolução ficam fora), nos dois lados.
    expect($orig('total'))->toEqual(180.0);
    expect((float) $linhaPage['valores']['cash'])->toEqual(100.0);
});

test('UC-RCX-02 — 25 por página do mais antigo ao mais novo; rodapé só da página', function () {
    $operador = \App\User::factory()->create(['business_id' => $this->business->id])->id;
    for ($i = 0; $i < 26; $i++) {
        rcxCaixa($this->business->id, $operador, $i, [['cash', 10 + $i, 'sell']]); // o mais antigo vale 10
    }

    $p1 = rcxPage($this, $operador, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect((float) $p1['linhas'][0]['total'])->toEqual(10.0);
    expect((float) $p1['rodape']['cash'])->toEqual((float) array_sum(range(10, 34)));
    expect((float) $p1['rodape']['total'])->toEqual((float) array_sum(range(10, 34)));

    $p2 = rcxPage($this, $operador, 2);
    expect($p2['linhas'])->toHaveCount(1);
    expect((float) $p2['linhas'][0]['total'])->toEqual(35.0);
});

test('UC-RCX-03 Tier 0 — caixa do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $operadorAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    rcxCaixa($alheio->id, $operadorAlheio, 0, [['cash', 77, 'sell']]);

    $props = rcxPage($this, $operadorAlheio);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['usuarios'])->pluck('id'))->not->toContain($operadorAlheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/register-report?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/register-report')->assertForbidden();
});
