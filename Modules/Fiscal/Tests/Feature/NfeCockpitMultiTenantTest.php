<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * PR #1 Fiscal/Nfe — isolation Tier 0 + permission gate.
 *
 * O Cockpit NF-e do módulo Fiscal é THIN agregador — lê NfeEmissao via
 * `Modules\NfeBrasil\Models\NfeEmissao` (HasBusinessScope global scope).
 * Esse teste verifica:
 *
 *   1. SQLite skip — schema NfeBrasil só roda em MySQL UltimatePOS
 *   2. Controller `index()` retorna 403 sem permission `fiscal.nfe.view`
 *   3. Counts são scoped por business_id (biz=1 não vê emissões biz=99)
 *   4. `buildRowsPayload` (deferred) respeita filtro `status=rejeitadas` cross-tenant
 *
 * ADR 0093: business_id Tier 0 IRREVOGÁVEL — toda Model que toca dados negócio.
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — Larissa, cliente real prod) em tests.
 *
 * Espelha pattern de `Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php`.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/Fiscal/Http/Controllers/NfeCockpitController.php
 * @see resources/js/Pages/Fiscal/Nfe.charter.md
 */

const FISCAL_BIZ_WAGNER = 1;
const FISCAL_BIZ_FICTICIO = 99;
const FISCAL_TAG_TEST = 'PR1-FISCAL-NFE-ISO-TEST';

/**
 * Guard de banco — chamado SÓ pelos casos que tocam `nfe_emissoes`.
 *
 * Antes isto era um `beforeEach` do arquivo inteiro, e o efeito colateral era caro: o caso do
 * `sefazCodes` (reflection pura, zero query) **skipava junto**. Como a lane de CI é SQLite e o
 * staging do CT 100 não tem as migrations do NfeBrasil, ele não executava em lugar nenhum —
 * cobertura de papel. Movendo o guard pra dentro dos casos que realmente precisam de tabela,
 * o que é DB-free passa a rodar em qualquer lane.
 */
function fiscalNfeCockpitExigeBanco(\Tests\TestCase $t): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        $t->markTestSkipped('SQLite-incompatível: NfeBrasil/Fiscal requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $t->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }

    // O global scope ScopeByBusiness só filtra com usuário AUTENTICADO — faz early-return
    // em `! auth()->check()` (app/Scopes/ScopeByBusiness.php:26) e lê a business
    // ativa de session('user.business_id'). Sem actingAs o scope no-opa e este guard de
    // isolamento contava biz=1 + biz=99 (3 em vez de 1) — falha de TESTE, não vazamento de
    // produto: a rota /fiscal/nfe roda atrás do middleware `auth`, onde auth()->check() é
    // sempre true. Autenticamos um usuário biz=1 (semeado pelo pest-mysql-setup; sem role →
    // não é superadmin) espelhando NfeBrasilMultiTenantIsolationTest. ADR 0093 + ADR 0101.
    $t->actingAs(\App\User::where('business_id', FISCAL_BIZ_WAGNER)->firstOrFail());
}

afterEach(function () {
    // Guard SQLite (Wagner 2026-05-25): cleanup só roda quando tabela existe.
    // beforeEach skipa tests que precisam dela, mas afterEach roda sempre —
    // sem guard, CI Pest SQLite (modules-pest.yml) quebra com QueryException
    // 'no such table: nfe_emissoes'.
    if (! Schema::hasTable('nfe_emissoes')) {
        return;
    }
    // Cleanup — qualquer emissão criada com tag de teste é removida hard.
    // Não usa global scope (precisa achar de TODOS os businesses).
    NfeEmissao::withoutGlobalScopes()
        ->where('chave_44', 'like', '%' . FISCAL_TAG_TEST . '%')
        ->forceDelete();
});

