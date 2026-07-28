<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — defaults tributários do business (cascade Nível 4, ADR ARQ-0006).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.casos.md
// UC-NFCD-01..06. Os casos derivam do CONTRATO (ARQ-0006 + US-NFE-010 + charter), não da
// implementação — teste derivado do código é tautológico (proibicoes.md §5 2026-06-05).

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-NFE-010 fase 2 · /nfe-brasil/tributacao/config-default (Nível 4 da cascade).
 *
 * POR QUE É HTTP E NÃO CHAMADA DIRETA AO CONTROLLER
 * -------------------------------------------------
 * Metade destes UCs (03/04/06) é sobre REJEIÇÃO — e a rejeição mora no
 * `UpsertConfigDefaultRequest` (rules + withValidator). Instanciar o controller na mão
 * pula o FormRequest inteiro: o teste passaria por NÃO-EXECUÇÃO da validação, que é o
 * verde tautológico banido em proibicoes.md §5 (2026-06-05 / 2026-07-24). Só o caminho
 * HTTP real exercita middleware → FormRequest → controller → persistência.
 *
 * POR QUE MYSQL-ONLY
 * ------------------
 * Isolamento multi-tenant e o `enum` de `regime` só valem contra o schema real. No lane
 * sqlite (:memory:) o schema é recriado à mão e o "verde" MENTE — é a razão declarada da
 * lane `nfebrasil-pest.yml`. Aqui a suite SKIPa em sqlite em vez de fingir cobertura.
 *
 * biz=1 (Wagner) e biz=2 (cross-tenant) são os semeados por `pest-mysql-setup`.
 * NUNCA biz=4 — é ROTA LIVRE / Larissa, cliente real em produção (ADR 0101).
 *
 * @see memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.charter.md
 */

const NFCD_BIZ = 1;        // Wagner — tenant sob teste
const NFCD_BIZ_OUTRO = 2;  // cross-tenant Tier 0 (semeado; NUNCA biz=4 — ADR 0101)
const NFCD_URL = '/nfe-brasil/tributacao/config-default';

/** Payload válido mínimo — o "controle positivo" de todo caso de rejeição. */
function nfcdPayloadValido(array $override = []): array
{
    return array_merge([
        'regime'          => 'simples',
        'ncm_default'     => '49019900',
        'cfop_default'    => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
    ], $override);
}

function nfcdLimpar(): void
{
    DB::table('nfe_business_configs')->whereIn('business_id', [NFCD_BIZ, NFCD_BIZ_OUTRO])->delete();
}

/** Spatie cacheia o mapa de permissões — sem invalidar, o grant/revoke do teste não vale. */
function nfcdEsquecerCache(): void
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: isolamento multi-tenant + enum regime exigem schema real (ADR 0101; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('nfe_business_configs ausente — rode as migrations do NfeBrasil.');
    }

    nfcdLimpar();

    // `UpsertConfigDefaultRequest::authorize()` exige `nfe.tributacao.manage`. Concedemos
    // direto ao usuário semeado do biz=1 (sem criar role: `roles.business_id` é NOT NULL
    // com FK em UltimatePOS — proibicoes.md §FSM). Revogado no afterEach.
    $perm = Permission::firstOrCreate(['name' => 'nfe.tributacao.manage', 'guard_name' => 'web']);
    $user = User::where('business_id', NFCD_BIZ)->firstOrFail();
    $user->givePermissionTo($perm);
    nfcdEsquecerCache();

    $this->actingAs($user);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_business_configs')) {
        return;
    }
    nfcdLimpar();

    // Re-resolve em vez de guardar em `$this->…`: propriedade dinâmica em TestCase é
    // deprecada no PHP 8.2+ e o custo aqui é um SELECT por teste.
    $perm = Permission::where('name', 'nfe.tributacao.manage')->where('guard_name', 'web')->first();
    $user = User::where('business_id', NFCD_BIZ)->first();
    if ($perm && $user) {
        $user->revokePermissionTo($perm);
        nfcdEsquecerCache();
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFCD-01 · Config de outro business não vaza nem é sobrescrita  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFCD-01 · config de outro business não vaza no show nem é sobrescrita no upsert', function () {
    // Vizinho (biz=2) com config bem diferente da default de tela.
    DB::table('nfe_business_configs')->insert([
        'business_id'        => NFCD_BIZ_OUTRO,
        'regime'             => 'lucro_real',
        'tributacao_default' => json_encode(['cfop' => '6102', 'cst' => '000', 'aliquota_icms' => 0.12]),
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
    $antes = DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ_OUTRO)->first();

    // (a) LEITURA — biz=1 não enxerga a config do vizinho; cai no default da tela.
    $this->get(NFCD_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('NfeBrasil/Tributacao/ConfigDefault')
            ->where('config.regime', 'simples')          // default de tela, NÃO 'lucro_real' do biz=2
            ->where('config.tributacao_default.cfop_default', '5102')
        );

    // (b) ESCRITA — o upsert do biz=1 cria a própria row e não encosta na do vizinho.
    $this->post(NFCD_URL, nfcdPayloadValido(['regime' => 'mei']))->assertRedirect();

    $depois = DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ_OUTRO)->first();
    expect($depois)->toEqual($antes);                                  // vizinho intacto, byte-a-byte
    expect(DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->count())->toBe(1);
});

