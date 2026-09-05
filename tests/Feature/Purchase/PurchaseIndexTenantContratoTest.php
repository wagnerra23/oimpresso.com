<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Contrato Tier 0 do payload da LISTA de compras (`PurchaseController@indexInertia`).
 *
 * UC-PURIDX-02 `[T0]` — a lista nunca sai do `business_id` da sessão.
 * UC-PURIDX-03 `[T0]` — a lista respeita `permitted_locations`.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE ARQUIVO EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Os dois UCs acima já estavam escritos no `Index.casos.md`, ambos `must [T0]`, e ambos
 * marcados `⚠️ 🧪 estrutural`: a única defesa era `file_get_contents` no fonte do
 * controller + `toContain`. Isso pega a REMOÇÃO de um trecho e mais nada — nenhum tenant
 * é montado, nenhum request é emitido, nenhuma resposta é lida.
 *
 * O risco não é teórico: `App\Transaction` NÃO tem global scope (medido 2026-09-04:
 * `grep -c addGlobalScope app/Transaction.php` = 0), então o isolamento desta tela é
 * escrito à mão em cada query. Foi exatamente essa ausência que produziu o IDOR de
 * escrita corrigido em `PurchaseController@update` (`UpdateCrossTenantIdorTest`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ANTI-VÁCUO (por que cada UC roda em PAR)
 * ─────────────────────────────────────────────────────────────────────────────
 * Um teste que só afirma "a linha alheia não aparece" fica VERDE por motivos errados:
 * lista vazia, 403, join que derruba tudo, filtro que zera. Um `abort()` incondicional
 * no controller passaria. Por isso cada contrato tem duas pernas:
 *   (A) CONTROLE POSITIVO — o registro PRÓPRIO aparece. Se (A) cair, o defeito é do
 *       setup/mecanismo e fica VISÍVEL, nunca silencioso.
 *   (B) CONTRATO — o registro alheio NÃO aparece.
 * Só o par discrimina (proibicoes.md §5 2026-07-24 — "verde por não-execução").
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TENANTS (ADR 0358 — supersede a 0101)
 * ─────────────────────────────────────────────────────────────────────────────
 * Tenant do usuário = 98, o FICTÍCIO canônico. NÃO biz=1: no CT 100 a base é clone de
 * prod que não se limpa entre runs, e o 1 é a WR2 Sistemas, empresa REAL. NÃO biz=99:
 * é o SUPPORT_CLIENT_TENANT_ID do Modo Suporte, e agente+cliente no mesmo id fariam o
 * cross-tenant ficar verde sem provar isolamento. NUNCA biz=4 (Larissa/ROTA LIVRE).
 * O adversário é DESCOBERTO, não fixado — qualquer business != 98 que tenha location
 * (a lista faz INNER join em `business_locations`, então sem location a compra alheia
 * não apareceria nem havendo vazamento: seria verde por vácuo).
 *
 * @covers-uc UC-PURIDX-02  lista escopada por business_id
 * @covers-uc UC-PURIDX-03  lista escopada por permitted_locations
 *
 * @see app/Http/Controllers/PurchaseController.php::indexInertia
 * @see app/Utils/TransactionUtil.php::getListPurchases
 * @see resources/js/Pages/Purchase/Index.casos.md (UC-PURIDX-02 · UC-PURIDX-03)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
// `Tests\TestCase` NÃO se declara aqui: `tests/Pest.php` já faz
// `uses(TestCase::class)->in('Feature')`, e repetir dá
// "already uses the test case [Tests\TestCase]" (medido no CT 100, 2026-09-04).
// O binding explícito só é necessário em `Modules/*/Tests`, que ficam fora daquele `->in()`.
uses(DatabaseTransactions::class);

const PURIDX_TENANT = 98;

/** Cria uma compra crua na `transactions`. */
function puridxCriarCompra(int $businessId, int $locationId, int $userId, string $refNo): int
{
    $agora = now();

    return DB::table('transactions')->insertGetId([
        'business_id' => $businessId,
        'location_id' => $locationId,
        'created_by' => $userId,
        'type' => 'purchase',
        'status' => 'received',
        'payment_status' => 'due',
        'ref_no' => $refNo,
        'transaction_date' => $agora,
        'total_before_tax' => 500.00,
        'final_total' => 500.00,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'essentials_duration' => 0,
        'created_at' => $agora,
        'updated_at' => $agora,
    ]);
}

/** Versão Inertia = md5 do manifest. Header fixo causa 409 (asset-version mismatch). */
function puridxInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Os `ref_no` que o payload de `Purchase/Index` devolveu, seja qual for o envelope. */
function puridxRefsDoPayload(array $payload): array
{
    $rows = $payload['props']['rows'] ?? null;
    // `rows` é lista direta no indexInertia; aceita envelope paginado por robustez.
    $linhas = is_array($rows) && isset($rows['data']) ? $rows['data'] : $rows;

    return is_array($linhas)
        ? array_values(array_filter(array_map(fn ($l) => $l['ref_no'] ?? null, $linhas)))
        : [];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: exige schema MySQL UltimatePOS (ADR 0062).');
    }
    foreach (['transactions', 'business', 'business_locations', 'invoice_schemes', 'invoice_layouts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}).");
        }
    }

    $this->tenant = \App\Business::find(PURIDX_TENANT);
    if (! $this->tenant) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358) — contrato não exercitável.');
    }

    // `business_locations` tem TRÊS FKs NOT NULL: business_id + invoice_scheme_id +
    // invoice_layout_id. As duas últimas apontam pra tabelas que o seed da lane só popula
    // pros tenants 1 e 2 — o 98 nasce sem nenhuma. Sem criá-las aqui, o insert de location
    // morre com SQLSTATE 23000 e o teste vira skip (exit 0 = falsa cobertura).
    $schemeId = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => PURIDX_TENANT, 'name' => 'CT Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layoutId = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => PURIDX_TENANT, 'name' => 'CT Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    $this->criarLocation = function (int $businessId, string $nome) use ($schemeId, $layoutId): int {
        return DB::table('business_locations')->insertGetId([
            'business_id' => $businessId, 'name' => $nome,
            'location_id' => strtoupper(substr(md5($nome . microtime()), 0, 8)),
            'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
            'invoice_scheme_id' => $schemeId, 'invoice_layout_id' => $layoutId, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };

    $this->locationA = ($this->criarLocation)(PURIDX_TENANT, 'CT Filial A');
    $this->locationB = ($this->criarLocation)(PURIDX_TENANT, 'CT Filial B');

    $this->user = \App\User::factory()->create([
        'business_id' => PURIDX_TENANT,
        'username' => 'ct_puridx_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    // O gate do index() aceita purchase.view OU purchase.create OU view_own_purchase.
    // `access_all_locations` é o que faz permitted_locations() devolver 'all' — dado
    // aqui e REVOGADO no UC-03, que é justamente o cenário de filial restrita.
    foreach (['purchase.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['purchase.view', 'access_all_locations']);

    $this->abrirLista = fn () => $this->actingAs($this->user)
        ->withSession(['user' => ['business_id' => PURIDX_TENANT, 'id' => $this->user->id]])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => puridxInertiaVersion(),
            'X-Inertia-Partial-Data' => 'rows',
            'X-Inertia-Partial-Component' => 'Purchase/Index',
        ])
        ->get('/purchases');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PURIDX-02 — a lista não sai do business_id da sessão
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PURIDX-02 (A · controle positivo) a compra do PROPRIO business aparece na lista', function () {
    $refPropria = 'CT-PURIDX-OWN-' . uniqid();
    puridxCriarCompra(PURIDX_TENANT, $this->locationA, $this->user->id, $refPropria);

    $refs = puridxRefsDoPayload(($this->abrirLista)()->assertOk()->json());

    // `assertContains` do PHPUnit, não `expect()->toContain()`: no Pest o 2º argumento
    // de `toContain` é OUTRO NEEDLE, não mensagem — passar texto ali faz o assert
    // procurar a própria mensagem dentro do array e falhar sempre (proibicoes.md
    // §5 2026-07-28; medido de novo aqui no CT 100 em 2026-09-04).
    $this->assertContains(
        $refPropria,
        $refs,
        'A compra do proprio business NAO apareceu — setup ou mecanismo quebrado. '
        . 'Sem esta perna, o contrato (B) ficaria verde por lista vazia. '
        . 'Refs recebidos: ' . json_encode($refs)
    );
});

it('UC-PURIDX-02 (B · contrato T0) a compra de OUTRO business NAO aparece na lista', function () {
    // O adversário é descoberto: precisa ter location própria, senão o INNER join de
    // `getListPurchases` derrubaria a linha alheia mesmo havendo vazamento (verde por vácuo).
    $locationAlheia = DB::table('business_locations')
        ->where('business_id', '!=', PURIDX_TENANT)
        ->orderBy('id')
        ->first();

    if (! $locationAlheia) {
        $this->markTestSkipped('Lane sem 2o business COM location — cross-tenant nao exercitavel.');
    }

    $refAlheia = 'CT-PURIDX-LEAK-' . uniqid();
    puridxCriarCompra(
        (int) $locationAlheia->business_id,
        (int) $locationAlheia->id,
        $this->user->id,
        $refAlheia
    );

    // Âncora: sem uma linha própria, um 200-com-lista-vazia passaria por engano.
    $refPropria = 'CT-PURIDX-OWN-' . uniqid();
    puridxCriarCompra(PURIDX_TENANT, $this->locationA, $this->user->id, $refPropria);

    $refs = puridxRefsDoPayload(($this->abrirLista)()->assertOk()->json());

    $this->assertContains(
        $refPropria,
        $refs,
        'Ancora ausente — a lista nao trouxe nem a linha propria. Refs: ' . json_encode($refs)
    );
    $this->assertNotContains(
        $refAlheia,
        $refs,
        'VAZAMENTO TIER 0 (ADR 0093): a compra do business ' . $locationAlheia->business_id
        . ' apareceu na lista do tenant ' . PURIDX_TENANT . '. App\\Transaction nao tem global '
        . 'scope — conferir o where(transactions.business_id) de getListPurchases.'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PURIDX-03 — a lista respeita permitted_locations
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PURIDX-03 (A · controle positivo) com acesso a todas as filiais, as DUAS aparecem', function () {
    $refA = 'CT-PURIDX-LOCA-' . uniqid();
    $refB = 'CT-PURIDX-LOCB-' . uniqid();
    puridxCriarCompra(PURIDX_TENANT, $this->locationA, $this->user->id, $refA);
    puridxCriarCompra(PURIDX_TENANT, $this->locationB, $this->user->id, $refB);

    // O usuário do beforeEach tem `access_all_locations` ⇒ permitted_locations() = 'all'.
    $refs = puridxRefsDoPayload(($this->abrirLista)()->assertOk()->json());

    $this->assertContains($refA, $refs, 'A compra da filial A sumiu — setup quebrado.');
    $this->assertContains(
        $refB,
        $refs,
        'Com acesso a todas as filiais as duas compras deveriam aparecer — se esta perna '
        . 'cai, o contrato (B) abaixo ficaria verde porque a filial B nunca aparece.'
    );
});

it('UC-PURIDX-03 (B · contrato T0) com UMA filial permitida, a compra da OUTRA some', function () {
    $refPermitida = 'CT-PURIDX-OKLOC-' . uniqid();
    $refProibida = 'CT-PURIDX-NOLOC-' . uniqid();
    puridxCriarCompra(PURIDX_TENANT, $this->locationA, $this->user->id, $refPermitida);
    puridxCriarCompra(PURIDX_TENANT, $this->locationB, $this->user->id, $refProibida);

    // Recorte de filial: sem `access_all_locations`, permitted_locations() passa a
    // devolver só as locations cuja permission `location.{id}` o usuário tem.
    $this->user->revokePermissionTo('access_all_locations');
    Permission::firstOrCreate(['name' => 'location.' . $this->locationA, 'guard_name' => 'web']);
    $this->user->givePermissionTo('location.' . $this->locationA);
    $this->user->unsetRelation('permissions');
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $refs = puridxRefsDoPayload(($this->abrirLista)()->assertOk()->json());

    $this->assertContains(
        $refPermitida,
        $refs,
        'Ancora ausente — a compra da filial PERMITIDA sumiu. Refs: ' . json_encode($refs)
    );
    $this->assertNotContains(
        $refProibida,
        $refs,
        'VAZAMENTO TIER 0: a compra da filial NAO permitida apareceu. O recorte de '
        . 'permitted_locations em indexInertia virou filtro de conveniencia.'
    );
});
