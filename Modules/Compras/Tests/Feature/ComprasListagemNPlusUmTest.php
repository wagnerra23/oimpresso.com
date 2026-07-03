<?php

declare(strict_types=1);

use App\Business;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\ComprasService;

/**
 * C15 (CAPTERRA-FICHA) / Gap #6 (AUDIT-SENIOR-2026-05-25 D6.c) — anti-N+1 na
 * listagem de compras.
 *
 * A FICHA marcou `listarCompras().paginate()` "sem `->with()` explícito
 * (N+1 risco nas rows)". A investigação (2026-07-03) mostrou que o achado é
 * FALSO POSITIVO: `ComprasService::listarCompras` devolve
 * `TransactionUtil::getListPurchases`, que faz **JOIN** com `contacts` e
 * `business_locations` e SELECTa `contacts.supplier_business_name` +
 * `BS.name as location_name` como colunas FLAT. O frontend
 * (`resources/js/Pages/Compras/Index.tsx`) lê exatamente essas colunas flat —
 * nunca `row.contact.*`/`row.location.*`. Logo NÃO há lazy-load por linha.
 *
 * `->with(['contact','location'])` seria INCORRETO aqui: o SELECT não inclui
 * as FKs `contact_id`/`location_id` e o `groupBy('transactions.id')` quebraria
 * o matching da hidratação Eloquent — custo sem benefício, além de tocar util
 * compartilhado (Sells/Expense).
 *
 * Este teste TRAVA o invariante correto: a contagem de queries da listagem é
 * CONSTANTE independente do nº de linhas. Se alguém trocar o JOIN por relation
 * lazy (`->contact->supplier_business_name` por linha), o teste quebra.
 *
 * Roda contra MySQL real (CT 100 `oimpresso-staging`, ADR 0062) — schema
 * UltimatePOS completo com joins polimórficos da tabela `transactions`.
 *
 * Refs:
 *   - CAPTERRA-FICHA.md C15 + AUDIT-SENIOR-2026-05-25 §D6.c Gap #6
 *   - app/Utils/TransactionUtil.php::getListPurchases (JOIN canônico)
 *   - ADR 0093 Multi-tenant Tier 0 (biz=1, ADR 0101 nunca cliente real)
 *   - ADR 0062 Testes no CT 100
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    try {
        $this->biz = Business::find(1) ?? Business::forceCreate([
            'id' => 1,
            'name' => 'Test Biz Primary (auto)',
            'currency_id' => 1,
            'start_date' => Carbon::now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => 1,
        ]);
    } catch (\Throwable $e) {
        $this->markTestSkipped(
            'Schema UltimatePOS ausente (tabela business/transactions): '
            . $e->getMessage() . ' — rode contra MySQL real (CT 100 staging).'
        );
    }

    $this->user = User::factory()->create([
        'business_id' => $this->biz->id,
        'username' => 'compras_nplus1_' . uniqid(),
    ]);

    // Location de biz=1 pra satisfazer o INNER JOIN `business_locations AS BS`.
    $this->location = DB::table('business_locations')
        ->where('business_id', $this->biz->id)->first();
    if (! $this->location) {
        $locId = DB::table('business_locations')->insertGetId([
            'business_id' => $this->biz->id,
            'name' => 'Loc NPlus1',
            'location_id' => 'LOCN1',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->location = DB::table('business_locations')->find($locId);
    }

    // Fornecedor real em biz=1 → `contact_id` populado faz o leftJoin `contacts`
    // devolver `supplier_business_name` (cenário FIEL ao cockpit renderizando a
    // coluna "Fornecedor" por linha — que é onde a FICHA suspeitou de N+1).
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz->id,
        'type' => 'supplier',
        'name' => 'Fornecedor NPlus1',
        'supplier_business_name' => 'FORNECEDOR NPLUS1 LTDA',
        'contact_id' => 'CT-N1-' . uniqid(),
        'created_by' => $this->user->id, // FK NOT NULL contacts.created_by → users.id
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

/**
 * Cria $n compras (type=purchase) ligadas ao fornecedor+location de biz=1.
 */
