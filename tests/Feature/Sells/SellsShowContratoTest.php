<?php

declare(strict_types=1);
// Cobre UC-VSHOW-01, UC-VSHOW-02, UC-VSHOW-03, UC-VSHOW-04, UC-VSHOW-05, UC-VSHOW-06,
// UC-VSHOW-07 (resources/js/Pages/Sells/Show.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de COMPORTAMENTO do detalhe da venda (`GET /sells/{id}` → `Sells/Show`).
 *
 * ÂNCORA (contrato, NÃO implementação) — ordem de fonte canônica (memory/how-trabalhar.md):
 *   1. CANON — `Show.charter.md` §Goals (multi-tenant Tier 0 · gate das 3 permissões · 4 KPIs
 *      Total/Pago/Falta/Status · `detail` deferido) + §Non-Goals (❌ edição inline · ❌ mudar
 *      stage FSM direto) + `RUNBOOK-show.md` §2 pré-condições / §9 DoD / §10 pegadinhas +
 *      `CASOS-USO-PIPELINE-VENDAS.md` §CU-07 (timeline auditável visível ao operador) +
 *      ADR 0093 (Tier 0) · ADR 0143 (FSM trait-protected) · ADR 0101 (biz=1, nunca biz=4).
 *   2. CÓDIGO — `SellController@show` (:2386) lido só pra CONFIRMAR o comportamento; nenhum
 *      assert daqui foi derivado dele (teste derivado do código é tautológico — proibicoes §5
 *      2026-06-05).
 *   3. DELPHI — sem `ANTI-REGRESSAO-*` pra Sells no canon (varrido: só existe pro Produto).
 *      Sem contrato de paridade, então nenhum caso alega paridade legada.
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────────────────
 * `Sells/Show.tsx` é o topo do débito Tier-0 (`node scripts/qa/exposicao-tier0.mjs`:
 * exposure_score 11, categorias dinheiro+estoque+fiscal) e não tinha `.casos.md` nem um único
 * teste de COMPORTAMENTO. Os 3 arquivos que pareciam cobri-la — `Wave1ShowBaselineTest`,
 * `Wave1ShowInertiaTest`, `SellsShowCoworkTest` — são estruturais: leem o `.tsx`/Controller com
 * `file_get_contents` e casam string. Provam que o código está ESCRITO; nenhum prova que a
 * resposta HTTP faz o que o charter promete.
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato, não do código.
 *    Vermelho aqui É o achado — não se ajusta o teste ao comportamento observado.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): todo caso que afirma "X não aparece / nada mudou"
 *    carrega pré-condição provando que a operação ACONTECEU (200 + component + prop esperada).
 *    Sem isso o teste mede NÃO-EXECUÇÃO e chama de contrato cumprido.
 *
 * ⛔ Alcance em prod: `show()` gateia o Inertia por `request()->header('X-Inertia')` (:2458) —
 *    NÃO por `?v=2` (esse padrão é do Purchase/StockAdjustment/StockTransfer). A tela React É
 *    alcançável hoje: 7 sites usam `<Link>`/`router.visit` pra `/sells/{id}` (SaleSheet:855 ·
 *    Cliente/_show/{SalesTab,PaymentsTab,RewardPointsTab,SubscriptionsTab} · Fiscal/NotaDrawer:350 ·
 *    Nfse/Show:294), e todos mandam o header. Vermelho aqui é incidente de produção, não
 *    bloqueador de migração.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business seedado.
 *    NUNCA biz=4 (ROTA LIVRE, cliente real).
 *
 * @see app/Http/Controllers/SellController.php::show (branch X-Inertia :2458)
 * @see resources/js/Pages/Sells/Show.tsx
 * @see resources/js/Pages/Sells/Show.charter.md · Show.casos.md
 */
uses(DatabaseTransactions::class);

/** Mesma version que o servidor calcula — evita o 409 antes de o controller rodar. */
function sellsShowInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET do detalhe da venda pelo caminho Inertia (o que o `<Link>` da SaleSheet faz). */
function sellsShowGet(object $test, int $saleId, array $extraHeaders = [])
{
    return $test->withHeaders(array_merge([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => sellsShowInertiaVersion(),
    ], $extraHeaders))->get("/sells/{$saleId}");
}

