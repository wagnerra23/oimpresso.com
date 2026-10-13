<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de /expenses na tela React (Financeiro/Despesas/Index) — US-MWART-015/016, thread financeiro/13.
 *
 * UC-DESPES-01 `[T0]` — a lista nova mostra só a despesa do meu negócio.
 * UC-DESPES-02 `[V0]` — total e devido do payload Inertia = `data-orig-value` do DataTable (dois caminhos).
 * UC-DESPES-03        — sem período, abre no ano fiscal.
 * UC-DESPES-04        — flag desligada, ou `?classico=1`, servem a Blade.
 * UC-DESPES-05 `[T0]` — excluir pela tela apaga a minha despesa e não alcança a de outro negócio.
 * UC-DESPES-06 `[V0]` — nova despesa: o total que a tela manda ("1234,56") grava 1234.56, conferido por 2 leituras.
 * UC-DESPES-07        — data da Blade (4 formatos de empresa) grava o que o uf_date original grava; a da tela nova, o mesmo minuto.
 *
 * Trio: resources/js/Pages/Financeiro/Despesas/{Index.charter.md,Index.casos.md}
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 x business 2; a despesa alheia usa o MEU local, então só o
 * `business_id` a separa. NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

const DIC_BIZ_ALHEIO = 2;
const DIC_FLAG = 'useV2FinanceiroDespesas';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    foreach (['transactions', 'transaction_payments', 'business_locations', 'invoice_schemes', 'invoice_layouts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode com DB_CONNECTION=mysql.");
        }
    }
    if (! DB::table('business')->where('id', DIC_BIZ_ALHEIO)->exists()) {
        $this->markTestSkipped('Sem business 2 no seed (pest-mysql-setup).');
    }

    $this->business = $this->seededTenant();
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->business->id, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->business->id, 'name' => 'Lay '.uniqid()]);
    $this->local = DB::table('business_locations')->insertGetId([
        'business_id' => $this->business->id, 'name' => 'Local '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ]);
    $this->user = $this->usuarioComPermissoes(['all_expense.access', 'access_all_locations'], $this->business);
    // Sessão VAZIA de propósito: o SetSessionData só monta a sessão (user, business, currency, ano fiscal)
    // quando ela não tem `user`; gravar `user.*` à mão deixava `currency` nula e o DataTable quebrava com
    // 500 no `num_f` (run 38456214086, #9267). Assim a sessão é a de uma requisição real.
    session()->flush();
    $this->actingAs($this->user);
    config(['feature-flags.forced_on' => DIC_FLAG]);
});

function dicDespesa($test, int $biz, string $ref, array $campos = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $biz, 'location_id' => $test->local, 'type' => 'expense', 'status' => 'final',
        'payment_status' => 'due', 'final_total' => 150, 'total_before_tax' => 150, 'ref_no' => $ref,
        'transaction_date' => now(), 'created_by' => $test->user->id, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function dicInertiaVersion(): string
{
    try {
        return (string) (new \App\Http\Middleware\HandleInertiaRequests)->version(request());
    } catch (\Throwable $e) {
        return '';
    }
}

/** Partial reload da prop deferida pedida — sem ele a prop nem existe no payload. */
function dicProp($test, string $prop, string $query = ''): array
{
    $r = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => dicInertiaVersion(),
        'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => 'Financeiro/Despesas/Index',
    ])->get('/expenses'.$query);

    $r->assertOk();
    $r->assertJsonPath('component', 'Financeiro/Despesas/Index');

    return (array) $r->json('props.'.$prop);
}

/** @return array<string, array> linhas do payload Inertia indexadas pela ref */
function dicLinhas($test, string $tag): array
{
    return collect(dicProp($test, 'despesas', '?start_date=2000-01-01&end_date=2100-12-31')['data'] ?? [])
        ->filter(fn ($l) => str_starts_with((string) $l['ref'], $tag))
        ->keyBy('ref')->all();
}

it('UC-DESPES-01 · a lista nova mostra a despesa do meu negócio e não a de outro no meu local', function () {
    $tag = 'DIC1'.uniqid();
    dicDespesa($this, $this->business->id, "$tag-meu");
    dicDespesa($this, DIC_BIZ_ALHEIO, "$tag-alheio");

    $linhas = dicLinhas($this, $tag);

    expect($linhas)->toHaveKey("$tag-meu");        // controle positivo
    expect($linhas)->not->toHaveKey("$tag-alheio"); // Tier 0
});

