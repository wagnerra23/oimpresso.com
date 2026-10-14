<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de /payments/v2 (TransactionPayment/Index) — lista cross-transaction de pagamentos.
 *
 * Fonte dos casos: `resources/js/Pages/TransactionPayment/Index.charter.md` (Intent · Props · UX ·
 * Validações Tier 0). Não há US de SPEC para esta tela (varrido 2026-10-14) — ver Index.casos.md.
 * O código (`TransactionPaymentController::indexInertia`) só CONFIRMA; os asserts vêm do charter.
 *
 * UC-TPIDX-01 `[T0]` — a lista mostra só pagamentos do meu negócio.
 * UC-TPIDX-02 `[T0]` — RBAC: `sell.payments` OU `purchase.payments`; nenhuma das duas → 403.
 * UC-TPIDX-03        — filtro tipo: recebido = venda, pago = compra, sem tipo = os dois.
 * UC-TPIDX-04        — filtro status filtra pelo status de pagamento da transação.
 * UC-TPIDX-05 `[V0]` — KPIs deferidos: fora do carregamento inicial; no partial reload somam
 *                      só o meu negócio nos últimos 30 dias (conta à mão × soma da lista).
 * UC-TPIDX-06        — paginação de 50 por página, mais recente primeiro, preservando o filtro.
 *
 * Não duplica o smoke `Modules/Financeiro/Tests/Feature/TransactionPaymentInertiaSmokeTest.php`
 * (em quarentena na lane financeiro-pest — não roda). Este arquivo roda.
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 × business 2 do seed. NUNCA biz=4.
 * Cabeçalhos: os que o cliente Inertia manda de verdade (X-Inertia + X-Requested-With +
 * versão) — §5 2026-09-08.
 */
uses(DatabaseTransactions::class);

const TPIDX_BIZ_ALHEIO = 2;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    foreach (['transactions', 'transaction_payments', 'business_locations', 'invoice_schemes', 'invoice_layouts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode com DB_CONNECTION=mysql.");
        }
    }
    if (! DB::table('business')->where('id', TPIDX_BIZ_ALHEIO)->exists()) {
        $this->markTestSkipped('Sem business 2 no seed (pest-mysql-setup).');
    }

    $this->business = $this->seededTenant();
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->business->id, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->business->id, 'name' => 'Lay '.uniqid()]);
    $this->local = DB::table('business_locations')->insertGetId([
        'business_id' => $this->business->id, 'name' => 'Local '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ]);

    tpidxEntrarCom($this, ['sell.payments']);
});

/** Usuário novo com exatamente estas permissões; sessão montada pelo SetSessionData. */
function tpidxEntrarCom($test, array $permissoes): void
{
    $test->user = $test->usuarioComPermissoes($permissoes, $test->business);
    session()->flush();
    $test->actingAs($test->user);
}

/** Transação + N pagamentos, gravados direto (sem observers — só leitura está sob teste). */
function tpidxPagamento($test, int $biz, string $ref, array $tx = [], array $pagamentos = [[]]): int
{
    $txId = DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $biz, 'location_id' => $test->local, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'paid', 'final_total' => 100, 'total_before_tax' => 100, 'ref_no' => $ref,
        'transaction_date' => now(), 'created_by' => $test->user->id, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $tx));

    foreach ($pagamentos as $i => $p) {
        DB::table('transaction_payments')->insert(array_merge([
            'business_id' => $biz, 'transaction_id' => $txId, 'amount' => 100, 'method' => 'cash',
            'paid_on' => now(), 'created_by' => $test->user->id, 'payment_ref_no' => "{$ref}-P{$i}",
            'created_at' => now(), 'updated_at' => now(),
        ], $p));
    }

    return $txId;
}

function tpidxInertiaVersion(): string
{
    try {
        return (string) (new \App\Http\Middleware\HandleInertiaRequests)->version(request());
    } catch (\Throwable $e) {
        return '';
    }
}

/** Visita Inertia como o browser manda. `$parcial` = partial reload da prop pedida. */
function tpidxGet($test, string $query = '', ?string $parcial = null)
{
    $h = [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => tpidxInertiaVersion(),
        'Accept' => 'text/html, application/xhtml+xml',
    ];
    if ($parcial !== null) {
        $h['X-Inertia-Partial-Data'] = $parcial;
        $h['X-Inertia-Partial-Component'] = 'TransactionPayment/Index';
    }

    return $test->get('/payments/v2'.$query, $h);
}

