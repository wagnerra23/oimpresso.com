<?php

declare(strict_types=1);

use Modules\OficinaAuto\Console\Commands\ImportFirebirdMartinhoCommand;
use Modules\OficinaAuto\Entities\ServiceOrderItem;

/**
 * W28 G4 — Importer Firebird Martinho (mapping fino + reconciliação domínio ADR 0194).
 *
 * Pattern do módulo (Wave 25/27): reflection-only, ZERO hit DB — roda paralelizado
 * em N worktrees sem conflito e não depende do suite de migration completo. Cobre a
 * lógica de mapping pura (vehicle_type/status/order_type/item) + os contratos imutáveis
 * (default 'caminhao' não 'cacamba'; whitelist do enum). O caminho DB-real (criar OS +
 * idempotência) é provado por smoke dry-run contra o DB migrado + CI.
 *
 * @see Modules/OficinaAuto/Console/Commands/ImportFirebirdMartinhoCommand.php
 * @see memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md
 */

function w28Invoke(string $method, mixed $arg): mixed
{
    $cmd = new ImportFirebirdMartinhoCommand();
    $m = new ReflectionMethod($cmd, $method);
    $m->setAccessible(true);

    return $m->invoke($cmd, $arg);
}

it('normalizeVehicleType: ADR 0194 — caminhao, NUNCA cacamba', function () {
    expect(w28Invoke('normalizeVehicleType', null))->toBe('caminhao');        // vazio → default
    expect(w28Invoke('normalizeVehicleType', ''))->toBe('caminhao');
    expect(w28Invoke('normalizeVehicleType', 'cacamba'))->toBe('caminhao');   // o bug antigo → corrigido
    expect(w28Invoke('normalizeVehicleType', 'cacamba_basculante'))->toBe('caminhao'); // doc legacy não-whitelisted
    expect(w28Invoke('normalizeVehicleType', 'caminhao_basculante'))->toBe('caminhao'); // docblock legacy
    expect(w28Invoke('normalizeVehicleType', 'BASCULANTE'))->toBe('caminhao');
    expect(w28Invoke('normalizeVehicleType', 'desconhecido_xyz'))->toBe('caminhao');
});

it('normalizeVehicleType: valor já na whitelist do enum é preservado', function () {
    expect(w28Invoke('normalizeVehicleType', 'caminhao'))->toBe('caminhao');
    expect(w28Invoke('normalizeVehicleType', 'automovel'))->toBe('automovel');
    expect(w28Invoke('normalizeVehicleType', 'cacamba_estacionaria'))->toBe('cacamba_estacionaria');
    expect(w28Invoke('normalizeVehicleType', 'semi_reboque'))->toBe('semi_reboque');
});

it('normalizeStatus: legacy WR (PT livre) → FSM manutencao', function () {
    expect(w28Invoke('normalizeStatus', 'ABERTA'))->toBe('aberta');
    expect(w28Invoke('normalizeStatus', 'orçamento'))->toBe('aberta');
    expect(w28Invoke('normalizeStatus', 'EM ANDAMENTO'))->toBe('em_servico');
    expect(w28Invoke('normalizeStatus', 'em_servico'))->toBe('em_servico');
    expect(w28Invoke('normalizeStatus', 'FINALIZADA'))->toBe('concluida');
    expect(w28Invoke('normalizeStatus', 'fechado'))->toBe('concluida');
    expect(w28Invoke('normalizeStatus', 'CANCELADO'))->toBe('cancelada');
    expect(w28Invoke('normalizeStatus', null))->toBe('concluida');   // histórico
    expect(w28Invoke('normalizeStatus', ''))->toBe('concluida');
});

it('normalizeOrderType: default manutencao, respeita mecanica; locacao erradicado→manutencao (ADR 0265)', function () {
    expect(w28Invoke('normalizeOrderType', null))->toBe('manutencao');
    expect(w28Invoke('normalizeOrderType', 'mecanica'))->toBe('mecanica');
    expect(w28Invoke('normalizeOrderType', 'locacao'))->toBe('manutencao'); // erradicado (ADR 0265)
    expect(w28Invoke('normalizeOrderType', 'qualquer_coisa'))->toBe('manutencao');
});

it('normalizeItemTipo: legacy → TIPOS_VALIDOS', function () {
    expect(w28Invoke('normalizeItemTipo', 'PRODUTO'))->toBe('peca');
    expect(w28Invoke('normalizeItemTipo', 'material'))->toBe('peca');
    expect(w28Invoke('normalizeItemTipo', 'MAO DE OBRA'))->toBe('mao_obra');
    expect(w28Invoke('normalizeItemTipo', 'hora trabalhada'))->toBe('mao_obra');
    expect(w28Invoke('normalizeItemTipo', 'servico_terceiro'))->toBe('servico_terceiro');
    expect(w28Invoke('normalizeItemTipo', 'terceirizado'))->toBe('servico_terceiro');
    expect(w28Invoke('normalizeItemTipo', null))->toBe('peca');
    foreach (['PRODUTO', 'MAO DE OBRA', 'terceiro', null, 'xyz'] as $in) {
        expect(w28Invoke('normalizeItemTipo', $in))->toBeIn(ServiceOrderItem::TIPOS_VALIDOS);
    }
});

it('contratos imutáveis: default caminhao + whitelist sem o pseudo-valor cacamba', function () {
    $rc = new ReflectionClass(ImportFirebirdMartinhoCommand::class);
    expect($rc->getConstant('DEFAULT_VEHICLE_TYPE'))->toBe('caminhao');

    $whitelist = $rc->getConstant('VEHICLE_TYPE_WHITELIST');
    expect($whitelist)->toContain('caminhao');
    expect($whitelist)->not->toContain('cacamba'); // 'cacamba' puro nunca foi valor do enum
});

it('o código não regrediu pro default cacamba hardcoded', function () {
    $src = file_get_contents((new ReflectionClass(ImportFirebirdMartinhoCommand::class))->getFileName());
    expect($src)->not->toContain("'vehicle_type' => 'cacamba'");
    expect($src)->toContain('ADR 0194');
});
