<?php

// @covers-us US-SELL-062

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da tela Assinaturas (`GET /sells/subscriptions` → `SellPosController@listSubscriptions`;
 * dados em `GET /sells/subscriptions?ajax=1` sem X-Inertia — o DataTable que o Blade e a Page leem;
 * pausar/retomar em `GET /toggle-subscription/{id}` → `SellPosController@toggleRecurringInvoices`).
 *
 * UCs: resources/js/Pages/Sells/Subscriptions.casos.md (UC-SSUB-01..05). Derivados do aceite da
 * US-SELL-062 (SPEC) + Subscriptions.charter.md + RUNBOOK-subscriptions.md — não do `.tsx`.
 *
 * Fixture (identificada pelo prefixo no invoice_no — a lane MySQL persiste dados alheios no
 * tenant 98, então só as linhas com o prefixo contam):
 *   tenant 98 · recorrente ativa (rAt) · recorrente pausada (rPa)
 *             · venda final NÃO recorrente (fNr) · rascunho recorrente (dRe)
 *   tenant 99 · recorrente ativa (xAt) — NUNCA aparece no 98 nem é alternada por ele
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 * Permissão DIRETA ao usuário (no CI não existem roles semeados).
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function ssubUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SSUB Assinatura',
        'username' => 'ssub_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function ssubLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'business.date_format' => 'd/m/Y',
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);

    return $test->actingAs($user);
}

function ssubTx(object $test, int $bizId, int $locationId, int $userId, string $sufixo, string $status, int $recorrente, bool $pausada = false): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => $locationId,
        'created_by' => $userId,
        'type' => 'sell',
        'status' => $status,
        'is_direct_sale' => 1,
        'payment_status' => 'due',
        'invoice_no' => $test->prefixo . $sufixo,
        'transaction_date' => now(),
        'total_before_tax' => 10,
        'final_total' => 10,
        'is_recurring' => $recorrente,
        'recur_interval' => 1,
        'recur_interval_type' => 'months',
        'recur_stopped_on' => $pausada ? now() : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** invoice_no (só os desta fixture) da lista de assinaturas, pelo endpoint do DataTable. */
function ssubLista(object $test): array
{
    $json = $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells/subscriptions?ajax=1')
        ->assertOk()
        ->json();

    // Anti-vácuo: erro de render do DataTable vira `data: []` com HTTP 200.
    expect($json['error'] ?? null)->toBeNull();
    expect($json)->toHaveKey('data');

    $nos = [];
    foreach ($json['data'] as $row) {
        $no = trim(strip_tags((string) ($row['invoice_no'] ?? '')));
        if (str_starts_with($no, $test->prefixo)) {
            $nos[] = substr($no, strlen($test->prefixo));
        }
    }
    sort($nos);

    return $nos;
}

function ssubVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET /sells/subscriptions como o cliente Inertia manda (X-Inertia E X-Requested-With — §5 2026-09-08). */
function ssubPagina(object $test)
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => ssubVersao(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/sells/subscriptions');
}

function ssubParada(int $id): ?string
{
    $v = DB::table('transactions')->where('id', $id)->value('recur_stopped_on');

    return $v === null ? null : (string) $v;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0358).');
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

    $this->prefixo = 'SSUB' . strtoupper(substr(uniqid(), -6)) . '-';
    $this->loc = EstoqueFixture::locationId($this->bizId);
    $this->locOutro = EstoqueFixture::locationId($this->outroBizId);

    $this->autor = ssubUsuario($this->bizId, ['access_all_locations']);
    $this->estranho = ssubUsuario($this->outroBizId, ['access_all_locations']);
});

/** Insere a fixture completa e devolve os ids por sufixo. */
function ssubFixture(object $test): array
{
    $ids = [
        'rAt' => ssubTx($test, $test->bizId, $test->loc, $test->autor->id, 'rAt', 'final', 1),
        'rPa' => ssubTx($test, $test->bizId, $test->loc, $test->autor->id, 'rPa', 'final', 1, true),
        'fNr' => ssubTx($test, $test->bizId, $test->loc, $test->autor->id, 'fNr', 'final', 0),
        'dRe' => ssubTx($test, $test->bizId, $test->loc, $test->autor->id, 'dRe', 'draft', 1),
        'xAt' => ssubTx($test, $test->outroBizId, $test->locOutro, $test->estranho->id, 'xAt', 'final', 1),
    ];
    // Anti-vácuo: as 5 linhas existem — o que some da lista some pelo filtro, não pela fixture.
    expect(DB::table('transactions')->where('invoice_no', 'like', $test->prefixo . '%')->count())->toBe(5);

    return $ids;
}

