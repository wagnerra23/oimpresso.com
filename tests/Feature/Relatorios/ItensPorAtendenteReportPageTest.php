<?php

declare(strict_types=1);
// Cobre UC-RIA-01, UC-RIA-02, UC-RIA-03, UC-RIA-04 (resources/js/Pages/Relatorios/ItensPorAtendente/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Quantidade, desconto e total por três caminhos: JSON do DataTable da Blade × props da Page × conta direta em
// transaction_sell_lines, mais a conta à mão. Atendente próprio do teste (filtro da Blade). Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['sales_representative.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    $this->periodo = ['start_date' => '2099-12-01', 'end_date' => '2099-12-31'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24]);
});

/** Um item vendido: [quantidade, preço antes do desconto, tipo de desconto, desconto, imposto, preço com imposto]. */
function riaItem(int $businessId, int $criador, ?int $atendente, string $data, array $valores, string $status = 'final'): int
{
    $produto = EstoqueFixture::singleProduct($businessId);
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => 'sell', 'status' => $status,
        'payment_status' => 'paid', 'transaction_date' => $data, 'final_total' => 0, 'total_before_tax' => 0, 'created_by' => $criador,
        'essentials_duration' => 0, 'invoice_no' => 'RIA-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    [$qtd, $preco, $tipo, $desconto, $imposto, $comImposto] = $valores;

    return DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $venda, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
        'quantity' => $qtd, 'unit_price_before_discount' => $preco, 'unit_price' => $preco, 'line_discount_type' => $tipo,
        'line_discount_amount' => $desconto, 'item_tax' => $imposto, 'unit_price_inc_tax' => $comImposto,
        'res_service_staff_id' => $atendente, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function riaPage($teste, int $atendente, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/service-staff-line-orders?'.http_build_query(['tela' => 'nova', 'service_staff_id' => $atendente, 'page' => $pagina] + $teste->periodo));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/ItensPorAtendente/Index');

    return $r->json('props');
}

test('UC-RIA-01 valor — quantidade, desconto e total = JSON do DataTable da Blade = conta direta', function () {
    $atendente = \App\User::factory()->create(['business_id' => $this->business->id])->id;
    $item = riaItem($this->business->id, $this->user->id, $atendente, '2099-12-05 10:00:00', [3, 50, 'percentage', 10, 2, 47]);
    riaItem($this->business->id, $this->user->id, $atendente, '2099-12-06 10:00:00', [9, 50, 'fixed', 0, 0, 50], 'draft');
    riaItem($this->business->id, $this->user->id, null, '2099-12-07 10:00:00', [9, 50, 'fixed', 0, 0, 50]);

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas do service_staff_report.blade.php.
    $colunas = [];
    foreach ([['transaction_date', 't.transaction_date'], ['invoice_no', 't.invoice_no'], ['service_staff', 'ss.first_name'], ['product_name', 'p.name'], ['quantity', 'quantity'], ['unit_price_before_discount', 'unit_price_before_discount'], ['line_discount_amount', 'line_discount_amount'], ['item_tax', 'item_tax'], ['unit_price_inc_tax', 'unit_price_inc_tax'], ['total', '']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => $d === 'total' ? 'false' : 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/service-staff-line-orders?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 0, 'dir' => 'desc']], 'search' => ['value' => '', 'regex' => 'false'], 'service_staff_id' => $atendente, 'location_id' => ''] + $this->periodo));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $orig = fn (string $c) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $dt->json('data.0.'.$c), $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $props = riaPage($this, $atendente);
    expect($props['linhas'])->toHaveCount(1);
    $linha = $props['linhas'][0];
    expect([(float) $linha['quantidade'], (float) $linha['desconto'], (float) $linha['total']])
        ->toEqual([$orig('quantity'), $orig('line_discount_amount'), $orig('total')]);

    // Caminho 3: conta direta no item.
    $l = DB::table('transaction_sell_lines')->where('id', $item)->first();
    expect([(float) $linha['desconto'], (float) $linha['total']])
        ->toEqual([(float) ($l->unit_price_before_discount * $l->line_discount_amount / 100), (float) ($l->unit_price_inc_tax * $l->quantity)]);

    // Conta à mão: desconto 50 × 10% = 5; total 47 × 3 = 141. O rascunho e o item sem atendente não aparecem.
    expect([(float) $linha['quantidade'], (float) $linha['desconto'], (float) $linha['total'], (float) $props['rodape']['total']])->toEqual([3.0, 5.0, 141.0, 141.0]);
});

