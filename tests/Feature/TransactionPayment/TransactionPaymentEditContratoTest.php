<?php

declare(strict_types=1);
// Cobre UC-TPEDT-01, UC-TPEDT-02, UC-TPEDT-03, UC-TPEDT-04, UC-TPEDT-05, UC-TPEDT-06,
// UC-TPEDT-07 (resources/js/Pages/TransactionPayment/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de COMPORTAMENTO da edição de pagamento (`GET /payments/v2/{id}/edit` →
 * `TransactionPayment/Edit`, submit em `PUT /payments/{id}` → `TransactionPaymentController@update`).
 *
 * ÂNCORA (contrato, NÃO implementação) — ordem de fonte de memory/how-trabalhar.md:
 *   1. CANON — `Edit.charter.md` (§Intent, §Props, §Tier 0, §Validação) +
 *      `memory/requisitos/Financeiro/RUNBOOK-transaction-payment.md` (F2/F4 + §Tier 0) +
 *      ADR 0093 (multi-tenant Tier 0) + REGRA MESTRE valor/estoque (memory/proibicoes.md) +
 *      ADR 0358 (tenant fictício 98). Não há US de SPEC para /payments/v2 (varrido 2026-10-14).
 *   2. PARIDADE LEGADA — o modal Blade `edit($id)` → `transaction_payment/edit_payment_row.blade.php`
 *      manda `paid_on` no formato da empresa e `amount` em pt-BR para o MESMO `update($id)`.
 *   3. CÓDIGO — o Controller foi lido só pra CONFIRMAR; nenhum assert deriva dele
 *      (proibicoes §5 2026-06-05). Sem ANTI-REGRESSAO Delphi para esta tela.
 *
 * ⚠️ PAGAMENTO É VALOR (REGRA MESTRE). Este arquivo só MEDE: nenhum código de produção muda
 *    junto. Vermelho aqui é ACHADO — a correção é decisão [W], nunca ajuste do teste.
 *
 * ⚠️ Efeito, não status: no `update()` sucesso e recusa podem sair como o MESMO 302
 *    (`redirect()->back()`), então os casos de escrita asseram o BANCO (§5 2026-09-05).
 *
 * ⚠️ Não duplica `Modules/Financeiro/Tests/Feature/TransactionPaymentInertiaSmokeTest.php`
 *    (em quarentena): aquele só assera status de GET. A causa (3) medida lá — 500 em
 *    `Util::allModulesEnabled` — bate com a sessão de teste sem `business.enabled_modules`
 *    (mesma causa medida no SellsEditContratoTest em 2026-08-31); aqui a sessão vai completa.
 *
 * ⛔ Tier 0: tenant 98 × o 2º business semeado. NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function tpEdtInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Headers que o `form.put` do @inertiajs/react manda de fato (X-Inertia + X-Requested-With). */
function tpEdtInertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => tpEdtInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
}

/**
 * Venda `final` de R$ 100,00 com UM pagamento de R$ 100,00 (logo `paid`). Estado inicial por
 * INSERT — não pelo fluxo sob teste.
 *
 * @return array{tx_id:int, payment_id:int}
 */