/** Venda `final` do business com 1 linha — o estado inicial vem de INSERT, não do fluxo sob teste. */
function sellsShowVenda(int $bizId, float $qty = 2.0, float $preco = 50.0): array
{
    $produto = EstoqueFixture::singleProduct($bizId);
    $locationId = EstoqueFixture::locationId($bizId);

    return EstoqueFixture::saleWithLine($produto, 0, $locationId, $qty, $preco);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    foreach (['transactions', 'transaction_sell_lines', 'transaction_payments'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — rode migrations base.");
        }
    }

    $this->bizId = EstoqueFixture::businessId(); // biz=1 (ADR 0101 — nunca biz=4).

    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->bizId,
        'user.id' => $this->user->id,
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // O user do seed da lane MySQL NÃO tem a role `Admin#{biz}` (o `Gate::before` do
    // AuthServiceProvider:41 só libera geral pra ela), então a permissão de leitura entra
    // explícita. Os casos 02/03 criam users PRÓPRIOS — não herdam isto.
    Permission::findOrCreate('sell.view', 'web');
    $this->user->givePermissionTo('sell.view');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

// =============================================================================
// UC-VSHOW-01 — charter §Goals "Multi-tenant Tier 0: scope business_id (ADR 0093)".
//   Abrir a venda de OUTRO business não entrega dado nenhum.
// =============================================================================

it('UC-VSHOW-01 · venda de outro business não abre (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheia = sellsShowVenda($outroBizId);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a venda alheia EXISTE (senão o 404 seria "id inexistente",
    // não "isolamento de tenant" — verde por não haver o que vazar).
    expect(DB::table('transactions')->where('id', $alheia['transaction_id'])->exists())->toBeTrue();

    sellsShowGet($this, $alheia['transaction_id'])->assertStatus(404);
});

// =============================================================================
// UC-VSHOW-02 — charter §Goals "Permission gate: sell.view OR direct_sell.access OR
//   view_own_sell_only" (RUNBOOK-show §2). Sem NENHUMA das três, a venda não abre.
// =============================================================================