it('UC-SSUB-01 [T0] a lista traz só as vendas finais recorrentes do business — sem venda comum, sem rascunho, sem outro business', function () {
    ssubFixture($this);
    ssubLogin($this, ssubUsuario($this->bizId, ['sell.view', 'access_all_locations']));

    expect(ssubLista($this))->toBe(['rAt', 'rPa']);
});

it('UC-SSUB-02 a tela abre com sell.view ou com direct_sell.access e é negada (403) sem nenhuma das duas', function () {
    foreach ([['sell.view'], ['direct_sell.access']] as $perm) {
        $resp = ssubPagina(ssubLogin($this, ssubUsuario($this->bizId, array_merge($perm, ['access_all_locations']))));
        $resp->assertOk();
        expect($resp->json('component'))->toBe('Sells/Subscriptions');
    }

    ssubPagina(ssubLogin($this, ssubUsuario($this->bizId, ['access_all_locations'])))->assertStatus(403);

    // O endpoint de dados também é negado — o gate não mora só no render da Page.
    ssubLogin($this, ssubUsuario($this->bizId, ['access_all_locations']))
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells/subscriptions?ajax=1')
        ->assertStatus(403);
});

it('UC-SSUB-03 [T0] os KPIs total/ativas/pausadas contam só as recorrentes finais do business', function () {
    $user = ssubUsuario($this->bizId, ['sell.view', 'access_all_locations']);
    $antes = ssubPagina(ssubLogin($this, $user))->json('props.kpis');

    ssubFixture($this);
    $depois = ssubPagina(ssubLogin($this, $user))->json('props.kpis');

    // Delta: +1 ativa (rAt) e +1 pausada (rPa). fNr (não recorrente), dRe (rascunho) e xAt
    // (outro business) não entram em contagem nenhuma.
    expect($depois['total'] - $antes['total'])->toBe(2);
    expect($depois['active'] - $antes['active'])->toBe(1);
    expect($depois['stopped'] - $antes['stopped'])->toBe(1);
});

it('UC-SSUB-04 [T0] pausar e retomar alterna a parada da assinatura do business; outro business e venda não recorrente ficam intactos', function () {
    $ids = ssubFixture($this);
    // sell.update é a permissão do contrato (SPEC/charter); sell.create é a que o código checa
    // hoje — as duas juntas para este caso não tomar partido na divergência do UC-SSUB-05.
    $user = ssubUsuario($this->bizId, ['sell.view', 'sell.update', 'sell.create', 'access_all_locations']);

    expect(ssubParada($ids['rAt']))->toBeNull();

    ssubLogin($this, $user)->get('/toggle-subscription/' . $ids['rAt'])->assertOk();
    expect(ssubParada($ids['rAt']))->not->toBeNull();

    ssubLogin($this, $user)->get('/toggle-subscription/' . $ids['rAt'])->assertOk();
    expect(ssubParada($ids['rAt']))->toBeNull();

    // Assinatura de outro business: recusada, e o banco não muda.
    $resp = ssubLogin($this, $user)->get('/toggle-subscription/' . $ids['xAt']);
    expect($resp->json('success'))->toBe(0);
    expect(ssubParada($ids['xAt']))->toBeNull();

    // Venda final NÃO recorrente do próprio business: não vira assinatura pausada.
    $resp = ssubLogin($this, $user)->get('/toggle-subscription/' . $ids['fNr']);
    expect($resp->json('success'))->toBe(0);
    expect(ssubParada($ids['fNr']))->toBeNull();
});

it('UC-SSUB-05 sem permissão de escrita o pausar é recusado (403) e a assinatura segue ativa; a Page diz se o usuário pode alternar', function () {
    $ids = ssubFixture($this);

    $leitor = ssubUsuario($this->bizId, ['sell.view', 'access_all_locations']);
    ssubLogin($this, $leitor)->get('/toggle-subscription/' . $ids['rAt'])->assertStatus(403);
    expect(ssubParada($ids['rAt']))->toBeNull();

    // A Page recebe a capacidade de escrita pela permissão de escrita do contrato (sell.update).
    expect(ssubPagina(ssubLogin($this, $leitor))->json('props.permissions.update'))->toBeFalse();
    $escritor = ssubUsuario($this->bizId, ['sell.view', 'sell.update', 'access_all_locations']);
    expect(ssubPagina(ssubLogin($this, $escritor))->json('props.permissions.update'))->toBeTrue();
});
