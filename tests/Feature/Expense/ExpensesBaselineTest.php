<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline F2 (MWART, ADR 0104) de /expenses — o que a Blade e o `ExpenseController` fazem
 * HOJE, antes da tela React (US-MWART-014).
 *
 * Plano: memory/requisitos/Financeiro/RUNBOOK-despesas.md · mapa: Financeiro/despesas-parity.md
 * (os testes citam o item da paridade que defendem).
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant de teste 98; adversário = business 2 do seed. NUNCA biz=4.
 * A despesa alheia usa o MEU local (o `index` faz INNER JOIN em `business_locations`): assim só o
 * filtro de `business_id` a separa, e o caso discrimina. Todo "não aparece" tem contraprova positiva.
 */
uses(DatabaseTransactions::class);

const DESP_BIZ_ALHEIO = 2;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    foreach (['transactions', 'transaction_payments', 'business_locations', 'invoice_schemes', 'invoice_layouts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode com DB_CONNECTION=mysql.");
        }
    }
    if (! DB::table('business')->where('id', DESP_BIZ_ALHEIO)->exists()) {
        $this->markTestSkipped('Sem business 2 no seed (pest-mysql-setup).');
    }

    $this->business = $this->seededTenant();
    $this->localA = despLocal($this->business->id);
    $this->localB = despLocal($this->business->id);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    // store/update passam por isSubscribed() quando o Superadmin está instalado.
    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->business->id, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode([]), 'created_id' => $this->business->owner_id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});

function despLocal(int $businessId): int
{
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $businessId, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $businessId, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $businessId, 'name' => 'Local '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ]);
}

function despEntrar($test, array $permissoes): \App\User
{
    $user = $test->usuarioComPermissoes($permissoes, $test->business);
    // Sessão VAZIA de propósito: o SetSessionData só monta a sessão (user, business, currency, ano fiscal)
    // quando ela não tem `user`. Gravar `user.*` à mão o pulava e deixava `currency` nula — o `num_f`
    // do DataTable quebrava com 500 (run 38456214086). Assim a sessão é a de uma requisição real.
    session()->flush();
    $test->actingAs($user);

    return $user;
}

