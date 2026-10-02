<?php

// @covers-us US-SELL-064

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Contrato da Lista de POS (`GET /pos` → `SellPosController@index`, dados de
 * `GET /sells-list-json?is_direct_sale=0` → `SellController@inertiaList`).
 *
 * UCs: resources/js/Pages/Sells/Pos/Index.casos.md (UC-POS-01..05 aqui; 06..08 no E2E).
 * Os casos saem do charter (R1..R6) e do legado `sale_pos/index`, não do `.tsx`.
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário, criado se faltar). Nunca biz=4.
 *
 * ISOLAMENTO DO DADO: a lane MySQL persiste dados de outros testes no tenant 98. Toda venda
 * daqui nasce com o prefixo `$this->prefixo` no `invoice_no` e toda consulta filtra por ele
 * (`q=`), então os totais esperados são só os desta fixture.
 *
 * REGRA MESTRE valor: nenhum cálculo muda neste trabalho. Os valores esperados saem à mão:
 *   tenant 98 · POS (is_direct_sale=0)
 *     A  100.00 paid     · pagamento 100.00
 *     B  200.00 partial  · pagamento  50.00
 *     C  300.00 due      · sem pagamento
 *   tenant 98 · venda direta (is_direct_sale=1)  D 1000.00 (NÃO entra na Lista de POS)
 *   tenant 99 · POS  X 5000.00 (NUNCA aparece no 98)
 *   ⇒ Lista de POS: 3 vendas · total 600.00 · pago 150.00 · em aberto 450.00
 *
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function sposUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SPOS Lista',
        'username' => 'spos_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function sposLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
}

function sposVenda(object $test, int $bizId, string $sufixo, float $total, string $status, int $isDirectSale = 0, array $extra = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $bizId,
        'created_by' => $test->user->id,
        'type' => 'sell',
        'status' => 'final',
        'is_direct_sale' => $isDirectSale,
        'payment_status' => $status,
        'invoice_no' => $test->prefixo . $sufixo,
        'transaction_date' => now()->format('Y-m-d') . ' 15:00:00',
        'total_before_tax' => $total,
        'final_total' => $total,
        'created_at' => now(),
        'updated_at' => now(),
    ], $extra));
}

