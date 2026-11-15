<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — conferência da natureza de operação, onda 3a (RUNBOOK-tributacao §Naturezas).

use Modules\NfeBrasil\Services\Tributacao\ConferenciaNaturezaService;

uses(Tests\TestCase::class);

/**
 * Casos derivados do protótipo aprovado (`prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`,
 * funções `vazia()` e `validar()`) e do plano da onda 3 (não muda imposto), não do controller.
 * Sem banco: roda nas duas lanes.
 */

/** A natureza vazia do protótipo (`vazia()`), Lucro Presumido. */
function notNatureza(array $mudar = []): array
{
    $n = [
        'nome' => 'Venda de mercadoria de terceiros', 'tipo' => 'S', 'fin' => '1', 'ativo' => true, 'ref' => false,
        'cfop' => ['in' => '5102', 'out' => '6102', 'ext' => ''],
        'icms' => ['cst' => '00', 'csosn' => '102', 'origem' => 'uf', 'p' => 0, 'modbc' => '3', 'red' => 0, 'pDif' => 0,
            'pCredSN' => 0, 'bFrete' => true, 'bIPI' => false, 'bDesc' => true,
            'st' => ['modbcst' => '4', 'mvaOrig' => 'produto', 'mva' => 0, 'redB' => 0], 'difal' => true, 'fcp' => true],
        'ipi' => ['on' => false, 'cst' => '53', 'cEnq' => '999', 'origem' => 'produto', 'p' => 0],
        'pis' => ['cst' => '01', 'cstSN' => '49', 'origem' => 'regime', 'p' => 0, 'exclICMS' => true],
        'cofins' => ['cst' => '01', 'cstSN' => '49', 'origem' => 'regime', 'p' => 0, 'exclICMS' => true],
        'ibs' => ['on' => true, 'cst' => '000', 'cClass' => '000001', 'origem' => 'padrao', 'pUF' => 0.1, 'pMun' => 0, 'pCBS' => 0.9, 'pRed' => 0],
    ];

    return array_replace_recursive($n, $mudar);
}

/** @return list<string> mensagens de erro */
function notErros(array $n, string $regime = 'LP', array $outras = []): array
{
    $a = (new ConferenciaNaturezaService())->conferir($n, $regime, $outras);

    return array_values(array_map(fn ($x) => $x['mensagem'], array_filter($a, fn ($x) => $x['nivel'] === 'erro')));
}

// --- Conferência (sem banco) ------------------------------------------------------------

it('UC-NFTR-44 · a natureza de revenda do protótipo passa na conferência sem erro', function () {
    expect(notErros(notNatureza()))->toBe([]);
});

it('UC-NFTR-44 · os erros do protótipo bloqueiam: devolução sem nota, CFOP trocado de destino, ST já retido sem CST 60', function () {
    expect(notErros(notNatureza(['fin' => '4', 'cfop' => ['in' => '5202', 'out' => '6202']])))
        ->toContain('Devolução exige nota referenciada. Marque "Pede a chave da nota de origem".');

    $trocado = notErros(notNatureza(['cfop' => ['in' => '6102']]));
    expect(implode(' | ', $trocado))->toContain('CFOP 6102 não serve para "dentro do estado"')
        ->and(implode(' | ', $trocado))->toContain('(ex.: 5102)');

    expect(notErros(notNatureza(['cfop' => ['in' => '5405', 'out' => '6404']])))
        ->toContain('CFOP de ST já retido pede CST 60.');
    // Controle: com CST 60 o mesmo CFOP passa.
    expect(notErros(notNatureza(['cfop' => ['in' => '5405', 'out' => '6404'], 'icms' => ['cst' => '60']])))->toBe([]);
});

it('UC-NFTR-44 · no Simples a conferência lê o CSOSN, e nome repetido de outra ativa bloqueia', function () {
    // CSOSN 102 não é de ST: venda como substituto (5401) acusa pedindo CSOSN de ST.
    expect(notErros(notNatureza(['cfop' => ['in' => '5401', 'out' => '6401']]), 'SN'))
        ->toContain('CFOP de venda como substituto pede CSOSN 201, 202 ou 203.');

    expect(notErros(notNatureza(), 'LP', ['  venda de MERCADORIA de terceiros ']))
        ->toContain('Já existe uma natureza ativa com este nome.');
});