it('UC-FNFE-01 · global scope HasBusinessScope esconde emissões cross-tenant na contagem do cockpit', function () {
    fiscalNfeCockpitExigeBanco($this);

    // Cria 1 emissão biz=1 + 2 emissões biz=99 com mesma chave-tag pra rastreio.
    $base = [
        'modelo'      => '55',
        'serie'       => '1',
        'status'      => 'autorizada',
        'cstat'       => 100,
        'valor_total' => 100.00,
        'emitido_em'  => now(),
    ];

    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => FISCAL_BIZ_WAGNER,
        'numero'      => 9001,
        'chave_44'    => str_pad('9001' . FISCAL_TAG_TEST, 44, '0', STR_PAD_RIGHT),
    ]);

    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => FISCAL_BIZ_FICTICIO,
        'numero'      => 9002,
        'chave_44'    => str_pad('9002' . FISCAL_TAG_TEST, 44, '0', STR_PAD_RIGHT),
    ]);

    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => FISCAL_BIZ_FICTICIO,
        'numero'      => 9003,
        'chave_44'    => str_pad('9003' . FISCAL_TAG_TEST, 44, '0', STR_PAD_RIGHT),
    ]);

    // Simula sessão biz=1 — HasBusinessScope deve filtrar transparente.
    session(['business.id' => FISCAL_BIZ_WAGNER, 'user.business_id' => FISCAL_BIZ_WAGNER]);

    $countBiz1 = NfeEmissao::query()
        ->where('chave_44', 'like', '%' . FISCAL_TAG_TEST . '%')
        ->count();

    expect($countBiz1)->toBe(1)
        ->and(NfeEmissao::withoutGlobalScopes()
            ->where('chave_44', 'like', '%' . FISCAL_TAG_TEST . '%')
            ->count())->toBe(3);
});

// ⚠️ REMOVIDO 2026-07-27 — o caso `isCancelavel respeita janela legal 24h NFC-e vs 168h NF-e`
// vivia aqui, mas era TAUTOLÓGICO: declarava um `$isCancelavel = function (...)` que
// RE-IMPLEMENTAVA a fórmula do Controller dentro do próprio teste, e depois testava esse clone.
// Se o `NfeCockpitController::isCancelavel` mudasse a janela de 24h para 12h, o teste seguiria
// verde (lápide §5 2026-06-05). O substituto invoca o método REAL por reflection e está em
// `AcoesContratoTest::UC-FNFE-02` — com mordida provada (afrouxar a regra deixa o teste vermelho).


it('UC-FNFE-01 · os cStat que alimentam a tradução não vazam de outro business (Tier 0)', function () {
    // Guarda da query NOVA introduzida em 2026-09-04 (`cstatsDoBusiness`). Antes, `sefazCodes()`
    // era um array literal e não tocava o banco — logo não havia superfície de vazamento. Agora há,
    // e ADR 0093 exige que ela seja provada: um cStat só de biz=99 não pode entrar na tradução que
    // biz=1 recebe. O risco não é hipotético — em produção os dois businesses têm conjuntos
    // DISJUNTOS (biz=1: 709/716/778/781 · biz=164: 100/101), então vazamento apareceria na tela.
    fiscalNfeCockpitExigeBanco($this);

    $base = [
        'modelo'      => '55',
        'serie'       => '1',
        'status'      => 'rejeitada',
        'valor_total' => 10.00,
        'emitido_em'  => now(),
    ];

    // 781 só existe em biz=1; 110 só existe em biz=99.
    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => FISCAL_BIZ_WAGNER,
        'numero'      => 9101,
        'cstat'       => 781,
        'chave_44'    => str_pad('9101' . FISCAL_TAG_TEST, 44, '0', STR_PAD_RIGHT),
    ]);

    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => FISCAL_BIZ_FICTICIO,
        'numero'      => 9102,
        'cstat'       => 110,
        'chave_44'    => str_pad('9102' . FISCAL_TAG_TEST, 44, '0', STR_PAD_RIGHT),
    ]);

    session(['business.id' => FISCAL_BIZ_WAGNER, 'user.business_id' => FISCAL_BIZ_WAGNER]);

    $metodo = new ReflectionMethod(Modules\Fiscal\Http\Controllers\NfeCockpitController::class, 'cstatsDoBusiness');
    $metodo->setAccessible(true);
    $codigos = $metodo->invoke(new Modules\Fiscal\Http\Controllers\NfeCockpitController());

    expect($codigos)->toContain(781)
        ->and($codigos)->not->toContain(110);
});

