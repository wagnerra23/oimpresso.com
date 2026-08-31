<?php

// @covers-us US-SELL-059

declare(strict_types=1);
// Cobre UC-SEDIT-01, UC-SEDIT-02, UC-SEDIT-03, UC-SEDIT-04, UC-SEDIT-05, UC-SEDIT-06,
// UC-SEDIT-07 (resources/js/Pages/Sells/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de COMPORTAMENTO do editor de venda (`GET /sells/{id}/edit` → `Sells/Edit`).
 *
 * ÂNCORA (contrato, NÃO implementação) — ordem de fonte canônica (memory/how-trabalhar.md):
 *   1. CANON — `Edit.charter.md` §Goals (form deferido via `Inertia::defer()` · FSM safety:
 *      NUNCA setar `current_stage_id`) + §Non-Goals (❌ venda com return → 422 · ❌ prazo
 *      expirado → 422 · ❌ venda doutra biz → 404) + §Endpoints alimentadores +
 *      `RUNBOOK-edit.md` §2 pré-condições / §5 estados / §9 DoD / §10 pegadinhas +
 *      `SDD-tela-venda-v1.0.md` §3.1 (Tier 0) e §3.2 (incidente `num_uf`, contrato de
 *      não-regressão de VALOR) + ADR 0093 (Tier 0) · ADR 0143 (FSM trait-protected) ·
 *      ADR 0358 (tenant fictício 98 — biz=4 proibido).
 *   2. CÓDIGO — `SellController@edit` (:2636) lido só pra CONFIRMAR o comportamento; nenhum
 *      assert daqui foi derivado dele (teste derivado do código é tautológico — proibicoes §5
 *      2026-06-05).
 *   3. DELPHI — sem `ANTI-REGRESSAO-*` pra Sells no canon (varrido: só existe pro Produto).
 *      Sem contrato de paridade, nenhum caso aqui alega paridade legada.
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────────────────
 * `Sells/Edit.tsx` **edita venda** — toca VALOR e ESTOQUE no mesmo request (REGRA MESTRE de
 * `memory/proibicoes.md`) — e está viva em produção (`route-hits:14hit@2026-08-22`). Os
 * arquivos que pareciam cobri-la (`Wave1EditBaselineTest`, `Wave1EditInertiaTest`,
 * `SellsEditCoworkTest`, `SellsEditParkingLotP1P2P3Test`, `CommissionSplitEditorTest`) são
 * ESTRUTURAIS: leem o `.tsx`/Controller com `file_get_contents` e casam string. Provam que o
 * código está ESCRITO; nenhum prova que a resposta HTTP faz o que o charter promete.
 *
 * NÃO DUPLICA o `SellsEditPrefillContractTest`: aquele congela a FORMA do payload deferido
 * (aliases flat, lista sequencial, linha filha excluída); o UC-SEDIT-07 daqui cobre o VALOR
 * que chega nesses campos. Propriedades diferentes do mesmo payload.
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato, não do código.
 *    Vermelho aqui É o achado — não se ajusta o teste ao comportamento observado.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): todo caso que afirma "X não aparece / nada mudou"
 *    carrega pré-condição provando que a operação ACONTECEU (200 + component + prop esperada).
 *
 * ⛔ Multi-tenant Tier 0: tenant canônico **98** (fictício, ADR 0358); cross-tenant contra o 2º
 *    business semeado. NUNCA biz=4 (ROTA LIVRE, cliente real).
 *
 * @see app/Http/Controllers/SellController.php::edit (branch X-Inertia :2949)
 * @see resources/js/Pages/Sells/Edit.tsx
 * @see resources/js/Pages/Sells/Edit.charter.md · Edit.casos.md
 */
uses(DatabaseTransactions::class);

/** Mesma version que o servidor calcula — evita o 409 antes de o controller rodar. */
function sellsEditInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET do editor pelo caminho Inertia (o que o botão "Editar" da lista faz). */
function sellsEditGet(object $test, int $saleId, array $extraHeaders = [])
{
    return $test->withHeaders(array_merge([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => sellsEditInertiaVersion(),
    ], $extraHeaders))->get("/sells/{$saleId}/edit");
}

