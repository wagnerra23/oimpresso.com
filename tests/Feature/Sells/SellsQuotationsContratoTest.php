<?php

// @covers-us US-SELL-061

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da tela Cotações (`GET /sells/quotations` → `SellController@getQuotations`;
 * dados em `GET /sells/draft-dt?is_quotation=1` → `SellController@getDraftDatables`, o
 * endpoint que o Blade legado lê — quotations.blade.php).
 *
 * UCs: resources/js/Pages/Sells/Quotations.casos.md (UC-QUO-01). Derivados do aceite da
 * US-SELL-061 (SPEC) + CU-SELL-08 (SDD-tela-venda) + ficha 03-orcamentos do playbook
 * venda-menu — não do `.tsx`.
 *
 * Fixture (identificada pelo prefixo no invoice_no — a lane MySQL persiste dados alheios
 * no tenant 98, então só as linhas com o prefixo contam):
 *   tenant 98 · cotação do vendedor A (qA) · cotação do vendedor B (qB)
 *             · rascunho comum de A (rA) · venda final de A (fA)
 *   tenant 99 · cotação (qX) — NUNCA aparece no 98
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário, criado se faltar). Nunca biz=4.
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function quoUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'QUO Cotacao',
        'username' => 'quo_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    // Cache ANTES do findOrCreate: com DatabaseTransactions, o cache pode guardar o id de uma
    // permissão criada num teste anterior e já revertida → FK 1452 em model_has_permissions
    // (medido no 1º run no CT 100).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function quoLogin(object $test, User $user): void
{
    $test->actingAs($user);
    // `currency` na sessão como o SetSessionData grava em produção: o DataTables formata
    // quantidade com @format_quantity, que lê session('currency')[...] — sem ela a resposta
    // vem 200 com `data: []` e `error` preenchido (medido no CT 100).
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);
}

function quoTx(object $test, int $bizId, int $locationId, int $userId, string $sufixo, string $status, ?string $subStatus): int
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

/** invoice_no (só os desta fixture) da lista de cotações, pelo endpoint de dados. */
function quoLista(object $test): array
{
    $json = $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells/draft-dt?is_quotation=1')
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

function quoInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET /sells/quotations como o cliente Inertia manda (X-Inertia E X-Requested-With — §5 2026-09-08). */
function quoPagina(object $test)
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => quoInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/sells/quotations');
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

    $this->prefixo = 'QUO' . strtoupper(substr(uniqid(), -6)) . '-';
    $loc = EstoqueFixture::locationId($this->bizId);
    $locOutro = EstoqueFixture::locationId($this->outroBizId);

    $this->vendedorA = quoUsuario($this->bizId, ['quotation.view_own', 'access_all_locations']);
    $this->vendedorB = quoUsuario($this->bizId, ['quotation.view_own', 'access_all_locations']);
    $estranho = quoUsuario($this->outroBizId, ['quotation.view_all', 'access_all_locations']);

    quoTx($this, $this->bizId, $loc, $this->vendedorA->id, 'qA', 'draft', 'quotation');
    quoTx($this, $this->bizId, $loc, $this->vendedorB->id, 'qB', 'draft', 'quotation');
    quoTx($this, $this->bizId, $loc, $this->vendedorA->id, 'rA', 'draft', null);
    quoTx($this, $this->bizId, $loc, $this->vendedorA->id, 'fA', 'final', null);
    quoTx($this, $this->outroBizId, $locOutro, $estranho->id, 'qX', 'draft', 'quotation');

    // Anti-vácuo: as 5 linhas existem com o prefixo — o que some da lista some pelo filtro.
    expect(DB::table('transactions')->where('invoice_no', 'like', $this->prefixo . '%')->count())->toBe(5);
});

it('UC-QUO-01 [T0] com quotation.view_all a lista traz as cotações do business — sem rascunho comum, sem venda final, sem outro business', function () {
    quoLogin($this, quoUsuario($this->bizId, ['quotation.view_all', 'access_all_locations']));

    expect(quoLista($this))->toBe(['qA', 'qB']);
});

it('UC-QUO-01 com só quotation.view_own o vendedor vê apenas as cotações que ele criou', function () {
    quoLogin($this, $this->vendedorA);
    expect(quoLista($this))->toBe(['qA']);

    // Controle positivo: o mesmo papel, outro vendedor, vê a dele — o recorte é por autor, não vazio.
    quoLogin($this, $this->vendedorB);
    expect(quoLista($this))->toBe(['qB']);
});

it('UC-QUO-01 a tela abre com quotation.view_own e é negada (403) sem nenhuma das duas permissões', function () {
    quoLogin($this, $this->vendedorA);
    $resp = quoPagina($this);
    $resp->assertOk();
    $page = json_decode($resp->getContent(), true);
    expect($page['component'] ?? null)->toBe('Sells/Quotations');
    // 'convert' (Q3, #8843): permissão de venda E a flag enable_convert_draft_to_invoice (false no repo).
    expect($page['props']['permissions'])->toBe(['view_all' => false, 'view_own' => true, 'convert' => false]);

    quoLogin($this, quoUsuario($this->bizId, ['access_all_locations']));
    quoPagina($this)->assertStatus(403);
});

