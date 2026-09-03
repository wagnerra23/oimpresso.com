<?php

declare(strict_types=1);

// @covers-us US-NFE-006 — Modo contingência: ATIVAÇÃO MANUAL.
// Contrato: memory/requisitos/NfeBrasil/adr/tech/0002-contingencia-epec-fsda-retentativa-ordenada.md
// §"Detecção: híbrida (auto-sugestão + ativação manual)" — "Auto-ativação: rejeitada".
// Casos derivam da ADR, não do ContingenciaService que eu escrevi (proibicoes.md §5 2026-06-05).

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-NFE-006 fase 2 — ligar/desligar contingência.
 *
 * O QUE ESTE ARQUIVO DEFENDE
 * --------------------------
 * 1. Que ativar seja ATO HUMANO AUTORIZADO — a ADR rejeitou auto-ativação.
 * 2. Que o motivo seja barreira real (auditoria fiscal), não campo decorativo.
 * 3. Que ligar num tenant NÃO ligue no vizinho (Tier 0 — ADR 0093).
 * 4. Que desativar NÃO minta sobre as notas já emitidas em contingência.
 *
 * MYSQL-ONLY pela mesma razão do resto do módulo: isolamento multi-tenant e ENUM só
 * valem contra o schema real; no sqlite :memory: o verde MENTE (ver nfebrasil-pest.yml).
 *
 * biz=1 e biz=2 são os tenants semeados por pest-mysql-setup.
 * NUNCA biz=4 — ROTA LIVRE / Larissa, cliente real em produção (ADR 0101 / 0358 R6).
 */
const CTA_BIZ = 1;
const CTA_BIZ_OUTRO = 2;
const CTA_PERM = 'nfe.contingencia.manage';
const CTA_URL_ATIVAR = '/nfe-brasil/contingencia/ativar';
const CTA_URL_DESATIVAR = '/nfe-brasil/contingencia/desativar';
const CTA_MOTIVO = 'SEFAZ-SC fora do ar desde as 14h — chamado 12345 aberto';

function ctaEsquecerCache(): void
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

function ctaSemearConfig(int $businessId, array $extra = []): void
{
    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => $businessId],
        array_merge([
            'regime' => 'simples',
            'tributacao_default' => json_encode(['cfop' => '5102']),
            'em_contingencia' => false,
            'contingencia_ativada_em' => null,
            'contingencia_motivo' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ], $extra)
    );
}

function ctaConfig(int $businessId): object
{
    return DB::table('nfe_business_configs')->where('business_id', $businessId)->first();
}

function ctaLimpar(): void
{
    DB::table('nfe_business_configs')->whereIn('business_id', [CTA_BIZ, CTA_BIZ_OUTRO])->delete();
    DB::table('nfe_emissoes')->where('serie', '998')->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: isolamento multi-tenant + schema real (ADR 0101; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasColumn('nfe_business_configs', 'em_contingencia')) {
        $this->markTestSkipped('Coluna em_contingencia ausente — rode as migrations do NfeBrasil (US-NFE-006 fase 1).');
    }

    ctaLimpar();
    ctaSemearConfig(CTA_BIZ);
    ctaSemearConfig(CTA_BIZ_OUTRO);

    // Permissão direto no usuário (sem role: roles.business_id é NOT NULL com FK — proibicoes §FSM).
    $perm = Permission::firstOrCreate(['name' => CTA_PERM, 'guard_name' => 'web']);
    $user = User::where('business_id', CTA_BIZ)->firstOrFail();
    $user->givePermissionTo($perm);
    ctaEsquecerCache();

    $this->actingAs($user);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('nfe_business_configs', 'em_contingencia')) {
        return;
    }
    ctaLimpar();

    $perm = Permission::where('name', CTA_PERM)->where('guard_name', 'web')->first();
    $user = User::where('business_id', CTA_BIZ)->first();
    if ($perm && $user) {
        $user->revokePermissionTo($perm);
        ctaEsquecerCache();
    }
});

