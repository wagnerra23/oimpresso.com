<?php

declare(strict_types=1);

/**
 * Pest test ESTRUTURAL — SellPosController@store invariantes (US-SELL-008 parte 1, caminho A do plano híbrido).
 *
 * Cobre baseline de regressão pré-canary biz=4 ROTA LIVRE (US-SELL-009).
 * Pattern coerente com SellControllerEndpointsTest / SellsCreatePageTest: lê o source PHP
 * como string e valida invariantes via regex/contains. NÃO faz integration HTTP real
 * (UltimatePOS @store tem ~30 deps — fixture full fica pra US-SELL-NNN integration test
 * full quando alguém de fato refatorar store(); por enquanto canary humano biz=1 7d cobre
 * comportamento e este test cobre estrutura).
 *
 * As 5 invariantes principais protegem o pipeline canônico de venda balcão:
 *   I1 · Permission guard (sell.create || direct_sell.access || so.create)
 *   I2 · Multi-tenant Tier 0 (business_id de session, NÃO request input — ADR 0093)
 *   I3 · DB transaction atomicidade (beginTransaction → commit; rollBack em catch)
 *   I4 · Branch is_credit_sale impede createOrUpdatePaymentLines (venda a prazo)
 *   I5 · Split payment suportado (input['payment'] é array iterável)
 *
 * 5 invariantes secundárias (custo ~0, fecham gaps de comportamento conhecido):
 *   I6 · CashRegister aberto pré-venda (countOpenedRegister == 0 → redirect Create Register)
 *   I7 · Credit limit checado ANTES de DB::beginTransaction (fail-fast, evita lock à toa)
 *   I8 · Branch quotation/proforma força status='draft' + is_quotation/sub_status
 *   I9 · SellCreatedOrModified::dispatch APÓS DB::commit (ordem importa pra Observers)
 *   I10 · Pipeline canônico (createSellTransaction + createOrUpdateSellLines + Media::uploadMedia) não bypassed
 *
 * Refs:
 *   - app/Http/Controllers/SellPosController.php @store l.361-740
 *   - memory/requisitos/Sells/SPEC.md US-SELL-008 (Pest 5+ fixtures store)
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md
 *   - tests/Feature/Sells/SellControllerEndpointsTest.php (pattern estrutural)
 *
 * @author Claude Opus 4.7 pareado com Wagner
 * @since 2026-05-15 (canary biz=1 prep)
 */

const SELL_POS_CONTROLLER_PATH = 'app/Http/Controllers/SellPosController.php';

function readSellPosController(): string
{
    return file_get_contents(base_path(SELL_POS_CONTROLLER_PATH));
}

function readSellPosStoreMethod(): string
{
    $source = readSellPosController();
    // Extrai apenas o corpo do método store() — heurística: do `public function store(` até
    // a próxima `public function ` ou fim do arquivo. Reduz falso-positivo de regex que casaria
    // texto em outros métodos.
    $start = strpos($source, 'public function store(Request $request)');
    expect($start)->not->toBeFalse();

    $rest = substr($source, $start);
    $end = strpos($rest, "\n    public function ", strlen('public function store(Request $request)'));
    return $end === false ? $rest : substr($rest, 0, $end);
}

// ──────────────────────────────────────────────────────────────────────────────
// I1 · Permission guard — sell.create || direct_sell.access || so.create
// ──────────────────────────────────────────────────────────────────────────────

it('store() bloqueia user sem permissão (sell.create || direct_sell.access || so.create)', function () {
    $store = readSellPosStoreMethod();

    expect($store)->toContain("can('sell.create')");
    expect($store)->toContain("can('direct_sell.access')");
    expect($store)->toContain("can('so.create')");
    expect($store)->toMatch('/abort\(403/');
});

// ──────────────────────────────────────────────────────────────────────────────
// I2 · Multi-tenant Tier 0 (ADR 0093) — business_id de session, NÃO request input
// ──────────────────────────────────────────────────────────────────────────────

