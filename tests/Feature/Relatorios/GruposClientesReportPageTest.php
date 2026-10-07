<?php

declare(strict_types=1);
// Cobre UC-RGC-01, UC-RGC-02 (resources/js/Pages/Relatorios/GruposClientes/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor por três caminhos: JSON do DataTable da Blade × props da Page × soma direta em
// transactions, mais a conta à mão. Tenant 98 × 99 (ADR 0358). Janela em 2099.

uses(DatabaseTransactions::class);

const RGC_INICIO = '2099-05-01';
const RGC_FIM = '2099-05-31';

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
});

function rgcVenda(int $businessId, int $criador, int $grupo, float $valor, string $status = 'final'): void
{
    DB::table('transactions')->insert([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => 'sell',
        'status' => $status, 'payment_status' => 'paid', 'customer_group_id' => $grupo,
        'transaction_date' => '2099-05-10 12:00:00', 'final_total' => $valor, 'total_before_tax' => $valor,
        'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'RGC-'.uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return array{0: string, 1: string} nomes únicos dos dois grupos do cenário */
function rgcCenario(int $businessId, int $criador): array
{
    $nomes = ['RGC-A-'.uniqid(), 'RGC-B-'.uniqid()];
    $ids = array_map(fn ($n) => DB::table('customer_groups')->insertGetId([
        'business_id' => $businessId, 'name' => $n, 'amount' => 0, 'created_by' => $criador,
        'created_at' => now(), 'updated_at' => now(),
    ]), $nomes);
    rgcVenda($businessId, $criador, $ids[0], 500);
    rgcVenda($businessId, $criador, $ids[0], 250);
    rgcVenda($businessId, $criador, $ids[1], 80);
    rgcVenda($businessId, $criador, $ids[1], 999, 'draft'); // controle: rascunho não é venda

    return $nomes;
}

/** Linhas da Page indexadas por grupo. */
function rgcPage($teste): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/customer-group?'.http_build_query(['tela' => 'nova', 'start_date' => RGC_INICIO, 'end_date' => RGC_FIM]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/GruposClientes/Index');

    return ['linhas' => collect($r->json('props.linhas'))->mapWithKeys(fn ($l) => [$l['grupo'] ?? '' => (float) $l['total']])->all(), 'props' => $r->json('props')];
}

test('UC-RGC-01 valor — linhas da Page = JSON do DataTable da Blade = soma direta', function () {
    [$a, $b] = rgcCenario($this->business->id, $this->user->id);

    // Caminho 1: o JSON que o DataTable da Blade pede (o total vem dentro de um <span>).
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/customer-group?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'start_date' => RGC_INICIO, 'end_date' => RGC_FIM]));
    $dt->assertOk();
    $linhasBlade = collect($dt->json('data'))->mapWithKeys(fn ($l) => [$l['name'] ?? '' => (float) trim(strip_tags((string) $l['total_sell']))])->all();

    // Caminho 2: a Page.
    $page = rgcPage($this);
    expect($page['linhas'])->toEqual($linhasBlade);

    // Caminho 3: soma direta, sem a consulta do controller.
    $direto = DB::table('transactions as t')->leftJoin('customer_groups as cg', 'cg.id', '=', 't.customer_group_id')
        ->where('t.business_id', $this->business->id)->where('t.type', 'sell')->where('t.status', 'final')
        ->whereBetween(DB::raw('DATE(t.transaction_date)'), [RGC_INICIO, RGC_FIM])
        ->groupBy('t.customer_group_id', 'cg.name')->select('cg.name', DB::raw('SUM(t.final_total) as total'))->get()
        ->mapWithKeys(fn ($r) => [$r->name ?? '' => (float) $r->total])->all();
    expect($page['linhas'])->toEqual($direto);

    // Conta à mão: A = 500 + 250; B = 80 (os 999 em rascunho ficam fora).
    expect($page['linhas'][$a])->toEqual(750.0);
    expect($page['linhas'][$b])->toEqual(80.0);
});

test('UC-RGC-02 Tier 0 — o negócio 99 não entra nas linhas, grupos nem locais; permissão da Blade', function () {
    [$a] = rgcCenario($this->business->id, $this->user->id);
    $antes = rgcPage($this);

    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    [$aAlheio] = rgcCenario($alheio->id, $donoAlheio);
    $localAlheio = EstoqueFixture::locationId($alheio->id);

    $depois = rgcPage($this);
    expect($depois['linhas'])->toEqual($antes['linhas']);
    expect($depois['linhas'])->not->toHaveKey($aAlheio);
    expect(collect($depois['props']['grupos'])->pluck('nome'))->toContain($a);
    expect(collect($depois['props']['grupos'])->pluck('nome'))->not->toContain($aAlheio);
    expect(collect($depois['props']['locais'])->pluck('id'))->not->toContain($localAlheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/customer-group?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/customer-group')->assertForbidden();
});
