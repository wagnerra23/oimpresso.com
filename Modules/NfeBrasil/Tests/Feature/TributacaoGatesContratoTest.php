<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — quem pode mexer na tributação, e em qual tenant a regra grava.
// Contrato das telas: resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md — UC-NFRF-01..04
//                     resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md  — UC-NFIM-01..04
// Os casos derivam do CONTRATO (US-NFE-010 + ADR arq/0006 + ADR 0093 + o §Backend dos charters),
// não da implementação — teste derivado do código é tautológico (proibicoes.md §5 2026-06-05).

use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-NFE-010 fases 2/3 · gates de permissão e fronteira de tenant na tributação.
 *
 * ⚠️ DOIS CASOS NASCEM VERMELHOS **POR DESENHO** — e o vermelho É o achado:
 *
 *   UC-NFRF-04  `TributacaoController@destroy` recebe `Illuminate\Http\Request` (não FormRequest) e
 *               o arquivo tem ZERO ocorrências de `can(`/`abort`; o grupo de rotas não tem
 *               middleware de permissão. Hoje QUALQUER usuário autenticado do tenant apaga uma
 *               regra tributária. Contado no SDD §5.4.1: 3 das 5 mutações estão assim
 *               (`destroy`, `toggleAutoEmission`, `aplicarTemplate`).
 *
 *   UC-NFIM-04  O import resolve o tenant DUAS vezes, em requests diferentes: `preview` guarda as
 *               linhas em `session('nfe_import_csv_linhas')` SEM carimbar o business, e `aplicar`
 *               lê `session('business.id')` naquele instante. Trocar de negócio entre os dois
 *               passos grava as regras do tenant A dentro do tenant B — sem erro, sem aviso.
 *
 * A correção é decisão [W], não conserto silencioso (proibicoes.md §Precedência). Por isso este
 * arquivo NÃO foi adicionado à allowlist do `nfebrasil-pest.yml`: ela é required com
 * `enforce_admins`, e um vermelho lá bloquearia o merge de todo mundo.
 *
 * POR QUE MYSQL-ONLY · biz=1 e biz=2 (NUNCA biz=4 — ROTA LIVRE em produção, ADR 0101).
 *
 * @see memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md §5.3 F7/F8 · §5.4.1 · §6.2
 * @see memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md
 */

function nfgtBiz(): int
{
    return 1;
}

function nfgtBizOutro(): int
{
    return 2;
}

function nfgtPermissao(): string
{
    return 'nfe.tributacao.manage';
}

/** Payload válido pro `UpsertRegraTributariaRequest` (regime Simples → CSOSN). */
function nfgtPayload(array $overrides = []): array
{
    return array_merge([
        'ncm'             => '49019900',
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'cst'             => null,
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
    ], $overrides);
}

