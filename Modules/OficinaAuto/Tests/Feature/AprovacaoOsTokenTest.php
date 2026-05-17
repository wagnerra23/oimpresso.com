<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\AprovacaoOsService;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D2 — AprovacaoOsService edge cases extras
 * (complementa WhatsAppAprovacaoPinTest).
 *
 * Foco: parsing de token corrompido, tamper detection HMAC, expiração,
 * lockout reset em nova geração, multi-tenant cross-business token reuse.
 *
 * @see Modules/OficinaAuto/Services/AprovacaoOsService.php
 */

const BIZ_APROV = 1;
const BIZ_APROV_OUTRO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('service_orders/vehicles tables missing');
    }
    Cache::flush();
});

function criarOsAprovacao(int $biz, string $status = 'orcamento'): ServiceOrder
{
    $v = Vehicle::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'plate'       => 'APR'.random_int(100, 999),
        'vehicle_type' => 'automovel',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $v->id,
        'status'      => $status,
        'entered_at'  => now(),
    ]);
}

it('cenario 1: token malformado (sem ponto) retorna null sem throw', function () {
    $svc = new AprovacaoOsService();
    expect($svc->validarToken('lixo_sem_ponto_aqui'))->toBeNull();
});

it('cenario 2: token com signature errada (tamper) retorna null', function () {
    $svc = new AprovacaoOsService();
    $os  = criarOsAprovacao(BIZ_APROV);

    $tk = $svc->gerarTokenAprovacao($os);
    [$payload] = explode('.', $tk['token']);

    // injeta signature errada
    $tampered = $payload.'.'.str_repeat('0', 64);
    expect($svc->validarToken($tampered))->toBeNull();
});

it('cenario 3: PIN bloqueia apos 5 tentativas (lockout)', function () {
    $svc = new AprovacaoOsService();
    $os  = criarOsAprovacao(BIZ_APROV);
    $svc->gerarTokenAprovacao($os);

    // 5 PINs errados
    for ($i = 0; $i < 5; $i++) {
        expect($svc->validarPin($os, '0000'))->toBeFalse();
    }

    // 6ª deve ser bloqueado (lockout) mesmo com PIN correto hipoteticamente
    expect($svc->tentativasRestantes($os))->toBe(0);
    expect($svc->validarPin($os, '1234'))->toBeFalse();
});

it('cenario 4: nova geracao de token reseta contador de tentativas', function () {
    $svc = new AprovacaoOsService();
    $os  = criarOsAprovacao(BIZ_APROV);
    $svc->gerarTokenAprovacao($os);

    $svc->validarPin($os, '0000');
    $svc->validarPin($os, '0000');
    expect($svc->tentativasRestantes($os))->toBe(3);

    $svc->gerarTokenAprovacao($os);
    expect($svc->tentativasRestantes($os))->toBe(5);
});

it('cenario 5: PIN nao-numerico (4 letras) conta como tentativa', function () {
    $svc = new AprovacaoOsService();
    $os  = criarOsAprovacao(BIZ_APROV);
    $svc->gerarTokenAprovacao($os);

    expect($svc->validarPin($os, 'abcd'))->toBeFalse();
    expect($svc->tentativasRestantes($os))->toBe(4);
});

it('cenario 6: PIN correto invalida cache (one-shot, nao reutilizavel)', function () {
    $svc = new AprovacaoOsService();
    $os  = criarOsAprovacao(BIZ_APROV);
    $g   = $svc->gerarTokenAprovacao($os);

    expect($svc->validarPin($os, $g['pin']))->toBeTrue();
    // segunda tentativa do mesmo PIN deve falhar (já consumido)
    expect($svc->validarPin($os, $g['pin']))->toBeFalse();
});

it('cenario 7: token gerado pra biz=1 NAO valida em OS de biz=99 (Tier 0)', function () {
    $svc = new AprovacaoOsService();
    $osBiz1 = criarOsAprovacao(BIZ_APROV);
    $g1 = $svc->gerarTokenAprovacao($osBiz1);

    // token vai resolver a OS biz=1 corretamente (porque carrega business_id assinado)
    $resolvedOs = $svc->validarToken($g1['token']);
    expect($resolvedOs)->not->toBeNull()
        ->and($resolvedOs->business_id)->toBe(BIZ_APROV);

    // Cria outra OS em outro business com mesmo plate — token de biz=1 NAO deve resolver pra ela
    criarOsAprovacao(BIZ_APROV_OUTRO);
    $resolved2 = $svc->validarToken($g1['token']);
    expect($resolved2->business_id)->toBe(BIZ_APROV);
});

it('cenario 8: OS em status diferente de orcamento nao valida via token', function () {
    $svc = new AprovacaoOsService();
    $os  = criarOsAprovacao(BIZ_APROV, status: 'em_servico');

    $tk = $svc->gerarTokenAprovacao($os);
    expect($svc->validarToken($tk['token']))->toBeNull();
});
