<?php
// @covers-us US-SELL-076

declare(strict_types=1);

use App\Business;
use App\Services\Sells\CupomDaVenda;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * UC-VCUP-01..05 · US-SELL-076 — cupom por código no caixa, com a regra de acúmulo.
 *
 * Âncora: resources/js/Pages/Sells/Create.casos.md (UC-VCUP-*), derivado da US-SELL-076.
 *
 * Carrinho do exemplo, feito à mão: subtotal 200,00 sem promoção; a promoção das linhas dá 20,00.
 *   não acumula, 15% → 30,00 > 20,00 → cupom substitui a promoção → total 170,00
 *   não acumula,  5% → 10,00 ≤ 20,00 → recusado, fica a promoção  → total 180,00
 *   acumula,     10% → sobre 180,00 = 18,00                       → total 162,00
 *   acumula, valor 250,00 → limitado à base 180,00                → total 20,00 (só a promoção paga)
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('sell_coupons')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL (sells-pest).');
    }
    config(['sells.cupom.ativo' => true]);
    $this->biz = EstoqueFixture::businessId();
    $this->cupom = fn (string $codigo, array $c = [], ?int $biz = null) => (int) DB::table('sell_coupons')->insertGetId($c + [
        'business_id' => $biz ?? $this->biz, 'codigo' => $codigo, 'tipo' => 'percentual', 'valor' => 10,
        'acumula_promocao' => 0, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->svc = app(CupomDaVenda::class);
    $this->avaliar = fn (string $codigo) => $this->svc->avaliar($this->biz, $codigo, 200.0, 20.0, now());
});

it('UC-VCUP-01 cupom que não acumula e dá mais que a promoção substitui a promoção', function () {
    ($this->cupom)('QUINZE', ['valor' => 15]);
    $r = ($this->avaliar)('QUINZE');

    expect($r['ok'])->toBeTrue();
    expect($r['desconto'])->toBe(30.0);
    expect($r['substitui_promocao'])->toBeTrue();
    expect(200.0 - $r['desconto'])->toBe(170.0);
});

it('UC-VCUP-02 cupom que não acumula e dá menos que a promoção é recusado', function () {
    ($this->cupom)('CINCO', ['valor' => 5]);
    $r = ($this->avaliar)('CINCO');

    expect($r['ok'])->toBeFalse();
    expect($r['motivo'])->toBe('promocao_maior');
    expect($r['desconto'])->toBe(0.0);
});

it('UC-VCUP-03 cupom que acumula incide sobre o subtotal já com a promoção, sem passar da base', function () {
    ($this->cupom)('DEZ', ['acumula_promocao' => 1]);
    ($this->cupom)('GRANDE', ['acumula_promocao' => 1, 'tipo' => 'valor', 'valor' => 250]);

    $r = ($this->avaliar)('DEZ');
    expect($r['desconto'])->toBe(18.0);
    expect($r['substitui_promocao'])->toBeFalse();
    expect(200.0 - 20.0 - $r['desconto'])->toBe(162.0);

    expect(($this->avaliar)('GRANDE')['desconto'])->toBe(180.0);
});

it('UC-VCUP-04 validade, limite de uso e valor mínimo recusam; o último uso não é gasto duas vezes', function () {
    ($this->cupom)('VENCIDO', ['valido_ate' => now()->subDay()]);
    ($this->cupom)('FUTURO', ['valido_de' => now()->addDay()]);
    ($this->cupom)('MINIMO', ['valor_minimo' => 300]);
    $unico = ($this->cupom)('UNICO', ['valor' => 50, 'limite_usos' => 1]);

    expect(($this->avaliar)('VENCIDO')['motivo'])->toBe('fora_da_validade');
    expect(($this->avaliar)('FUTURO')['motivo'])->toBe('fora_da_validade');
    expect(($this->avaliar)('MINIMO')['motivo'])->toBe('abaixo_do_minimo');

    expect($this->svc->consumir($this->biz, $unico))->toBeTrue();
    expect($this->svc->consumir($this->biz, $unico))->toBeFalse();
    expect(($this->avaliar)('UNICO')['motivo'])->toBe('esgotado');
    expect((int) DB::table('sell_coupons')->where('id', $unico)->value('usos'))->toBe(1);
});

it('UC-VCUP-05 a rota só existe ligada; código em minúscula vale; cupom de outra empresa é inválido', function () {
    ($this->cupom)('QUINZE', ['valor' => 15]);
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra) {
        ($this->cupom)('SOLA', ['valor' => 90], $outra);
    }
    $user = $this->usuarioComPermissoes(['sell.create'], Business::findOrFail($this->biz));
    $req = fn (string $codigo) => $this->actingAs($user)
        ->withSession(['user.business_id' => $this->biz, 'business.id' => $this->biz])
        ->postJson('/sells/cupom', ['codigo' => $codigo, 'subtotal' => 200, 'promocao' => 20]);

    $req(' quinze ')->assertOk()->assertJsonPath('desconto', 30.0)->assertJsonPath('ok', true);
    if ($outra) {
        $req('SOLA')->assertOk()->assertJsonPath('ok', false)->assertJsonPath('motivo', 'invalido');
    }

    config(['sells.cupom.ativo' => false]);
    $req('QUINZE')->assertNotFound();
});
