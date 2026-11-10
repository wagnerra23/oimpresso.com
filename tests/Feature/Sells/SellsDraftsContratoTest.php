<?php

// @covers-us US-SELL-060

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da tela Rascunhos (`GET /sells/drafts` → `SellController@getDrafts`;
 * dados em `GET /sells/draft-dt?is_quotation=0` → `SellController@getDraftDatables`, o
 * endpoint que o Blade legado lê — sale_pos/draft.blade.php — e que a Page recebe em
 * `urls.datatable`).
 *
 * UCs: resources/js/Pages/Sells/Drafts.casos.md (UC-DRAFT-01..04, 06 e 07). Derivados do aceite da
 * US-SELL-060 (SPEC) + CU-SELL-08/CU-SELL-13 (SDD-tela-venda-v1.0) + Drafts.charter.md
 * (Mission/Goals) + RUNBOOK-drafts.md §2 — não do `.tsx`.
 *
 * Fixture (identificada pelo prefixo no invoice_no — a lane MySQL persiste dados alheios
 * no tenant 98, então só as linhas com o prefixo contam na lista; o KPI é medido por DELTA):
 *   tenant 98 · rascunho do vendedor A (rA) · rascunho do vendedor B (rB) · venda final de A (fA)
 *   tenant 99 · rascunho (rX) — NUNCA aparece no 98
 *
 * Cotação fica FORA da fixture da lista de propósito: o endpoint com is_quotation=0 não filtra
 * sub_status (lido em getDraftDatables) — divergência com o charter registrada como [BACKLOG]
 * no casos.md, sem teste vermelho numa lane required.
 *
 * Permissões são dadas DIRETO ao usuário: no CI não existem roles semeadas.
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function drfUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'DRF Rascunho',
        'username' => 'drf_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    // Cache ANTES do findOrCreate: com DatabaseTransactions o cache pode guardar id de permissão
    // criada num teste anterior e já revertida → FK em model_has_permissions (receita do
    // SellsQuotationsContratoTest).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function drfLogin(object $test, User $user): void
{
    $test->actingAs($user);
    // `currency` como o SetSessionData grava: o DataTables formata quantidade lendo
    // session('currency') — sem ela vem 200 com `data: []` e `error` preenchido.
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);
}

function drfTx(object $test, int $bizId, int $locationId, int $userId, string $sufixo, string $status, ?string $subStatus): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => $locationId,
        'created_by' => $userId,
        'type' => 'sell',
        'status' => $status,
        'sub_status' => $subStatus,
        'is_direct_sale' => 1,
        'payment_status' => 'due',
        'invoice_no' => $test->prefixo . $sufixo,
        'transaction_date' => now(),
        'total_before_tax' => 10,
        'final_total' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** invoice_no (só os desta fixture) da lista de rascunhos, pelo endpoint de dados. */
function drfLista(object $test): array
{
    $json = $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells/draft-dt?is_quotation=0')
        ->assertOk()
        ->json();

    // Anti-vácuo: erro de render vira `data: []` com HTTP 200 — sem isto, "nada listado" passaria.
    expect($json['error'] ?? null)->toBeNull();

    $nos = [];
    foreach ($json['data'] as $row) {
        // editColumn('invoice_no') pode anexar rótulos HTML depois do número.
        $no = trim(strip_tags(explode('<', (string) $row['invoice_no'])[0]));
        if (str_starts_with($no, $test->prefixo)) {
            $nos[] = substr($no, strlen($test->prefixo));
        }
    }
    sort($nos);

    return $nos;
}

function drfInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET /sells/drafts como o cliente Inertia manda (X-Inertia E X-Requested-With — §5 2026-09-08). */
function drfPagina(object $test)
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => drfInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/sells/drafts');
}

/** KPI "rascunhos pendentes" que a tela recebe. */
function drfKpiTotal(object $test): int
{
    $resp = drfPagina($test);
    $resp->assertOk();
    $page = json_decode($resp->getContent(), true);
    expect($page['component'] ?? null)->toBe('Sells/Drafts');

    return (int) $page['props']['kpis']['total'];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'business', 'business_locations', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    // PRÉ-CONDIÇÃO: empresas DISTINTAS (senão o [T0] é tautológico).
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->prefixo = 'DRF' . strtoupper(substr(uniqid(), -6)) . '-';
    $this->loc = EstoqueFixture::locationId($this->bizId);
    $this->locOutro = EstoqueFixture::locationId($this->outroBizId);

    $this->vendedorA = drfUsuario($this->bizId, ['draft.view_own', 'access_all_locations']);
    $this->vendedorB = drfUsuario($this->bizId, ['draft.view_own', 'access_all_locations']);
    $this->estranho = drfUsuario($this->outroBizId, ['draft.view_all', 'access_all_locations']);

    drfTx($this, $this->bizId, $this->loc, $this->vendedorA->id, 'rA', 'draft', null);
    drfTx($this, $this->bizId, $this->loc, $this->vendedorB->id, 'rB', 'draft', null);
    drfTx($this, $this->bizId, $this->loc, $this->vendedorA->id, 'fA', 'final', null);
    drfTx($this, $this->outroBizId, $this->locOutro, $this->estranho->id, 'rX', 'draft', null);

    // Anti-vácuo: as 4 linhas existem com o prefixo — o que some da lista some pelo filtro.
    expect(DB::table('transactions')->where('invoice_no', 'like', $this->prefixo . '%')->count())->toBe(4);
});

it('UC-DRAFT-01 [T0] com draft.view_all a lista traz os rascunhos do business — sem venda final, sem rascunho de outro business', function () {
    drfLogin($this, drfUsuario($this->bizId, ['draft.view_all', 'access_all_locations']));

    expect(drfLista($this))->toBe(['rA', 'rB']);
});