function comprasNPlus1Seed(object $t, int $n): void
{
    for ($i = 0; $i < $n; $i++) {
        Transaction::forceCreate([
            'business_id' => $t->biz->id,
            'location_id' => $t->location->id,
            'contact_id' => $t->contactId,
            'type' => 'purchase',
            'status' => 'received',
            'payment_status' => 'due',
            'transaction_date' => Carbon::now()->toDateTimeString(),
            'ref_no' => 'COM-N1-' . $i . '-' . uniqid(),
            'final_total' => 500,
            'total_before_tax' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'created_by' => $t->user->id,
        ]);
    }
}

/**
 * Conta as queries disparadas ao resolver a página inteira E serializar cada
 * linha (`toArray()` = o que o Inertia faz ao mandar `props.rows.data`).
 * Usa o query log (resettável) — não DB::listen (que não desregistra).
 */
function comprasNPlus1CountQueries(ComprasService $svc, int $businessId): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $paginator = $svc->listarCompras($businessId, [])->paginate(200);
    foreach ($paginator->items() as $row) {
        $row->toArray(); // dispararia lazy-load se houvesse relation não-carregada
    }

    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $n;
}

it('listagem de compras NAO escala queries com o nº de linhas (anti-N+1 — C15/D6.c)', function () {
    $svc = app(ComprasService::class);

    // Baseline: 3 compras.
    comprasNPlus1Seed($this, 3);
    $qSmall = comprasNPlus1CountQueries($svc, $this->biz->id);

    // +12 compras (total 15 minhas — 5× mais linhas na página).
    comprasNPlus1Seed($this, 12);
    $qLarge = comprasNPlus1CountQueries($svc, $this->biz->id);

    // Invariante anti-N+1: contagem CONSTANTE independente do nº de linhas.
    // `getListPurchases` traz supplier_business_name + location_name via JOIN
    // (colunas flat no SELECT) → serializar N linhas NÃO dispara N lazy-loads.
    expect($qLarge)->toBe(
        $qSmall,
        "N+1 detectado: 3 linhas => {$qSmall} queries; 15 linhas => {$qLarge} queries. "
        . 'A listagem deve manter contagem CONSTANTE (dados via JOIN em getListPurchases, '
        . 'nunca eager/lazy relation por linha).'
    );

    // Sanity: contagem baixa (paginate = 1 count + 1 select). Bound folgado pra
    // absorver eventual query de infra sem mascarar N+1 real (que seria O(linhas)).
    expect($qLarge)->toBeLessThanOrEqual(4);
});

it('rows trazem supplier_business_name + location_name FLAT via JOIN (sem relation carregada)', function () {
    comprasNPlus1Seed($this, 2);

    $paginator = app(ComprasService::class)->listarCompras($this->biz->id, [])->paginate(200);
    $row = collect($paginator->items())
        ->firstWhere('supplier_business_name', 'FORNECEDOR NPLUS1 LTDA');

    expect($row)->not->toBeNull('linha com supplier_business_name esperado não veio do JOIN');
    expect($row->supplier_business_name)->toBe('FORNECEDOR NPLUS1 LTDA');
    expect($row->location_name)->not->toBeNull('location_name deve vir de BS.name (JOIN)');

    // A prova do "não-N+1": os dados vêm do JOIN, então as relations Eloquent
    // NÃO estão carregadas (não há `->with()` nem lazy-load por linha).
    expect($row->relationLoaded('contact'))->toBeFalse('contact NÃO deve estar eager-loaded (dado vem do JOIN)');
    expect($row->relationLoaded('location'))->toBeFalse('location NÃO deve estar eager-loaded (dado vem do JOIN)');
});
