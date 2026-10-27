<?php
// @covers-us US-SELL-079

declare(strict_types=1);

use App\Services\Sells\ResgatePontosException;
use App\Services\Sells\ResgatePontosFidelidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UC-SRP-01..06 · US-SELL-079 — resgate de pontos de fidelidade na venda: o servidor confere pontos
 * e valor antes de gravar. Âncora: resources/js/Pages/Sells/Create.casos.md (UC-SRP-*).
 *
 * Contas à mão (UC-SRP-01, a MESMA tabela de tests/js/resgate-pontos-valor.test.ts):
 *   50 × 0,10 = 5,00 · 7 × 0,15 = 1,05 · 3 × 0,3333 = 0,9999 → 1,00 · 1 × 0,005 → 0,01 · 1 × 0,0049 → 0,00
 * Empresa: pontos ligados, ponto vale 0,10, resgate mínimo 10 pontos, venda mínima 20,00.
 * Cliente com 100 pontos: resgatar 50 → 5,00.
 *
 * Tenant 98 (fictício, ADR 0358); 99 é a outra empresa. NUNCA biz=4.
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! Schema::hasColumn('business', 'enable_rp') || ! Schema::hasColumn('contacts', 'total_rp')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL.');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) $this->seededSupportClientTenant()->id;
    $this->uid = (int) $this->usuarioComPermissoes([], $this->seededTenant())->id;
    DB::table('business')->where('id', $this->biz)->update([
        'enable_rp' => 1, 'rp_name' => 'Pontos', 'redeem_amount_per_unit_rp' => '0.1000',
        'min_order_total_for_redeem' => '20.0000', 'min_redeem_point' => 10, 'max_redeem_point' => null,
    ]);
    $this->cliente = fn (int $biz, int $pontos = 100, bool $padrao = false) => (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente Pontos Teste', 'contact_id' => 'RP-' . uniqid(),
        'total_rp' => $pontos, 'is_default' => $padrao ? 1 : 0, 'created_by' => $this->uid, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->servico = fn () => app(ResgatePontosFidelidade::class);
});

it('UC-SRP-01 o valor do resgate é pontos × valor do ponto, arredondado a 2 casas sem ponto flutuante', function () {
    foreach ([[50, '0.1000', '5.00'], [7, '0.1500', '1.05'], [3, '0.3333', '1.00'], [1, '0.0050', '0.01'], [1, '0.0049', '0.00'], [1234, '1.2500', '1542.50'], [0, '0.5000', '0.00']] as [$p, $v, $esperado]) {
        expect(ResgatePontosFidelidade::valorDoResgate($p, $v))->toBe($esperado);
    }
});

it('UC-SRP-02 resgate válido devolve pontos e valor conferidos', function () {
    $c = ($this->cliente)($this->biz);

    expect(($this->servico)()->conferir($this->biz, $c, '50', '5.00', 120.0))->toBe(['pontos' => 50, 'valor' => '5.00']);
    expect(($this->servico)()->conferir($this->biz, $c, 0, '0', 120.0))->toBe(['pontos' => 0, 'valor' => '0.00']);
});

it('UC-SRP-03 recusa mais pontos que o disponível, abaixo do mínimo de pontos e venda abaixo do mínimo', function () {
    $c = ($this->cliente)($this->biz, 30);

    expect(fn () => ($this->servico)()->conferir($this->biz, $c, 31, '3.10', 120.0))->toThrow(ResgatePontosException::class, 'até 30 pontos');
    expect(fn () => ($this->servico)()->conferir($this->biz, $c, 5, '0.50', 120.0))->toThrow(ResgatePontosException::class, 'mínimo é de 10');
    expect(fn () => ($this->servico)()->conferir($this->biz, $c, 20, '2.00', 19.99))->toThrow(ResgatePontosException::class, 'valor mínimo');
});

it('UC-SRP-04 recusa valor que não bate com os pontos (o total da venda já veio descontado)', function () {
    $c = ($this->cliente)($this->biz);

    expect(fn () => ($this->servico)()->conferir($this->biz, $c, 50, '50.00', 120.0))->toThrow(ResgatePontosException::class, '5,00, mas a venda');
    expect(($this->servico)()->conferir($this->biz, $c, 50, '5.01', 120.0)['valor'])->toBe('5.00'); // tolerância de 1 centavo
});

it('UC-SRP-05 recusa Cliente padrão, cliente de outra empresa e empresa sem pontos', function () {
    $padrao = ($this->cliente)($this->biz, 100, true);
    $deFora = ($this->cliente)($this->outro, 100);

    expect(fn () => ($this->servico)()->conferir($this->biz, $padrao, 50, '5.00', 120.0))->toThrow(ResgatePontosException::class, 'cliente cadastrado');
    expect(fn () => ($this->servico)()->conferir($this->biz, $deFora, 50, '5.00', 120.0))->toThrow(ResgatePontosException::class, 'cliente cadastrado');

    DB::table('business')->where('id', $this->biz)->update(['enable_rp' => 0]);
    $c = ($this->cliente)($this->biz);
    expect(fn () => ($this->servico)()->conferir($this->biz, $c, 50, '5.00', 120.0))->toThrow(ResgatePontosException::class, 'não estão ligados');
});

it('UC-SRP-06 a tela só recebe a configuração com a flag ligada e a empresa com pontos', function () {
    config(['sells.resgate_pontos.enabled' => false]);
    expect(ResgatePontosFidelidade::propsDaTela($this->biz))->toBeNull();

    config(['sells.resgate_pontos.enabled' => true]);
    expect(ResgatePontosFidelidade::propsDaTela($this->biz))
        ->toBe(['nome' => 'Pontos', 'valor_ponto' => '0.1000', 'minimo_venda' => '20.0000', 'minimo_pontos' => 10]);

    DB::table('business')->where('id', $this->biz)->update(['enable_rp' => 0]);
    expect(ResgatePontosFidelidade::propsDaTela($this->biz))->toBeNull();
});
