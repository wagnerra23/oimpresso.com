<?php

declare(strict_types=1);

// @covers-us US-NFE-TPL-001 — template sugerido (playbook Fiscal thread 21 · UC-NFTR-15).
// Contrato: 21-certificado-template-backend.md + ConfigDefault.charter ("mostra valores antes de
// aplicar"). Os slugs esperados vêm dos arquivos de Resources/templates (a curadoria), não do
// algoritmo de pontuação.

use Modules\NfeBrasil\Services\Tributacao\TributacaoTemplateService;

uses(Tests\TestCase::class);

it('UC-NFTR-15 · sugestão por regime, UF e CNAE', function () {
    $svc = new TributacaoTemplateService;

    // Gráfica no Simples em SP, CNAE 1813-0/01 (impressão de material para uso publicitário).
    $sugestoes = $svc->sugerir('simples', 'SP', ['1813-0/01']);

    expect($sugestoes[0]['slug'])->toBe('industria-grafica-simples-sp')
        ->and($sugestoes[0]['aderencia'])->toMatchArray(['regime' => true, 'uf' => true, 'setor' => true]);

    // Cada sugestão traz os valores que APLICARIA — a tela mostra antes do clique.
    foreach ($sugestoes as $s) {
        expect($s['tributacao_default'])->toHaveKey('cfop');
    }
    expect($sugestoes[0]['tributacao_default'])->toMatchArray(['cfop' => '5101', 'csosn' => '101']);

    // Sugerir não é aplicar: devolve a lista inteira, ordenada, sem descartar nenhuma.
    expect($sugestoes)->toHaveCount(count($svc->listar()));

    // Controle positivo: trocar o regime para presumido muda o 1º sugerido.
    $presumido = $svc->sugerir('lucro_presumido', 'SP', ['1813-0/01']);
    expect($presumido[0]['slug'])->toBe('industria-grafica-presumido-sp')
        ->and($presumido[0]['regime'])->toBe('lucro_presumido');

    // E a UF pesa: comércio no Simples em MG não recebe template de SP em 1º.
    expect($svc->sugerir('simples', 'MG', ['4761-0/03'])[0]['slug'])->toBe('comercio-varejo-simples-mg');
});
