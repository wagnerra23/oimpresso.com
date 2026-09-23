<?php

// @covers-us US-SELL-063

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Contrato da tela Caixa do dia (`GET /vendas/caixa` → `SellController@inertiaCaixa`).
 *
 * UCs: resources/js/Pages/Sells/Caixa/Index.casos.md (UC-SCAIXA-01..09).
 * Os UC foram derivados do CÓDIGO atual — decisão [W] 2026-09-23: "o caixa segue o código
 * atual". O charter foi reconciliado no mesmo trabalho.
 *
 * Tenants: 98 (canônico de teste, ADR 0358) × 99 (adversário, `seededSupportClientTenant()`,
 * criado se faltar — o [T0] NUNCA vira skip por ausência do 99). Nunca biz=4.
 *
 * REGRA MESTRE valor: este teste NÃO muda cálculo nenhum. Todo valor esperado é derivado à
 * mão da fixture abaixo, num dia isolado (2031-03-17) para não somar dado de outros testes:
 *
 *   tenant 98 · dia D
 *     V1 balcão  final 100.00 · pagamentos: dinheiro 60.00 + cartão 40.00
 *                              + troco dinheiro 5.00 com is_return=1 (NÃO conta)
 *     V2 oficina final  50.00 · os_ref OS-7001 · pagamento: dinheiro 50.00
 *     V3 balcão  draft 999.00 (NÃO conta: status != final)
 *   tenant 98 · dia D-1
 *     V4 balcão  final  30.00 · dinheiro 30.00 (só aparece com ?date=D-1)
 *   tenant 99 · dia D
 *     VX oficina final 70000.00 · os_ref OS-9999 · dinheiro 70000.00 (NUNCA aparece no 98)
 *
 *   ⇒ em D, tenant 98: totalDia 150.00 · countDia 2
 *                      dinheiro: 2 vendas · 110.00  |  cartão: 1 venda · 40.00
 *                      balcão: 1 · 100.00  |  oficina: 1 · 50.00 · refs [V2/OS-7001]
 *
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

const SCAIXA_DIA = '2031-03-17';
const SCAIXA_DIA_ANTERIOR = '2031-03-16';

/** Versão Inertia igual à do servidor — evita o 409 antes de o controller rodar. */
function scaixaInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET pelo caminho que o browser usa (Inertia manda X-Inertia E X-Requested-With). */
function scaixaGet(object $test, array $query = [])
{
    $url = '/vendas/caixa' . ($query === [] ? '' : '?' . http_build_query($query));

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => scaixaInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get($url);
}

/** Props da página, com pré-condição anti-vácuo: a tela certa renderizou. */
function scaixaProps(object $test, array $query = []): array
{
    $response = scaixaGet($test, $query);
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    expect($page['component'] ?? null)->toBe('Sells/Caixa/Index');

    return $page['props'];
}

