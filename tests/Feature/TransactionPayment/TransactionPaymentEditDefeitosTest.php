<?php

declare(strict_types=1);
// Cobre UC-TPEDT-05 (resources/js/Pages/TransactionPayment/Edit.casos.md) — G-2.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * ❌ DEFEITOS MEDIDOS do `PUT /payments/{id}` (`TransactionPaymentController@update`), separados de
 * `TransactionPaymentEditContratoTest.php` por decisão [W] em 2026-10-14 — este arquivo NÃO está
 * na lane `PHP / Pest (Financeiro · MySQL)` (required) até o conserto do `update()`.
 *
 * Recibo: run 38569047207 (e antes 38564874922):
 *   - UC-TPEDT-07 `[T0][V0]` (já CONSERTADO e devolvido ao arquivo-irmão, que roda na lane):
 *     "Failed asserting that 410.0 is identical to 500.0" — PUT sobre pagamento ALHEIO alterava o
 *     adiantamento-pai de OUTRA empresa.
 *   - UC-TPEDT-05 `[V0]`: "update() devolveu success=false" — o payload que o `Edit.tsx` manda
 *     (`paid_on` AAAA-MM-DD HH:MM) não salva.
 *
 * O PR que consertar o UC-TPEDT-05 liga este arquivo na lane: a prova é ele ficar VERDE lá.
 * Mesmo contrato e mesmas âncoras do arquivo-irmão (ver o docblock dele); helpers copiados com
 * prefixo `tpEdtDef` porque o Pest carrega os dois arquivos no mesmo processo.
 *
 * ⛔ Tier 0: tenant 98 × o 2º business semeado. NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function tpEdtDefInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Headers que o `form.put` do @inertiajs/react manda de fato (X-Inertia + X-Requested-With). */
function tpEdtDefInertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => tpEdtDefInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
}

/**
 * Venda `final` de 100,00 com UM pagamento de 100,00 (logo `paid`). Estado inicial por
 * INSERT — não pelo fluxo sob teste.
 *
 * @return array{tx_id:int, payment_id:int}
 */
function tpEdtDefVendaPaga(int $bizId, ?int $parentId = null): array
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

function tpEdtDefPagamento(int $id): ?object
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

// UC-TPEDT-05 — charter §Intent ("editar ... em página full ao invés de modal") + §Diferenças
//   (submit reusa `PUT /payments/{id}`) + RUNBOOK F2 ("POST update reusa update() existente").
//   O payload é o que `Edit.tsx` manda com o formulário aberto e só o valor trocado:
//   `paid_on` = `payment_line.paid_on.substring(0, 16)` ("Y-m-d H:i") e `amount` de
//   `<input type="number">` (ponto decimal).
it('UC-TPEDT-05 · salvar pela tela React grava o valor digitado', function () {
    $meu = tpEdtDefVendaPaga($this->bizId);

    $this->withHeaders(tpEdtDefInertiaHeaders())->put("/payments/{$meu['payment_id']}", [
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
    $depois = tpEdtDefPagamento($meu['payment_id']);
    expect($depois)->not->toBeNull();
    expect(round((float) $depois->amount, 2))->toBe(60.5);
    // Caminho 2 — o lado da venda.
    expect(DB::table('transactions')->where('id', $meu['tx_id'])->value('payment_status'))->toBe('partial');
});

