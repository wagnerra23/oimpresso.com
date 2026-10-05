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
 * Contrato do registro de devolução (`GET /sell-return/add/{venda}` → `SellReturnController@add`,
 * ramo Inertia → `SellReturn/Add`; grava por `POST /sell-return` → `store()`, INTACTO).
 * Thread 03 de venda-menu, PR 2 de 2.
 *
 * UCs: resources/js/Pages/SellReturn/Add.casos.md (UC-SRADD-01..06).
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 *
 * REGRA MESTRE (valor + estoque) — conta à mão, independente do código:
 *
 *   tenant 98 · produto P · local L · saldo inicial 10
 *   venda A e venda B (gêmeas): 1 linha, 3 un × 1.234,50 · desconto da venda 10% · sem imposto
 *   devolver 2 un de cada venda:
 *     subtotal = 2 × 1.234,50          = 2.469,00
 *     desconto = 10% × 2.469,00        =   246,90
 *     imposto  = 0
 *     total    = 2.469,00 − 246,90     = 2.222,10
 *   estoque P em L: 10 → 12 (devolução de A, payload da Blade) → 14 (devolução de B, payload da Page)
 *   cada devolução: quantity_returned 2 · payment_status due · 0 pagamentos lançados
 *
 * Caminho A da dupla prova = UC-SRADD-05: o MESMO store() recebe o payload que o form Blade
 * monta (expressões de sell_return/add.blade.php, copiadas aqui) e o payload que a Page monta
 * (os textos que o servidor entrega à Page + a quantidade digitada). As duas gravações têm de
 * ser iguais entre si E iguais à conta acima.
 *
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function sraddVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Headers que o cliente Inertia manda de verdade (X-Inertia E X-Requested-With). */
function sraddHeaders(array $extra = []): array
{
    return array_merge([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => sraddVersao(),
        'X-Requested-With' => 'XMLHttpRequest',
    ], $extra);
}

function sraddUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SRADD Devolucao',
        'username' => 'sradd_' . uniqid(),
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

/** Sessão que o SetSessionData montaria em produção para uma empresa pt-BR. */
function sraddSessao(User $user): array
{
    return [
        'user' => ['id' => $user->id, 'business_id' => (int) $user->business_id],
        'business' => [
            'id' => (int) $user->business_id,
            'date_format' => 'd/m/Y',
            'time_format' => 24,
            'currency_precision' => 2,
            'quantity_precision' => 2,
        ],
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];
}

/** A venda deferida que a Page recebe (partial reload da prop `venda`). */
function sraddVenda(object $test, int $vendaId): array
{
    $res = $test->withHeaders(sraddHeaders([
        'X-Inertia-Partial-Data' => 'venda',
        'X-Inertia-Partial-Component' => 'SellReturn/Add',
    ]))->get("/sell-return/add/{$vendaId}");
    $res->assertStatus(200);
    $page = json_decode($res->getContent(), true);

    expect($page['component'] ?? null)->toBe('SellReturn/Add');
    expect(array_key_exists('venda', $page['props']))->toBeTrue();

    return $page['props']['venda'];
}

/**
 * Payload do form Blade (sell_return/add.blade.php + public/js/sell_return.js, serialize()),
 * com as expressões das diretivas daquela view. Cópia declarada: se a Blade mudar, isto muda.
 */
function sraddPayloadBlade(int $vendaId, int $sellLineId, float $preco, string $quantidadeDigitada): array
{
    $num = fn ($v) => number_format((float) $v, 2, ',', '.'); // @num_format com sessão pt-BR

    return [
        'transaction_id' => $vendaId,
        'invoice_no' => '',
        'transaction_date' => \Carbon::createFromTimestamp(strtotime('now'))->format('d/m/Y H:i'),
        'products' => [[
            'quantity' => $quantidadeDigitada,
            'unit_price_inc_tax' => $num($preco),
            'sell_line_id' => $sellLineId,
        ]],
        'discount_type' => 'percentage',
        'discount_amount' => $num(10),
        'tax_id' => '',
        'tax_amount' => '0',
    ];
}