function sposPagamento(int $vendaId, int $bizId, int $userId, float $valor): void
{
    DB::table('transaction_payments')->insert([
        'transaction_id' => $vendaId,
        'business_id' => $bizId,
        'amount' => $valor,
        'method' => 'cash',
        'is_return' => 0,
        'paid_on' => now(),
        'created_by' => $userId,
        'payment_ref_no' => 'SPOS-' . uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Lista JSON pelo mesmo caminho que a Page usa. */
function sposLista(object $test, array $query = [])
{
    return $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells-list-json?' . http_build_query(array_merge(['q' => $test->prefixo], $query)));
}

function sposInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET /pos como o cliente Inertia manda (X-Inertia E X-Requested-With — §5 2026-09-08). */
function sposPagina(object $test)
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => sposInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/pos');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'transaction_payments', 'business', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->prefixo = 'SPOS' . strtoupper(substr(uniqid(), -6)) . '-';
    // Papel de balcão: vê e abre POS, sem excluir. `access_all_locations` = sem restrição de local.
    $this->user = sposUsuario($this->bizId, ['sell.view', 'sell.create', 'access_all_locations']);
    $uid = $this->user->id;

    $this->a = sposVenda($this, $this->bizId, 'A', 100.00, 'paid');
    sposPagamento($this->a, $this->bizId, $uid, 100.00);
    $this->b = sposVenda($this, $this->bizId, 'B', 200.00, 'partial');
    sposPagamento($this->b, $this->bizId, $uid, 50.00);
    $this->c = sposVenda($this, $this->bizId, 'C', 300.00, 'due');
    $this->d = sposVenda($this, $this->bizId, 'D', 1000.00, 'due', 1);
    $this->x = sposVenda($this, $this->outroBizId, 'X', 5000.00, 'due');

    sposLogin($this, $this->user);
});

it('UC-POS-01 [V0][T0] a Lista de POS traz só as vendas de POS do business, com o rodapé do servidor', function () {
    // Anti-vácuo: a venda direta e a do outro business EXISTEM com o mesmo prefixo.
    expect((int) DB::table('transactions')->where('id', $this->d)->value('is_direct_sale'))->toBe(1);
    expect((int) DB::table('transactions')->where('id', $this->x)->value('business_id'))->toBe($this->outroBizId);

    $json = sposLista($this, ['is_direct_sale' => 0])->assertOk()->json();

    $ids = array_map(fn ($r) => (int) $r['id'], $json['data']);
    sort($ids);
    $esperado = [$this->a, $this->b, $this->c];
    sort($esperado);
    expect($ids)->toBe($esperado);

    expect((int) $json['totals']['count'])->toBe(3);
    expect(round((float) $json['totals']['sum_final_total'], 2))->toBe(600.0);
    expect(round((float) $json['totals']['sum_total_paid'], 2))->toBe(150.0);
    expect(round((float) $json['totals']['sum_due'], 2))->toBe(450.0);
});

it('UC-POS-01 (controle) sem is_direct_sale a lista geral segue igual — a venda direta volta', function () {
    $gerente = sposUsuario($this->bizId, ['direct_sell.view']);
    sposLogin($this, $gerente);

    $json = sposLista($this)->assertOk()->json();

    expect((int) $json['totals']['count'])->toBe(4);
    expect(round((float) $json['totals']['sum_final_total'], 2))->toBe(1600.0);
});

it('UC-POS-02 [V0] filtro Vencido deixa só a vencida e o rodapé recalcula', function () {
    $vencida = sposVenda($this, $this->bizId, 'V', 80.00, 'due', 0, [
        'transaction_date' => now()->subDays(60)->format('Y-m-d') . ' 10:00:00',
        'pay_term_number' => 30,
        'pay_term_type' => 'days',
    ]);
    // A "C" (due, sem prazo) não está vencida. Dá um prazo que ainda não venceu pra provar o corte.
    DB::table('transactions')->where('id', $this->c)->update(['pay_term_number' => 30, 'pay_term_type' => 'days']);

    $json = sposLista($this, ['is_direct_sale' => 0, 'payment_status' => 'overdue'])->assertOk()->json();

    expect(array_map(fn ($r) => (int) $r['id'], $json['data']))->toBe([$vencida]);
    expect($json['data'][0]['is_overdue'])->toBeTrue();
    expect((int) $json['totals']['count'])->toBe(1);
    expect(round((float) $json['totals']['sum_final_total'], 2))->toBe(80.0);
    expect(round((float) $json['totals']['sum_due'], 2))->toBe(80.0);
});

it('UC-POS-03 venda quitada chega sem saldo devedor', function () {
    $json = sposLista($this, ['is_direct_sale' => 0])->assertOk()->json();

    $quitada = collect($json['data'])->firstWhere('id', $this->a);
    $parcial = collect($json['data'])->firstWhere('id', $this->b);
    expect($quitada)->not->toBeNull();
    expect($parcial)->not->toBeNull();

    // A Page só oferece "Adicionar pagamento" com final_total - total_paid > 0.
    expect(round((float) $quitada['final_total'] - (float) $quitada['total_paid'], 2))->toBe(0.0);
    expect(round((float) $parcial['final_total'] - (float) $parcial['total_paid'], 2))->toBe(150.0);
});

it('UC-POS-04 papel sem sell.delete abre a tela com Excluir desabilitado', function () {
    $response = sposPagina($this);
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    expect($page['component'] ?? null)->toBe('Sells/Pos/Index');
    expect($page['props']['permissions']['delete'])->toBeFalse();
    expect($page['props']['permissions']['create'])->toBeTrue();

    $comExclusao = sposUsuario($this->bizId, ['sell.view', 'sell.delete']);
    sposLogin($this, $comExclusao);
    $page2 = json_decode(sposPagina($this)->assertStatus(200)->getContent(), true);
    expect($page2['props']['permissions']['delete'])->toBeTrue();
});

it('UC-POS-04 (gate) sem sell.view nem sell.create a tela e a lista são negadas', function () {
    $semPermissao = sposUsuario($this->bizId, ['access_all_locations']);
    sposLogin($this, $semPermissao);

    sposPagina($this)->assertStatus(403);
    sposLista($this, ['is_direct_sale' => 0])->assertStatus(403);
});

it('UC-POS-04 (gate) sell.view abre a Lista de POS mas não a lista geral', function () {
    sposLista($this, ['is_direct_sale' => 0])->assertOk();
    sposLista($this)->assertStatus(403);
});

it('UC-POS-05 período Hoje deixa só as vendas do dia', function () {
    $ontem = sposVenda($this, $this->bizId, 'O', 40.00, 'paid', 0, [
        'transaction_date' => now()->subDay()->format('Y-m-d') . ' 15:00:00',
    ]);
    $hoje = now()->format('Y-m-d');

    $json = sposLista($this, [
        'is_direct_sale' => 0,
        'date_from' => $hoje,
        'date_to' => $hoje . ' 23:59:59',
    ])->assertOk()->json();

    $ids = array_map(fn ($r) => (int) $r['id'], $json['data']);
    expect(in_array($ontem, $ids, true))->toBeFalse();
    expect(in_array($this->a, $ids, true))->toBeTrue();
    expect((int) $json['totals']['count'])->toBe(3);
});
