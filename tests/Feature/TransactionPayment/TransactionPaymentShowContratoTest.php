<?php

declare(strict_types=1);
// Cobre UC-TPSHW-01, UC-TPSHW-02, UC-TPSHW-03, UC-TPSHW-04, UC-TPSHW-05, UC-TPSHW-06,
// UC-TPSHW-07 (resources/js/Pages/TransactionPayment/Show.casos.md) — G-2 rastreabilidade.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Contrato de COMPORTAMENTO do detalhe do pagamento (`GET /payments/v2/{id}` → `TransactionPayment/Show`).
 *
 * ÂNCORA (contrato, NÃO implementação) — ordem de fonte (memory/how-trabalhar.md):
 *   1. CANON — `Show.charter.md` (Props · UX Cards 1-3 · Tier 0 "RBAC mesmo set que viewPayment" e
 *      "404 cross-tenant") + `memory/requisitos/Financeiro/RUNBOOK-transaction-payment.md` (F2/F4).
 *      Não há US de SPEC para `/payments/v2` (varrido: SPECs de Financeiro/Sells sem âncora) — pergunta
 *      aberta no casos.md, nada inventado.
 *   2. PARIDADE — `TransactionPaymentController@viewPayment` + Blade
 *      `transaction_payment/single_payment_view.blade.php` (o modal que a tela substitui).
 *   3. CÓDIGO — `TransactionPaymentController@showInertia` lido só pra CONFIRMAR.
 *
 * ⛔ PAGAMENTO É VALOR (REGRA MESTRE): este arquivo só LÊ. Zero mudança em produção. Divergência
 *    contrato×comportamento vira ❌ no casos.md com recibo da lane — correção é decisão [W].
 *
 * ⛔ Tenants (ADR 0358): sessão = 98 (fictício); adversário = 99. NUNCA biz=4. Fixture por INSERT
 *    direto (não pelo fluxo sob teste, e sem acionar o TransactionPaymentObserver do Financeiro).
 *
 * ⛔ Permissão DIRETA no user, nunca via Role: no banco do CI não existe role nenhuma — foi a causa (1)
 *    do `TransactionPaymentInertiaSmokeTest` em quarentena (.github/financeiro-pest-quarantine.list).
 *    Este arquivo NÃO substitui nem edita aquele smoke.
 *
 * ⛔ Headers do browser: `X-Inertia` + `X-Requested-With` (o cliente Inertia manda os dois — §5 2026-09-08).
 *
 * @see app/Http/Controllers/TransactionPaymentController.php::showInertia
 * @see app/Http/Controllers/TransactionPaymentController.php::viewPayment
 * @see resources/js/Pages/TransactionPayment/Show.charter.md · Show.casos.md
 */
uses(DatabaseTransactions::class);

const TPSHW_TENANT = 98;

/** As 6 permissões do gate (charter Tier 0 = "mesmo set que viewPayment"). */
const TPSHW_PERMISSOES = [
    'sell.payments',
    'purchase.payments',
    'edit_sell_payment',
    'delete_sell_payment',
    'edit_purchase_payment',
    'delete_purchase_payment',
];

function tpshwInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET pelo caminho que o browser usa (Link/router.visit do Inertia). */
function tpshwGet(object $test, User $user, int $paymentId)
{
    return $test->actingAs($user)
        ->withSession([
            'user.business_id' => $user->business_id,
            'user.id' => $user->id,
            'business.id' => $user->business_id,
        ])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => tpshwInertiaVersion(),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get("/payments/v2/{$paymentId}");
}

/** User novo do business com exatamente as permissões dadas, concedidas DIRETO (sem role). */
function tpshwUser(int $businessId, array $permissoes): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'ct_tpshw_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    if ($permissoes !== []) {
        $user->givePermissionTo($permissoes);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/**
 * Contato + venda + pagamento no business dado. Marcadores únicos (ref, nome, nota) servem de
 * sonda de vazamento: se aparecerem no corpo de uma resposta de outro business, vazou.
 *
 * @return array{tx_id:int, payment_id:int, contact_id:int, ref:string, nome:string, nota:string, amount:float}
 */