function tpEdtVendaPaga(int $bizId, ?int $parentId = null): array
{
    $userId = EstoqueFixture::userId($bizId);
    $txId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId),
        'contact_id' => DB::table('contacts')->where('business_id', $bizId)->orderBy('id')->value('id'),
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'final_total' => 100.00,
        'total_before_tax' => 100.00,
        'ref_no' => 'TPEDT-'.uniqid(),
        'transaction_date' => now(),
        'created_by' => $userId,
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payId = (int) DB::table('transaction_payments')->insertGetId([
        'business_id' => $bizId,
        'transaction_id' => $txId,
        'amount' => 100.00,
        'method' => 'cash',
        'paid_on' => '2026-10-01 08:00:00',
        'created_by' => $userId,
        'parent_id' => $parentId,
        'payment_ref_no' => 'TPEDT-PAY-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['tx_id' => $txId, 'payment_id' => $payId];
}

function tpEdtPagamento(int $id): ?object
{
    return DB::table('transaction_payments')->where('id', $id)->first();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'transaction_payments', 'business_locations', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}).");
        }
    }

    $this->bizId = EstoqueFixture::businessId(); // tenant 98 (ADR 0358 — nunca biz=4).
    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    // Sessão montada como o SetSessionData monta em produção. Com `user` presente o middleware
    // NÃO reconstrói, então tudo o que o caminho lê tem de estar aqui — vindo do banco:
    //   · `business.enabled_modules` — `Util::allModulesEnabled` lê por chave direta (sem `??`);
    //     ausente = `Undefined array key` (a causa 3 do smoke em quarentena).
    //   · `business.date_format`/`time_format` — `uf_date()` do `update()` parseia `paid_on` com
    //     eles. d/m/Y 24h é o formato pt-BR, o que o modal legado devolve.
    //   · `currency` — o que `num_f`/`num_uf` consultam.
    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))
        ->first();
    $this->sessao = [
        'user.business_id' => $this->bizId,
        'user.id' => $this->user->id,
        'business.id' => $this->bizId,
        'business.date_format' => 'd/m/Y',
        'business.time_format' => 24,
        'business.enabled_modules' => (array) json_decode(
            (string) DB::table('business')->where('id', $this->bizId)->value('enabled_modules'),
            true
        ),
        'currency' => [
            'id' => (int) ($currency->id ?? 1),
            'code' => (string) ($currency->code ?? 'BRL'),
            'symbol' => (string) ($currency->symbol ?? 'R$'),
            'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
            'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
        ],
    ];

    // O user do seed da lane NÃO tem role nenhuma (medido no smoke em quarentena, causa 1):
    // a permissão entra DIRETO no user.
    Permission::findOrCreate('edit_sell_payment', 'web');
    $this->user->givePermissionTo('edit_sell_payment');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs(User::findOrFail($this->user->id));
    session($this->sessao);
});

// UC-TPEDT-01 — charter §Tier 0 ("editInertia já valida Transaction.business_id == session → 404
//   cross-tenant") + RUNBOOK F4 #7 + ADR 0093.
it('UC-TPEDT-01 · pagamento de outro business não abre na edição (404, Tier 0)', function () {
    $outroBiz = EstoqueFixture::secondBusinessId();
    if ($outroBiz === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant.');
    }
    $alheio = tpEdtVendaPaga($outroBiz);

    // ANTI-VÁCUO: o pagamento alheio EXISTE (senão o 404 seria "id inexistente").
    expect(DB::table('transaction_payments')->where('id', $alheio['payment_id'])->exists())->toBeTrue();
    // ANTI-VÁCUO: o mesmo user TEM a permissão de edição — o 404 não pode ser o gate de RBAC.
    expect(auth()->user()->can('edit_sell_payment'))->toBeTrue();

    $r = $this->withHeaders(tpEdtInertiaHeaders())->get("/payments/v2/{$alheio['payment_id']}/edit");

    $r->assertStatus(404);
    expect((string) $r->getContent())->not->toContain('TPEDT-PAY-');
});

// UC-TPEDT-02 — charter §Props (payment_line · transaction · payment_types · accounts) +
//   RUNBOOK F2 ("editInertia retorna payment_line + transaction + payment_types + accounts").
it('UC-TPEDT-02 · pagamento próprio abre a tela Edit com o pagamento, a transação e as formas de pagamento', function () {
    $meu = tpEdtVendaPaga($this->bizId);

    $r = $this->withHeaders(tpEdtInertiaHeaders())->get("/payments/v2/{$meu['payment_id']}/edit");

    $r->assertOk();
    expect($r->json('component'))->toBe('TransactionPayment/Edit');
    expect((int) $r->json('props.payment_line.id'))->toBe($meu['payment_id']);
    expect(round((float) $r->json('props.payment_line.amount'), 2))->toBe(100.0);
    expect((int) $r->json('props.transaction.id'))->toBe($meu['tx_id']);
    expect((int) $r->json('props.transaction.business_id'))->toBe($this->bizId);
    expect($r->json('props.payment_types'))->toBeArray();
    expect($r->json('props.payment_types'))->not->toBeEmpty();
    expect(array_key_exists('accounts', (array) $r->json('props')))->toBeTrue();
});