describe('US-NFE-006 · ativação manual de contingência (ADR TECH-0002)', function () {

    it('UC-CONT-10 · ativação manual liga o flag, grava o motivo e carimba o relógio', function () {
        $this->post(CTA_URL_ATIVAR, ['motivo' => CTA_MOTIVO])->assertRedirect();

        $cfg = ctaConfig(CTA_BIZ);

        expect((bool) $cfg->em_contingencia)->toBeTrue();
        expect($cfg->contingencia_motivo)->toBe(CTA_MOTIVO);
        expect($cfg->contingencia_ativada_em)->not->toBeNull();
    });

    it('UC-CONT-11 · motivo curto é RECUSADO e o tenant NÃO entra em contingência', function () {
        $this->post(CTA_URL_ATIVAR, ['motivo' => 'ok'])->assertSessionHasErrors('motivo');

        // O que importa não é o erro de validação — é o EFEITO não ter acontecido.
        // Sem este assert, a regra passaria mesmo se o controller gravasse antes de validar.
        expect((bool) ctaConfig(CTA_BIZ)->em_contingencia)->toBeFalse();
    });

    it('UC-CONT-12 · re-ativar NÃO reseta o relógio — o banner "ATIVA há N dias" tem que envelhecer', function () {
        $ontem = now()->subDay()->startOfMinute();
        ctaSemearConfig(CTA_BIZ, [
            'em_contingencia' => true,
            'contingencia_ativada_em' => $ontem,
            'contingencia_motivo' => 'motivo anterior suficientemente longo',
        ]);

        $this->post(CTA_URL_ATIVAR, ['motivo' => CTA_MOTIVO])->assertRedirect();

        $cfg = ctaConfig(CTA_BIZ);

        // A ADR TECH-0002 lista como risco "tenant esquecer de desativar" e mitiga com o
        // banner de duração. Se cada clique reiniciasse o relógio, a mitigação era enfeite.
        expect(\Carbon\Carbon::parse($cfg->contingencia_ativada_em)->timestamp)
            ->toBe($ontem->timestamp);
        expect($cfg->contingencia_motivo)->toBe(CTA_MOTIVO); // motivo atualiza; relógio não
    });

    it('UC-CONT-13 · desativar limpa o estado do tenant', function () {
        ctaSemearConfig(CTA_BIZ, [
            'em_contingencia' => true,
            'contingencia_ativada_em' => now()->subHours(3),
            'contingencia_motivo' => 'motivo anterior suficientemente longo',
        ]);

        $this->post(CTA_URL_DESATIVAR)->assertRedirect();

        $cfg = ctaConfig(CTA_BIZ);
        expect((bool) $cfg->em_contingencia)->toBeFalse();
        expect($cfg->contingencia_ativada_em)->toBeNull();
        expect($cfg->contingencia_motivo)->toBeNull();
    });

    it('UC-CONT-14 · [T0] ativar num tenant NÃO liga contingência no vizinho', function () {
        $this->post(CTA_URL_ATIVAR, ['motivo' => CTA_MOTIVO])->assertRedirect();

        // Tier 0 (ADR 0093): contingência do biz=1 não pode arrastar o biz=2 — seriam
        // notas de OUTRA empresa saindo em contingência sem ninguém ter pedido.
        expect((bool) ctaConfig(CTA_BIZ)->em_contingencia)->toBeTrue();
        expect((bool) ctaConfig(CTA_BIZ_OUTRO)->em_contingencia)->toBeFalse();
        expect(ctaConfig(CTA_BIZ_OUTRO)->contingencia_ativada_em)->toBeNull();
    });

    it('UC-CONT-15 · sem nfe.contingencia.manage a ativação é barrada (403)', function () {
        $user = User::where('business_id', CTA_BIZ)->firstOrFail();
        $perm = Permission::where('name', CTA_PERM)->where('guard_name', 'web')->firstOrFail();
        $user->revokePermissionTo($perm);
        ctaEsquecerCache();

        $this->post(CTA_URL_ATIVAR, ['motivo' => CTA_MOTIVO])->assertForbidden();

        expect((bool) ctaConfig(CTA_BIZ)->em_contingencia)->toBeFalse();
    });

    it('CONTROLE NEGATIVO · COM a permissão a rota NÃO devolve 403 (o 403 acima é do gate, não da rota)', function () {
        // Sem este caso, o UC-CONT-15 passaria até se a rota não existisse ou quebrasse
        // por outro motivo qualquer — é o par que dá sentido ao anterior.
        $this->post(CTA_URL_ATIVAR, ['motivo' => CTA_MOTIVO])->assertRedirect();

        expect((bool) ctaConfig(CTA_BIZ)->em_contingencia)->toBeTrue();
    });

    it('UC-CONT-16 · desativar NÃO mexe nas notas já emitidas em contingência', function () {
        ctaSemearConfig(CTA_BIZ, [
            'em_contingencia' => true,
            'contingencia_ativada_em' => now()->subHour(),
            'contingencia_motivo' => 'motivo anterior suficientemente longo',
        ]);

        $emissaoId = DB::table('nfe_emissoes')->insertGetId([
            'business_id' => CTA_BIZ,
            'transaction_id' => null,
            'modelo' => '65',
            'serie' => '998',
            'numero' => 998001,
            'status' => 'contingencia',
            'tp_emis' => 9,
            'valor_total' => 25.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(CTA_URL_DESATIVAR)->assertRedirect();

        // Desligar contingência diz "as PRÓXIMAS saem normais" — NUNCA "as anteriores foram
        // transmitidas". Se este assert cair, a UI estaria mentindo sobre nota fiscal real.
        $emissao = DB::table('nfe_emissoes')->find($emissaoId);
        expect($emissao->status)->toBe('contingencia');
        expect((int) $emissao->tp_emis)->toBe(9);
    });
});