it('UC-DESPES-02 · total e devido da tela nova são os mesmos do DataTable, com o estorno negativo', function () {
    $tag = 'DIC2'.uniqid();
    $normal = dicDespesa($this, $this->business->id, "$tag-normal", ['payment_status' => 'partial']);
    $estorno = dicDespesa($this, $this->business->id, "$tag-estorno", ['type' => 'expense_refund', 'payment_status' => 'partial']);
    foreach ([$normal, $estorno] as $id) {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $id, 'business_id' => $this->business->id, 'amount' => 50, 'method' => 'cash',
            'paid_on' => now(), 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // Caminho 1: payload Inertia da tela nova.
    $nova = dicLinhas($this, $tag);
    // Caminho 2: o JSON do DataTable da Blade (a mesma requisição que o relatório por representante usa).
    $antiga = collect($this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/expenses?length=-1')->assertOk()->json('data'))
        ->filter(fn ($l) => str_starts_with((string) $l['ref_no'], $tag))
        ->mapWithKeys(fn ($l) => [explode(' ', (string) $l['ref_no'])[0] => $l])->all();
    $orig = fn (string $html) => (float) (preg_match('/data-orig-value="([^"]+)"/', $html, $m) ? $m[1] : 'NaN');

    expect($nova["$tag-normal"]['total'])->toEqual(150.0);
    expect($nova["$tag-normal"]['devido'])->toEqual(100.0);
    expect($nova["$tag-estorno"]['total'])->toEqual(-150.0);
    expect($nova["$tag-estorno"]['devido'])->toEqual(-100.0);
    foreach (["$tag-normal", "$tag-estorno"] as $ref) {
        expect($nova[$ref]['total'])->toEqual($orig($antiga[$ref]['final_total']));
        expect($nova[$ref]['devido'])->toEqual($orig($antiga[$ref]['payment_due']));
    }
});

it('UC-DESPES-03 · sem período na URL, os filtros abrem no ano fiscal do negócio', function () {
    $ano = app(\App\Utils\BusinessUtil::class)->getCurrentFinancialYear($this->business->id);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => dicInertiaVersion()])->get('/expenses');

    $r->assertOk()->assertJsonPath('component', 'Financeiro/Despesas/Index');
    $r->assertJsonPath('props.filtros.start_date', $ano['start']);
    $r->assertJsonPath('props.filtros.end_date', $ano['end']);
});

it('UC-DESPES-04 · flag desligada ou ?classico=1 servem a Blade', function () {
    $this->get('/expenses?classico=1')->assertOk()->assertViewIs('expense.index');

    config(['feature-flags.forced_on' => '']);
    $this->mock(\App\Services\FeatureFlagService::class, fn ($mock) =>
        $mock->shouldReceive('isOn')->andReturn(false)); // todas as flags desligadas, inclusive a desta tela

    $this->get('/expenses')->assertOk()->assertViewIs('expense.index');
});

it('UC-DESPES-05 · excluir pela tela apaga a minha despesa e não alcança a de outro negócio', function () {
    $this->user = $this->usuarioComPermissoes(['all_expense.access', 'access_all_locations', 'expense.delete'], $this->business);
    session()->flush(); // o SetSessionData monta a sessão do novo usuário (ver beforeEach)
    $this->actingAs($this->user);
    $tag = 'DIC5'.uniqid();
    $minha = dicDespesa($this, $this->business->id, "$tag-minha");
    $alheia = dicDespesa($this, DIC_BIZ_ALHEIO, "$tag-alheia");
    $ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    // A mesma chamada que a Page faz (fetch DELETE com X-Requested-With).
    $this->withHeaders($ajax)->delete("/expenses/{$minha}")->assertOk()->assertJsonPath('success', true);
    $this->withHeaders($ajax)->delete("/expenses/{$alheia}");

    expect(DB::table('transactions')->where('id', $minha)->exists())->toBeFalse(); // controle positivo
    expect(DB::table('transactions')->where('id', $alheia)->exists())->toBeTrue();  // Tier 0
});