// UC-TPEDT-03 — charter §Tier 0 RBAC ("edit_purchase_payment OR edit_sell_payment") +
//   paridade do modal legado (`edit()` e `update()` abortam 403 sem as permissões).
it('UC-TPEDT-03 · sem permissão de editar pagamento, a tela não abre e o PUT não muda o banco', function () {
    $meu = tpEdtVendaPaga($this->bizId);

    $semPermissao = User::factory()->create([
        'business_id' => $this->bizId,
        'user_type' => 'user',
        'username' => 'tpedt-sem-perm-'.uniqid(),
    ]);
    $semPermissao = User::findOrFail($semPermissao->id);
    // ANTI-VÁCUO: nenhuma das 4 permissões que o update() aceita.
    foreach (['edit_sell_payment', 'edit_purchase_payment', 'all_expense.access', 'view_own_expense'] as $p) {
        expect($semPermissao->can($p))->toBeFalse();
    }

    $this->actingAs($semPermissao);
    session(array_merge($this->sessao, ['user.id' => $semPermissao->id]));

    $this->withHeaders(tpEdtInertiaHeaders())->get("/payments/v2/{$meu['payment_id']}/edit")->assertStatus(403);

    $this->withHeaders(tpEdtInertiaHeaders())->put("/payments/{$meu['payment_id']}", [
        'amount' => '10,00', 'method' => 'cash', 'paid_on' => '05/10/2026 09:00', 'note' => 'nao-deveria',
    ])->assertStatus(403);

    $depois = tpEdtPagamento($meu['payment_id']);
    expect(round((float) $depois->amount, 2))->toBe(100.0);
    expect((string) $depois->paid_on)->toBe('2026-10-01 08:00:00');
    expect(DB::table('transactions')->where('id', $meu['tx_id'])->value('payment_status'))->toBe('paid');
});

// UC-TPEDT-04 — charter §Diferenças ("mesmo endpoint PUT /payments/{id} reusado") + paridade
//   do modal legado (`edit_payment_row` → `update`) + REGRA MESTRE (dupla prova do valor).
it('UC-TPEDT-04 · editar o valor com o payload do modal legado grava o valor e recalcula a situação da venda', function () {
    $meu = tpEdtVendaPaga($this->bizId);

    $this->withHeaders(tpEdtInertiaHeaders())->put("/payments/{$meu['payment_id']}", [
        'amount' => '60,00', 'method' => 'cash', 'paid_on' => '10/10/2026 09:15', 'note' => 'parcial',
    ]);

    // Antes → depois (à mão): pagamento 100,00 → 60,00; venda de 100,00 passa de paga a parcial.
    // Caminho 1 — a linha do pagamento.
    $depois = tpEdtPagamento($meu['payment_id']);
    expect($depois)->not->toBeNull();
    expect(round((float) $depois->amount, 2))->toBe(60.0);
    expect((string) $depois->paid_on)->toBe('2026-10-10 09:15:00');
    // Caminho 2 — o lado da venda: soma dos pagamentos e a situação recalculada.
    $somaPaga = (float) DB::table('transaction_payments')->where('transaction_id', $meu['tx_id'])->sum('amount');
    expect(round($somaPaga, 2))->toBe(60.0);
    expect(DB::table('transactions')->where('id', $meu['tx_id'])->value('payment_status'))->toBe('partial');
});