/** Payload da Page (Add.tsx → salvar()): os textos da prop `venda` + a quantidade digitada. */
function sraddPayloadPage(array $venda, string $quantidadeDigitada): array
{
    $produtos = [];
    foreach ($venda['linhas'] as $l) {
        $produtos[] = [
            'quantity' => $quantidadeDigitada,
            'unit_price_inc_tax' => $l['preco_unitario_txt'],
            'sell_line_id' => $l['sell_line_id'],
        ];
    }

    return [
        'transaction_id' => $venda['id'],
        'invoice_no' => $venda['numero_devolucao_txt'],
        'transaction_date' => $venda['data_devolucao_txt'],
        'products' => $produtos,
        'discount_type' => $venda['desconto_tipo'],
        'discount_amount' => $venda['desconto_valor_txt'],
        'tax_id' => $venda['tax_id'] === null ? '' : (string) $venda['tax_id'],
        'tax_amount' => '0,00',
    ];
}

/** Retrato da devolução gravada para uma venda. */
function sraddRetrato(int $vendaId, int $sellLineId): array
{
    $dev = DB::table('transactions')->where('type', 'sell_return')->where('return_parent_id', $vendaId)->first();

    return [
        'existe' => $dev !== null,
        'final_total' => $dev ? round((float) $dev->final_total, 2) : null,
        'total_before_tax' => $dev ? round((float) $dev->total_before_tax, 2) : null,
        'tax_amount' => $dev ? round((float) $dev->tax_amount, 2) : null,
        'discount_type' => $dev->discount_type ?? null,
        'discount_amount' => $dev ? round((float) $dev->discount_amount, 2) : null,
        'payment_status' => $dev->payment_status ?? null,
        'pagamentos' => $dev ? DB::table('transaction_payments')->where('transaction_id', $dev->id)->count() : null,
        'quantity_returned' => round((float) DB::table('transaction_sell_lines')->where('id', $sellLineId)->value('quantity_returned'), 4),
    ];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'transaction_sell_lines', 'transaction_payments', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id; // cria o 99 se faltar
    // PRÉ-CONDIÇÃO: os dois papéis são empresas DISTINTAS (senão o [T0] é tautológico).
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->loc = EstoqueFixture::locationId($this->bizId);
    $this->produto = EstoqueFixture::singleProduct($this->bizId);
    EstoqueFixture::setStock($this->produto, 0, $this->loc, 10.0);

    $gemea = function () {
        $v = EstoqueFixture::saleWithLine($this->produto, 0, $this->loc, 3.0, 1234.50);
        DB::table('transactions')->where('id', $v['transaction_id'])
            ->update(['discount_type' => 'percentage', 'discount_amount' => 10, 'invoice_no' => 'SRADD-' . uniqid()]);

        return $v;
    };
    $this->vendaA = $gemea();
    $this->vendaB = $gemea();

    $this->usuario = sraddUsuario($this->bizId, ['access_sell_return', 'access_all_locations']);
    $this->actingAs($this->usuario)->withSession(sraddSessao($this->usuario));
});

it('UC-SRADD-01 a visita Inertia recebe SellReturn/Add com a venda de origem e as linhas', function () {
    $res = $this->withHeaders(sraddHeaders())->get("/sell-return/add/{$this->vendaA['transaction_id']}");
    $res->assertStatus(200);
    $page = json_decode($res->getContent(), true);

    expect($page['component'] ?? null)->toBe('SellReturn/Add');
    // A venda vem deferida: não está na carga inicial.
    expect(array_key_exists('venda', $page['props']))->toBeFalse();

    $venda = sraddVenda($this, $this->vendaA['transaction_id']);
    expect((int) $venda['id'])->toBe($this->vendaA['transaction_id']);
    expect($venda['numero'])->toStartWith('SRADD-');
    expect($venda['devolucao'])->toBeNull();
    expect(count($venda['linhas']))->toBe(1);
    expect((int) $venda['linhas'][0]['sell_line_id'])->toBe($this->vendaA['sell_line_id']);
});