function nfgtRegra(int $businessId, string $ncm = '22021000'): int
{
    return (int) DB::table('nfe_fiscal_rules')->insertGetId([
        'business_id'     => $businessId,
        'ncm'             => $ncm,
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.12,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

function nfgtCsv(): string
{
    $cabecalho = implode(',', ImportRegrasCsvService::COLUNAS_OBRIGATORIAS);

    return $cabecalho . "\n" . '84439100,SP,,5102,102,,0.18,0.0065,0.03,0' . "\n";
}

function nfgtArquivo(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('regras.csv', nfgtCsv());
}

function nfgtLimpar(): void
{
    $bizs = [nfgtBiz(), nfgtBizOutro()];
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', $bizs)->delete();
    }
    DB::table('nfe_fiscal_rules')->whereIn('business_id', $bizs)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    session()->forget('nfe_import_csv_linhas');
}

/** Spatie cacheia o mapa de permissões — sem invalidar, o grant/revoke do teste não vale. */
function nfgtEsquecerCache(): void
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

function nfgtLogar(bool $comPermissao): User
{
    $user = User::where('business_id', nfgtBiz())->firstOrFail();
    $perm = Permission::firstOrCreate(['name' => nfgtPermissao(), 'guard_name' => 'web']);

    $comPermissao ? $user->givePermissionTo($perm) : $user->revokePermissionTo($perm);
    nfgtEsquecerCache();

    test()->actingAs($user);
    // `withSession` (não o helper `session()`) porque é ele que persiste entre requests do mesmo
    // teste; e com o bloco `user` preenchido o middleware `SetSessionData` fica no-op, então o que
    // semeamos é o que o Controller lê — inclusive quando o UC-NFIM-04 troca o tenant de propósito.
    test()->withSession(['business.id' => nfgtBiz(), 'user.business_id' => nfgtBiz()]);

    return $user;
}

/** Conta regras do business inteiro — é o denominador de "nada foi gravado". */
function nfgtContar(int $businessId): int
{
    return (int) DB::table('nfe_fiscal_rules')
        ->where('business_id', $businessId)
        ->whereNull('deleted_at')
        ->count();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: isolamento multi-tenant exige schema real (ADR 0101; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('Tabela nfe_fiscal_rules ausente — rode as migrations do módulo.');
    }

    nfgtLimpar();

    // O bridge `SyncFiscalRuleToTaxRate` (ADR arq/0005) deriva tax_rates a partir da regra — tem
    // cobertura própria em SyncFiscalRuleToTaxRateTest. Aqui ele só adicionaria ruído.
    Event::fake([
        \Modules\NfeBrasil\Events\FiscalRuleCreated::class,
        \Modules\NfeBrasil\Events\FiscalRuleUpdated::class,
        \Modules\NfeBrasil\Events\FiscalRuleDeleted::class,
    ]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_fiscal_rules')) {
        return;
    }
    nfgtLimpar();

    $perm = Permission::where('name', nfgtPermissao())->where('guard_name', 'web')->first();
    $user = User::where('business_id', nfgtBiz())->first();
    if ($perm && $user) {
        $user->revokePermissionTo($perm);
        nfgtEsquecerCache();
    }
});

// =======================================================================================
// RegraForm — /nfe-brasil/tributacao/regras/*
// =======================================================================================

// ---------------------------------------------------------------------------------------
// UC-NFRF-01 · Criar/editar regra exige a permissão fiscal  [T0] [V0]
// ---------------------------------------------------------------------------------------
it('UC-NFRF-01 · criar e editar regra exigem nfe.tributacao.manage', function () {
    nfgtLogar(comPermissao: false);

    $antes = nfgtContar(nfgtBiz());
    $existente = nfgtRegra(nfgtBiz());

    $this->post('/nfe-brasil/tributacao/regras', nfgtPayload())->assertForbidden();
    $this->put("/nfe-brasil/tributacao/regras/{$existente}", nfgtPayload(['aliquota_icms' => 0.25]))
        ->assertForbidden();

    // Nada gravado: a contagem só subiu pela regra que o próprio teste semeou…
    expect(nfgtContar(nfgtBiz()))->toBe($antes + 1);
    // …e a alíquota da existente não mudou.
    expect((float) DB::table('nfe_fiscal_rules')->where('id', $existente)->value('aliquota_icms'))
        ->toBe(0.12);

    // CONTROLE POSITIVO — com a permissão, os dois passam. Sem isto o 403 poderia vir do payload,
    // da rota ou do CSRF, e o caso não provaria que é o gate.
    nfgtLogar(comPermissao: true);

    $this->post('/nfe-brasil/tributacao/regras', nfgtPayload())->assertRedirect();
    expect(nfgtContar(nfgtBiz()))->toBe($antes + 2);

    $this->put("/nfe-brasil/tributacao/regras/{$existente}", nfgtPayload(['aliquota_icms' => 0.25]))
        ->assertRedirect();
    expect((float) DB::table('nfe_fiscal_rules')->where('id', $existente)->value('aliquota_icms'))
        ->toBe(0.25);
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-02 · A regra nasce no meu tenant, nunca no de quem a rota apontar  [T0] [V0]
// ---------------------------------------------------------------------------------------
it('UC-NFRF-02 · business_id vindo no corpo é ignorado — a regra nasce no tenant da sessão', function () {
    nfgtLogar(comPermissao: true);

    $antesVizinho = nfgtContar(nfgtBizOutro());

    $this->post('/nfe-brasil/tributacao/regras', nfgtPayload([
        'ncm'         => '61091000',
        'business_id' => nfgtBizOutro(), // ← tentativa de mandar pro vizinho
    ]))->assertRedirect();

    // A regra existe — no MEU tenant (controle positivo)…
    expect(
        DB::table('nfe_fiscal_rules')
            ->where('business_id', nfgtBiz())
            ->where('ncm', '61091000')
            ->count()
    )->toBe(1);

    // …e o vizinho não ganhou nada.
    expect(nfgtContar(nfgtBizOutro()))->toBe($antesVizinho);
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-03 · Abrir a edição de regra alheia é 404 — e não vaza a alíquota dela  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFRF-03 · edit de regra de outro business dá 404 e não vaza os valores dela', function () {
    nfgtLogar(comPermissao: true);

    $minha     = nfgtRegra(nfgtBiz(), '49019900');
    $doVizinho = nfgtRegra(nfgtBizOutro(), '99999999');

    $res = $this->get("/nfe-brasil/tributacao/regras/{$doVizinho}/edit");
    $res->assertNotFound();
    // Não basta o 404: um 404 renderizado com o payload já montado ainda vazaria.
    expect($res->getContent())->not->toContain('99999999');

    // CONTROLE POSITIVO — a minha abre e traz os valores.
    $this->get("/nfe-brasil/tributacao/regras/{$minha}/edit")
        ->assertOk()
        ->assertInertia(function ($page) {
            expect($page->toArray()['props']['regra']['ncm'] ?? null)->toBe('49019900');
        });
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-04 · Apagar regra exige a permissão fiscal  [T0] [V0] — ❌ FALHA ESPERADA
// ---------------------------------------------------------------------------------------
it('UC-NFRF-04 · apagar regra sem nfe.tributacao.manage deve dar 403', function () {
    nfgtLogar(comPermissao: false);

    $regra = nfgtRegra(nfgtBiz(), '84439100');

    // Pré-condição anti-vácuo: a regra existe e está ativa antes do DELETE.
    expect(DB::table('nfe_fiscal_rules')->where('id', $regra)->whereNull('deleted_at')->count())->toBe(1);

    $this->delete("/nfe-brasil/tributacao/regras/{$regra}")->assertForbidden();

    // `NfeFiscalRule` usa SoftDeletes — regra fiscal não some do histórico. O que este caso exige é
    // que ela continue ATIVA (deleted_at nulo), não apenas presente na tabela.
    expect(DB::table('nfe_fiscal_rules')->where('id', $regra)->whereNull('deleted_at')->count())
        ->toBe(1, 'usuário sem nfe.tributacao.manage apagou uma regra tributária (SDD §5.4.1)');
});

// =======================================================================================
// ImportCsv — /nfe-brasil/tributacao/import/*
// =======================================================================================

// ---------------------------------------------------------------------------------------
// UC-NFIM-01 · O preview confere sem gravar nada  [V0]
// ---------------------------------------------------------------------------------------
it('UC-NFIM-01 · preview não grava regra nenhuma; aplicar depois grava', function () {
    nfgtLogar(comPermissao: true);

    $antes = nfgtContar(nfgtBiz());

    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfgtArquivo()])
        ->assertRedirect()
        ->assertSessionHasNoErrors(); // validação que falha TAMBÉM redireciona — sem isto o caso
                                      // passaria por o CSV nunca ter sido aceito.

    // Pré-condição anti-vácuo: o preview de fato parseou linhas.
    expect(session('nfe_import_csv_linhas'))->not->toBeEmpty();

    // O passo de conferência só vale se for inócuo.
    expect(nfgtContar(nfgtBiz()))->toBe($antes);

    // CONTROLE POSITIVO — aplicar em seguida grava de verdade. Sem isto, "não gravou" poderia ser
    // apenas o CSV nunca ter sido lido (verde por não-execução).
    $this->post('/nfe-brasil/tributacao/import/aplicar')->assertRedirect();
    expect(nfgtContar(nfgtBiz()))->toBe($antes + 1);
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-02 · Aplicar sem preview não grava nada  [V0]
// ---------------------------------------------------------------------------------------
it('UC-NFIM-02 · aplicar sem preview anterior é recusado e não grava', function () {
    nfgtLogar(comPermissao: true);

    // Pré-condição anti-vácuo: existe regra no tenant, então "a contagem não mudou" é afirmação
    // sobre um número real, não sobre o vazio.
    nfgtRegra(nfgtBiz());
    $antes = nfgtContar(nfgtBiz());

    session()->forget('nfe_import_csv_linhas');

    $this->post('/nfe-brasil/tributacao/import/aplicar')
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect(nfgtContar(nfgtBiz()))->toBe($antes);
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-03 · Aplicar exige a permissão fiscal  [T0] [V0]
// ---------------------------------------------------------------------------------------
it('UC-NFIM-03 · aplicar sem nfe.tributacao.manage dá 403 e não grava', function () {
    // Passo 1 com permissão: o preview precisa acontecer pra haver linhas na sessão — senão o 403
    // do passo 2 poderia estar medindo "não tinha nada pra aplicar".
    nfgtLogar(comPermissao: true);
    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfgtArquivo()])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect(session('nfe_import_csv_linhas'))->not->toBeEmpty();

    $antes = nfgtContar(nfgtBiz());

    // Passo 2 sem permissão.
    nfgtLogar(comPermissao: false);
    $this->post('/nfe-brasil/tributacao/import/aplicar')->assertForbidden();

    expect(nfgtContar(nfgtBiz()))->toBe($antes);
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-04 · O conferido num tenant não pode ser gravado noutro  [T0] [V0] — ❌ FALHA ESPERADA
// ---------------------------------------------------------------------------------------
it('UC-NFIM-04 · linhas conferidas no business A não podem ser gravadas no business B', function () {
    nfgtLogar(comPermissao: true);

    // Passo 1 — confiro logado no business A (=biz 1).
    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfgtArquivo()])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect(session('nfe_import_csv_linhas'))->not->toBeEmpty();

    $antesB = nfgtContar(nfgtBizOutro());

    // Passo 2 — o tenant da sessão passou a ser o business B, e só ele. As linhas seguem na sessão,
    // sem carimbo de origem. (O bloco `user` já está populado, então `SetSessionData` não desfaz a
    // troca — é exatamente o que acontece quando um usuário multi-business troca de negócio.)
    $this->withSession(['business.id' => nfgtBizOutro(), 'user.business_id' => nfgtBizOutro()]);

    $this->post('/nfe-brasil/tributacao/import/aplicar');

    // O contrato aceita DUAS saídas honestas — recusar, ou gravar em A. O que não pode é gravar em
    // B. Por isso o assert é sobre ONDE a regra existe depois, não sobre status HTTP nem mensagem:
    // acoplar ao status reprovaria arbitrariamente uma das duas saídas.
    expect(nfgtContar(nfgtBizOutro()))->toBe(
        $antesB,
        'linhas conferidas no business A foram gravadas no business B (SDD §5.3 F8)',
    );
});