/** @return array<string, array> linhas do payload indexadas pela ref do pagamento, só as da tag */
function tpidxLinhas($test, string $tag, string $query = ''): array
{
    $r = tpidxGet($test, $query);
    $r->assertOk()->assertJsonPath('component', 'TransactionPayment/Index');

    return collect($r->json('props.pagamentos.data') ?? [])
        ->filter(fn ($l) => str_starts_with((string) ($l['payment_ref_no'] ?? ''), $tag))
        ->keyBy('payment_ref_no')->all();
}

it('UC-TPIDX-01 · a lista mostra o pagamento do meu negócio e não o de outro negócio', function () {
    $tag = 'TPI1'.uniqid();
    $dia = ['paid_on' => '2001-02-10 10:00:00'];
    tpidxPagamento($this, $this->business->id, "$tag-meu", [], [$dia]);
    tpidxPagamento($this, TPIDX_BIZ_ALHEIO, "$tag-alheio", [], [$dia]);

    $linhas = tpidxLinhas($this, $tag, '?from=2001-02-01&to=2001-02-28');

    expect($linhas)->toHaveKey("$tag-meu-P0");        // controle positivo
    expect(count($linhas))->toBe(1);                   // Tier 0: nada além do meu
});

it('UC-TPIDX-02 · entra com sell.payments ou purchase.payments; sem nenhuma das duas, 403', function () {
    // sell.payments (beforeEach)
    tpidxGet($this)->assertOk()->assertJsonPath('component', 'TransactionPayment/Index');

    tpidxEntrarCom($this, ['purchase.payments']);
    tpidxGet($this)->assertOk()->assertJsonPath('component', 'TransactionPayment/Index');

    // Permissão de EDITAR pagamento não abre a LISTA: o charter pede sell/purchase.payments.
    tpidxEntrarCom($this, ['edit_sell_payment']);
    tpidxGet($this)->assertForbidden();

    tpidxEntrarCom($this, []);
    tpidxGet($this)->assertForbidden();
});

it('UC-TPIDX-03 · filtro tipo: recebido mostra a venda, pago mostra a compra, sem tipo os dois', function () {
    $tag = 'TPI3'.uniqid();
    $dia = ['paid_on' => '2001-03-10 10:00:00'];
    tpidxPagamento($this, $this->business->id, "$tag-venda", ['type' => 'sell'], [$dia]);
    tpidxPagamento($this, $this->business->id, "$tag-compra", ['type' => 'purchase'], [$dia]);
    $janela = 'from=2001-03-01&to=2001-03-31';

    $recebido = tpidxLinhas($this, $tag, "?tipo=recebido&$janela");
    $pago = tpidxLinhas($this, $tag, "?tipo=pago&$janela");
    $todos = tpidxLinhas($this, $tag, "?$janela");

    expect(array_keys($recebido))->toBe(["$tag-venda-P0"]);
    expect(array_keys($pago))->toBe(["$tag-compra-P0"]);
    expect(count($todos))->toBe(2);
});

it('UC-TPIDX-04 · filtro status mostra só o pagamento da transação naquele status', function () {
    $tag = 'TPI4'.uniqid();
    $dia = ['paid_on' => '2001-04-10 10:00:00'];
    foreach (['paid', 'partial', 'due'] as $st) {
        tpidxPagamento($this, $this->business->id, "$tag-$st", ['payment_status' => $st], [$dia]);
    }
    $janela = 'from=2001-04-01&to=2001-04-30';

    foreach (['paid', 'partial', 'due'] as $st) {
        $r = tpidxGet($this, "?status=$st&$janela")->assertOk();
        $refs = collect($r->json('props.pagamentos.data'))->pluck('payment_ref_no')
            ->filter(fn ($x) => str_starts_with((string) $x, $tag))->values()->all();

        expect($refs)->toBe(["$tag-$st-P0"]);
        expect($r->json('props.filtros.status'))->toBe($st); // a tela reabre com o chip marcado
    }
});