// ---------------------------------------------------------------------------------------
// UC-NFCD-02 · O default persistido carrega a chave `cfop` que o motor Nível 4 lê  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFCD-02 · upsert grava o alias cfop que o motor Nível 4 consome', function () {
    $this->post(NFCD_URL, nfcdPayloadValido(['cfop_default' => '6102']))->assertRedirect();

    $td = json_decode(
        DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->value('tributacao_default'),
        true,
    );

    // `MotorTributarioService::aplicarDefaults()` lê `$defaults['cfop']` — se a chave sumir
    // ele NÃO quebra: cai no literal `?? '5102'` e emite CFOP de operação INTERNA numa venda
    // interestadual. Falha silenciosa, nota autorizada e errada (ARQ-0006 §Nível 4).
    expect($td)->toHaveKey('cfop');
    expect($td['cfop'])->toBe('6102');
});

// ---------------------------------------------------------------------------------------
// UC-NFCD-03 · Regime fora do enum dos 4 regimes é rejeitado  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFCD-03 · regime fora do enum é rejeitado e não grava config', function () {
    $this->from(NFCD_URL)
        ->post(NFCD_URL, nfcdPayloadValido(['regime' => 'lucro_arbitrado']))
        ->assertSessionHasErrors('regime');

    expect(DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->count())->toBe(0);

    // Controle positivo — prova que a rejeição acima veio da regra, não de o POST nunca
    // chegar ao gravador (verde por não-execução, proibicoes.md §5 2026-07-24).
    $this->post(NFCD_URL, nfcdPayloadValido(['regime' => 'lucro_real', 'csosn' => null, 'cst' => '000']))
        ->assertRedirect();
    expect(DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->value('regime'))->toBe('lucro_real');
});

// ---------------------------------------------------------------------------------------
// UC-NFCD-04 · Alíquota é decimal ∈ [0,1] — "18" (por "18%") é rejeitado  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFCD-04 · alíquota fora de [0,1] é rejeitada e o decimal válido persiste', function () {
    // Erro de digitação clássico: 18 querendo dizer 18% → seria alíquota de 1800%.
    $this->from(NFCD_URL)
        ->post(NFCD_URL, nfcdPayloadValido(['aliquota_icms' => 18]))
        ->assertSessionHasErrors('aliquota_icms');

    expect(DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->count())->toBe(0);

    // Controle positivo: o decimal correto entra E persiste com o valor exato.
    $this->post(NFCD_URL, nfcdPayloadValido(['aliquota_icms' => 0.18]))->assertRedirect();

    $td = json_decode(
        DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->value('tributacao_default'),
        true,
    );
    expect((float) $td['aliquota_icms'])->toBe(0.18);
});

// ---------------------------------------------------------------------------------------
// UC-NFCD-05 · Salvar config não reescreve NFe já emitida  [T0] [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFCD-05 · upsert de config não altera nem apaga nfe_emissoes já gravadas', function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes ausente — rode as migrations do NfeBrasil.');
    }

    // Uma nota já autorizada guarda o SNAPSHOT da tributação do momento da emissão.
    // Mudar o default hoje não pode reescrever o que a SEFAZ autorizou ontem — o número
    // segue oficialmente usado (CONFAZ, Ajuste SINIEF 07/2005, Art. 14), por isso
    // `nfe_emissoes` é append-only (proibicoes.md §FSM Pipeline Canônico).
    $serie = '999';
    DB::table('nfe_emissoes')->where('business_id', NFCD_BIZ)->where('serie', $serie)->delete();
    DB::table('nfe_emissoes')->insert([
        'business_id'    => NFCD_BIZ,
        'transaction_id' => null,
        'modelo'         => '65',
        'serie'          => $serie,
        'numero'         => 987654,
        'status'         => 'autorizada',
        'cstat'          => '100',
        'valor_total'    => 123.45,
        'emitido_em'     => now()->subDay(),
        'created_at'     => now()->subDay(),
        'updated_at'     => now()->subDay(),
    ]);

    $antes = DB::table('nfe_emissoes')->where('business_id', NFCD_BIZ)->orderBy('id')->get()->toArray();

    // Config nova com alíquotas DIFERENTES das que a nota levou.
    $this->post(NFCD_URL, nfcdPayloadValido(['aliquota_icms' => 0.07, 'aliquota_pis' => 0.0165]))
        ->assertRedirect();

    $depois = DB::table('nfe_emissoes')->where('business_id', NFCD_BIZ)->orderBy('id')->get()->toArray();

    expect($depois)->toEqual($antes);  // nada reescrito, nada removido

    DB::table('nfe_emissoes')->where('business_id', NFCD_BIZ)->where('serie', $serie)->delete();
});

// ---------------------------------------------------------------------------------------
// UC-NFCD-06 · CSOSN e CST são mutuamente exclusivos  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFCD-06 · CSOSN e CST juntos são rejeitados e a ausência dos dois também', function () {
    // (a) os dois juntos — nota com dois códigos de situação tributária concorrentes.
    $this->from(NFCD_URL)
        ->post(NFCD_URL, nfcdPayloadValido(['csosn' => '102', 'cst' => '000']))
        ->assertSessionHasErrors('csosn');
    expect(DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->count())->toBe(0);

    // (b) nenhum dos dois — a cascade Nível 4 ficaria sem código tributário.
    $this->from(NFCD_URL)
        ->post(NFCD_URL, nfcdPayloadValido(['csosn' => null, 'cst' => null]))
        ->assertSessionHasErrors(['csosn', 'cst']);
    expect(DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->count())->toBe(0);

    // Controle positivo — exatamente UM dos dois grava (ARQ-0006 §Onboarding: Simples→CSOSN).
    $this->post(NFCD_URL, nfcdPayloadValido(['csosn' => '102', 'cst' => null]))->assertRedirect();
    $td = json_decode(
        DB::table('nfe_business_configs')->where('business_id', NFCD_BIZ)->value('tributacao_default'),
        true,
    );
    expect($td)->toHaveKey('csosn')->and($td)->not->toHaveKey('cst');
});
