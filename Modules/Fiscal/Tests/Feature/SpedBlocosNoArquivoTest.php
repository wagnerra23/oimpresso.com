<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

uses(Tests\TestCase::class);

/**
 * UC-FSPED-15..17 (Sped.casos.md) — o que os Blocos 0, C, E e H escrevem NO ARQUIVO.
 *
 * Substituem quatro casos que só procuravam texto no código-fonte do gerador
 * (`SpedIcmsIpiGeneratorServiceTest`: "23 registros", "E110 consolida", "E116 só com ICMS",
 * "H esqueleto"). Aqui o gerador roda inteiro, sem banco: business e NF-e do tenant fictício 98
 * em memória, e um motor tributário de teste que cobra 18% de ICMS. O que se lê é o TXT.
 */
function spedArquivoGerador(array $emissoes, bool $comIcms): SpedIcmsIpiGeneratorService
{
    $motor = $comIcms ? new class extends MotorTributarioService
    {
        public function __construct()
        {
        }

        public function calcular(ProdutoFiscalContext $produto, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '5102', csosn: null, cst: '00',
                aliquota_icms: 0.18, aliquota_pis: 0.0, aliquota_cofins: 0.0, aliquota_ipi: 0.0,
                valor_icms: round($produto->valor * 0.18, 2),
                valor_pis: 0.0, valor_cofins: 0.0, valor_ipi: 0.0, nivel_usado: 2,
            );
        }
    } : null;

    return new class($motor, $emissoes) extends SpedIcmsIpiGeneratorService
    {
        public function __construct(?MotorTributarioService $motor, private array $fake)
        {
            parent::__construct($motor);
        }

        protected function carregarBusiness(int $businessId): ?object
        {
            return (object) ['id' => $businessId, 'name' => 'CI TENANT 98 (FICTICIO)', 'state' => 'SC'];
        }

        protected function carregarEmissoes(int $businessId, CarbonImmutable $ini, CarbonImmutable $fim): Collection
        {
            return collect($this->fake);
        }
    };
}

function spedArquivoNota(int $id, float $valor): NfeEmissao
{
    $e = new NfeEmissao();
    $e->id = $id;
    $e->business_id = 98;
    $e->modelo = '55';
    $e->serie = '1';
    $e->numero = 1000 + $id;
    $e->chave_44 = str_pad((string) $id, 44, '4', STR_PAD_LEFT);
    $e->status = 'autorizada';
    $e->valor_total = $valor;
    $e->emitido_em = CarbonImmutable::create(2026, 1, 10, 12);
    $e->metadata = ['ncm' => '61091000', 'dest_uf' => 'SC', 'dest_cnpj' => '00000000000191', 'dest_name' => 'CLIENTE TESTE'];

    return $e;
}

/** @return array<string, list<list<string>>> linhas do TXT agrupadas pelo registro */
function spedArquivoPorRegistro(string $txt): array
{
    $por = [];
    foreach (array_filter(explode("\r\n", $txt), fn ($l) => $l !== '') as $l) {
        $c = explode('|', substr($l, 1, -1));
        $por[$c[0]][] = $c;
    }

    return $por;
}

it('UC-FSPED-15 · os 23 registros do gerador saem no arquivo de um mês com nota', function () {
    $por = spedArquivoPorRegistro(spedArquivoGerador([spedArquivoNota(1, 1000.00)], true)->gerar(98, 2026, 1));

    $esperados = [
        '0000', '0001', '0005', '0150', '0190', '0200', '0990',
        'C001', 'C100', 'C170', 'C190', 'C990',
        'E001', 'E100', 'E110', 'E116', 'E990',
        'H001', 'H990',
        '9001', '9900', '9990', '9999',
    ];
    expect($esperados)->toHaveCount(23);
    foreach ($esperados as $reg) {
        expect(array_key_exists($reg, $por))->toBeTrue("registro {$reg} ausente do arquivo");
    }
});

it('UC-FSPED-16 · o E110 soma o ICMS dos C190 e o E116 leva o valor a recolher', function () {
    // 1.000,00 e 500,00 a 18% → 180,00 + 90,00 = 270,00 de débito; sem crédito, recolhe 270,00.
    $por = spedArquivoPorRegistro(spedArquivoGerador(
        [spedArquivoNota(1, 1000.00), spedArquivoNota(2, 500.00)],
        true,
    )->gerar(98, 2026, 1));

    $icmsC190 = array_sum(array_map(fn ($c) => (float) str_replace(',', '.', $c[6]), $por['C190']));
    expect($icmsC190)->toBe(270.0);

    $e110 = $por['E110'][0];
    expect($e110[1])->toBe('270,00')   // VL_TOT_DEBITOS
        ->and($e110[10])->toBe('270,00') // VL_SLD_APURADO
        ->and($e110[12])->toBe('270,00'); // VL_ICMS_RECOLHER

    expect($por['E116'])->toHaveCount(1)
        ->and($por['E116'][0][2])->toBe('270,00')
        ->and($por['E116'][0][3])->toBe('20022026'); // vence dia 20 do mês seguinte
});

it('UC-FSPED-16 · sem ICMS no mês o E110 sai zerado e o E116 não sai', function () {
    $por = spedArquivoPorRegistro(spedArquivoGerador([spedArquivoNota(1, 1000.00)], false)->gerar(98, 2026, 1));

    expect($por['E110'][0][1])->toBe('0,00')
        ->and($por['E110'][0][12])->toBe('0,00')
        ->and(array_key_exists('E116', $por))->toBeFalse()
        ->and($por['E990'][0][1])->toBe('4'); // E001, E100, E110, E990
});

it('UC-FSPED-17 · o Bloco H sai sem movimento: H001 = 1, H990 = 2 e nenhum item de inventário', function () {
    $por = spedArquivoPorRegistro(spedArquivoGerador([spedArquivoNota(1, 1000.00)], true)->gerar(98, 2026, 1));

    expect($por['H001'][0][1])->toBe('1')
        ->and($por['H990'][0][1])->toBe('2')
        ->and(array_key_exists('H005', $por))->toBeFalse()
        ->and(array_key_exists('H010', $por))->toBeFalse();
});
