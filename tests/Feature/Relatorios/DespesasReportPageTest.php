<?php

declare(strict_types=1);
// Cobre UC-RDE-01, UC-RDE-02 (resources/js/Pages/Relatorios/Despesas/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor por três caminhos: o que a Blade recebe (viewData) × props da Page × soma direta em
// transactions, mais a conta à mão das categorias do cenário. Tenant 98 × 99 (ADR 0358).
// Janela = mês corrente, o padrão da Blade sem date_range (não depende do formato de data do negócio).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['expense_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function rdeCategoria(int $businessId, string $nome): int
{
    return DB::table('expense_categories')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rdeDespesa(int $businessId, int $criador, ?int $categoria, float $valor, string $tipo = 'expense', ?string $data = null): void
{
    DB::table('transactions')->insert([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => $tipo,
        'status' => 'final', 'payment_status' => 'paid', 'expense_category_id' => $categoria,
        'transaction_date' => $data ?? now()->startOfMonth()->addDays(2)->setTime(12, 0)->toDateTimeString(),
        'final_total' => $valor, 'total_before_tax' => $valor, 'created_by' => $criador, 'essentials_duration' => 0,
        'ref_no' => 'RDE-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return array{0: string, 1: string} nomes únicos das duas categorias do cenário */
function rdeCenario(int $businessId, int $criador): array
{
    $a = 'RDE-A-'.uniqid();
    $b = 'RDE-B-'.uniqid();
    $ca = rdeCategoria($businessId, $a);
    $cb = rdeCategoria($businessId, $b);
    rdeDespesa($businessId, $criador, $ca, 300);
    rdeDespesa($businessId, $criador, $ca, 120);
    rdeDespesa($businessId, $criador, $ca, 20, 'expense_refund');        // devolução: entra negativa
    rdeDespesa($businessId, $criador, $cb, 75);
    rdeDespesa($businessId, $criador, $cb, 999, 'expense', now()->subMonths(3)->toDateTimeString()); // controle: fora do período

    return [$a, $b];
}

/** Linhas da Page indexadas por categoria (null = "Outros"). */
function rdePage($teste): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/expense-report?tela=nova');
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Despesas/Index');

    return ['linhas' => collect($r->json('props.linhas'))->mapWithKeys(fn ($l) => [$l['categoria'] ?? '' => (float) $l['total']])->all(),
        'total' => (float) $r->json('props.total'), 'props' => $r->json('props')];
}

test('UC-RDE-01 valor — linhas e total da Page = o que a Blade recebe = soma direta', function () {
    [$a, $b] = rdeCenario($this->business->id, $this->user->id);
    rdeDespesa($this->business->id, $this->user->id, null, 40); // sem categoria → "Outros"

    // Caminho 1: o que a Blade recebe.
    $blade = $this->get('/reports/expense-report');
    $blade->assertOk();
    $linhasBlade = collect($blade->viewData('expenses'))->mapWithKeys(fn ($e) => [$e->category ?? '' => (float) $e->total_expense])->all();
    $totalBlade = (float) collect($blade->viewData('expenses'))->sum('total_expense'); // o tfoot da Blade soma as linhas

    // Caminho 2: a Page.
    $page = rdePage($this);
    expect($page['linhas'])->toEqual($linhasBlade);
    expect($page['total'])->toEqual($totalBlade);

    // Caminho 3: soma direta no mês corrente, sem TransactionUtil.
    $direto = (float) DB::table('transactions')->where('business_id', $this->business->id)
        ->whereIn('type', ['expense', 'expense_refund'])
        ->whereBetween(DB::raw('DATE(transaction_date)'), [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
        ->sum(DB::raw("IF(type = 'expense_refund', -1 * final_total, final_total)"));
    expect(round($page['total'], 4))->toEqual(round($direto, 4));

    // Conta à mão das linhas do cenário: A = 300 + 120 − 20; B = 75 (os 999 fora do período ficam fora).
    expect($page['linhas'][$a])->toEqual(400.0);
    expect($page['linhas'][$b])->toEqual(75.0);
    expect($page['linhas'][''] ?? null)->not->toBeNull(); // a despesa sem categoria aparece como "Outros"
});

test('UC-RDE-02 Tier 0 — o negócio 99 não entra nas linhas, categorias nem locais; permissão da Blade', function () {
    [$a] = rdeCenario($this->business->id, $this->user->id);
    $antes = rdePage($this);

    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    // Usuário PRÓPRIO do 99: o dono semeado só fica ligado ao 99 quando o helper cria a empresa;
    // se o 99 já existia, a consulta voltava null → created_by=0 e a FK de contacts derrubava o insert.
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    [$aAlheia] = rdeCenario($alheio->id, $donoAlheio);
    $localAlheio = EstoqueFixture::locationId($alheio->id);

    $depois = rdePage($this);
    expect($depois['linhas'])->toEqual($antes['linhas']);
    expect($depois['total'])->toEqual($antes['total']);
    expect($depois['linhas'])->not->toHaveKey($aAlheia);
    expect(collect($depois['props']['categorias'])->pluck('nome'))->toContain($a);
    expect(collect($depois['props']['categorias'])->pluck('nome'))->not->toContain($aAlheia);
    expect(collect($depois['props']['locais'])->pluck('id'))->not->toContain($localAlheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/expense-report?tela=nova')->assertForbidden();
    $this->get('/reports/expense-report')->assertForbidden();
});
