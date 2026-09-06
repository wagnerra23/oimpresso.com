<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeDfeRecebido;

uses(Tests\TestCase::class);

/**
 * PR #3 Wave DF-e Fiscal — isolation Tier 0 (ADR 0093) + doutrina de tenant (ADR 0358).
 *
 * 2026-09-06 — a lane NfeBrasil passou a EXECUTAR este arquivo (#6902) e o UC-FDFE-01 caiu
 * vermelho. Não era vazamento de produto: o ScopeByBusiness faz early-return em
 * `! auth()->check()` (app/Scopes/ScopeByBusiness.php) e o teste não autenticava ninguém —
 * media o banco cru, e havia linha de outro business deixada por teste vizinho da lane.
 * Mesma falha de TESTE já catalogada em CockpitMultiTenantTest. Agora: autentica um usuário
 * do tenant canônico, planta UMA linha de cada lado com tag própria, e prova as duas pernas
 * (scope esconde · linha existe sem o scope) — senão o verde seria tautológico.
 */

const DFE_TAG = 'UC-FDFE-01-ISO';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeDfeRecebido requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_dfe_recebidos')) {
        $this->markTestSkipped('nfe_dfe_recebidos table missing');
    }
});

afterEach(function () {
    if (! Schema::hasTable('nfe_dfe_recebidos')) {
        return;
    }
    // SUPERADMIN: limpeza do fixture dos DOIS tenants — é o próprio teste de isolamento
    NfeDfeRecebido::withoutGlobalScopes()
        ->where('chave_44', 'like', '%' . DFE_TAG . '%')
        ->delete();
});

it('UC-FDFE-01 · NfeDfeRecebido HasBusinessScope esconde cross-tenant da listagem DF-e', function () {
    $tenant  = $this->seededTenant();
    $bizMeu  = (int) $tenant->id;
    $bizOutro = \Tests\Support\WithSeededTenant::SUPPORT_CLIENT_TENANT_ID; // adversário cross-tenant (99)

    $this->actingAs(\App\User::where('business_id', $bizMeu)->firstOrFail());
    session(['business.id' => $bizMeu, 'user.business_id' => $bizMeu]);

    $base = [
        'nsu'           => 1,
        'cnpj_emitente' => '12345678000199',
        'nome_emitente' => 'Fixture ' . DFE_TAG,
        'valor_total'   => 10,
        'data_emissao'  => now(),
    ];
    // SUPERADMIN: plantar o fixture fora do scope é o setup do próprio teste de isolamento
    NfeDfeRecebido::withoutGlobalScopes()->create($base + [
        'business_id' => $bizMeu,
        'chave_44'    => str_pad('1' . DFE_TAG, 44, '0', STR_PAD_RIGHT),
    ]);
    NfeDfeRecebido::withoutGlobalScopes()->create($base + [
        'business_id' => $bizOutro,
        'chave_44'    => str_pad('2' . DFE_TAG, 44, '0', STR_PAD_RIGHT),
    ]);

    // Controle positivo: a linha do outro tenant EXISTE — só o scope pode escondê-la.
    $semScope = NfeDfeRecebido::withoutGlobalScopes() // SUPERADMIN: controle positivo do teste
        ->where('business_id', $bizOutro)
        ->where('chave_44', 'like', '%' . DFE_TAG . '%')
        ->count();
    expect($semScope)->toBe(1, 'fixture cross-tenant não foi plantado — o verde abaixo seria tautológico');

    $crossTenantCount = NfeDfeRecebido::query()
        ->where('business_id', '!=', $bizMeu)
        ->count();
    expect($crossTenantCount)->toBe(0, 'Global scope deve esconder cross-tenant');

    $meus = NfeDfeRecebido::query()
        ->where('chave_44', 'like', '%' . DFE_TAG . '%')
        ->count();
    expect($meus)->toBe(1, 'a linha do próprio tenant tem que continuar visível');
});

it('UC-FDFE-02 · STATUS constants estão definidas — Controller depende delas pra filtros', function () {
    expect(NfeDfeRecebido::STATUS_PENDENTE)->toBe('pendente')
        ->and(NfeDfeRecebido::STATUS_CIENCIA)->toBe('ciencia')
        ->and(NfeDfeRecebido::STATUS_CONFIRMADA)->toBe('confirmada')
        ->and(NfeDfeRecebido::STATUS_DESCONHECIDA)->toBe('desconhecida')
        ->and(NfeDfeRecebido::STATUS_NAO_REALIZADA)->toBe('nao_realizada');
});

it('UC-FDFE-02 · isPendenteManifestacao retorna true pra status PENDENTE e CIENCIA', function () {
    $pendente = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_PENDENTE]);
    $ciencia  = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_CIENCIA]);
    $conf     = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_CONFIRMADA]);

    expect($pendente->isPendenteManifestacao())->toBeTrue()
        ->and($ciencia->isPendenteManifestacao())->toBeTrue()
        ->and($conf->isPendenteManifestacao())->toBeFalse();
});