/** Venda `final` do business com 1 linha — estado inicial por INSERT, não pelo fluxo sob teste. */
function sellsEditVenda(int $bizId, float $qty = 2.0, float $preco = 50.0): array
{
    $produto = EstoqueFixture::singleProduct($bizId);
    $locationId = EstoqueFixture::locationId($bizId);
    $venda = EstoqueFixture::saleWithLine($produto, 0, $locationId, $qty, $preco);

    // Venda real tem cliente; reusa um contato já semeado do business (sem inventar INSERT novo).
    $contactId = DB::table('contacts')->where('business_id', $bizId)->orderBy('id')->value('id');
    if ($contactId) {
        DB::table('transactions')->where('id', $venda['transaction_id'])->update(['contact_id' => $contactId]);
    }

    return $venda;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    foreach (['transactions', 'transaction_sell_lines', 'business_locations'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — rode migrations base.");
        }
    }

    $this->bizId = EstoqueFixture::businessId(); // tenant 98 (ADR 0358 — nunca biz=4).

    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    // O `SetSessionData` só reconstrói a sessão quando `user` está ausente/sem business_id —
    // com o bloco abaixo presente, ele NÃO sobrescreve o `business.*` declarado aqui.
    // `transaction_edit_days` é pré-condição do RUNBOOK-edit §2 e o insumo do UC-SEDIT-04.
    session([
        'user.business_id' => $this->bizId,
        'user.id' => $this->user->id,
        'business.transaction_edit_days' => 30,
        'business.date_format' => 'd/m/Y',
        'business.time_format' => 24,
        // `enabled_modules` é lido por `Util::allModulesEnabled` (app/Utils/Util.php:253) via
        // `session('business')['enabled_modules']` — acesso DIRETO por chave, sem `??`. Como o
        // bloco `business` acima existe, o `has('business')` é true e o fallback por
        // `Business::find()` (:254) nunca roda: sem esta chave o `edit()` morre em
        // `Undefined array key`. Vem do banco, como o `currency` abaixo — não inventado.
        // (Medido no CI em 2026-08-31: era a causa das 4 falhas dos UC-SEDIT-04/05/06/07.)
        'business.enabled_modules' => (array) json_decode(
            (string) DB::table('business')->where('id', $this->bizId)->value('enabled_modules'),
            true
        ),
    ]);

    // `currency` é o que o SetSessionData põe em produção e o que `Util::num_f` lê
    // (`session('currency')['thousand_separator']`) ao formatar `formatted_qty_available`
    // dentro do `edit()`. Sem este bloco a sessão de teste fica MENOS populada que a real e
    // o caminho sob teste roda em condição que produção nunca tem. Vem do banco, não inventado.
    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))
        ->first();
    session(['currency' => [
        'id' => (int) ($currency->id ?? 1),
        'code' => (string) ($currency->code ?? 'BRL'),
        'symbol' => (string) ($currency->symbol ?? 'R$'),
        'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
        'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
    ]]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // O user do seed da lane MySQL NÃO tem a role `Admin#{biz}` (o `Gate::before` do
    // AuthServiceProvider:41 só libera geral pra ela), então a permissão de edição entra
    // explícita. O caso 02 cria user PRÓPRIO — não herda isto.
    Permission::findOrCreate('direct_sell.update', 'web');
    $this->user->givePermissionTo('direct_sell.update');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

// =============================================================================
// UC-SEDIT-01 — charter §Non-Goals "❌ Edição de venda doutra biz (Tier 0 firstOrFail → 404)"
//   + RUNBOOK-edit §10 ("NÃO usar Transaction::find sem scope business_id").
// =============================================================================

it('UC-SEDIT-01 · venda de outro business não abre pra edição (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheia = sellsEditVenda($outroBizId);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (a): a venda alheia EXISTE (senão o 404 seria "id inexistente",
    // não "isolamento de tenant" — verde por não haver o que vazar).
    expect(DB::table('transactions')->where('id', $alheia['transaction_id'])->exists())->toBeTrue();

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (b): ela está DENTRO do prazo de edição. Sem isto o 404 poderia
    // ser mascarado pelo 422 do guard de prazo, que roda ANTES do escopo por business
    // (a variante fora-do-prazo está declarada no §Backlog do Edit.casos.md).
    $data = DB::table('transactions')->where('id', $alheia['transaction_id'])->value('transaction_date');
    expect(\Carbon\Carbon::parse($data)->greaterThanOrEqualTo(now()->subDays(30)))->toBeTrue(
        'A venda alheia nasceu fora da janela de edição — o 422 do guard de prazo mascararia o 404.');

    sellsEditGet($this, $alheia['transaction_id'])->assertStatus(404);
});

// =============================================================================
// UC-SEDIT-02 — RUNBOOK-edit §2 pré-condições / §9 DoD: "Permission gate direct_sell.update
//   OU so.update". Sem NENHUMA das duas, o editor não abre.
// =============================================================================

