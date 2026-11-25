<?php

// @covers-us US-SELL-066

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da "+ Nova devolução" (`GET /sell-return/create` → `SellReturnController@create`
 * → `SellReturn/Create`): etapa 1 do protótipo Devolução V3, escolher a venda que volta.
 *
 * UCs: resources/js/Pages/SellReturn/Create.casos.md (UC-SRCRE-01..07).
 *
 * Tenants: 98 (canônico de teste, ADR 0358) × 99 (adversário, `seededSupportClientTenant()`).
 * Nunca biz=4. A tela só LÊ — nenhum valor é calculado aqui.
 *
 *   tenant 98
 *     VN  venda final com NF-e 55 autorizada (número 9.871.001)
 *     VS  venda final sem nota
 *     VD  venda final que já tem devolução DV
 *     RS  rascunho (status draft) — não entra
 *     DV  sell_return — não entra (não é venda)
 *   tenant 99
 *     VX  venda final — nunca aparece no 98
 *
 * As vendas recebem um prefixo único no invoice_no e a busca filtra por ele, para não contar
 * venda que já exista no tenant 98 da lane.
 *
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function srcreVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Headers que o cliente Inertia manda de verdade (X-Inertia E X-Requested-With). */
function srcreHeaders(array $extra = []): array
{
    return array_merge([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => srcreVersao(),
        'X-Requested-With' => 'XMLHttpRequest',
    ], $extra);
}

/** Partial reload da prop deferida `vendas`, com pré-condição anti-vácuo. */
function srcreVendas(object $test, array $query): array
{
    $url = '/sell-return/create?' . http_build_query($query);
    $res = $test->withHeaders(srcreHeaders([
        'X-Inertia-Partial-Data' => 'vendas',
        'X-Inertia-Partial-Component' => 'SellReturn/Create',
    ]))->get($url);
    $res->assertStatus(200);
    $page = json_decode($res->getContent(), true);

    expect($page['component'] ?? null)->toBe('SellReturn/Create');
    expect(array_key_exists('vendas', $page['props']))->toBeTrue();

    return $page['props']['vendas'];
}

/** @return int[] ids das linhas */
function srcreIds(array $vendas): array
{
    return array_map(fn ($l) => (int) $l['id'], $vendas['data']);
}

function srcreTransacao(array $campos): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'status' => 'final',
        'payment_status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ], $campos));
}

function srcreUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SRC Nova devolucao',
        'username' => 'srcre_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function srcreLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'business_locations', 'business', 'users', 'nfe_emissoes'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    // PRÉ-CONDIÇÃO: empresas DISTINTAS (senão o [T0] é tautológico).
    expect($this->outroBizId)->not->toBe($this->bizId);

    $loc = EstoqueFixture::locationId($this->bizId);
    $locOutro = EstoqueFixture::locationId($this->outroBizId);

    $this->user = srcreUsuario($this->bizId, ['access_sell_return', 'access_all_locations', 'direct_sell.access']);

    $this->prefixo = 'SRCRE' . substr(uniqid(), -6) . '-';
    $data = now()->subDays(3)->format('Y-m-d') . ' 10:00:00';
    $base = ['business_id' => $this->bizId, 'location_id' => $loc, 'type' => 'sell',
        'created_by' => $this->user->id, 'transaction_date' => $data,
        'final_total' => 120.00, 'total_before_tax' => 120.00];

    $this->vn = srcreTransacao(array_merge($base, ['invoice_no' => $this->prefixo . 'VN']));
    $this->vs = srcreTransacao(array_merge($base, ['invoice_no' => $this->prefixo . 'VS']));
    $this->vd = srcreTransacao(array_merge($base, ['invoice_no' => $this->prefixo . 'VD']));
    $this->rs = srcreTransacao(array_merge($base, ['invoice_no' => $this->prefixo . 'RS', 'status' => 'draft']));
    $this->dv = srcreTransacao(array_merge($base, ['invoice_no' => $this->prefixo . 'DV', 'type' => 'sell_return',
        'return_parent_id' => $this->vd]));

    DB::table('nfe_emissoes')->insert([
        'business_id' => $this->bizId, 'transaction_id' => $this->vn, 'modelo' => '55', 'serie' => '987',
        'numero' => 9_871_001, 'status' => 'autorizada', 'valor_total' => 120, 'emitido_em' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->vx = srcreTransacao(['business_id' => $this->outroBizId, 'location_id' => $locOutro, 'type' => 'sell',
        'created_by' => $this->user->id, 'transaction_date' => $data, 'invoice_no' => $this->prefixo . 'VX',
        'final_total' => 70000.00, 'total_before_tax' => 70000.00]);

    srcreLogin($this, $this->user);
});

