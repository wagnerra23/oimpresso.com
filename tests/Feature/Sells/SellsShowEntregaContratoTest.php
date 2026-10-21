<?php
// @covers-us US-SELL-073

declare(strict_types=1);
// Cobre UC-VSHOW-15, UC-VSHOW-16 (resources/js/Pages/Sells/Show.casos.md) — UC-FLX-23.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * UC-FLX-23 · US-SELL-073 — o detalhe da venda mostra o protocolo de entrega que o app das lojas
 * gravou em `app_entregas`: data, quem recebeu, volumes e itens.
 *
 * Âncora: aceite aberto da US-SELL-073 (memory/requisitos/Sells/SPEC.md) — "o detalhe da venda
 * (/sells/{id}) mostra o protocolo, os itens, os volumes e quem recebeu, só para quem vê a venda
 * na empresa" — e o UC-FLX-23 do catálogo de fluxos (app/Domain/Fsm/Modelos/CasosDoPrototipo.php).
 *
 * O protocolo é semeado por INSERT (o fluxo que grava já tem teste próprio:
 * AppPedidoEntregaContratoTest). Aqui só se prova a leitura.
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

function entregaShowGet(object $test, int $saleId)
{
    $manifest = public_path('build-inertia/manifest.json');

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Inertia-Partial-Component' => 'Sells/Show',
        'X-Inertia-Partial-Data' => 'detail',
    ])->get("/sells/{$saleId}");
}

/** Linha de `app_entregas` como o PedidoEntregaController grava. */
function entregaShowSemear(int $bizId, int $saleId, int $userId, string $recebidoPor, array $itens, ?int $volumes): void
{
    $numero = (int) DB::table('app_entregas')->where('business_id', $bizId)->max('numero') + 1;
    DB::table('app_entregas')->insert([
        'business_id' => $bizId, 'transaction_id' => $saleId, 'numero' => $numero,
        'protocolo' => 'ENT-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT),
        'recebido_por' => $recebidoPor, 'documento' => null, 'com_assinatura' => false,
        'assinatura_arquivo_id' => null, 'sem_assinatura_motivo' => 'Cliente sem caneta no balcão',
        'itens' => json_encode($itens), 'volumes' => $volumes, 'user_id' => $userId,
        'registrado_em' => '2026-10-20 14:30:00', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('app_entregas')
        || ! Schema::hasColumn('app_entregas', 'volumes')) {
        $this->markTestSkipped('Schema UltimatePOS/app_entregas ausente — roda na lane MySQL / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $biz = \App\Business::findOrFail($this->bizId);
    // Usuário novo com só `sell.view`: o primeiro do seed pode ser admin e o teste não mediria a permissão real.
    $this->user = $this->usuarioComPermissoes(['sell.view'], $biz);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id]);

    $produto = EstoqueFixture::singleProduct($this->bizId);
    $venda = EstoqueFixture::saleWithLine($produto, 0, EstoqueFixture::locationId($this->bizId), 2.0, 50.0);
    $this->saleId = $venda['transaction_id'];
    $this->lineId = $venda['sell_line_id'];
    $this->produtoNome = (string) DB::table('products')->where('id', $produto->productId)->value('name');
});

it('UC-VSHOW-15 · a entrega registrada pelo app aparece no detalhe da venda', function () {
    // Controle: sem protocolo, o detalhe chega e a entrega é nula.
    $antes = json_decode(entregaShowGet($this, $this->saleId)->assertStatus(200)->getContent(), true);
    expect($antes['props'])->toHaveKey('detail');
    expect($antes['props']['detail']['entrega'])->toBeNull();

    entregaShowSemear($this->bizId, $this->saleId, (int) $this->user->id, 'Maria da Recepção',
        [['linha_id' => $this->lineId, 'conferido' => true]], 3);

    $page = json_decode(entregaShowGet($this, $this->saleId)->assertStatus(200)->getContent(), true);
    $entrega = $page['props']['detail']['entrega'];

    expect($entrega)->not->toBeNull()
        ->and($entrega['protocolo'])->toStartWith('ENT-')
        ->and($entrega['recebido_por'])->toBe('Maria da Recepção')
        ->and($entrega['volumes'])->toBe(3)
        ->and($entrega['registrado_em'])->toStartWith('2026-10-20 14:30')
        ->and($entrega['com_assinatura'])->toBeFalse()
        ->and($entrega['assinatura_url'])->toBeNull()
        ->and($entrega['sem_assinatura_motivo'])->toBe('Cliente sem caneta no balcão');

    expect($entrega['itens'])->toHaveCount(1);
    expect($entrega['itens'][0]['linha_id'])->toBe($this->lineId)
        ->and($entrega['itens'][0]['produto'])->toBe($this->produtoNome)
        ->and((float) $entrega['itens'][0]['quantidade'])->toBe(2.0)
        ->and($entrega['itens'][0]['conferido'])->toBeTrue();
});

it('UC-VSHOW-15 · volumes não informados chegam nulos, sem número inventado', function () {
    entregaShowSemear($this->bizId, $this->saleId, (int) $this->user->id, 'João',
        [['linha_id' => $this->lineId, 'conferido' => true]], null);

    $page = json_decode(entregaShowGet($this, $this->saleId)->assertStatus(200)->getContent(), true);

    expect($page['props']['detail']['entrega'])->not->toBeNull();
    expect($page['props']['detail']['entrega']['volumes'])->toBeNull();
});

it('UC-VSHOW-16 · a entrega de outra empresa não aparece na venda (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant.');
    }
    $outroUserId = (int) DB::table('users')->where('business_id', $outroBizId)->value('id');

    // Linha de outra empresa apontando para o MESMO transaction_id desta venda.
    entregaShowSemear($outroBizId, $this->saleId, $outroUserId ?: (int) $this->user->id, 'Recebedor Alheio',
        [['linha_id' => $this->lineId, 'conferido' => true]], 9);
    expect(DB::table('app_entregas')->where('business_id', $outroBizId)
        ->where('transaction_id', $this->saleId)->exists())->toBeTrue();

    $r = entregaShowGet($this, $this->saleId)->assertStatus(200);
    $page = json_decode($r->getContent(), true);
    expect($page['props'])->toHaveKey('detail');
    expect($page['props']['detail']['entrega'])->toBeNull();
    expect($r->getContent())->not->toContain('Recebedor Alheio');

    // Controle positivo: a entrega da própria empresa aparece, e a alheia segue fora.
    entregaShowSemear($this->bizId, $this->saleId, (int) $this->user->id, 'Recebedor Daqui',
        [['linha_id' => $this->lineId, 'conferido' => true]], 1);
    $r2 = entregaShowGet($this, $this->saleId)->assertStatus(200);
    $depois = json_decode($r2->getContent(), true);
    expect($depois['props']['detail']['entrega']['recebido_por'])->toBe('Recebedor Daqui');
    expect($r2->getContent())->not->toContain('Recebedor Alheio');
});