// UC-TPEDT-05 — charter §Intent ("editar ... em página full ao invés de modal") + §Diferenças
//   (submit reusa `PUT /payments/{id}`) + RUNBOOK F2 ("POST update reusa update() existente").
//   O payload é o que `Edit.tsx` manda com o formulário aberto e só o valor trocado:
//   `paid_on` = `payment_line.paid_on.substring(0, 16)` ("Y-m-d H:i") e `amount` de
//   `<input type="number">` (ponto decimal).
it('UC-TPEDT-05 · salvar pela tela React grava o valor digitado', function () {
    $meu = tpEdtVendaPaga($this->bizId);

    $this->withHeaders(tpEdtInertiaHeaders())->put("/payments/{$meu['payment_id']}", [
        'amount' => '60.5',
        'method' => 'cash',
        'paid_on' => '2026-10-01 08:00',
        'note' => '',
        'account_id' => '',
        'card_holder_name' => '', 'card_number' => '', 'card_transaction_number' => '',
        'cheque_number' => '', 'bank_account_number' => '',
        'transaction_no_1' => '', 'transaction_no_2' => '', 'transaction_no_3' => '',
    ]);

    // Diagnóstico: o update() devolve o desfecho no flash `status` (success true/false).
    $this->assertTrue((bool) (session('status')['success'] ?? false), 'update() devolveu success=false para o payload da tela React');

    // Caminho 1 — a linha do pagamento.
    $depois = tpEdtPagamento($meu['payment_id']);
    expect($depois)->not->toBeNull();
    expect(round((float) $depois->amount, 2))->toBe(60.5);
    // Caminho 2 — o lado da venda.
    expect(DB::table('transactions')->where('id', $meu['tx_id'])->value('payment_status'))->toBe('partial');
});

// UC-TPEDT-06 — charter §Tier 0 (multi-tenant) + RUNBOOK §Tier 0 ("Transaction::where('business_id')
//   em todo query") + ADR 0093.
it('UC-TPEDT-06 · PUT no pagamento de outro business não muda o pagamento nem a venda alheia', function () {
    $outroBiz = EstoqueFixture::secondBusinessId();
    if ($outroBiz === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant.');
    }
    $meu = tpEdtVendaPaga($this->bizId);
    $alheio = tpEdtVendaPaga($outroBiz);
    $payload = ['amount' => '10,00', 'method' => 'cash', 'paid_on' => '05/10/2026 09:00', 'note' => 'invasao'];

    // CONTROLE POSITIVO (anti-vácuo): o MESMO user e o MESMO payload alteram o pagamento próprio.
    $this->withHeaders(tpEdtInertiaHeaders())->put("/payments/{$meu['payment_id']}", $payload);
    expect(round((float) tpEdtPagamento($meu['payment_id'])->amount, 2))->toBe(10.0);

    $this->withHeaders(tpEdtInertiaHeaders())->put("/payments/{$alheio['payment_id']}", $payload);

    $depois = tpEdtPagamento($alheio['payment_id']);
    expect(round((float) $depois->amount, 2))->toBe(100.0);
    expect((string) $depois->paid_on)->toBe('2026-10-01 08:00:00');
    expect($depois->note)->toBeNull();
    expect(DB::table('transactions')->where('id', $alheio['tx_id'])->value('payment_status'))->toBe('paid');
});

// UC-TPEDT-07 — mesmas âncoras do UC-TPEDT-06, no pagamento que nasce de um adiantamento
//   (`parent_id`): o adiantamento alheio também é dado de outro business.
it('UC-TPEDT-07 · PUT no pagamento alheio não mexe no adiantamento-pai alheio', function () {
    $outroBiz = EstoqueFixture::secondBusinessId();
    if ($outroBiz === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant.');
    }

    $paiId = (int) DB::table('transaction_payments')->insertGetId([
        'business_id' => $outroBiz,
        'transaction_id' => null,
        'amount' => 500.00,
        'method' => 'advance',
        'is_advance' => 1,
        'paid_on' => '2026-09-30 10:00:00',
        'created_by' => EstoqueFixture::userId($outroBiz),
        'payment_ref_no' => 'TPEDT-ADV-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $alheio = tpEdtVendaPaga($outroBiz, $paiId);

    // ANTI-VÁCUO: o filho alheio aponta mesmo para o pai alheio.
    expect((int) tpEdtPagamento($alheio['payment_id'])->parent_id)->toBe($paiId);

    $this->withHeaders(tpEdtInertiaHeaders())->put("/payments/{$alheio['payment_id']}", [
        'amount' => '10,00', 'method' => 'cash', 'paid_on' => '05/10/2026 09:00',
    ]);

    // Antes → depois esperado: NADA muda. Adiantamento alheio segue 500,00; filho alheio 100,00.
    expect(round((float) tpEdtPagamento($paiId)->amount, 2))->toBe(500.0);
    expect(round((float) tpEdtPagamento($alheio['payment_id'])->amount, 2))->toBe(100.0);
});