it('UC-SRCRE-01 a visita Inertia recebe SellReturn/Create, com as vendas deferidas', function () {
    $res = $this->withHeaders(srcreHeaders())->get('/sell-return/create');
    $res->assertStatus(200);
    $page = json_decode($res->getContent(), true);

    expect($page['component'] ?? null)->toBe('SellReturn/Create');
    // A lista é cara: não vem na carga inicial, vem no partial reload.
    expect(array_key_exists('vendas', $page['props']))->toBeFalse();
    expect($page['props']['filtros']['nota'])->toBe('todas');

    $vendas = srcreVendas($this, ['q' => $this->prefixo]);
    expect((int) $vendas['current_page'])->toBe(1);
    expect(array_key_exists('links', $vendas))->toBeTrue();
});

it('UC-SRCRE-02 [T0] venda de outro business não aparece, nem pela busca', function () {
    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a venda alheia existe, com o mesmo prefixo.
    expect(DB::table('transactions')->where('id', $this->vx)->where('business_id', $this->outroBizId)->exists())->toBeTrue();

    expect(srcreIds(srcreVendas($this, ['q' => $this->prefixo])))->not->toContain($this->vx);
    expect(srcreVendas($this, ['q' => $this->prefixo . 'VX'])['data'])->toBe([]);
});

it('UC-SRCRE-03 só vendas finalizadas: sem rascunho e sem a própria devolução', function () {
    $ids = srcreIds(srcreVendas($this, ['q' => $this->prefixo]));

    sort($ids);
    $esperado = [$this->vn, $this->vs, $this->vd];
    sort($esperado);
    expect($ids)->toBe($esperado);
});

it('UC-SRCRE-04 filtro Com NF-e e Sem nota separam as vendas pela nota autorizada', function () {
    $comNota = srcreVendas($this, ['q' => $this->prefixo, 'nota' => 'nfe']);
    expect(srcreIds($comNota))->toBe([$this->vn]);
    expect($comNota['data'][0]['nota'])->toBe(['numero' => '9871001', 'tipo' => 'NF-e']);

    $semNota = srcreIds(srcreVendas($this, ['q' => $this->prefixo, 'nota' => 'sem']));
    sort($semNota);
    $esperado = [$this->vs, $this->vd];
    sort($esperado);
    expect($semNota)->toBe($esperado);
});

it('UC-SRCRE-05 a busca pelo número da venda traz só ela', function () {
    expect(srcreIds(srcreVendas($this, ['q' => $this->prefixo . 'VS'])))->toBe([$this->vs]);
});

it('UC-SRCRE-06 sem permissão de devolução a tela devolve 403', function () {
    srcreLogin($this, srcreUsuario($this->bizId, ['access_all_locations']));

    $this->withHeaders(srcreHeaders())->get('/sell-return/create')->assertStatus(403);
});

it('UC-SRCRE-07 venda que já tem devolução traz o número dela', function () {
    $vendas = srcreVendas($this, ['q' => $this->prefixo . 'VD']);

    expect(srcreIds($vendas))->toBe([$this->vd]);
    expect($vendas['data'][0]['devolucao_numero'])->toBe($this->prefixo . 'DV');
});

it('UC-SRCRE-08 cada venda traz o cliente dela, para a tela só juntar vendas do mesmo cliente', function () {
    $contato = DB::table('contacts')->where('business_id', $this->bizId)->value('id');
    if ($contato === null) {
        $this->markTestSkipped('Sem contato no tenant de teste.');
    }
    DB::table('transactions')->where('id', $this->vs)->update(['contact_id' => $contato]);

    $vendas = srcreVendas($this, ['q' => $this->prefixo . 'VS']);

    expect(srcreIds($vendas))->toBe([$this->vs]);
    expect($vendas['data'][0]['contato_id'])->toBe((int) $contato);
});
