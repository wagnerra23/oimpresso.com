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
    expect($page['props']['permissions'])->toBe(['view_all' => false, 'view_own' => true]);

    quoLogin($this, quoUsuario($this->bizId, ['access_all_locations']));
    quoPagina($this)->assertStatus(403);
});