it('UC-SRADD-02 [T0] venda de outro business: 404 na tela e nada gravado pelo POST', function () {
    $locOutro = EstoqueFixture::locationId($this->outroBizId);
    $produtoOutro = EstoqueFixture::singleProduct($this->outroBizId);
    EstoqueFixture::setStock($produtoOutro, 0, $locOutro, 7.0);
    $vendaOutro = EstoqueFixture::saleWithLine($produtoOutro, 0, $locOutro, 3.0, 50.0);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a venda alheia existe, no outro business.
    expect((int) DB::table('transactions')->where('id', $vendaOutro['transaction_id'])->value('business_id'))->toBe($this->outroBizId);

    $this->withHeaders(sraddHeaders())->get("/sell-return/add/{$vendaOutro['transaction_id']}")->assertStatus(404);

    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/sell-return', sraddPayloadBlade($vendaOutro['transaction_id'], $vendaOutro['sell_line_id'], 50.0, '1'))
        ->assertJson(['success' => 0]);

    expect(DB::table('transactions')->where('type', 'sell_return')->where('return_parent_id', $vendaOutro['transaction_id'])->exists())->toBeFalse();
    expect(EstoqueFixture::currentStock($produtoOutro, 0, $locOutro))->toBe(7.0);
});

it('UC-SRADD-03 sem permissão de devolução a tela devolve 403', function () {
    $semPermissao = sraddUsuario($this->bizId, ['access_all_locations']);
    $this->actingAs($semPermissao)->withSession(sraddSessao($semPermissao));

    $this->withHeaders(sraddHeaders())->get("/sell-return/add/{$this->vendaA['transaction_id']}")->assertStatus(403);
});

it('UC-SRADD-04 [E0] o teto da linha é a quantidade vendida e o excesso é recusado', function () {
    $venda = sraddVenda($this, $this->vendaA['transaction_id']);
    expect((float) $venda['linhas'][0]['quantidade_vendida'])->toBe(3.0);
    expect($venda['linhas'][0]['quantidade_vendida_txt'])->toBe('3,00');

    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/sell-return', sraddPayloadPage($venda, '5'))
        ->assertJson(['success' => 0]);

    expect(sraddRetrato($this->vendaA['transaction_id'], $this->vendaA['sell_line_id'])['existe'])->toBeFalse();
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->loc))->toBe(10.0);
});

it('UC-SRADD-05 [V0][E0] o payload da tela e o da Blade gravam o mesmo valor e o mesmo estoque', function () {
    // ANTES — medido, não assumido.
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->loc))->toBe(10.0);
    expect(sraddRetrato($this->vendaA['transaction_id'], $this->vendaA['sell_line_id'])['existe'])->toBeFalse();
    expect(sraddRetrato($this->vendaB['transaction_id'], $this->vendaB['sell_line_id'])['existe'])->toBeFalse();

    $post = fn (array $payload) => $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/sell-return', $payload);

    // Venda A pelo form Blade.
    $post(sraddPayloadBlade($this->vendaA['transaction_id'], $this->vendaA['sell_line_id'], 1234.50, '2'))
        ->assertJson(['success' => 1]);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->loc))->toBe(12.0);

    // Venda B pela tela React.
    $vendaB = sraddVenda($this, $this->vendaB['transaction_id']);
    $post(sraddPayloadPage($vendaB, '2'))->assertJson(['success' => 1]);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->loc))->toBe(14.0);

    $blade = sraddRetrato($this->vendaA['transaction_id'], $this->vendaA['sell_line_id']);
    $page = sraddRetrato($this->vendaB['transaction_id'], $this->vendaB['sell_line_id']);

    $esperado = [
        'existe' => true,
        'final_total' => 2222.10,
        'total_before_tax' => 2469.00,
        'tax_amount' => 0.0,
        'discount_type' => 'percentage',
        'discount_amount' => 10.0,
        'payment_status' => 'due',
        'pagamentos' => 0,
        'quantity_returned' => 2.0,
    ];
    expect($blade)->toBe($esperado);
    expect($page)->toBe($esperado);
});

it('UC-SRADD-06 [V0] a tela recebe os campos como texto no formato da empresa', function () {
    $venda = sraddVenda($this, $this->vendaA['transaction_id']);
    $linha = $venda['linhas'][0];

    expect($linha['preco_unitario_txt'])->toBe('1.234,50');
    expect($linha['quantidade_devolvida_txt'])->toBe('0,00');
    expect($venda['desconto_tipo'])->toBe('percentage');
    expect($venda['desconto_valor_txt'])->toBe('10,00');
    expect($venda['tax_id'])->toBeNull();
    expect(preg_match('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#', $venda['data_devolucao_txt']))->toBe(1);
});