/*
 * P-037 — orçamento copiado guarda de qual veio e a lista mostra (mesma coluna da venda
 * duplicada, `transactions.duplicated_from_id`, UC-SDUP-01..06). A cópia de orçamento vem
 * por `SellPosController::copyQuotation` ("Copiar orçamento"), não pelo `duplicateSell`.
 *
 * REGRA MESTRE: copiar não muda valor nem estoque. Origem e cópia: 1 linha de 3 un × 20,00 =
 * 60,00; cotação é rascunho, não baixa estoque (saldo 10 antes e depois).
 */
function quoOrcamentoComLinha(object $test, string $sufixo): array
{
    $loc = EstoqueFixture::locationId($test->bizId);
    $produto = EstoqueFixture::singleProduct($test->bizId);
    EstoqueFixture::setStock($produto, 0, $loc, 10);
    $v = EstoqueFixture::saleWithLine($produto, 0, $loc, 3, 20.0);
    DB::table('transactions')->where('id', $v['transaction_id'])->update([
        'status' => 'draft',
        'sub_status' => 'quotation',
        'payment_status' => 'due',
        'is_direct_sale' => 1,
        'invoice_no' => $test->prefixo . $sufixo,
        'invoice_token' => 'tok' . uniqid(),
        'process_id' => 987001,
        'current_stage_id' => 987002,
    ]);

    return ['id' => $v['transaction_id'], 'produto' => $produto, 'loc' => $loc];
}

/** Linhas da lista de cotações desta fixture: [sufixo => origem (sufixo) ou null]. */
function quoOrigensNaLista(object $test): array
{
    $json = $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells/draft-dt?is_quotation=1')->assertOk()->json();
    expect($json['error'] ?? null)->toBeNull();

    $origens = [];
    foreach ($json['data'] as $row) {
        $no = trim(strip_tags(explode('<', (string) $row['invoice_no'])[0]));
        if (str_starts_with($no, $test->prefixo)) {
            $de = $row['duplicated_from_invoice_no'] ?? null;
            $origens[substr($no, strlen($test->prefixo))] = $de === null ? null : substr((string) $de, strlen($test->prefixo));
        }
    }
    ksort($origens);

    return $origens;
}

it('UC-QUO-11 [V0][E0] copiar o orçamento cria um orçamento novo que guarda de qual veio', function () {
    if (! Schema::hasColumn('transactions', 'duplicated_from_id')) {
        $this->markTestSkipped('Coluna duplicated_from_id ausente — migration do P-031.');
    }
    $origem = quoOrcamentoComLinha($this, 'O75');
    quoLogin($this, quoUsuario($this->bizId, ['quotation.update', 'quotation.view_all', 'access_all_locations']));

    $antes = (int) DB::table('transactions')->max('id');
    $this->get('/sells/copy-quotation/' . $origem['id'])->assertRedirect();
    $copia = DB::table('transactions')->where('id', '>', $antes)->where('business_id', $this->bizId)
        ->where('sub_status', 'quotation')->orderByDesc('id')->first();

    expect($copia)->not->toBeNull('Copiar orçamento não criou a cópia.');
    expect((int) $copia->duplicated_from_id)->toBe($origem['id']);
    expect($copia->status)->toBe('draft');
    // O link público é por token: a cópia não pode herdar o da origem (abriria o orçamento errado).
    expect($copia->invoice_token)->toBeNull();
    expect($copia->process_id)->toBeNull();
    expect($copia->current_stage_id)->toBeNull();

    // Valor — prova 1 à mão (3 × 20,00 = 60,00); prova 2 linha a linha contra a origem.
    expect((float) $copia->final_total)->toBe(60.0);
    $linhas = fn (int $id) => DB::table('transaction_sell_lines')->where('transaction_id', $id)->orderBy('id')
        ->get(['product_id', 'variation_id', 'quantity', 'unit_price_inc_tax'])
        ->map(fn ($l) => [(int) $l->product_id, (int) $l->variation_id, (float) $l->quantity, (float) $l->unit_price_inc_tax])->all();
    expect($linhas((int) $copia->id))->toBe($linhas($origem['id']));
    expect(EstoqueFixture::currentStock($origem['produto'], 0, $origem['loc']))->toBe(10.0);
});

it('UC-QUO-12 [T0] a lista de orçamentos mostra de qual orçamento a cópia veio, só dentro da empresa', function () {
    if (! Schema::hasColumn('transactions', 'duplicated_from_id')) {
        $this->markTestSkipped('Coluna duplicated_from_id ausente — migration do P-031.');
    }
    $loc = EstoqueFixture::locationId($this->bizId);
    $qA = (int) DB::table('transactions')->where('invoice_no', $this->prefixo . 'qA')->value('id');
    $qX = (int) DB::table('transactions')->where('invoice_no', $this->prefixo . 'qX')->value('id');
    $copia = quoTx($this, $this->bizId, $loc, $this->vendedorA->id, 'qC', 'draft', 'quotation');
    $forjada = quoTx($this, $this->bizId, $loc, $this->vendedorA->id, 'qF', 'draft', 'quotation');
    DB::table('transactions')->where('id', $copia)->update(['duplicated_from_id' => $qA]);
    // Vínculo forjado para um orçamento de OUTRA empresa: não pode vazar o número dele.
    DB::table('transactions')->where('id', $forjada)->update(['duplicated_from_id' => $qX]);

    quoLogin($this, quoUsuario($this->bizId, ['quotation.view_all', 'access_all_locations']));

    expect(quoOrigensNaLista($this))->toBe(['qA' => null, 'qB' => null, 'qC' => 'qA', 'qF' => null]);
});
