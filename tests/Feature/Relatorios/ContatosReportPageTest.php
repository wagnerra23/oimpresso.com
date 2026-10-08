<?php

declare(strict_types=1);
// Cobre UC-RCT-01, UC-RCT-02, UC-RCT-03 (resources/js/Pages/Relatorios/Contatos/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor por três caminhos: JSON do DataTable da Blade × props da Page × soma direta em
// transactions/transaction_payments. Contatos, grupo e janela (2099, só colunas DATETIME) próprios do
// teste. Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

const RCT_INICIO = '2099-11-01';
const RCT_FIM = '2099-11-30';

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['contacts_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    // As colunas de valor do DataTable da Blade formatam com @format_currency / num_f, que leem a moeda da sessão.
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.currency_symbol_placement' => 'before']);
});

function rctContato(int $businessId, int $criador, string $nome, ?int $grupo = null): int
{
    return DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => $nome, 'mobile' => '0', 'contact_status' => 'active',
        'customer_group_id' => $grupo, 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Transação do contato no dia 10 da janela, com pagamento opcional. */
function rctTransacao(int $businessId, int $criador, int $contato, string $tipo, float $valor, float $pago = 0, ?string $subtipo = null): void
{
    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => $tipo,
        'sub_type' => $subtipo, 'status' => 'final', 'payment_status' => 'partial', 'contact_id' => $contato,
        'transaction_date' => '2099-11-10 12:00:00', 'final_total' => $valor, 'total_before_tax' => $valor,
        'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'RCT-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($pago > 0) {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $tx, 'business_id' => $businessId, 'amount' => $pago, 'method' => 'cash', 'is_return' => 0,
            'paid_on' => '2099-11-10 12:00:00', 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function rctPage($teste, array $filtro, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/customer-supplier?'.http_build_query(
        ['tela' => 'nova', 'start_date' => RCT_INICIO, 'end_date' => RCT_FIM, 'page' => $pagina] + $filtro
    ));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Contatos/Index');

    return $r->json('props');
}

test('UC-RCT-01 valor — vendas, devoluções e devido = JSON do DataTable da Blade = soma direta', function () {
    $cliente = rctContato($this->business->id, $this->user->id, 'RCT Cliente '.uniqid());
    rctTransacao($this->business->id, $this->user->id, $cliente, 'sell', 500, 200);
    rctTransacao($this->business->id, $this->user->id, $cliente, 'sell_return', 50, 50);
    rctTransacao($this->business->id, $this->user->id, $cliente, 'ledger_discount', 20, 0, 'sell_discount');

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas do report.js.
    $colunas = [];
    foreach (['name', 'total_purchase', 'total_purchase_return', 'total_invoice', 'total_sell_return', 'opening_balance_due', 'due'] as $i => $c) {
        $colunas[$i] = ['data' => $c, 'name' => $c, 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/customer-supplier?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'],
            'contact_id' => $cliente, 'start_date' => RCT_INICIO, 'end_date' => RCT_FIM]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $linhaBlade = $dt->json('data.0');
    $orig = fn (string $campo) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $linhaBlade[$campo], $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $linhaPage = rctPage($this, ['contact_id' => $cliente])['linhas'][0];
    expect((float) $linhaPage['vendas'])->toEqual($orig('total_invoice'));
    expect((float) $linhaPage['devolucoes_venda'])->toEqual($orig('total_sell_return'));
    expect((float) $linhaPage['devido'])->toEqual($orig('due'));

    // Caminho 3: soma direta (venda − recebido) − (devolução − devolvido), sem a consulta do controller.
    $soma = fn (string $tipo) => (float) DB::table('transactions')->where('contact_id', $cliente)->where('type', $tipo)->sum('final_total');
    $pago = fn (string $tipo) => (float) DB::table('transaction_payments as tp')->join('transactions as t', 't.id', '=', 'tp.transaction_id')
        ->where('t.contact_id', $cliente)->where('t.type', $tipo)->sum('tp.amount');
    $devidoDireto = ($soma('sell') - $pago('sell')) - ($soma('sell_return') - $pago('sell_return'));
    expect((float) $linhaPage['devido'])->toEqual($devidoDireto);

    // Conta à mão: (500 − 200) − (50 − 50) = 300. O desconto de razão de venda de 20 NÃO entra — é a
    // conta que está em produção (RUNBOOK §4, decisão [W]); se alguém "consertar" calado, isto cai.
    expect((float) $linhaPage['devido'])->toEqual(300.0);
    expect($orig('due'))->toEqual(300.0);
});

test('UC-RCT-02 — 25 por página em ordem de nome; rodapé só da página', function () {
    $grupo = DB::table('customer_groups')->insertGetId([
        'business_id' => $this->business->id, 'name' => 'RCT-G-'.uniqid(), 'amount' => 0, 'created_by' => $this->user->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    for ($i = 0; $i < 26; $i++) {
        $c = rctContato($this->business->id, $this->user->id, sprintf('RCT %02d', $i), $grupo);
        rctTransacao($this->business->id, $this->user->id, $c, 'sell', 10 + $i);
    }

    $p1 = rctPage($this, ['customer_group_id' => $grupo], 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['nome'])->toBe('RCT 00');
    expect((float) $p1['rodape']['vendas'])->toEqual((float) array_sum(range(10, 34)));

    $p2 = rctPage($this, ['customer_group_id' => $grupo], 2);
    expect($p2['linhas'])->toHaveCount(1);
    expect($p2['linhas'][0]['nome'])->toBe('RCT 25');
});

test('UC-RCT-03 Tier 0 — contato do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $contatoAlheio = rctContato($alheio->id, $donoAlheio, 'RCT Alheio '.uniqid());
    rctTransacao($alheio->id, $donoAlheio, $contatoAlheio, 'sell', 77);

    $props = rctPage($this, ['contact_id' => $contatoAlheio]);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['contatos'])->pluck('id'))->not->toContain($contatoAlheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/customer-supplier?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/customer-supplier')->assertForbidden();
});