function despDespesa(int $biz, int $local, int $criador, string $ref, array $campos = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $biz, 'location_id' => $local, 'type' => 'expense', 'status' => 'final',
        'payment_status' => 'due', 'final_total' => 150, 'total_before_tax' => 150, 'ref_no' => $ref,
        'transaction_date' => now(), 'created_by' => $criador, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

/** Linhas do JSON do DataTable, indexadas pela ref (a coluna `ref_no` vem com selos HTML depois da ref). */
function despLista($test, string $tag): array
{
    $r = $test->withHeaders($test->ajax)->get('/expenses?length=-1');
    $r->assertOk();

    return collect($r->json('data'))
        ->filter(fn ($l) => str_starts_with((string) $l['ref_no'], $tag))
        ->mapWithKeys(fn ($l) => [explode(' ', (string) $l['ref_no'])[0] => $l])
        ->all();
}

it('baseline: a lista traz a despesa do negócio e não a de outro, mesmo no meu local (paridade 1, 5)', function () {
    $user = despEntrar($this, ['all_expense.access', 'access_all_locations']);
    $tag = 'DSPB1'.uniqid();
    despDespesa($this->business->id, $this->localA, $user->id, "$tag-meu");
    despDespesa($this->business->id, $this->localA, $user->id, "$tag-estorno", ['type' => 'expense_refund']);
    despDespesa(DESP_BIZ_ALHEIO, $this->localA, $user->id, "$tag-alheio");

    $linhas = despLista($this, $tag);

    expect($linhas)->toHaveKey("$tag-meu");      // controle positivo
    expect($linhas)->toHaveKey("$tag-estorno");  // expense_refund entra na mesma lista
    expect($linhas)->not->toHaveKey("$tag-alheio"); // Tier 0
});

it('baseline: sem acesso a todos os locais, só vem a despesa do local permitido (paridade 2)', function () {
    $tag = 'DSPB2'.uniqid();
    $user = despEntrar($this, ['all_expense.access', 'location.'.$this->localA]);
    despDespesa($this->business->id, $this->localA, $user->id, "$tag-a");
    despDespesa($this->business->id, $this->localB, $user->id, "$tag-b");

    $linhas = despLista($this, $tag);

    expect($linhas)->toHaveKey("$tag-a");
    expect($linhas)->not->toHaveKey("$tag-b");
});

it('baseline: com só view_own_expense, vem o que criei e o que é para mim, não o de outro (paridade 3)', function () {
    $tag = 'DSPB3'.uniqid();
    $eu = despEntrar($this, ['view_own_expense', 'access_all_locations']);
    $outro = \App\User::factory()->create(['business_id' => $this->business->id]);
    despDespesa($this->business->id, $this->localA, $eu->id, "$tag-criei");
    despDespesa($this->business->id, $this->localA, $outro->id, "$tag-para-mim", ['expense_for' => $eu->id]);
    despDespesa($this->business->id, $this->localA, $outro->id, "$tag-do-outro");

    $linhas = despLista($this, $tag);

    expect($linhas)->toHaveKey("$tag-criei");
    expect($linhas)->toHaveKey("$tag-para-mim");
    expect($linhas)->not->toHaveKey("$tag-do-outro");
});

it('baseline: sem all_expense.access nem view_own_expense a lista devolve 403 (paridade 4)', function () {
    despEntrar($this, ['access_all_locations']);

    $this->withHeaders($this->ajax)->get('/expenses')->assertForbidden();
});

it('baseline: devido = total − pagamentos, e o estorno sai negativo no total e no devido (paridade 6, 7)', function () {
    $user = despEntrar($this, ['all_expense.access', 'access_all_locations']);
    $tag = 'DSPB6'.uniqid();
    $normal = despDespesa($this->business->id, $this->localA, $user->id, "$tag-normal", ['payment_status' => 'partial']);
    $estorno = despDespesa($this->business->id, $this->localA, $user->id, "$tag-estorno", ['type' => 'expense_refund', 'payment_status' => 'partial']);
    foreach ([$normal, $estorno] as $id) {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $id, 'business_id' => $this->business->id, 'amount' => 50, 'method' => 'cash',
            'paid_on' => now(), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $linhas = despLista($this, $tag);

    expect($linhas["$tag-normal"]['payment_due'])->toContain('data-orig-value="100');
    expect($linhas["$tag-normal"]['final_total'])->toContain('data-orig-value="150');
    expect($linhas["$tag-estorno"]['payment_due'])->toContain('data-orig-value="-100');
    expect($linhas["$tag-estorno"]['final_total'])->toContain('data-orig-value="-150');
});

it('baseline: cadastrar grava no negócio da sessão, com "1.234,56" virando 1234.56 (paridade 1, VALOR)', function () {
    $user = despEntrar($this, ['expense.add', 'all_expense.access', 'access_all_locations']);
    $ref = 'DSPB7'.uniqid();

    $this->post('/expenses', ['ref_no' => $ref, 'location_id' => $this->localA, 'final_total' => '1.234,56'])
        ->assertRedirect();

    $linha = DB::table('transactions')->where('ref_no', $ref)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe((int) $this->business->id);
    expect((int) $linha->created_by)->toBe((int) $user->id);
    expect($linha->type)->toBe('expense');
    expect($linha->payment_status)->toBe('due');
    expect((float) $linha->final_total)->toBe(1234.56);
});

it('baseline: cadastrar com is_refund=1 grava expense_refund', function () {
    despEntrar($this, ['expense.add', 'all_expense.access', 'access_all_locations']);
    $ref = 'DSPB8'.uniqid();

    $this->post('/expenses', ['ref_no' => $ref, 'location_id' => $this->localA, 'final_total' => '80', 'is_refund' => 1])
        ->assertRedirect();

    expect(DB::table('transactions')->where('ref_no', $ref)->value('type'))->toBe('expense_refund');
});

it('baseline: editar altera a despesa do negócio e não alcança a de outro', function () {
    $user = despEntrar($this, ['expense.edit', 'all_expense.access', 'access_all_locations']);
    $tag = 'DSPB9'.uniqid();
    $minha = despDespesa($this->business->id, $this->localA, $user->id, "$tag-minha");
    $alheia = despDespesa(DESP_BIZ_ALHEIO, $this->localA, $user->id, "$tag-alheia");

    $this->put("/expenses/{$minha}", ['final_total' => '200', 'location_id' => $this->localA])->assertRedirect();
    $this->put("/expenses/{$alheia}", ['final_total' => '999', 'location_id' => $this->localA])->assertRedirect();

    expect((float) DB::table('transactions')->where('id', $minha)->value('final_total'))->toBe(200.0); // controle positivo
    expect((float) DB::table('transactions')->where('id', $alheia)->value('final_total'))->toBe(150.0); // Tier 0
});