it('UC-SEDIT-02 · sem direct_sell.update nem so.update, o editor é negado', function () {
    $venda = sellsEditVenda($this->bizId);

    // User limpo do MESMO business: sem role Admin#{biz} (senão `Gate::before` do
    // AuthServiceProvider:41 devolve true pra tudo e o caso mediria o cenário errado).
    $semPermissao = User::factory()->create([
        'business_id' => $this->bizId,
        'user_type' => 'user',
        'username' => 'sedit-sem-perm-' . uniqid(),
    ]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: as 2 permissões EXISTEM no registrar (senão o `can()` daria
    // false por elas não existirem, e o caso mediria "permissão inexistente", não "user sem
    // direito") e o user realmente não tem nenhuma delas.
    foreach (['direct_sell.update', 'so.update'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['direct_sell.update', 'so.update'] as $p) {
        expect($semPermissao->can($p))->toBeFalse("User de teste veio COM {$p} — cenário inválido.");
    }

    $this->actingAs($semPermissao);
    session(['user.business_id' => $this->bizId, 'user.id' => $semPermissao->id]);

    $status = sellsEditGet($this, $venda['transaction_id'])->status();

    // 403 (gate do controller) ou 302/401 (camada de auth) — o contrato é NÃO ENTREGAR o editor.
    expect($status)->not->toBe(200,
        'O editor abriu para usuário sem direct_sell.update / so.update — o gate do RUNBOOK §2 '
        . 'não está valendo, e quem não pode alterar venda chegou na tela que reescreve preço.');
});

// =============================================================================
// UC-SEDIT-03 — charter §Non-Goals "❌ Edição de venda com return associada → backend 422"
//   + RUNBOOK-edit §5 (estado `bloqueado return_exist`) / §10.
// =============================================================================

it('UC-SEDIT-03 · venda com devolução associada não é editável (422)', function () {
    $venda = sellsEditVenda($this->bizId);
    $saleId = $venda['transaction_id'];

    // Fato independente por INSERT: a devolução existe ANTES da leitura.
    DB::table('transactions')->insert([
        'business_id' => $this->bizId,
        'location_id' => EstoqueFixture::locationId($this->bizId),
        'type' => 'sell_return',
        'status' => 'final',
        'payment_status' => 'paid',
        'return_parent_id' => $saleId,
        'transaction_date' => now(),
        'total_before_tax' => 0,
        'final_total' => 0,
        'created_by' => $this->user->id,
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a devolução foi mesmo gravada apontando pra esta venda.
    expect(DB::table('transactions')->where('return_parent_id', $saleId)->exists())->toBeTrue();

    $response = sellsEditGet($this, $saleId);

    $response->assertStatus(422);
    expect($response->json('success'))->toBe(0);
    expect((string) $response->json('msg'))->not->toBeEmpty(
        'O 422 do guard de devolução veio sem mensagem — o front não tem o que mostrar no toast '
        . '(RUNBOOK-edit §5, estado `bloqueado return_exist`).');
});

// =============================================================================
// UC-SEDIT-04 — charter §Non-Goals "❌ Edição após transaction_edit_days expirar → 422"
//   + RUNBOOK-edit §5 (estado `bloqueado edit_days`).
// =============================================================================

it('UC-SEDIT-04 · passado o transaction_edit_days, a venda trava (422) — e a de hoje abre', function () {
    // Metade A — venda velha: fora da janela de 30 dias declarada no beforeEach.
    $velha = sellsEditVenda($this->bizId);
    DB::table('transactions')->where('id', $velha['transaction_id'])
        ->update(['transaction_date' => now()->subDays(90)]);

    $bloqueado = sellsEditGet($this, $velha['transaction_id']);
    $bloqueado->assertStatus(422);
    expect($bloqueado->json('success'))->toBe(0);

    // Metade B — venda de hoje abre. Sem ela, o caso passaria se alguém travasse a tela pra
    // TODO MUNDO: prova que o bloqueio é do PRAZO, não da tela.
    $nova = sellsEditVenda($this->bizId);
    $liberado = sellsEditGet($this, $nova['transaction_id']);
    $liberado->assertStatus(200);
    expect(json_decode($liberado->getContent(), true)['component'])->toBe('Sells/Edit');
});

// =============================================================================
// UC-SEDIT-05 — charter §Goals "Form deferred via Inertia::defer()" + §UX Targets
//   (p95 first-paint < 800ms) + RUNBOOK-edit §9 DoD ("Defer payload + Deferred wrap").
// =============================================================================

it('UC-SEDIT-05 · o formulário pesado não viaja na primeira resposta (defer)', function () {
    $venda = sellsEditVenda($this->bizId);

    $response = sellsEditGet($this, $venda['transaction_id']);
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a tela renderizou de verdade — "form ausente" só significa
    // "deferido" se o resto do payload CHEGOU.
    expect($page['component'])->toBe('Sells/Edit');
    expect($page['props'])->toHaveKey('headline');
    expect($page['props'])->toHaveKey('permissions');
    expect($page['props'])->toHaveKey('urls');

    // O CONTRATO: `form` fica de fora do primeiro response...
    expect($page['props'])->not->toHaveKey('form');

    // ...e chega quando o front o pede (o `<Deferred data="form">` do Edit.tsx). Sem esta
    // segunda metade, o caso passaria se alguém simplesmente DELETASSE a prop.
    $deferido = sellsEditGet($this, $venda['transaction_id'], [
        'X-Inertia-Partial-Component' => 'Sells/Edit',
        'X-Inertia-Partial-Data' => 'form',
    ]);
    $deferido->assertStatus(200);
    $paginaForm = json_decode($deferido->getContent(), true);

    expect($paginaForm['props'])->toHaveKey('form');
    expect($paginaForm['props']['form'])->toHaveKey('sellDetails');
    expect(count($paginaForm['props']['form']['sellDetails']))->toBeGreaterThanOrEqual(1);
});

// =============================================================================
// UC-SEDIT-06 — charter §Goals "FSM safety: NUNCA setar current_stage_id" + §Non-Goals
//   ("❌ Mudança de status pra cancelled/completed direto") + ADR 0143 + REGRA MESTRE.
// =============================================================================

it('UC-SEDIT-06 · abrir o editor não altera a venda (GET é leitura pura)', function () {
    $venda = sellsEditVenda($this->bizId);
    $saleId = $venda['transaction_id'];

    $temFsm = Schema::hasColumn('transactions', 'current_stage_id');
    $colunas = array_filter(['updated_at', 'final_total', 'payment_status', 'status', $temFsm ? 'current_stage_id' : null]);
    $antes = DB::table('transactions')->where('id', $saleId)->first($colunas);

    // Percorre o caminho INTEIRO — inclusive o payload deferido, que é onde mora a lógica pesada.
    $response = sellsEditGet($this, $saleId, [
        'X-Inertia-Partial-Component' => 'Sells/Edit',
        'X-Inertia-Partial-Data' => 'form',
    ]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: sem isto, "nada mudou" seria verdade só porque a request
    // abortou antes de tocar em qualquer coisa.
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'])->toBe('Sells/Edit');
    expect($page['props'])->toHaveKey('form');

    $depois = DB::table('transactions')->where('id', $saleId)->first($colunas);

    expect((array) $depois)->toBe((array) $antes,
        'Abrir o editor escreveu na transação — o charter §Non-Goals proíbe mudar status direto '
        . 'e a ADR 0143 protege current_stage_id (só via ExecuteStageActionService).');
});

// =============================================================================
// UC-SEDIT-07 — REGRA MESTRE valor/estoque + SDD §3.2 (incidente num_uf) + charter §Goals
//   (pre-fill). O que o formulário mostra é o que está gravado.
// =============================================================================

it('UC-SEDIT-07 · o pré-fill traz os valores do banco, sem fallback', function () {
    // 3 × 49,90 = 149,70 — fracionário de propósito (a família do incidente num_uf).
    $venda = sellsEditVenda($this->bizId, 3.0, 49.90);
    $saleId = $venda['transaction_id'];

    // Fato independente: o que o banco tem ANTES da leitura.
    $noBanco = DB::table('transactions')->where('id', $saleId)->first(['final_total']);
    expect(round((float) $noBanco->final_total, 2))->toBe(149.70);

    $response = sellsEditGet($this, $saleId, [
        'X-Inertia-Partial-Component' => 'Sells/Edit',
        'X-Inertia-Partial-Data' => 'form',
    ]);
    $response->assertStatus(200);
    $form = json_decode($response->getContent(), true)['props']['form'];

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o payload chegou com a linha da venda.
    expect($form)->toHaveKey('transaction');
    expect($form)->toHaveKey('sellDetails');
    expect(count($form['sellDetails']))->toBe(1);

    // O CONTRATO: total e linha batem com o banco. Tolerância de centavo pelo decimal(22,4).
    expect(round((float) $form['transaction']['final_total'], 2))->toBe(149.70,
        'O total pré-preenchido divergiu do gravado — Larissa "corrige" em cima de número errado '
        . 'e salva o número errado (SDD §3.2, incidente num_uf).');

    $linha = $form['sellDetails'][0];
    expect(round((float) $linha['quantity_ordered'], 2))->toBe(3.0,
        'A quantidade pré-preenchida não é a da venda — pré-fill em fallback (incidente "venda em branco").');
    expect(round((float) $linha['sell_price_inc_tax'], 2))->toBe(49.90,
        'O preço unitário pré-preenchido não é o da venda — pré-fill em fallback.');
});
