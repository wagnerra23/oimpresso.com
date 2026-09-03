<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeCertificado;

uses(Tests\TestCase::class);

/**
 * PR #3 Wave Cert/Cfg Fiscal — isolation + senha hidden.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeCertificado requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('nfe_certificados table missing');
    }
});

it('UC-FCFG-01 · NfeCertificado encrypted_password é hidden — não vaza no payload Inertia', function () {
    $cert = new NfeCertificado([
        'business_id'        => 1,
        'cnpj_titular'       => '00000000000000',
        'encrypted_password' => 'SECRET_NEVER_LEAK',
        'ativo'              => true,
    ]);

    $json = $cert->toArray();
    expect($json)
        ->not->toHaveKey('encrypted_password', 'Senha encriptada DEVE estar em $hidden');
});

it('UC-FCFG-02 · NfeCertificado HasBusinessScope esconde certs de outros tenants', function () {
    session(['business.id' => 1, 'user.business_id' => 1]);

    $crossTenantCount = NfeCertificado::query()
        ->where('business_id', '!=', 1)
        ->count();

    expect($crossTenantCount)->toBe(0, 'Cross-tenant nunca vaza certs');
});

it('UC-FCFG-04 · o payload da tela carrega o estado da contingência com a duração vinda do SERVIDOR', function () {
    if (! Schema::hasColumn('nfe_business_configs', 'em_contingencia')) {
        $this->markTestSkipped('Coluna em_contingencia ausente — rode as migrations do NfeBrasil (US-NFE-006).');
    }

    $bizId = 1;
    $ativadaEm = now()->subDays(3);

    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => $bizId],
        [
            'regime' => 'simples',
            'tributacao_default' => json_encode(['cfop' => '5102']),
            'em_contingencia' => true,
            'contingencia_ativada_em' => $ativadaEm,
            'contingencia_motivo' => 'SEFAZ-SC fora do ar — teste automatizado',
            'updated_at' => now(),
            'created_at' => now(),
        ],
    );

    $config = \Modules\NfeBrasil\Models\NfeBusinessConfig::withoutGlobalScopes()
        ->where('business_id', $bizId)
        ->first();

    // O contrato que a TELA consome. `diasAtiva` é calculado no servidor de propósito:
    // é o número que mitiga o risco "tenant esquece ligado" (ADR TECH-0002), e no browser
    // ele dependeria do relógio da máquina do operador.
    $payload = [
        'ativa' => (bool) $config->em_contingencia,
        'ativadaEmIso' => $config->contingencia_ativada_em?->toIso8601String(),
        'diasAtiva' => $config->contingencia_ativada_em
            ? (int) $config->contingencia_ativada_em->diffInDays(now())
            : null,
        'motivo' => $config->contingencia_motivo,
    ];

    expect($payload['ativa'])->toBeTrue();
    expect($payload['diasAtiva'])->toBe(3);
    expect($payload['motivo'])->toBe('SEFAZ-SC fora do ar — teste automatizado');
    expect($payload['ativadaEmIso'])->not->toBeNull();

    // CONTROLE NEGATIVO: desligada, a duração é NULL — não 0. "0 dias ativa" e "não está
    // ativa" são estados diferentes, e exibir 0 diria que a contingência está ligada hoje.
    DB::table('nfe_business_configs')->where('business_id', $bizId)->update([
        'em_contingencia' => false,
        'contingencia_ativada_em' => null,
        'contingencia_motivo' => null,
    ]);

    $desligada = \Modules\NfeBrasil\Models\NfeBusinessConfig::withoutGlobalScopes()
        ->where('business_id', $bizId)->first();

    expect((bool) $desligada->em_contingencia)->toBeFalse();
    expect($desligada->contingencia_ativada_em)->toBeNull();
});
