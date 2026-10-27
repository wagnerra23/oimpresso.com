<?php

declare(strict_types=1);

// @covers-us US-NFE-002 — a config da SEFAZ leva o CSC da empresa com o nome que a sped-nfe lê (R-NFE-045).

use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * R-NFE-045 · `Common\Tools::addQRCode` da sped-nfe lê `$config->CSC` e `$config->CSCid` (e lança
 * "faltam dados CSC e/ou CSCId" quando o QR Code não é a versão 3). O NfeService mandava
 * `tokenCSC`/`idCSC` vazios — nomes que a lib não lê. Sem banco: o business entra como objeto.
 * CSC de fixture, fictício.
 */

function configSefaz45(object $business): array
{
    $m = new ReflectionMethod(NfeService::class, 'montarConfigSefaz');
    $m->setAccessible(true);

    return json_decode((string) $m->invoke(new NfeService(\Mockery::mock(CertificadoService::class)), $business, ['uf' => 'SC']), true); // UF por override: dispensa o business_locations
}

it('UC-FNFE-53 · a config leva CSC e CSCid da empresa', function () {
    $cfg = configSefaz45((object) ['cnpj' => '11222333000181', 'name' => 'FIXTURE', 'ambiente' => 2,
        'state' => 'SC', 'csc' => 'TOKEN-CSC-FIXTURE', 'csc_id' => '000001']);

    expect($cfg['CSC'])->toBe('TOKEN-CSC-FIXTURE');
    expect($cfg['CSCid'])->toBe('000001');
    expect($cfg)->not->toHaveKey('tokenCSC');
    expect($cfg)->not->toHaveKey('idCSC');
});

it('UC-FNFE-53 · empresa sem CSC cadastrado leva vazio (a lib decide pelo QR v3)', function () {
    $cfg = configSefaz45((object) ['cnpj' => '11222333000181', 'name' => 'FIXTURE', 'ambiente' => 2, 'state' => 'SC']);

    expect($cfg['CSC'])->toBe('');
    expect($cfg['CSCid'])->toBe('');
});
