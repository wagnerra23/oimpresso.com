<?php

declare(strict_types=1);
// Regra: despesa só dos locais que o usuário pode ver (permitted_locations, Tier 0).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// TransactionUtil::getExpenseReport lia um $permitted_locations nunca definido: quem só tem o
// local A via as despesas de todos os locais. Vale para os dois chamadores — o relatório de
// Despesas e o total de despesas do relatório de comissão. A tela nova de Despesas (#8977) lê a
// mesma lista que a Blade recebe, então herda a regra. Tenant 98 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->localA = EstoqueFixture::locationId($this->business->id, '-RDE-A');
    $this->localB = EstoqueFixture::locationId($this->business->id, '-RDE-B');

    $this->categoria = 'RDE-LOC-'.uniqid();
    $cat = DB::table('expense_categories')->insertGetId([
        'business_id' => $this->business->id, 'name' => $this->categoria, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->despesaPara = \App\User::factory()->create(['business_id' => $this->business->id]);
    foreach ([[$this->localA, 100], [$this->localB, 50]] as [$local, $valor]) {
        DB::table('transactions')->insert([
            'business_id' => $this->business->id, 'location_id' => $local, 'type' => 'expense', 'status' => 'final',
            'payment_status' => 'paid', 'expense_category_id' => $cat, 'expense_for' => $this->despesaPara->id,
            'transaction_date' => now()->startOfMonth()->addDays(2)->setTime(12, 0)->toDateTimeString(),
            'final_total' => $valor, 'total_before_tax' => $valor, 'created_by' => $this->despesaPara->id,
            'essentials_duration' => 0, 'ref_no' => 'RDE-LOC-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});

/** Entra como um usuário com as permissões dadas e devolve [total da categoria na Blade, total do relatório de comissão]. */
function rdeLocVisto($teste, array $permissoes): array
{
    $u = $teste->usuarioComPermissoes(array_merge(['expense_report.view', 'sales_representative.view'], $permissoes), $teste->business);
    $teste->actingAs($u);
    session(['user.business_id' => $teste->business->id, 'user.id' => $u->id, 'business.id' => $teste->business->id]);

    $blade = $teste->get('/reports/expense-report');
    $blade->assertOk();
    $naBlade = (float) (collect($blade->viewData('expenses'))->firstWhere('category', $teste->categoria)->total_expense ?? 0);

    $comissao = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/sales-representative-total-expense?'.http_build_query([
            'expense_for' => $teste->despesaPara->id,
            'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(),
        ]));
    $comissao->assertOk();

    return [$naBlade, (float) $comissao->json('total_expense')];
}

test('Tier 0 — despesa só dos locais permitidos, no relatório de Despesas e no de comissão', function () {
    // Só o local A: vê 100, não os 50 do B.
    expect(rdeLocVisto($this, ['location.'.$this->localA]))->toEqual([100.0, 100.0]);

    // Todos os locais: continua vendo os dois.
    expect(rdeLocVisto($this, ['access_all_locations']))->toEqual([150.0, 150.0]);

    // Nenhum local: não vê despesa nenhuma.
    expect(rdeLocVisto($this, []))->toEqual([0.0, 0.0]);
});