it('store() lê business_id da session (Tier 0 multi-tenant — ADR 0093), nunca de request input', function () {
    $store = readSellPosStoreMethod();

    // Padrão canônico UltimatePOS: session()->get('user.business_id') ou variantes
    expect($store)->toMatch('/\$business_id\s*=\s*\$request->session\(\)->get\([\'"]user\.business_id[\'"]\)/');

    // Anti-padrão: business_id vindo do request input direto seria leak Tier 0.
    expect($store)->not->toMatch('/\$business_id\s*=\s*\$request->input\([\'"]business_id[\'"]\)/');
    expect($store)->not->toMatch('/\$business_id\s*=\s*\$request->get\([\'"]business_id[\'"]\)/');
});

// ──────────────────────────────────────────────────────────────────────────────
// I3 · DB transaction atomicidade — beginTransaction → commit / rollBack em catch
// ──────────────────────────────────────────────────────────────────────────────

it('store() envolve criação de venda em DB::beginTransaction → DB::commit', function () {
    $store = readSellPosStoreMethod();

    expect($store)->toContain('DB::beginTransaction()');
    expect($store)->toContain('DB::commit()');

    // commit DEVE aparecer DEPOIS de beginTransaction no source (ordem importa).
    $posBegin = strpos($store, 'DB::beginTransaction()');
    $posCommit = strpos($store, 'DB::commit()');
    expect($posBegin)->toBeLessThan($posCommit);
});

it('store() faz DB::rollBack() em catch (atomicidade preserve em exception)', function () {
    $store = readSellPosStoreMethod();

    expect($store)->toMatch('/catch\s*\(\\\\?Exception\s+\$e\)\s*\{[^}]*?DB::rollBack/s');
});

// ──────────────────────────────────────────────────────────────────────────────
// I4 · Branch is_credit_sale — venda a prazo NÃO cria transaction_payments
// ──────────────────────────────────────────────────────────────────────────────

it('store() NÃO chama createOrUpdatePaymentLines quando is_credit_sale=1 (venda a prazo fica payment_status=due implícito)', function () {
    $store = readSellPosStoreMethod();

    // Padrão canônico: `if (!$transaction->is_suspend && !empty($input['payment']) && !$is_credit_sale)`
    expect($store)->toMatch('/if\s*\(.*?!\$is_credit_sale.*?\)\s*\{[\s\n]+\$this->transactionUtil->createOrUpdatePaymentLines/s');

    // is_credit_sale vem do input
    expect($store)->toMatch('/\$is_credit_sale\s*=\s*isset\(\$input\[[\'"]is_credit_sale[\'"]\]\)/');
});

// ──────────────────────────────────────────────────────────────────────────────
// I5 · Split payment — input['payment'] é array iterável
// ──────────────────────────────────────────────────────────────────────────────

it('store() suporta split payment via input[payment] como array (não single linha)', function () {
    $store = readSellPosStoreMethod();

    // change_return é appendado como nova linha do array — comprova que é array, não single object
    expect($store)->toMatch('/\$input\[[\'"]payment[\'"]\]\[\]\s*=\s*\$change_return/');

    // createOrUpdatePaymentLines recebe o array inteiro (não item-by-item)
    expect($store)->toContain("createOrUpdatePaymentLines(\$transaction, \$input['payment'])");
});

// ──────────────────────────────────────────────────────────────────────────────
// I6 · CashRegister aberto pré-venda (não-direct-sale)
// ──────────────────────────────────────────────────────────────────────────────

it('store() redireciona pra CashRegister/Create quando !is_direct_sale && countOpenedRegister==0', function () {
    $store = readSellPosStoreMethod();

    expect($store)->toMatch('/!\$is_direct_sale\s*&&\s*\$this->cashRegisterUtil->countOpenedRegister\(\)\s*==\s*0/');
    expect($store)->toMatch('/redirect\(\)->action\(\[\\\\?App\\\\Http\\\\Controllers\\\\CashRegisterController::class,\s*[\'"]create[\'"]\]\)/');
});