it('UC-DRAFT-02 com só draft.view_own o vendedor vê apenas os rascunhos que ele criou', function () {
    drfLogin($this, $this->vendedorA);
    expect(drfLista($this))->toBe(['rA']);

    // Controle positivo: mesmo papel, outro vendedor, vê o dele — o recorte é por autor, não vazio.
    drfLogin($this, $this->vendedorB);
    expect(drfLista($this))->toBe(['rB']);
});

it('UC-DRAFT-03 a tela abre com draft.view_own e é negada (403) sem nenhuma das duas permissões', function () {
    drfLogin($this, $this->vendedorA);
    $resp = drfPagina($this);
    $resp->assertOk();
    $page = json_decode($resp->getContent(), true);
    expect($page['component'] ?? null)->toBe('Sells/Drafts');
    // `delete` (P-076): a tela só mostra "Excluir rascunho" ativo para quem tem draft.delete.
    expect($page['props']['permissions'])->toBe(['view_all' => false, 'view_own' => true, 'delete' => false]);

    drfLogin($this, drfUsuario($this->bizId, ['access_all_locations']));
    drfPagina($this)->assertStatus(403);
});

it('UC-DRAFT-04 [T0] o KPI de rascunhos pendentes sobe só com rascunho comum do próprio business — cotação e outro business não contam', function () {
    drfLogin($this, drfUsuario($this->bizId, ['draft.view_all', 'access_all_locations']));
    $antes = drfKpiTotal($this);
    // A fixture já pôs rA + rB no business: o KPI não pode estar zerado.
    expect($antes)->toBeGreaterThanOrEqual(2);

    // Cotação do próprio business e rascunho de outra empresa: o KPI não se mexe.
    drfTx($this, $this->bizId, $this->loc, $this->vendedorA->id, 'qA', 'draft', 'quotation');
    drfTx($this, $this->outroBizId, $this->locOutro, $this->estranho->id, 'rY', 'draft', null);
    expect(drfKpiTotal($this))->toBe($antes);

    // Controle positivo: um rascunho comum do próprio business soma exatamente 1.
    drfTx($this, $this->bizId, $this->loc, $this->vendedorB->id, 'rC', 'draft', null);
    expect(drfKpiTotal($this))->toBe($antes + 1);
});

/** id da transação desta fixture pelo sufixo do invoice_no. */
function drfId(object $test, string $sufixo): int
{
    return (int) DB::table('transactions')->where('invoice_no', $test->prefixo . $sufixo)->value('id');
}

/** DELETE /sells/{id} como a tela manda (XHR + JSON) — SellPosController@destroy. */
function drfExcluir(object $test, int $id)
{
    return $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->delete("/sells/{$id}");
}

it('UC-DRAFT-06 [T0] com draft.delete o rascunho excluído sai da lista e do total; a lista traz o valor do rascunho', function () {
    drfLogin($this, drfUsuario($this->bizId, ['draft.view_all', 'draft.delete', 'access_all_locations']));

    // A tela recebe a permissão que liga o "Excluir rascunho".
    $page = json_decode(drfPagina($this)->assertOk()->getContent(), true);
    expect($page['props']['permissions']['delete'])->toBeTrue();

    // A lista traz o valor gravado de cada rascunho (coluna Valor e confirmação).
    $json = $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells/draft-dt?is_quotation=0')->assertOk()->json();
    $linha = collect($json['data'])->first(fn ($r) => str_starts_with((string) $r['invoice_no'], $this->prefixo . 'rA'));
    expect($linha)->not->toBeNull();
    expect((float) $linha['final_total'])->toBe(10.0);

    $antes = drfKpiTotal($this);
    $alvo = drfId($this, 'rA');
    expect($alvo)->toBeGreaterThan(0);

    drfExcluir($this, $alvo)->assertOk()->assertJsonPath('success', true);

    expect(DB::table('transactions')->where('id', $alvo)->exists())->toBeFalse();
    expect(drfLista($this))->toBe(['rB']);
    expect(drfKpiTotal($this))->toBe($antes - 1);
});

it('UC-DRAFT-07 [T0] draft.delete só apaga rascunho comum da própria empresa — e, com só view_own, só o próprio', function () {
    $vendedor = drfUsuario($this->bizId, ['draft.view_own', 'draft.delete', 'access_all_locations']);
    drfLogin($this, $vendedor);

    drfTx($this, $this->bizId, $this->loc, $vendedor->id, 'qV', 'draft', 'quotation');
    drfTx($this, $this->bizId, $this->loc, $vendedor->id, 'fV', 'final', null);
    drfTx($this, $this->bizId, $this->loc, $vendedor->id, 'rV', 'draft', null);

    // Recusados: cotação, venda final, rascunho de outra empresa e rascunho do colega.
    foreach (['qV', 'fV', 'rX', 'rB'] as $sufixo) {
        $id = drfId($this, $sufixo);
        expect($id)->toBeGreaterThan(0);
        drfExcluir($this, $id)->assertStatus(403);
        expect(DB::table('transactions')->where('id', $id)->exists())->toBeTrue();
    }

    // Sem draft.delete, nem o próprio rascunho.
    drfLogin($this, $this->vendedorA);
    drfExcluir($this, drfId($this, 'rA'))->assertStatus(403);
    expect(DB::table('transactions')->where('id', drfId($this, 'rA'))->exists())->toBeTrue();

    // Controle positivo: o próprio rascunho comum sai.
    drfLogin($this, $vendedor);
    $proprio = drfId($this, 'rV');
    drfExcluir($this, $proprio)->assertOk()->assertJsonPath('success', true);
    expect(DB::table('transactions')->where('id', $proprio)->exists())->toBeFalse();
});