test('UC-RIA-02 — 25 por página pela data decrescente; rodapé só da página', function () {
    $atendente = \App\User::factory()->create(['business_id' => $this->business->id])->id;
    for ($i = 0; $i < 26; $i++) {
        riaItem($this->business->id, $this->user->id, $atendente, sprintf('2099-12-%02d 10:00:00', $i + 1), [1, 10 + $i, 'fixed', 0, 0, 10 + $i]);
    }

    $p1 = riaPage($this, $atendente, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['data'])->toBe('26/12/2099');
    expect((float) $p1['rodape']['total'])->toEqual((float) array_sum(range(11, 35))); // sem o dia 1 (10), que cai na página 2

    $p2 = riaPage($this, $atendente, 2);
    expect(array_column($p2['linhas'], 'data'))->toBe(['01/12/2099']);
});

test('UC-RIA-03 Tier 0 — item do negócio 99 não aparece; permissão da página da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $atendenteAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    riaItem($alheio->id, $atendenteAlheio, $atendenteAlheio, '2099-12-05 10:00:00', [2, 30, 'fixed', 0, 0, 30]);

    expect(riaPage($this, $atendenteAlheio)['linhas'])->toBe([]);

    // Sem a permissão: 403 na tela nova e na página da Blade que mostra esta aba.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/service-staff-line-orders?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/service-staff-report')->assertForbidden();
});

test('UC-RIA-04 Tier 0 — endpoint com a permissão da página e só os locais permitidos', function () {
    $sufixo = uniqid();
    $localA = EstoqueFixture::locationId($this->business->id, '-RIA-A-'.$sufixo);
    $localB = EstoqueFixture::locationId($this->business->id, '-RIA-B-'.$sufixo);
    $atendente = \App\User::factory()->create(['business_id' => $this->business->id])->id;
    foreach ([[$localA, 'RIA-A-'.$sufixo], [$localB, 'RIA-B-'.$sufixo]] as [$local, $numero]) {
        $produto = EstoqueFixture::singleProduct($this->business->id);
        $venda = DB::table('transactions')->insertGetId([
            'business_id' => $this->business->id, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
            'transaction_date' => '2099-12-05 10:00:00', 'final_total' => 0, 'total_before_tax' => 0, 'created_by' => $this->user->id,
            'essentials_duration' => 0, 'invoice_no' => $numero, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $venda, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
            'quantity' => 1, 'unit_price_before_discount' => 10, 'unit_price' => 10, 'line_discount_amount' => 0, 'item_tax' => 0,
            'unit_price_inc_tax' => 10, 'res_service_staff_id' => $atendente, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Vendas deste teste que o usuário vê: [no JSON da Blade, na tela nova]; o JSON pode responder 403. */
    $visto = function (array $permissoes, array $locais) use ($atendente): array {
        $u = $this->usuarioComPermissoes($permissoes, $this->business);
        // Local é permissão DIRETA no usuário — é só $user->permissions que o User::permitted_locations lê.
        foreach ($locais as $local) {
            $u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('location.'.$local, 'web'));
        }
        $this->actingAs($u);
        session(['user.business_id' => $this->business->id, 'user.id' => $u->id, 'business.id' => $this->business->id]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('/reports/service-staff-line-orders?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1,
                'columns' => [['data' => 'invoice_no', 'name' => 't.invoice_no', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']]],
                'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'service_staff_id' => $atendente] + $this->periodo));
        if ($json->status() === 403) {
            return ['403', null];
        }
        $json->assertOk();
        $tela = $this->withHeaders($this->inertia)->get('/reports/service-staff-line-orders?'.http_build_query(['tela' => 'nova', 'service_staff_id' => $atendente] + $this->periodo));
        $tela->assertOk();
        $vendas = fn (array $nomes) => collect($nomes)->map(fn ($n) => substr((string) $n, 0, 5))->sort()->values()->all();

        return [$vendas(array_column($json->json('data'), 'invoice_no')), $vendas(array_column($tela->json('props.linhas'), 'venda'))];
    };

    // Sem a permissão da página: 403 no JSON.
    expect($visto([], []))->toBe(['403', null]);
    // Todos os locais: os dois, como antes.
    expect($visto(['sales_representative.view', 'access_all_locations'], []))->toBe([['RIA-A', 'RIA-B'], ['RIA-A', 'RIA-B']]);
    // Só o local A: só o do A, nas duas telas.
    expect($visto(['sales_representative.view'], [$localA]))->toBe([['RIA-A'], ['RIA-A']]);
    // Nenhum local: nada.
    expect($visto(['sales_representative.view'], []))->toBe([[], []]);
});