it('UC-VSHOW-02 · sem nenhuma das 3 permissões de venda, o detalhe é negado', function () {
    $venda = sellsShowVenda($this->bizId);

    // User limpo do MESMO business: sem role Admin#{biz} (senão `Gate::before` do
    // AuthServiceProvider:41 devolve true pra tudo e o caso mediria o cenário errado).
    $semPermissao = User::factory()->create([
        'business_id' => $this->bizId,
        'user_type' => 'user',
        'username' => 'vshow-sem-perm-' . uniqid(),
    ]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o cenário é o pretendido — o user realmente não tem as 3.
    foreach (['sell.view', 'direct_sell.access', 'view_own_sell_only'] as $p) {
        expect($semPermissao->can($p))->toBeFalse("User de teste veio COM {$p} — cenário inválido.");
    }

    $this->actingAs($semPermissao);
    session(['user.business_id' => $this->bizId, 'user.id' => $semPermissao->id]);

    $status = sellsShowGet($this, $venda['transaction_id'])->status();

    // 403 (gate do controller) ou 302/401 (camada de auth) — o contrato é NÃO ENTREGAR a venda.
    expect($status)->not->toBe(200,
        'A venda abriu para usuário sem sell.view / direct_sell.access / view_own_sell_only — '
        . 'o gate do charter §Goals não está valendo.');
});

// =============================================================================
// UC-VSHOW-03 — charter §Goals + RUNBOOK-show §2: quem só tem `view_own_sell_only`
//   enxerga APENAS as próprias vendas — venda de outro vendedor do mesmo business não abre.
// =============================================================================

it('UC-VSHOW-03 · com view_own_sell_only, a venda de outro vendedor não abre', function () {
    $vendaDoOutro = sellsShowVenda($this->bizId);

    $vendedor = User::factory()->create([
        'business_id' => $this->bizId,
        'user_type' => 'user',
        'username' => 'vshow-own-only-' . uniqid(),
    ]);

    foreach (['sell.view', 'direct_sell.access', 'view_own_sell_only'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $vendedor->givePermissionTo('view_own_sell_only');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (a): cenário válido — tem SÓ a permissão restrita.
    expect($vendedor->can('view_own_sell_only'))->toBeTrue();
    expect($vendedor->can('sell.view'))->toBeFalse('User ganhou sell.view — a restrição nem se aplicaria.');
    expect($vendedor->can('direct_sell.access'))->toBeFalse();

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (b): a venda é MESMO de outro criador, no mesmo business.
    $criador = (int) DB::table('transactions')->where('id', $vendaDoOutro['transaction_id'])->value('created_by');
    expect($criador)->not->toBe($vendedor->id);

    $this->actingAs($vendedor);
    session(['user.business_id' => $this->bizId, 'user.id' => $vendedor->id]);

    sellsShowGet($this, $vendaDoOutro['transaction_id'])->assertStatus(404);
});

// =============================================================================
// UC-VSHOW-04 — charter §Goals "4 KPIs grandes: Total / Pago / Falta / Status pgto" +
//   REGRA MESTRE valor (proibicoes): o dinheiro que a tela mostra é o dinheiro do banco.
// =============================================================================

it('UC-VSHOW-04 · os KPIs de dinheiro batem com os pagamentos registrados', function () {
    $venda = sellsShowVenda($this->bizId, 2.0, 50.0); // final_total = 100,00
    $saleId = $venda['transaction_id'];

    // Estado inicial por INSERT (não pelo fluxo sob teste — anti-tautologia).
    // Parcial de propósito: 30 + 25 = 55 pagos de 100 → "Falta" = 45.
    DB::table('transactions')->where('id', $saleId)->update([
        'final_total' => 100.0,
        'payment_status' => 'partial',
    ]);
    foreach ([30.0, 25.0] as $i => $valor) {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $saleId,
            'business_id' => $this->bizId,
            'amount' => $valor,
            'method' => 'cash',
            'paid_on' => now(),
            'created_by' => $this->user->id,
            'payment_ref_no' => 'VSHOW-' . $i . '-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = sellsShowGet($this, $saleId);
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a tela REALMENTE renderizou com o cabeçalho.
    expect($page['component'])->toBe('Sells/Show');
    expect($page['props'])->toHaveKey('headline');

    $headline = $page['props']['headline'];

    // O CONTRATO: Total, Pago e Status vêm do banco; Falta (derivada no front, Show.tsx:303)
    // fecha a conta. Tolerância de centavo por causa do decimal(22,4).
    expect(round((float) $headline['final_total'], 2))->toBe(100.0);
    expect(round((float) $headline['total_paid'], 2))->toBe(55.0,
        'O KPI "Pago" não é a soma dos pagamentos da venda — a tela mente sobre quanto o cliente já pagou.');
    expect((string) $headline['payment_status'])->toBe('partial');
    expect(round((float) $headline['final_total'] - (float) $headline['total_paid'], 2))->toBe(45.0);
});

// =============================================================================
// UC-VSHOW-05 — charter §Goals "Detail prop deferred via Inertia::defer()" + §UX Targets
//   (p95 first-paint < 800ms) + RUNBOOK-show §10 ("NÃO eager-load 8 with() na resposta inicial").
// =============================================================================

it('UC-VSHOW-05 · o detalhe pesado não viaja na primeira resposta (defer)', function () {
    $venda = sellsShowVenda($this->bizId);

    $response = sellsShowGet($this, $venda['transaction_id']);
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a tela renderizou de verdade — "detail ausente" só significa
    // "deferido" se o resto do payload CHEGOU.
    expect($page['component'])->toBe('Sells/Show');
    expect($page['props'])->toHaveKey('headline');
    expect($page['props'])->toHaveKey('permissions');

    // O CONTRATO: `detail` fica de fora do primeiro response...
    expect($page['props'])->not->toHaveKey('detail');

    // ...e chega quando o front o pede (o `<Deferred data="detail">` do Show.tsx). Sem esta
    // segunda metade, o caso passaria se alguém simplesmente DELETASSE a prop.
    $deferido = sellsShowGet($this, $venda['transaction_id'], [
        'X-Inertia-Partial-Component' => 'Sells/Show',
        'X-Inertia-Partial-Data' => 'detail',
    ]);
    $deferido->assertStatus(200);
    $paginaDetalhe = json_decode($deferido->getContent(), true);

    expect($paginaDetalhe['props'])->toHaveKey('detail');
    expect($paginaDetalhe['props']['detail'])->toHaveKey('lines');
    expect(count($paginaDetalhe['props']['detail']['lines']))->toBeGreaterThanOrEqual(1);
});

// =============================================================================
// UC-VSHOW-06 — charter §Non-Goals "❌ Edição inline" + "❌ Mudança de stage FSM direto
//   (current_stage_id é trait-protected, ADR 0143)": abrir a venda é leitura pura.
// =============================================================================

it('UC-VSHOW-06 · abrir a venda não altera a venda (GET é leitura pura)', function () {
    $venda = sellsShowVenda($this->bizId);
    $saleId = $venda['transaction_id'];

    $temFsm = Schema::hasColumn('transactions', 'current_stage_id');
    $antes = DB::table('transactions')->where('id', $saleId)
        ->first(array_filter(['updated_at', 'final_total', 'payment_status', $temFsm ? 'current_stage_id' : null]));

    $response = sellsShowGet($this, $saleId);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a request percorreu o caminho inteiro. Sem isto, "nada mudou"
    // seria verdade só porque a request abortou antes de tocar em qualquer coisa.
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'])->toBe('Sells/Show');
    expect($page['props'])->toHaveKey('headline');

    $depois = DB::table('transactions')->where('id', $saleId)
        ->first(array_filter(['updated_at', 'final_total', 'payment_status', $temFsm ? 'current_stage_id' : null]));

    expect((array) $depois)->toBe((array) $antes,
        'Abrir o detalhe da venda escreveu na transação — o charter §Non-Goals proíbe edição '
        . 'inline e a ADR 0143 protege current_stage_id (só via ExecuteStageActionService).');
});

// =============================================================================
// UC-VSHOW-07 — CASOS-USO-PIPELINE-VENDAS §CU-07 "Timeline auditável visível ao operador":
//   quem abre a venda vê QUEM fez O QUÊ e QUANDO — sem abrir o banco.
// =============================================================================

it('UC-VSHOW-07 · o histórico da venda chega ao operador com autor e data', function () {
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('activity_log ausente — sem trilha pra provar o CU-07.');
    }

    $venda = sellsShowVenda($this->bizId);
    $saleId = $venda['transaction_id'];

    // Fato independente semeado por INSERT: a trilha existe no banco ANTES da leitura.
    $descricao = 'VSHOW-CU07-' . uniqid();
    DB::table('activity_log')->insert([
        'log_name' => 'default',
        'description' => $descricao,
        'subject_id' => $saleId,
        'subject_type' => \App\Transaction::class,
        'business_id' => $this->bizId,
        'causer_id' => $this->user->id,
        'causer_type' => User::class,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = sellsShowGet($this, $saleId, [
        'X-Inertia-Partial-Component' => 'Sells/Show',
        'X-Inertia-Partial-Data' => 'detail',
    ]);
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a prop deferida chegou (senão "não achei o item" seria trivial).
    expect($page['props'])->toHaveKey('detail');
    expect($page['props']['detail'])->toHaveKey('activities');

    $atividades = $page['props']['detail']['activities'];

    // O CONTRATO (CU-07): o item da trilha chega IDENTIFICADO — o que aconteceu e quando.
    $item = collect($atividades)->firstWhere('description', $descricao);
    expect($item)->not->toBeNull(
        'A trilha registrada em activity_log não chegou ao detalhe da venda — o operador não '
        . 'consegue ver quem fez o quê (CU-07 §Estado atual: "registra tudo, mas não tem UI").');
    expect((string) $item['created_at'])->not->toBeEmpty();
});