function dicEntrarCom($test, array $permissoes): void
{
    $test->user = $test->usuarioComPermissoes($permissoes, $test->business);
    $test->actingAs($test->user);
    session(['user.business_id' => $test->business->id, 'user.id' => $test->user->id, 'business.id' => $test->business->id]);
}

/** O store passa por isSubscribed() quando o Superadmin está instalado. */
function dicAssinatura($test): void
{
    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $test->business->id, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode([]), 'created_id' => $test->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

it('UC-DESPES-06 · nova despesa: o total que a tela manda grava 1234.56, e as duas listas leem 1234.56', function () {
    dicEntrarCom($this, ['all_expense.access', 'access_all_locations', 'expense.add']);
    dicAssinatura($this);
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24]);
    $ref = 'DIC6'.uniqid();

    // A mesma chamada da NovaDespesaSheet: POST ajax, total com 2 casas e vírgula decimal, data ISO com 'T'.
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/expenses', ['ref_no' => $ref, 'location_id' => $this->local, 'final_total' => '1234,56', 'transaction_date' => '2026-03-05T14:30'])
        ->assertOk()->assertJsonPath('success', 1);

    $linha = DB::table('transactions')->where('ref_no', $ref)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe((int) $this->business->id);
    expect((float) $linha->final_total)->toBe(1234.56);

    // Leitura 1: payload da tela nova. Leitura 2: JSON do DataTable da Blade.
    $nova = dicLinhas($this, $ref);
    $antiga = collect($this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/expenses?length=-1')->assertOk()->json('data'))
        ->first(fn ($l) => str_starts_with((string) $l['ref_no'], $ref));
    preg_match('/data-orig-value="([^"]+)"/', (string) ($antiga['final_total'] ?? ''), $m);

    expect($nova[$ref]['total'])->toEqual(1234.56);
    expect((float) ($m[1] ?? 'NaN'))->toEqual(1234.56);
});

// Formatos de empresa e o que a Blade de hoje envia neles (o campo é `@format_datetime('now')`: data+hora).
dataset('formatos_da_blade', [
    'd/m/Y 24h' => ['d/m/Y', 24, '05/03/2026 14:30'],
    'd/m/Y 12h' => ['d/m/Y', 12, '05/03/2026 02:30 PM'],
    'Y-m-d 24h' => ['Y-m-d', 24, '2026-03-05 14:30'],
    'm/d/Y 24h' => ['m/d/Y', 24, '03/05/2026 14:30'],
]);

it('UC-DESPES-07 · a data da Blade grava o que o uf_date original grava, e a da tela nova grava o mesmo minuto', function (string $formato, int $hora, string $daBlade) {
    dicEntrarCom($this, ['all_expense.access', 'access_all_locations', 'expense.add']);
    dicAssinatura($this);
    session(['business.date_format' => $formato, 'business.time_format' => $hora]);
    $ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    $tag = 'DIC7'.uniqid();

    $this->withHeaders($ajax)->post('/expenses', ['ref_no' => "$tag-blade", 'location_id' => $this->local, 'final_total' => '10', 'transaction_date' => $daBlade])
        ->assertOk()->assertJsonPath('success', 1);
    $this->withHeaders($ajax)->post('/expenses', ['ref_no' => "$tag-tela", 'location_id' => $this->local, 'final_total' => '10', 'transaction_date' => '2026-03-05T14:30'])
        ->assertOk()->assertJsonPath('success', 1);

    // Oráculo = o código antigo: o mesmo uf_date que o createExpense chama (o TransactionUtil não mudou).
    // Ao minuto: sem `!` no formato, o Carbon::createFromFormat pega os segundos do relógio, antes e depois.
    $oraculo = substr((string) app(\App\Utils\TransactionUtil::class)->uf_date($daBlade, true), 0, 16);
    $blade = substr((string) DB::table('transactions')->where('ref_no', "$tag-blade")->value('transaction_date'), 0, 16);
    $tela = substr((string) DB::table('transactions')->where('ref_no', "$tag-tela")->value('transaction_date'), 0, 16);

    expect($oraculo)->toBe('2026-03-05 14:30'); // controle: o oráculo lê o formato
    expect($blade)->toBe($oraculo);              // a Blade grava o que gravava
    expect($tela)->toBe('2026-03-05 14:30');     // a tela nova grava o mesmo minuto
})->with('formatos_da_blade');