it('UC-TPIDX-05 · KPIs deferidos somam só o meu negócio nos últimos 30 dias', function () {
    // Carregamento inicial: os KPIs NÃO vêm (Inertia::defer) — a lista não espera o agregado.
    $inicial = tpidxGet($this)->assertOk();
    expect($inicial->json('props'))->not->toHaveKey('kpis');

    $kpis = fn () => (array) tpidxGet($this, '', 'kpis')->assertOk()->json('props.kpis');
    $antes = $kpis();

    $tag = 'TPI5'.uniqid();
    $hoje = now()->subDay()->format('Y-m-d H:i:s');
    tpidxPagamento($this, $this->business->id, "$tag-venda", ['type' => 'sell'], [['amount' => 123.45, 'paid_on' => $hoje]]);
    tpidxPagamento($this, $this->business->id, "$tag-compra", ['type' => 'purchase'], [['amount' => 67.89, 'paid_on' => $hoje]]);
    // fora da janela de 30 dias — não pode somar
    tpidxPagamento($this, $this->business->id, "$tag-velha", ['type' => 'sell'], [['amount' => 500, 'paid_on' => now()->subDays(40)]]);
    // outro negócio, dentro da janela — não pode somar (Tier 0)
    tpidxPagamento($this, TPIDX_BIZ_ALHEIO, "$tag-alheia", ['type' => 'sell', 'payment_status' => 'due'], [['amount' => 1000, 'paid_on' => $hoje]]);
    // pendentes: +1 due e +1 partial no meu negócio; a 'due' alheia acima não conta
    tpidxPagamento($this, $this->business->id, "$tag-due", ['payment_status' => 'due'], []);
    tpidxPagamento($this, $this->business->id, "$tag-parc", ['payment_status' => 'partial'], []);

    $depois = $kpis();

    // Caminho 1: conta à mão.
    expect(round($depois['recebido_30d'] - $antes['recebido_30d'], 2))->toBe(123.45);
    expect(round($depois['pago_30d'] - $antes['pago_30d'], 2))->toBe(67.89);
    expect($depois['pendentes_count'] - $antes['pendentes_count'])->toBe(2);

    // Caminho 2: o mesmo delta lido da LISTA da tela (pagamentos da tag nos últimos 30 dias).
    $desde = now()->subDays(30);
    $soma = fn (string $tipo) => round(collect(tpidxLinhas($this, $tag, "?tipo=$tipo&from=".$desde->toDateString()))
        ->filter(fn ($l) => \Carbon\Carbon::parse($l['paid_on'])->gte($desde))
        ->sum(fn ($l) => (float) $l['amount']), 2);

    expect(round($depois['recebido_30d'] - $antes['recebido_30d'], 2))->toBe($soma('recebido'));
    expect(round($depois['pago_30d'] - $antes['pago_30d'], 2))->toBe($soma('pago'));
});

it('UC-TPIDX-06 · 50 por página, mais recente primeiro, e a página 2 mantém o filtro', function () {
    $tag = 'TPI6'.uniqid();
    $pags = [];
    for ($i = 0; $i < 51; $i++) {
        $pags[] = ['paid_on' => now()->setDate(2001, 6, 1)->startOfDay()->addMinutes($i)->format('Y-m-d H:i:s')];
    }
    tpidxPagamento($this, $this->business->id, $tag, [], $pags);
    $janela = 'from=2001-06-01&to=2001-06-30';

    $p1 = tpidxGet($this, "?$janela")->assertOk();
    $linhas1 = collect($p1->json('props.pagamentos.data'))->filter(fn ($l) => str_starts_with((string) $l['payment_ref_no'], $tag));

    expect($p1->json('props.pagamentos.per_page'))->toBe(50);
    expect($linhas1->count())->toBe(50);
    // mais recente primeiro: o 1º da página é o último minuto gravado
    expect($linhas1->first()['payment_ref_no'])->toBe("$tag-P50");
    $datas = $linhas1->pluck('paid_on')->all();
    $ordenadas = $datas;
    rsort($ordenadas);
    expect($datas)->toBe($ordenadas);

    // o link da página 2 leva o filtro junto (senão a 2ª página mostra outra lista)
    expect((string) $p1->json('props.pagamentos.next_page_url'))->toContain('from=2001-06-01');

    $p2 = tpidxGet($this, "?$janela&page=2")->assertOk();
    $refs2 = collect($p2->json('props.pagamentos.data'))->pluck('payment_ref_no')
        ->filter(fn ($x) => str_starts_with((string) $x, $tag))->values()->all();
    expect($refs2)->toBe(["$tag-P0"]);
});