function tpshwPagamento(int $businessId, int $createdBy, array $pagamento = []): array
{
    $marca = strtoupper(substr(md5(uniqid('', true)), 0, 10));
    $nome = "CT TPSHW Contato {$marca}";

    $contactId = (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId,
        'type' => 'customer',
        'name' => $nome,
        'mobile' => '00000000000',
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $txId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $businessId,
        'contact_id' => $contactId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'transaction_date' => now(),
        'total_before_tax' => 123.45,
        'final_total' => 123.45,
        'ref_no' => "CT-TX-{$marca}",
        'created_by' => $createdBy,
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ref = "CT-PAY-{$marca}";
    $nota = "CT TPSHW nota {$marca}";
    $amount = 123.45;

    $paymentId = (int) DB::table('transaction_payments')->insertGetId(array_merge([
        'business_id' => $businessId,
        'transaction_id' => $txId,
        'amount' => $amount,
        'method' => 'cash',
        'paid_on' => now(),
        'created_by' => $createdBy,
        'payment_ref_no' => $ref,
        'note' => $nota,
        'created_at' => now(),
        'updated_at' => now(),
    ], $pagamento));

    return ['tx_id' => $txId, 'payment_id' => $paymentId, 'contact_id' => $contactId,
        'ref' => $ref, 'nome' => $nome, 'nota' => $nota, 'amount' => $amount];
}

/** Pagamento-PAI (transaction_id nulo) com 1 filho apontando pra transação dada. */
function tpshwPagamentoPai(int $businessIdPai, int $businessIdFilho, int $txFilho, int $createdBy): array
{
    $marca = strtoupper(substr(md5(uniqid('', true)), 0, 10));
    $ref = "CT-PAI-{$marca}";
    $paiId = (int) DB::table('transaction_payments')->insertGetId([
        'business_id' => $businessIdPai,
        'transaction_id' => null,
        'amount' => 50.00,
        'method' => 'cash',
        'paid_on' => now(),
        'created_by' => $createdBy,
        'payment_ref_no' => $ref,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('transaction_payments')->insert([
        'business_id' => $businessIdFilho,
        'transaction_id' => $txFilho,
        'parent_id' => $paiId,
        'amount' => 50.00,
        'method' => 'cash',
        'paid_on' => now(),
        'created_by' => $createdBy,
        'payment_ref_no' => "CT-FILHO-{$marca}",
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['payment_id' => $paiId, 'ref' => $ref];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requer MySQL com schema UltimatePOS — roda na lane financeiro-pest / CT 100.');
    }
    foreach (['business', 'transactions', 'transaction_payments', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}).");
        }
    }
    if (! \App\Business::find(TPSHW_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358) — contrato não exercitável.');
    }

    // Leitor padrão: 1 permissão do set (a mais comum no balcão).
    $this->leitor = tpshwUser(TPSHW_TENANT, ['sell.payments']);
});

// =============================================================================
// UC-TPSHW-01 [T0] — pagamento de outro business: 404 e nada do conteúdo viaja.
// =============================================================================

it('UC-TPSHW-01 [T0] · pagamento de outro business devolve 404 e nenhum dado dele aparece no corpo', function () {
    $adversario = $this->seededSupportClientTenant()->id;
    expect($adversario)->not->toBe(TPSHW_TENANT);

    $alheio = tpshwPagamento($adversario, $this->leitor->id);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o pagamento alheio EXISTE (senão 404 = "id inexistente").
    expect(DB::table('transaction_payments')->where('id', $alheio['payment_id'])->exists())->toBeTrue();

    // CONTROLE POSITIVO: o mesmo formato de fixture, no business da sessão, abre e mostra o marcador.
    $proprio = tpshwPagamento(TPSHW_TENANT, $this->leitor->id);
    $ok = tpshwGet($this, $this->leitor, $proprio['payment_id']);
    $ok->assertOk();
    $this->assertStringContainsString($proprio['ref'], (string) $ok->getContent(), 'Controle positivo caiu: a tela não mostra nem o pagamento do próprio business.');

    $resp = tpshwGet($this, $this->leitor, $alheio['payment_id']);
    $resp->assertNotFound();

    $corpo = (string) $resp->getContent();
    foreach ([$alheio['ref'], $alheio['nome'], $alheio['nota']] as $marcador) {
        $this->assertStringNotContainsString($marcador, $corpo, "Vazamento cross-tenant: '{$marcador}' do business {$adversario} no corpo da resposta.");
    }
});

// =============================================================================
// UC-TPSHW-02 [V0] — o pagamento próprio abre com valor, método e referências do banco.
// =============================================================================

it('UC-TPSHW-02 [V0] · pagamento próprio abre a tela Show com o valor, o método rotulado e as referências gravadas', function () {
    $p = tpshwPagamento(TPSHW_TENANT, $this->leitor->id);

    $resp = tpshwGet($this, $this->leitor, $p['payment_id']);
    $resp->assertOk();
    expect($resp->json('component'))->toBe('TransactionPayment/Show');

    $linha = $resp->json('props.single_payment_line');
    $this->assertIsArray($linha, 'A tela não recebeu a linha do pagamento.');
    expect((int) $linha['id'])->toBe($p['payment_id']);
    // Valor: o número do banco, sem transformação (tolerância de centavo pelo decimal).
    expect(abs((float) $linha['amount'] - $p['amount']))->toBeLessThan(0.005);
    expect($linha['payment_ref_no'])->toBe($p['ref']);
    expect($linha['note'])->toBe($p['nota']);

    $tipos = $resp->json('props.payment_types');
    $this->assertIsArray($tipos, 'A tela não recebeu os rótulos de método de pagamento.');
    $this->assertNotEmpty($tipos[$linha['method']] ?? null, 'O método do pagamento não tem rótulo — a tela mostraria a chave crua.');

    expect((int) $resp->json('props.transaction.id'))->toBe($p['tx_id']);
});

// =============================================================================
// UC-TPSHW-03 — quem não tem nenhuma das 6 permissões não vê o pagamento.
// =============================================================================

it('UC-TPSHW-03 · usuário do mesmo business sem nenhuma permissão de pagamento é recusado e nada vaza', function () {
    $semPermissao = tpshwUser(TPSHW_TENANT, []);
    foreach (TPSHW_PERMISSOES as $perm) {
        expect($semPermissao->can($perm))->toBeFalse();
    }

    $p = tpshwPagamento(TPSHW_TENANT, $this->leitor->id);

    // Controle positivo: o mesmo pagamento abre para quem tem a permissão.
    tpshwGet($this, $this->leitor, $p['payment_id'])->assertOk();

    $resp = tpshwGet($this, $semPermissao, $p['payment_id']);
    expect($resp->status())->toBe(403);

    $corpo = (string) $resp->getContent();
    foreach ([$p['ref'], $p['nome'], $p['nota']] as $marcador) {
        $this->assertStringNotContainsString($marcador, $corpo, "Usuário sem permissão recebeu '{$marcador}'.");
    }
});

// =============================================================================
// UC-TPSHW-04 — cada permissão do set, sozinha, basta (paridade com viewPayment).
// =============================================================================

it('UC-TPSHW-04 · cada uma das 6 permissões do gate, sozinha, abre o pagamento', function (string $permissao) {
    $user = tpshwUser(TPSHW_TENANT, [$permissao]);
    $p = tpshwPagamento(TPSHW_TENANT, $user->id);

    $resp = tpshwGet($this, $user, $p['payment_id']);
    $resp->assertOk();
    $this->assertStringContainsString($p['ref'], (string) $resp->getContent(), "Com só '{$permissao}' a tela abriu sem o pagamento.");
})->with(TPSHW_PERMISSOES);

// =============================================================================
// UC-TPSHW-05 — o contato da transação chega na tela (Card 1).
// =============================================================================

it('UC-TPSHW-05 · o cliente da transação do pagamento chega na tela', function () {
    $p = tpshwPagamento(TPSHW_TENANT, $this->leitor->id);

    $resp = tpshwGet($this, $this->leitor, $p['payment_id']);
    $resp->assertOk();
    expect($resp->json('props.transaction.contact.name'))->toBe($p['nome']);
});

// =============================================================================
// UC-TPSHW-06 [T0] — pagamento-pai (sem transaction_id) resolve a transação pelo filho do MESMO business.
// =============================================================================

it('UC-TPSHW-06 · pagamento-pai do business abre e mostra a transação do pagamento-filho', function () {
    $filho = tpshwPagamento(TPSHW_TENANT, $this->leitor->id);
    $pai = tpshwPagamentoPai(TPSHW_TENANT, TPSHW_TENANT, $filho['tx_id'], $this->leitor->id);

    $resp = tpshwGet($this, $this->leitor, $pai['payment_id']);
    $resp->assertOk();
    expect((int) $resp->json('props.transaction.id'))->toBe($filho['tx_id']);
    expect($resp->json('props.single_payment_line.payment_ref_no'))->toBe($pai['ref']);
});

it('UC-TPSHW-06 [T0] · pagamento-pai cujo filho é de OUTRO business devolve 404 sem vazar a transação alheia', function () {
    $adversario = $this->seededSupportClientTenant()->id;
    $filhoAlheio = tpshwPagamento($adversario, $this->leitor->id);
    $pai = tpshwPagamentoPai(TPSHW_TENANT, $adversario, $filhoAlheio['tx_id'], $this->leitor->id);

    // Pré-condição: o vínculo pai→filho existe de fato.
    expect(DB::table('transaction_payments')->where('parent_id', $pai['payment_id'])->count())->toBe(1);

    $resp = tpshwGet($this, $this->leitor, $pai['payment_id']);
    $resp->assertNotFound();
    $this->assertStringNotContainsString($filhoAlheio['nome'], (string) $resp->getContent(), 'Contato do business adversário vazou pelo pagamento-pai.');
});

// =============================================================================
// UC-TPSHW-07 [V0] — abrir o pagamento não altera pagamento nem transação.
// =============================================================================

it('UC-TPSHW-07 [V0] · abrir o detalhe do pagamento não altera o pagamento, a transação nem cria linha', function () {
    $p = tpshwPagamento(TPSHW_TENANT, $this->leitor->id);

    $antesPg = (array) DB::table('transaction_payments')->where('id', $p['payment_id'])->first();
    $antesTx = (array) DB::table('transactions')->where('id', $p['tx_id'])->first();
    $antesQtd = DB::table('transaction_payments')->where('business_id', TPSHW_TENANT)->count();

    // Anti-vácuo: a leitura aconteceu de verdade.
    $resp = tpshwGet($this, $this->leitor, $p['payment_id']);
    $resp->assertOk();
    expect($resp->json('component'))->toBe('TransactionPayment/Show');

    $depoisPg = (array) DB::table('transaction_payments')->where('id', $p['payment_id'])->first();
    $depoisTx = (array) DB::table('transactions')->where('id', $p['tx_id'])->first();

    foreach (['amount', 'method', 'paid_on', 'payment_ref_no', 'updated_at'] as $c) {
        expect($depoisPg[$c])->toEqual($antesPg[$c]);
    }
    foreach (['final_total', 'payment_status', 'updated_at'] as $c) {
        expect($depoisTx[$c])->toEqual($antesTx[$c]);
    }
    expect(DB::table('transaction_payments')->where('business_id', TPSHW_TENANT)->count())->toBe($antesQtd);
});
