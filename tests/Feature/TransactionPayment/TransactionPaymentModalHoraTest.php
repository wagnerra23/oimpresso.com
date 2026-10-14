<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * MEDIÇÃO — o modal Blade de edição de pagamento move a hora do pagamento ao salvar?
 *
 * Hipótese levantada no smoke de produção (biz=1, 2026-10-14, pagamento 16226): o banco tinha
 * `paid_on` 18:41 e o modal `GET /payments/{id}/edit` mostrava 21:41. O campo é preenchido por
 * `@format_datetime` (AppServiceProvider), que compila para
 * `\Carbon::createFromTimestamp(strtotime($date))`: o `strtotime` lê no fuso da empresa (o
 * middleware `Timezone` faz `date_default_timezone_set`) e o `createFromTimestamp` do Carbon 3
 * formata em UTC. O `update()` lê de volta com `uf_date()` (`createFromFormat`, hora LOCAL) e grava
 * o que recebe. Se a hipótese vale, cada "salvar" pelo modal soma ao `paid_on` o offset do fuso
 * da empresa (3h em America/Sao_Paulo).
 *
 * CONTRATO medido (não derivado do código): abrir o modal e salvar sem alterar nada NÃO muda o
 * pagamento. Âncora: o docblock de `Util::format_date_no_shift` (recibo com hora errada para a
 * Larissa em 2026-05-27 tratado como defeito) + ADR 0066 (o deslocamento foi preservado só para
 * EXIBIÇÃO de vendas antigas, não para regravar dado). Este arquivo só MEDE: nenhum código de
 * produção muda junto. Vermelho aqui é achado; a correção é decisão [W] (regra mestre — data de
 * pagamento entra em relatório financeiro).
 *
 * Controle: o mesmo fluxo com a empresa em UTC. Lá o deslocamento é zero por construção; se o caso
 * de São Paulo cair e o de UTC passar, a causa é o fuso, não a montagem do teste. Sem fixar o fuso
 * na sessão o CI roda em UTC e o caso principal passaria sem provar nada.
 *
 * RESULTADO (run 38582625593, 2026-10-14): hipótese CONFIRMADA. São Paulo: modal mostra 11:00 e
 * grava 11:00 para um pagamento das 08:00. UTC: 08:00 / 08:00. Arquivo em quarentena declarada
 * (.github/financeiro-pest-quarantine.list) até o conserto, que é decisão [W].
 *
 * ⛔ Tier 0: tenant 98. NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function tpHoraVendaPaga(int $bizId): array
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
        'ref_no' => 'TPHORA-'.uniqid(),
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
        'payment_ref_no' => 'TPHORA-PAY-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['tx_id' => $txId, 'payment_id' => $payId];
}

/** Valor do <input name="paid_on"> do modal, como o navegador o leria. */
function tpHoraPaidOnDoModal(string $html): ?string
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

/** Abre o modal e reenvia exatamente o que ele mostra. Devolve [valor no modal, paid_on gravado]. */
function tpHoraAbreESalvaSemMudar($teste, int $paymentId): array
{
    $modal = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get("/payments/{$paymentId}/edit");
    $modal->assertOk();
    $noModal = tpHoraPaidOnDoModal((string) $modal->getContent());
    expect($noModal)->not->toBeNull();

    $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->put("/payments/{$paymentId}", [
        'amount' => '100,00',
        'method' => 'cash',
        'paid_on' => $noModal,
        'note' => '',
    ]);

    // Sucesso de verdade (o 302 de sucesso e o de recusa são o mesmo — §5 2026-09-05).
    expect((bool) (session('status')['success'] ?? false))->toBeTrue();

    return [$noModal, (string) DB::table('transaction_payments')->where('id', $paymentId)->value('paid_on')];
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
    Permission::findOrCreate('edit_sell_payment', 'web');
    $this->user->givePermissionTo('edit_sell_payment');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs(User::findOrFail($this->user->id));
});

afterEach(function () {
    if (isset($this->fusoOriginal)) {
        date_default_timezone_set($this->fusoOriginal);
        config(['app.timezone' => $this->configFusoOriginal]);
    }
});

it('mede: empresa em America/Sao_Paulo — abrir o modal e salvar sem mudar nada mantém a hora do pagamento', function () {
    session($this->sessaoBase + ['business_timezone' => 'America/Sao_Paulo', 'business.time_zone' => 'America/Sao_Paulo']);
    $p = tpHoraVendaPaga($this->bizId);

    [$noModal, $gravado] = tpHoraAbreESalvaSemMudar($this, $p['payment_id']);

    // O que o operador VÊ e o que o banco GUARDA num assert só: a falha mostra os dois valores
    // (o 1º run, 38581375849, parou no 1º assert e não mediu a gravação).
    expect(['no_modal' => $noModal, 'gravado' => $gravado])
        ->toBe(['no_modal' => '01/10/2026 08:00', 'gravado' => '2026-10-01 08:00:00']);
});

it('mede (controle): empresa em UTC — o mesmo fluxo mantém a hora (deslocamento zero por construção)', function () {
    session($this->sessaoBase + ['business_timezone' => 'UTC', 'business.time_zone' => 'UTC']);
    $p = tpHoraVendaPaga($this->bizId);

    [$noModal, $gravado] = tpHoraAbreESalvaSemMudar($this, $p['payment_id']);

    expect($noModal)->toBe('01/10/2026 08:00');
    expect($gravado)->toBe('2026-10-01 08:00:00');
});
