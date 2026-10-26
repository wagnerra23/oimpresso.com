<?php

declare(strict_types=1);

// @covers-us US-NFE-002 — itens do regime normal saem com base e alíquota em percentual (R-NFE-040).

use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

uses(Tests\TestCase::class);

/**
 * R-NFE-040 · a régua guarda alíquota em fração (0,18 = 18%, `nfe_fiscal_rules.aliquota_icms`
 * decimal(7,4) e os templates de presumido/real com 0.18); o leiaute pede percentual em
 * pICMS/pPIS/pCOFINS. E o item tributado do regime normal leva vBC = base, porque a SEFAZ confere
 * vICMS = vBC × pICMS / 100.
 *
 * Dupla prova (fixture): item de 1.000,00, ICMS 18%, PIS 1,65%, COFINS 7,6%.
 *   À mão: vBC 1.000,00 · pICMS 18 · vICMS 180,00 · pPIS 1,65 · vPIS 16,50 · pCOFINS 7,6 · vCOFINS 76,00.
 *   Conferência: 1.000,00 × 18 / 100 = 180,00 — o mesmo vICMS que o motor calcula com 0,18.
 */

function motorFake40(?string $csosn, ?string $cst, float $icms, ?float $baseIcms = null): MotorTributarioService
{
    return new class($csosn, $cst, $icms, $baseIcms) extends MotorTributarioService {
        public function __construct(private ?string $csosn, private ?string $cst, private float $icms, private ?float $baseIcms) {}

        public function calcular(ProdutoFiscalContext $p, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '5102', csosn: $this->csosn, cst: $this->cst,
                aliquota_icms: $this->icms, aliquota_pis: 0.0165, aliquota_cofins: 0.076, aliquota_ipi: 0.0,
                valor_icms: round(($this->baseIcms ?? $p->valor) * $this->icms, 2),
                valor_pis: round($p->valor * 0.0165, 2), valor_cofins: round($p->valor * 0.076, 2),
                valor_ipi: 0.0, nivel_usado: 2, base_icms: $this->baseIcms,
            );
        }
    };
}

function montar40(MotorTributarioService $motor): array
{
    return app(NfeService::class)->montarItensNfe(
        linhas: [[
            'sell_line_id' => 1, 'product_id' => 9001, 'cprod' => 'SKU1', 'xprod' => 'Produto 1',
            'ncm' => '61091000', 'unidade' => 'UN', 'quantidade' => 1.0, 'valor_unitario' => 1000.00,
        ]],
        valorNota: 1000.00, frete: 0, ncmDefault: '49111090',
        motor: $motor, businessId: 98, ufOrigem: 'SC', ufDestino: 'SC',
    );
}

it('UC-FNFE-42 · regime normal CST 00: alíquotas em percentual e base cheia no item e no total', function () {
    $r = montar40(motorFake40(null, '00', 0.18));
    $d = $r['dets'][0];

    expect($d['icms']['picms'])->toBe(18.0);
    expect($d['icms']['vbc'])->toBe(1000.0);
    expect($d['icms']['vicms'])->toBe(180.0);
    expect($d['pis']['ppis'])->toBe(1.65);
    expect($d['cofins']['pcofins'])->toBe(7.6);
    expect($r['total']['v_bc_icms'])->toBe(1000.0);
    expect($r['total']['v_icms'])->toBe(180.0);
    // A conta que a SEFAZ confere fecha: vBC × pICMS / 100 = vICMS.
    expect(round($d['icms']['vbc'] * $d['icms']['picms'] / 100, 2))->toBe($d['icms']['vicms']);
});

it('UC-FNFE-42 · com base reduzida (benefício) a base do motor prevalece', function () {
    $r = montar40(motorFake40(null, '20', 0.18, 600.0));

    expect($r['dets'][0]['icms']['vbc'])->toBe(600.0);
    expect($r['total']['v_bc_icms'])->toBe(600.0);
});

it('UC-FNFE-43 · Simples (CSOSN 102) e CST sem ICMS seguem com base 0, como antes', function () {
    $simples = montar40(motorFake40('102', null, 0.0));
    expect($simples['dets'][0]['icms']['vbc'])->toBe(0.0);
    expect($simples['total']['v_bc_icms'])->toBe(0.0);

    $isento = montar40(motorFake40(null, '40', 0.0));
    expect($isento['dets'][0]['icms']['vbc'])->toBe(0.0);

    // CST tributado mas com alíquota zero na regra: sem base inventada.
    expect(montar40(motorFake40(null, '00', 0.0))['dets'][0]['icms']['vbc'])->toBe(0.0);
});