it('UC-FNFE-03 · o cStat vira o rótulo OFICIAL da SEFAZ, não um apelido escrito à mão', function () {
    // Âncora: Manual de Orientação do Contribuinte / tabela cStat da SEFAZ, distribuída em
    // `vendor/nfephp-org/sped-nfe/storage/cstat.json` (528 códigos) com o SDK que este projeto
    // usa pra transmitir. Os textos abaixo estão TRANSCRITOS do MOC de propósito: são contrato
    // externo. Se alguém trocar a derivação por um array literal com apelidos, este caso cai.
    //
    // Por que não basta `toHaveKeys`: a versão anterior deste caso só checava presença e três
    // tons — e passava verde enquanto a tela dizia "CST/CFOP inválido" para o 778, cujo texto
    // oficial é "Informado NCM inexistente". Presença não é correção (LC-11).
    $servico = new Modules\Fiscal\Services\SefazCstatService();

    $mapa = $servico->mapaPara([100, 204, 220, 539, 691, 709, 716, 778, 781, 999]);

    expect($mapa[100]['label'])->toBe('Autorizado o uso da NF-e')
        ->and($mapa[220]['label'])->toBe('Prazo de Cancelamento superior ao previsto na Legislação')
        ->and($mapa[691]['label'])->toBe('Chave de Acesso da NF-e diverge da Chave de Acesso do EPEC')
        ->and($mapa[778]['label'])->toBe('Informado NCM inexistente')
        ->and($mapa[999]['label'])->toBe('Erro não catalogado (informar a mensagem de erro capturado no tratamento da exceção)');

    // Placeholder de exemplo do MOC não vaza pra pílula — o valor real da nota vem no `motivo`.
    expect($mapa[204]['label'])->toBe('Duplicidade de NF-e')
        ->and($mapa[539]['label'])->toBe('Duplicidade de NF-e com diferença na Chave de Acesso');

    // Tom: segue o campo `status` da tabela oficial ("1" = aceito). Rejeição é vermelha.
    expect($mapa[100]['tone'])->toBe('ok')
        ->and($mapa[220]['tone'])->toBe('bad')
        ->and($mapa[691]['tone'])->toBe('bad');
});

it('UC-FNFE-03 · os 3 códigos que biz=1 tem em produção deixaram de cair no fallback', function () {
    // Medido em produção 2026-09-04: biz=1 tem 9 emissões — 3 com cStat 781, 1 com 709, 1 com 716,
    // 1 com 778 e 3 sem cStat. Antes desta correção NENHUM dos três primeiros existia no mapa: a
    // tela renderizava "781 Status" e "709 Status" para a contadora. Este caso é o antídoto: se
    // o mapa voltar a ser lista fixa, os três somem de novo e o teste cai.
    $servico = new Modules\Fiscal\Services\SefazCstatService();

    $mapa = $servico->mapaPara([781, 709, 716]);

    expect($mapa[781]['label'])->toBe('Emissor não habilitado para emissão da NFC-e')
        ->and($mapa[709]['label'])->toBe('NFC-e com formato de DANFE inválido')
        ->and($mapa[716]['label'])->toBe('NFC-e em operação não destinada a consumidor final')
        ->and($mapa[781]['tone'])->toBe('bad');
});

it('UC-FNFE-03 · código fora da tabela some do mapa em vez de virar rótulo inventado', function () {
    // Controle negativo. O serviço NÃO adivinha: código desconhecido simplesmente não entra, e a
    // tela resolve pelo status de domínio (`sefazPill` em `_lib/fiscal-helpers.ts`). Se algum dia
    // ele passar a devolver um rótulo genérico aqui, o "Status" nu volta pela porta dos fundos.
    $servico = new Modules\Fiscal\Services\SefazCstatService();

    expect($servico->mapaPara([424242]))->toBe([])
        ->and($servico->mapaPara([0, null]))->toBe([]);
});

it('UC-FNFE-03 · a tabela oficial está instalada — sem ela a tela perde a tradução', function () {
    // Não é teste do vendor: é o contrato de que a FONTE existe. `nfephp-org/sped-nfe` é
    // dependência declarada no composer.json, então ausência aqui é defeito de ambiente que
    // merece vermelho — não skip silencioso (LC-13: skip sai exit 0 e parece cobertura).
    expect(is_file(base_path(Modules\Fiscal\Services\SefazCstatService::CAMINHO_TABELA)))
        ->toBeTrue('Tabela cStat do sped-nfe ausente — rode composer install');

    expect(count(Modules\Fiscal\Services\SefazCstatService::tabela()))->toBeGreaterThan(400);
});