function scaixaVenda(int $bizId, int $userId, string $dia, float $total, string $status = 'final', string $source = 'balcao', ?string $osRef = null): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'created_by' => $userId,
        'type' => 'sell',
        'status' => $status,
        'payment_status' => 'paid',
        'invoice_no' => 'SCX-' . uniqid(),
        'transaction_date' => $dia . ' 10:00:00',
        'total_before_tax' => $total,
        'final_total' => $total,
        'source' => $source,
        'os_ref' => $osRef,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function scaixaPagamento(int $vendaId, int $bizId, int $userId, float $valor, string $metodo, int $isReturn = 0): void
{
    DB::table('transaction_payments')->insert([
        'transaction_id' => $vendaId,
        'business_id' => $bizId,
        'amount' => $valor,
        'method' => $metodo,
        'is_return' => $isReturn,
        'paid_on' => now(),
        'created_by' => $userId,
        'payment_ref_no' => 'SCX-' . uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Linha de uma coleção pelo campo-chave (forma de pagamento ou origem). */
function scaixaLinha(array $linhas, string $campo, string $valor): ?array
{
    foreach ($linhas as $l) {
        if (($l[$campo] ?? null) === $valor) {
            return $l;
        }
    }

    return null;
}

function scaixaUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SCX Caixa',
        'username' => 'scx_' . uniqid(),
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

function scaixaLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'transaction_payments', 'cash_registers', 'business', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }
    if (! Schema::hasColumn('transactions', 'os_ref')) {
        $this->markTestSkipped('Migration 2026_05_25 (source/os_ref) não rodou.');
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id; // cria o 99 se faltar

    // PRÉ-CONDIÇÃO: os dois papéis são empresas DISTINTAS (senão o [T0] é tautológico).
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->user = scaixaUsuario($this->bizId, ['direct_sell.view']);
    $uid = $this->user->id;

    $this->v1 = scaixaVenda($this->bizId, $uid, SCAIXA_DIA, 100.00);
    scaixaPagamento($this->v1, $this->bizId, $uid, 60.00, 'cash');
    scaixaPagamento($this->v1, $this->bizId, $uid, 40.00, 'card');
    scaixaPagamento($this->v1, $this->bizId, $uid, 5.00, 'cash', 1);

    $this->v2 = scaixaVenda($this->bizId, $uid, SCAIXA_DIA, 50.00, 'final', 'oficina', 'OS-7001');
    scaixaPagamento($this->v2, $this->bizId, $uid, 50.00, 'cash');

    scaixaVenda($this->bizId, $uid, SCAIXA_DIA, 999.00, 'draft');

    $v4 = scaixaVenda($this->bizId, $uid, SCAIXA_DIA_ANTERIOR, 30.00);
    scaixaPagamento($v4, $this->bizId, $uid, 30.00, 'cash');

    $this->vx = scaixaVenda($this->outroBizId, $uid, SCAIXA_DIA, 70000.00, 'final', 'oficina', 'OS-9999');
    scaixaPagamento($this->vx, $this->outroBizId, $uid, 70000.00, 'cash');

    scaixaLogin($this, $this->user);
});

it('UC-SCAIXA-01 [V0] faturado do dia soma só vendas finais do dia', function () {
    $p = scaixaProps($this, ['date' => SCAIXA_DIA]);

    expect(round((float) $p['totalDia'], 2))->toBe(150.0);
    expect((int) $p['countDia'])->toBe(2);
    expect($p['dateSelected'])->toBe(SCAIXA_DIA);
});

it('UC-SCAIXA-02 [T0] venda de outro business não entra em nenhum agregado', function () {
    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a venda alheia existe no mesmo dia.
    expect(DB::table('transactions')->where('id', $this->vx)->where('business_id', $this->outroBizId)->exists())->toBeTrue();

    $p = scaixaProps($this, ['date' => SCAIXA_DIA]);

    expect(round((float) $p['totalDia'], 2))->toBe(150.0);
    expect((int) $p['countDia'])->toBe(2);

    $dinheiro = scaixaLinha($p['porFormaPagamento'], 'key', 'cash');
    expect($dinheiro)->not->toBeNull();
    expect(round((float) $dinheiro['total'], 2))->toBe(110.0);

    $oficina = scaixaLinha($p['porOrigem'], 'source', 'oficina');
    expect($oficina)->not->toBeNull();
    expect((int) $oficina['count'])->toBe(1);
    expect(round((float) $oficina['total'], 2))->toBe(50.0);

    $refIds = array_map(fn ($r) => (int) $r['id'], $oficina['refs']);
    expect(in_array($this->vx, $refIds, true))->toBeFalse();
});

it('UC-SCAIXA-03 sem permissão de venda a tela devolve 403', function () {
    $semPermissao = scaixaUsuario($this->bizId, []);
    scaixaLogin($this, $semPermissao);

    scaixaGet($this, ['date' => SCAIXA_DIA])->assertStatus(403);
});

it('UC-SCAIXA-04 a data escolhida muda o dia; sem data o dia é hoje', function () {
    $anterior = scaixaProps($this, ['date' => SCAIXA_DIA_ANTERIOR]);
    expect(round((float) $anterior['totalDia'], 2))->toBe(30.0);
    expect((int) $anterior['countDia'])->toBe(1);
    expect($anterior['dateSelected'])->toBe(SCAIXA_DIA_ANTERIOR);

    $semData = scaixaProps($this);
    expect($semData['dateSelected'])->toBe(now()->format('Y-m-d'));
});

it('UC-SCAIXA-05 [V0] por forma de pagamento conta vendas e soma valor sem o troco', function () {
    $p = scaixaProps($this, ['date' => SCAIXA_DIA]);

    $dinheiro = scaixaLinha($p['porFormaPagamento'], 'key', 'cash');
    $cartao = scaixaLinha($p['porFormaPagamento'], 'key', 'card');
    expect($dinheiro)->not->toBeNull();
    expect($cartao)->not->toBeNull();

    // dinheiro = 60 (V1) + 50 (V2); o troco de 5.00 (is_return=1) fica fora.
    expect((int) $dinheiro['count'])->toBe(2);
    expect(round((float) $dinheiro['total'], 2))->toBe(110.0);
    expect($dinheiro['label'])->toBe('Dinheiro');

    expect((int) $cartao['count'])->toBe(1);
    expect(round((float) $cartao['total'], 2))->toBe(40.0);
    expect($cartao['label'])->toBe('Cartão');
});

it('UC-SCAIXA-06 por origem separa balcão e oficina e entrega o id que abre a venda da OS', function () {
    $p = scaixaProps($this, ['date' => SCAIXA_DIA]);

    expect(count($p['porOrigem']))->toBe(2);

    $balcao = scaixaLinha($p['porOrigem'], 'source', 'balcao');
    $oficina = scaixaLinha($p['porOrigem'], 'source', 'oficina');
    expect($balcao)->not->toBeNull();
    expect($oficina)->not->toBeNull();

    expect((int) $balcao['count'])->toBe(1);
    expect(round((float) $balcao['total'], 2))->toBe(100.0);
    expect($oficina['label'])->toBe('Oficina');

    // O id da ref é o que o link ↗ #OS usa em /sells?open=<id>.
    expect(count($oficina['refs']))->toBe(1);
    expect((int) $oficina['refs'][0]['id'])->toBe($this->v2);
    expect($oficina['refs'][0]['os_ref'])->toBe('OS-7001');
});

it('UC-SCAIXA-07 [T0] caixa aberto é o do próprio usuário no próprio business', function () {
    $fechado = scaixaProps($this, ['date' => SCAIXA_DIA]);
    expect($fechado['caixaAberto'])->toBeFalse();
    expect($fechado['cashRegisterId'])->toBeNull();

    // Caixa aberto do mesmo user_id mas em OUTRO business: não pode acender.
    DB::table('cash_registers')->insert([
        'business_id' => $this->outroBizId, 'user_id' => $this->user->id, 'status' => 'open',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $aindaFechado = scaixaProps($this, ['date' => SCAIXA_DIA]);
    expect($aindaFechado['caixaAberto'])->toBeFalse();

    $regId = DB::table('cash_registers')->insertGetId([
        'business_id' => $this->bizId, 'user_id' => $this->user->id, 'status' => 'open',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $aberto = scaixaProps($this, ['date' => SCAIXA_DIA]);
    expect($aberto['caixaAberto'])->toBeTrue();
    expect((int) $aberto['cashRegisterId'])->toBe($regId);
});

it('UC-SCAIXA-08 o botão Fechar/Abrir caixa só existe com close_cash_register', function () {
    $sem = scaixaProps($this, ['date' => SCAIXA_DIA]);
    expect($sem['permissions']['close'])->toBeFalse();

    $fechador = scaixaUsuario($this->bizId, ['direct_sell.view', 'close_cash_register']);
    scaixaLogin($this, $fechador);

    $com = scaixaProps($this, ['date' => SCAIXA_DIA]);
    expect($com['permissions']['close'])->toBeTrue();
});

it('UC-SCAIXA-09 Imprimir Z aponta para uma rota registrada do caixa legado', function () {
    $rota = app('router')->getRoutes()->match(
        \Illuminate\Http\Request::create('/cash-register/register-details', 'GET')
    );

    expect($rota->getActionName())->toBe('App\Http\Controllers\CashRegisterController@getRegisterDetails');
});
