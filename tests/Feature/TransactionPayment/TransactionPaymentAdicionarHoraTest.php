<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Os modais Blade de ADICIONAR pagamento não gravam mais a hora deslocada.
 *
 * Irmão do TransactionPaymentModalHoraTest (modal de EDIÇÃO, defeito confirmado no run 38582625593).
 * Os dois modais de adicionar pré-preenchem `paid_on` com `Carbon::now()->toDateTimeString()` e o
 * mostram com `@format_datetime` — a mesma diretiva que, no modal de edição, formatava em UTC:
 *   - `transaction_payment/payment_row.blade.php` ← `addPayment()` → grava por `store()` (`uf_date`)
 *   - `transaction_payment/pay_supplier_due_modal.blade.php` ← `getPayContactDue()` → grava por
 *     `postPayContactDue()` → `TransactionUtil::payContact()` (`uf_date`)
 * Hipótese: o operador que só confirma o modal grava o pagamento com a hora + offset do fuso.
 *
 * CONTRATO medido (não derivado do código): o modal pré-preenche com a hora ATUAL e, confirmado sem
 * mudança, grava a hora atual. Âncora: o mesmo do arquivo irmão (Util::format_date_no_shift tratou
 * hora deslocada em recibo como defeito; ADR 0066 preserva o deslocamento só para exibição antiga).
 * Medição (run de 2026-10-14 na lane Financeiro): America/Sao_Paulo vermelho nos 2 modais, UTC verde.
 * CONSERTO ([W] 2026-10-21): os dois modais passaram a preencher o campo com Util::format_date_no_shift,
 * como o modal de edição no #9392. Este arquivo fica na lane como prova do conserto.
 *
 * Relógio fixo em 2026-10-01 08:00 de America/Sao_Paulo. Controle: a mesma coisa com a empresa em UTC.
 *
 * ⛔ Tier 0: tenant 98. NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function tpHoraAddHeaders(): array
{
    return ['X-Requested-With' => 'XMLHttpRequest'];
}

function tpHoraAddPaidOn(string $html): ?string
{
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
    foreach ($dom->getElementsByTagName('input') as $input) {
        if ($input->getAttribute('name') === 'paid_on') {
            return $input->getAttribute('value');
        }
    }

    return null;
}

/** Venda final ainda devida (sem pagamento), do tenant de teste. */
function tpHoraAddVendaDevida(int $bizId): array
{
    $userId = EstoqueFixture::userId($bizId);
    $contactId = (int) DB::table('contacts')->where('business_id', $bizId)->orderBy('id')->value('id');
    $txId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId),
        'contact_id' => $contactId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'final_total' => 100.00,
        'total_before_tax' => 100.00,
        'ref_no' => 'TPHORAADD-'.uniqid(),
        'transaction_date' => '2026-10-01 07:00:00',
        'created_by' => $userId,
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['tx_id' => $txId, 'contact_id' => $contactId];
}

/** Monta a sessão no fuso pedido e fixa o relógio em 2026-10-01 08:00 desse fuso. */
function tpHoraAddFixaRelogio($teste, string $fuso): void
{
    session($teste->sessaoBase + ['business_timezone' => $fuso, 'business.time_zone' => $fuso]);
    Carbon::setTestNow(Carbon::parse('2026-10-01 08:00:00', $fuso));
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'transaction_payments', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}).");
        }
    }

    $this->bizId = EstoqueFixture::businessId(); // tenant 98 (ADR 0358 — nunca biz=4).
    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    // O middleware Timezone muda o fuso do PROCESSO; devolvo ao fim de cada caso.
    $this->fusoOriginal = date_default_timezone_get();
    $this->configFusoOriginal = config('app.timezone');

    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))
        ->first();
    $this->sessaoBase = [
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

    // Sem roles semeadas na lane: a permissão vai DIRETO ao user.
    Permission::findOrCreate('sell.payments', 'web');
    $this->user->givePermissionTo('sell.payments');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs(User::findOrFail($this->user->id));
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset($this->fusoOriginal)) {
        date_default_timezone_set($this->fusoOriginal);
        config(['app.timezone' => $this->configFusoOriginal]);
    }
});

dataset('fusos', [
    'America/Sao_Paulo' => ['America/Sao_Paulo'],
    'UTC (controle)' => ['UTC'],
]);

it('mede: modal de adicionar pagamento à venda pré-preenche e grava a hora atual', function (string $fuso) {
    tpHoraAddFixaRelogio($this, $fuso);
    $v = tpHoraAddVendaDevida($this->bizId);

    $r = $this->withHeaders(tpHoraAddHeaders())->get("/payments/add_payment/{$v['tx_id']}");
    $r->assertOk();
    $noModal = tpHoraAddPaidOn((string) json_decode((string) $r->getContent(), true)['view']);
    expect($noModal)->not->toBeNull();

    $antes = (int) DB::table('transaction_payments')->max('id');
    $this->withHeaders(tpHoraAddHeaders())->post('/payments', [
        'transaction_id' => $v['tx_id'],
        'amount' => '10,00',
        'method' => 'cash',
        'paid_on' => $noModal,
        'note' => '',
    ]);
    $gravado = (string) DB::table('transaction_payments')
        ->where('id', '>', $antes)->where('transaction_id', $v['tx_id'])->value('paid_on');

    expect(['no_modal' => $noModal, 'gravado' => $gravado])
        ->toBe(['no_modal' => '01/10/2026 08:00', 'gravado' => '2026-10-01 08:00:00']);
})->with('fusos');

it('mede: modal de pagar a dívida do contato pré-preenche e grava a hora atual', function (string $fuso) {
    tpHoraAddFixaRelogio($this, $fuso);
    $v = tpHoraAddVendaDevida($this->bizId);

    $r = $this->withHeaders(tpHoraAddHeaders())->get("/payments/pay-contact-due/{$v['contact_id']}?type=sell");
    $r->assertOk();
    $noModal = tpHoraAddPaidOn((string) $r->getContent());
    expect($noModal)->not->toBeNull();

    $antes = (int) DB::table('transaction_payments')->max('id');
    $this->withHeaders(tpHoraAddHeaders())->post('/payments/pay-contact-due', [
        'contact_id' => $v['contact_id'],
        'due_payment_type' => 'sell',
        'amount' => '10,00',
        'method' => 'cash',
        'paid_on' => $noModal,
        'note' => '',
    ]);
    // O pagamento-pai é o que payContact() cria com payment_for = contato.
    $gravado = (string) DB::table('transaction_payments')
        ->where('id', '>', $antes)->where('payment_for', $v['contact_id'])
        ->orderBy('id')->value('paid_on');

    expect(['no_modal' => $noModal, 'gravado' => $gravado])
        ->toBe(['no_modal' => '01/10/2026 08:00', 'gravado' => '2026-10-01 08:00:00']);
})->with('fusos');