// ──────────────────────────────────────────────────────────────────────────────
// I7 · Credit limit ANTES de DB::beginTransaction (fail-fast)
// ──────────────────────────────────────────────────────────────────────────────

it('store() checa isCustomerCreditLimitExeeded ANTES de abrir DB transaction (fail-fast, evita lock à toa)', function () {
    $store = readSellPosStoreMethod();

    $posCreditCheck = strpos($store, 'isCustomerCreditLimitExeeded');
    $posBeginTx = strpos($store, 'DB::beginTransaction()');

    expect($posCreditCheck)->not->toBeFalse();
    expect($posBeginTx)->not->toBeFalse();
    expect($posCreditCheck)->toBeLessThan($posBeginTx);
});

// ──────────────────────────────────────────────────────────────────────────────
// I8 · Quotation/proforma branch
// ──────────────────────────────────────────────────────────────────────────────

it('store() converte status=quotation pra status=draft + is_quotation=1 + sub_status=quotation', function () {
    $store = readSellPosStoreMethod();

    expect($store)->toMatch('/if\s*\(\s*\$input\[[\'"]status[\'"]\]\s*==\s*[\'"]quotation[\'"]\s*\)\s*\{/');
    // Dentro do branch: força draft + sub_status=quotation
    expect($store)->toMatch('/\$input\[[\'"]status[\'"]\]\s*=\s*[\'"]draft[\'"]/');
    expect($store)->toMatch('/\$input\[[\'"]is_quotation[\'"]\]\s*=\s*1/');
    expect($store)->toMatch('/\$input\[[\'"]sub_status[\'"]\]\s*=\s*[\'"]quotation[\'"]/');
});

it('store() converte status=proforma pra status=draft + sub_status=proforma', function () {
    $store = readSellPosStoreMethod();

    expect($store)->toMatch('/elseif\s*\(\s*\$input\[[\'"]status[\'"]\]\s*==\s*[\'"]proforma[\'"]\s*\)/');
    expect($store)->toMatch('/\$input\[[\'"]sub_status[\'"]\]\s*=\s*[\'"]proforma[\'"]/');
});

// ──────────────────────────────────────────────────────────────────────────────
// I9 · SellCreatedOrModified::dispatch APÓS DB::commit
// ──────────────────────────────────────────────────────────────────────────────

it('store() dispara event SellCreatedOrModified APÓS DB::commit (Observers veem state já persistido)', function () {
    $store = readSellPosStoreMethod();

    $posCommit = strpos($store, 'DB::commit()');
    $posDispatch = strpos($store, 'SellCreatedOrModified::dispatch');

    expect($posCommit)->not->toBeFalse();
    expect($posDispatch)->not->toBeFalse();
    expect($posCommit)->toBeLessThan($posDispatch);
});

// ──────────────────────────────────────────────────────────────────────────────
// I10 · Pipeline canônico — createSellTransaction + createOrUpdateSellLines + Media::uploadMedia
// ──────────────────────────────────────────────────────────────────────────────

it('store() chama o pipeline canônico de criação (createSellTransaction + createOrUpdateSellLines + Media::uploadMedia + activityLog)', function () {
    $store = readSellPosStoreMethod();

    expect($store)->toContain('->createSellTransaction(');
    expect($store)->toContain('->createOrUpdateSellLines(');
    expect($store)->toContain('Media::uploadMedia(');
    expect($store)->toContain("->activityLog(\$transaction, 'added')");

    // Ordem canônica: transaction primeiro, lines depois, media depois, log por último.
    $posCreate = strpos($store, '->createSellTransaction(');
    $posLines = strpos($store, '->createOrUpdateSellLines(');
    $posLog = strpos($store, "->activityLog(\$transaction, 'added')");

    expect($posCreate)->toBeLessThan($posLines);
    expect($posLines)->toBeLessThan($posLog);
});
