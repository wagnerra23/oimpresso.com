<?php

declare(strict_types=1);
// Cobre UC-RIM-01, UC-RIM-02, UC-RIM-03, UC-RIM-04 (resources/js/Pages/Relatorios/Impostos/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Imposto por alíquota por três caminhos: JSON do DataTable da Blade × props da Page × conta à mão, inclusive a quebra
// do imposto composto. Contato próprio do teste (filtro da Blade). Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['tax_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    $this->periodo = ['start_date' => '2099-01-01', 'end_date' => '2099-12-31'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        'currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
});

function rimAliquota(int $businessId, int $criador, string $nome, float $valor, bool $composto = false, array $subs = []): int
{
    $id = DB::table('tax_rates')->insertGetId([
        'business_id' => $businessId, 'name' => $nome.' '.uniqid(), 'amount' => $valor, 'is_tax_group' => $composto ? 1 : 0,
        'for_tax_group' => 0, 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($subs as $sub) {
        DB::table('group_sub_taxes')->insert(['group_tax_id' => $id, 'tax_id' => $sub]);
    }

    return $id;
}

function rimContato(int $businessId, int $criador): int
{
    return DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'both', 'name' => 'RIM '.uniqid(), 'mobile' => '0',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Transação com imposto próprio opcional e itens [aliquota, imposto por unidade, quantidade, devolvido]. */
function rimTransacao(int $businessId, int $criador, int $contato, string $tipo, string $status, string $data, float $total, ?int $aliquota = null, float $imposto = 0, array $itens = []): int
{
    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => $tipo, 'status' => $status,
        'payment_status' => 'paid', 'contact_id' => $contato, 'transaction_date' => $data, 'final_total' => $total + $imposto,
        'total_before_tax' => $total, 'tax_id' => $aliquota, 'tax_amount' => $imposto, 'created_by' => $criador,
        'essentials_duration' => 0, 'ref_no' => 'RIM-'.uniqid(), 'invoice_no' => 'RIM-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($itens as [$aliq, $impostoItem, $qtd, $devolvido]) {
        $produto = EstoqueFixture::singleProduct($businessId);
        $comum = ['transaction_id' => $tx, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
            'quantity' => $qtd, 'quantity_returned' => $devolvido, 'tax_id' => $aliq, 'item_tax' => $impostoItem, 'created_at' => now(), 'updated_at' => now()];
        if ($tipo === 'purchase') {
            DB::table('purchase_lines')->insert($comum + ['purchase_price' => 1, 'purchase_price_inc_tax' => 1 + $impostoItem]);
        } else {
            DB::table('transaction_sell_lines')->insert($comum + ['unit_price' => 1, 'unit_price_inc_tax' => 1 + $impostoItem, 'unit_price_before_discount' => 1]);
        }
    }

    return $tx;
}

function rimPage($teste, string $tipo, int $contato, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/tax-details?'.http_build_query(['tela' => 'nova', 'tipo' => $tipo, 'contact_id' => $contato, 'page' => $pagina] + $teste->periodo));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Impostos/Index');

    return $r->json('props');
}

test('UC-RIM-01 valor — imposto por alíquota = JSON do DataTable da Blade = conta à mão (com o composto)', function () {
    $t10 = rimAliquota($this->business->id, $this->user->id, 'RIM 10%', 10);
    $t5 = rimAliquota($this->business->id, $this->user->id, 'RIM 5%', 5);
    $t15 = rimAliquota($this->business->id, $this->user->id, 'RIM 15%', 15);
    $comp = rimAliquota($this->business->id, $this->user->id, 'RIM 5+15', 20, true, [$t5, $t15]);
    $contato = rimContato($this->business->id, $this->user->id);
    rimTransacao($this->business->id, $this->user->id, $contato, 'purchase', 'received', '2099-03-10 10:00:00', 100, $t10, 5, [[$t10, 2, 3, 1], [$comp, 4, 1, 0]]);

    // Caminho 1: o JSON que o DataTable da aba entrada pede, com as colunas do tax_report.blade.php.
    $colunas = [];
    foreach ([['transaction_date', 'transaction_date'], ['ref_no', 'ref_no'], ['contact_name', 'c.name'], ['tax_number', 'c.tax_number'], ['total_before_tax', 'total_before_tax'], ['payment_methods', ''], ['discount_amount', 'discount_amount']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => $d === 'payment_methods' ? 'false' : 'true', 'orderable' => $d === 'payment_methods' ? 'false' : 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    foreach ([$t10, $t5, $t15, $comp] as $id) {
        $colunas[] = ['data' => 'tax_'.$id, 'name' => '', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/tax-details?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'type' => 'purchase', 'contact_id' => $contato, 'location_id' => ''] + $this->periodo));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $blade = [];
    foreach ([$t10, $t5, $t15, $comp] as $id) {
        $blade[$id] = (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $dt->json('data.0.tax_'.$id), $m) ? $m[1] : 0);
    }

    // Caminho 2: a Page.
    $props = rimPage($this, 'purchase', $contato);
    expect($props['linhas'])->toHaveCount(1);
    $page = [];
    foreach ([$t10, $t5, $t15, $comp] as $id) {
        $page[$id] = (float) $props['linhas'][0]['impostos'][(string) $id];
    }
    expect($page)->toEqual($blade);

    // Conta à mão: 10% = 2 × (3 − 1) + 5 da compra = 9; composto = 4 × 1 = 4, quebrado em 5% = 4 × 5/20 = 1 e
    // 15% = 4 × 15/20 = 3.
    expect($page)->toEqual([$t10 => 9.0, $t5 => 1.0, $t15 => 3.0, $comp => 4.0]);
    expect((float) $props['rodape']['impostos'][(string) $t10])->toEqual(9.0);
});

test('UC-RIM-02 — cada aba com o seu tipo de transação', function () {
    $t10 = rimAliquota($this->business->id, $this->user->id, 'RIM 10%', 10);
    $contato = rimContato($this->business->id, $this->user->id);
    rimTransacao($this->business->id, $this->user->id, $contato, 'purchase', 'received', '2099-03-10 10:00:00', 111, $t10, 1);
    rimTransacao($this->business->id, $this->user->id, $contato, 'purchase', 'pending', '2099-03-11 10:00:00', 999, $t10, 1);
    rimTransacao($this->business->id, $this->user->id, $contato, 'sell', 'final', '2099-03-12 10:00:00', 222, null, 0, [[$t10, 2, 1, 0]]);
    rimTransacao($this->business->id, $this->user->id, $contato, 'sell', 'draft', '2099-03-13 10:00:00', 888, null, 0, [[$t10, 2, 1, 0]]);
    rimTransacao($this->business->id, $this->user->id, $contato, 'expense', 'final', '2099-03-14 10:00:00', 333, $t10, 3);

    $totais = fn (string $tipo) => array_map(fn ($l) => (float) $l['total'], rimPage($this, $tipo, $contato)['linhas']);
    expect([$totais('purchase'), $totais('sell'), $totais('expense')])->toEqual([[111.0], [222.0], [333.0]]);
});

test('UC-RIM-03 — 25 por página na ordem da aba entrada (data crescente); rodapé só da página', function () {
    $t10 = rimAliquota($this->business->id, $this->user->id, 'RIM 10%', 10);
    $contato = rimContato($this->business->id, $this->user->id);
    for ($i = 0; $i < 26; $i++) {
        rimTransacao($this->business->id, $this->user->id, $contato, 'purchase', 'received', sprintf('2099-11-%02d 10:00:00', $i + 1), 10 + $i, $t10, 1);
    }

    $p1 = rimPage($this, 'purchase', $contato, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['data'])->toBe('01/11/2099 10:00');
    expect((float) $p1['rodape']['total'])->toEqual((float) array_sum(range(10, 34))); // sem o dia 26 (35), que cai na página 2

    $p2 = rimPage($this, 'purchase', $contato, 2);
    expect(array_map(fn ($l) => (float) $l['total'], $p2['linhas']))->toEqual([35.0]);
});

test('UC-RIM-04 Tier 0 — compra do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $t10Alheio = rimAliquota($alheio->id, $donoAlheio, 'RIM 10%', 10);
    $contatoAlheio = rimContato($alheio->id, $donoAlheio);
    rimTransacao($alheio->id, $donoAlheio, $contatoAlheio, 'purchase', 'received', '2099-03-10 10:00:00', 50, $t10Alheio, 5);

    $props = rimPage($this, 'purchase', $contatoAlheio);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['contatos'])->pluck('id'))->not->toContain($contatoAlheio);
    expect(collect($props['aliquotas'])->pluck('id'))->not->toContain((string) $t10Alheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/tax-details?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/tax-report')->assertForbidden();
});
