<?php

declare(strict_types=1);

// @covers-us US-NFE-072 — a NF-e da venda sai para o cliente da venda (R-NFE-036).

use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

uses(Tests\TestCase::class);

/**
 * R-NFE-036 · NfeService::destinatarioDoContato + o destino que chega ao motor.
 *
 * Sem banco: o contato entra como objeto (o mesmo formato de `DB::table('contacts')->first()`)
 * e o motor é falso, gravando o que recebeu. Os números do UC-FNFE-32 são fixture: um item de
 * 1.000,00 vendido de SC para um não contribuinte em RJ, interna do RJ 20%.
 */

function contato36(array $campos = []): object
{
    return (object) array_merge([
        'name' => 'Cliente Fixture', 'supplier_business_name' => null,
        'tax_number' => '11.222.333/0001-81', 'cpf_cnpj' => null, # pii-allowlist: CNPJ fictício de fixture
        'inscricao_estadual' => '12.345.678', 'ie' => null, 'indicador_ie' => null,
        'address_line_1' => 'Rua das Flores', 'numero' => '100', 'neighborhood' => 'Centro',
        'bairro' => null, 'city' => 'Rio de Janeiro', 'city_code' => '3304557',
        'state' => 'rj', 'zip_code' => '20000-000', 'email' => 'fixture@example.test',
    ], $campos);
}

function motorFake36(ArrayObject $log): MotorTributarioService
{
    return new class($log) extends MotorTributarioService {
        public function __construct(private ArrayObject $log) {}

        public function calcular(ProdutoFiscalContext $produto, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            $this->log[] = ['via' => 'calcular', 'uf_destino' => $ufDestino];

            return $this->tributo($produto, $ufOrigem, $ufDestino, null, null);
        }

        public function calcularComDestino(
            ProdutoFiscalContext $produto,
            int $businessId,
            string $ufOrigem,
            string $ufDestino,
            ?float $aliquotaInternaDestino = null,
            ?bool $destinatarioContribuinte = null,
            ?int $operacaoId = null,
            ?string $dataEmissao = null,
        ): TributoCalculado {
            $this->log[] = [
                'via' => 'calcularComDestino', 'uf_destino' => $ufDestino,
                'interna' => $aliquotaInternaDestino, 'contribuinte' => $destinatarioContribuinte,
            ];

            return $this->tributo($produto, $ufOrigem, $ufDestino, $aliquotaInternaDestino, $destinatarioContribuinte);
        }

        private function tributo(ProdutoFiscalContext $p, string $o, string $d, ?float $interna, ?bool $contrib): TributoCalculado
        {
            $difal = ($interna !== null && $contrib === false && $o !== $d) ? round($p->valor * ($interna - 0.12), 2) : 0.0;

            return new TributoCalculado(
                cfop: $o === $d ? '5102' : '6102', csosn: null, cst: '00',
                aliquota_icms: 0.12, aliquota_pis: 0.0, aliquota_cofins: 0.0, aliquota_ipi: 0.0,
                valor_icms: round($p->valor * 0.12, 2), valor_pis: 0.0, valor_cofins: 0.0, valor_ipi: 0.0,
                nivel_usado: 2, valor_difal: $difal,
            );
        }
    };
}

function linha36(float $valor): array
{
    return [
        'sell_line_id' => 1, 'product_id' => 9001, 'cprod' => 'SKU1', 'xprod' => 'Produto 1',
        'ncm' => '61091000', 'unidade' => 'UN', 'quantidade' => 1.0, 'valor_unitario' => $valor,
    ];
}

it('UC-FNFE-32 · cliente com CNPJ e IE vira o destinatário, com a UF dele', function () {
    $dest = app(NfeService::class)->destinatarioDoContato(contato36(), 'SC');

    expect($dest['cnpj'])->toBe('11222333000181');
    expect($dest['ie'])->toBe('12345678');
    expect($dest['ind_ie_dest'])->toBe('1');
    expect($dest['uf'])->toBe('RJ');
    expect($dest['cod_municipio'])->toBe('3304557');
    expect($dest['cep'])->toBe('20000000');
    expect($dest['nome'])->toBe('Cliente Fixture');
});

it('UC-FNFE-32 · sem IE é não contribuinte (9) e não leva tag IE; indicador cadastrado manda', function () {
    $svc = app(NfeService::class);

    $semIe = $svc->destinatarioDoContato(contato36(['inscricao_estadual' => null]), 'SC');
    expect($semIe['ind_ie_dest'])->toBe('9');
    expect($semIe)->not->toHaveKey('ie');

    $isento = $svc->destinatarioDoContato(contato36(['indicador_ie' => 2]), 'SC');
    expect($isento['ind_ie_dest'])->toBe('2');
    expect($isento)->not->toHaveKey('ie');

    $cpf = $svc->destinatarioDoContato(contato36(['tax_number' => '529.982.247-25', 'inscricao_estadual' => null]), 'SC'); # pii-allowlist: CPF fictício de fixture
    expect($cpf['cpf'])->toBe('52998224725');
    expect($cpf)->not->toHaveKey('cnpj');
});

it('UC-FNFE-33 · contato sem CPF/CNPJ não vira destinatário (a nota segue anônima)', function () {
    $svc = app(NfeService::class);

    expect($svc->destinatarioDoContato(contato36(['tax_number' => '', 'cpf_cnpj' => null]), 'SC'))->toBeNull();
    expect($svc->destinatarioDoContato(contato36(['tax_number' => '123']), 'SC'))->toBeNull();
    // UF inválida no cadastro: a operação fica interna, na UF do emitente.
    expect($svc->destinatarioDoContato(contato36(['state' => 'Rio']), 'SC')['uf'])->toBe('SC');
});

it('UC-FNFE-32 · o destino chega ao motor: UF, contribuinte e interna — e o total não muda', function () {
    $log = new ArrayObject();
    $r = app(NfeService::class)->montarItensNfe(
        linhas: [linha36(1000.00)], valorNota: 1000.00, frete: 0, ncmDefault: '49111090',
        motor: motorFake36($log), businessId: 98, ufOrigem: 'SC', ufDestino: 'RJ',
        internaDestino: 0.20, destinatarioContribuinte: false,
    );

    expect($log[0])->toBe(['via' => 'calcularComDestino', 'uf_destino' => 'RJ', 'interna' => 0.20, 'contribuinte' => false]);
    expect($r['dets'][0]['cfop'])->toBe('6102');
    expect($r['total']['v_nf'])->toBe(1000.0);
    expect($r['total']['v_icms'])->toBe(120.0);
});

it('UC-FNFE-33 · sem dado de destino a chamada ao motor é a de sempre (NFC-e e anônimo)', function () {
    $log = new ArrayObject();
    $r = app(NfeService::class)->montarItensNfe(
        linhas: [linha36(1000.00)], valorNota: 1000.00, frete: 0, ncmDefault: '49111090',
        motor: motorFake36($log), businessId: 98, ufOrigem: 'SC', ufDestino: 'SC',
    );

    expect($log[0])->toBe(['via' => 'calcular', 'uf_destino' => 'SC']);
    expect($r['dets'][0]['cfop'])->toBe('5102');
    expect($r['total']['v_nf'])->toBe(1000.0);
});
