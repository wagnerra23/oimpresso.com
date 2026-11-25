<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — a reimportação muda a versão vigente da regra, não a encerrada.
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md — UC-NFIM-09
// Âncora: SPEC NfeBrasil R-NFE-019 (editar gera versão nova; a antiga só ganha `valida_ate`) +
// ImportCsv.charter.md (idempotente pela chave). Nasceu vermelho em 2026-11-24 (a reimportação
// gravava na versão encerrada); a correção em ImportRegrasCsvService::aplicar entrou no mesmo PR.

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvConferencia;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;

uses(Tests\TestCase::class);

/**
 * Ponta a ponta pela rota (preview → aplicar), como a tela faz. MySQL-only, tenant fictício 98
 * via `seededTenant()` (ADR 0358), usuário novo só com `nfe.tributacao.manage`.
 */
function rvvBiz(): int
{
    return test()->seededTenant()->id;
}

/** CSV com uma regra Nível 3 (todas as UFs) e uma Nível 2 (SP → RJ), do mesmo NCM. */
function rvvCsv(float $icmsTodas = 0.18, float $icmsRj = 0.12): string
{
    $cab = implode(',', ImportRegrasCsvService::COLUNAS_OBRIGATORIAS);

    return $cab . "\n"
        . "84439100,SP,,5102,102,,{$icmsTodas},0.0065,0.03,0\n"
        . "84439100,SP,RJ,6102,102,,{$icmsRj},0.0065,0.03,0\n";
}

function rvvLimpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $regras = DB::table('nfe_fiscal_rules')->where('business_id', rvvBiz())->where('ncm', '84439100')->pluck('id');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('fiscal_rule_id', $regras)->delete();
    }
    if (Schema::hasTable('nfe_revisoes_contador')) {
        DB::table('nfe_revisoes_contador')->whereIn('regra_id', $regras)->delete();
    }
    DB::table('nfe_fiscal_rules')->whereIn('id', $regras)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function rvvLogar(): void
{
    test()->actingAs(test()->usuarioComPermissoes(['nfe.tributacao.manage']));
    test()->withSession(['business.id' => rvvBiz(), 'user.business_id' => rvvBiz()]);
}

/** Confere e aplica o CSV como a tela faz; devolve a mensagem de resumo. */
function rvvImportar(string $csv): string
{
    test()->post('/nfe-brasil/tributacao/import/preview', [
        'arquivo' => UploadedFile::fake()->createWithContent('regras.csv', $csv),
    ])->assertSessionHasNoErrors();

    // Pré-condição anti-vácuo: o preview leu as duas linhas.
    expect(session('preview.total_validas'))->toBe(2);
    expect(session(ImportRegrasCsvConferencia::SESSAO_REF))->toBeString();

    $res = test()->post('/nfe-brasil/tributacao/import/aplicar')->assertRedirect();

    return (string) $res->getSession()->get('success');
}

/** Regras vigentes do tenant (as que o motor enxerga), sem as versões encerradas. */
function rvvVigentes(): \Illuminate\Support\Collection
{
    return DB::table('nfe_fiscal_rules')
        ->where('business_id', rvvBiz())
        ->where('ncm', '84439100')
        ->whereNull('deleted_at')
        ->when(Schema::hasColumn('nfe_fiscal_rules', 'valida_ate'), fn ($q) => $q->where(
            fn ($w) => $w->whereNull('valida_ate')->orWhere('valida_ate', '>=', now()->toDateString())
        ))
        ->get(['id', 'uf_destino', 'aliquota_icms']);
}

function rvvIcms(?string $ufDestino): float
{
    $r = rvvVigentes()->firstWhere('uf_destino', $ufDestino);
    expect($r)->not->toBeNull();

    return (float) $r->aliquota_icms;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: a chave da regra é lida do schema real de nfe_fiscal_rules.');
    }
    if (! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('Tabela nfe_fiscal_rules ausente — rode as migrations do módulo.');
    }

    rvvLimpar();

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
    rvvLimpar();
    session()->forget([ImportRegrasCsvConferencia::SESSAO_REF, ImportRegrasCsvConferencia::SESSAO_BUSINESS]);
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-09 · A reimportação muda a versão vigente, não a encerrada  [V0] [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFIM-09 · regra editada pela tela: a reimportação muda a versão vigente e não reescreve a encerrada', function () {
    if (! Schema::hasColumn('nfe_fiscal_rules', 'valida_ate')) {
        $this->markTestSkipped('Sem versionamento de regra (migração 2026_10_07_000001).');
    }
    rvvLogar();
    rvvImportar(rvvCsv());

    // Editar pela tela gera versão nova (R-NFE-019): a antiga fica com `valida_ate`.
    $todas = rvvVigentes()->firstWhere('uf_destino', null)->id;
    $this->put("/nfe-brasil/tributacao/regras/{$todas}", [
        'ncm' => '84439100', 'uf_origem' => 'SP', 'uf_destino' => null, 'cfop' => '5102',
        'csosn' => '102', 'cst' => null, 'aliquota_icms' => 0.25, 'aliquota_pis' => 0.0065,
        'aliquota_cofins' => 0.03, 'aliquota_ipi' => 0,
    ])->assertSessionHasNoErrors();
    expect(rvvIcms(null))->toBe(0.25);

    rvvImportar(rvvCsv(icmsTodas: 0.17));

    // A versão encerrada explica as notas de antes da edição: nenhuma coluna dela muda (R-NFE-019).
    expect((float) DB::table('nfe_fiscal_rules')->where('id', $todas)->value('aliquota_icms'))->toBe(0.18);

    // O que o motor usa é a versão vigente: é ela que tem de ter o valor do CSV.
    expect(rvvVigentes())->toHaveCount(2);
    expect(rvvIcms(null))->toBe(0.17);

    // Segundo caminho: o motor que a nota usa. Item de 1.000,00 de SP para MG (cai na regra de
    // todas as UFs) sai com ICMS 170,00. Antes da correção saía 250,00 (a versão da tela).
    $tributo = (new \Modules\NfeBrasil\Services\MotorTributarioService)->calcular(
        new \Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext(ncm: '84439100', valor: 1000.0),
        businessId: rvvBiz(), ufOrigem: 'SP', ufDestino: 'MG',
    );
    expect($tributo->valor_icms)->toBe(170.0);
});
