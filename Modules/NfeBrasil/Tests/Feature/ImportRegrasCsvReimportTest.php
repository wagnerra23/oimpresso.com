<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — reimportar o mesmo CSV atualiza as regras pela chave, nunca duplica.
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md — UC-NFIM-08
// Âncora: ImportCsv.charter.md ("idempotente pela chave NCM + UF origem + UF destino") + SDD
// CU-NFE-07. O Service foi lido só para confirmar; o caso deriva do charter (§5 2026-06-05).

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;

uses(Tests\TestCase::class);

/**
 * Ponta a ponta pela rota (preview → aplicar), como a tela faz. MySQL-only, tenant fictício 98
 * via `seededTenant()` (ADR 0358), usuário novo só com `nfe.tributacao.manage`.
 */
function rimBiz(): int
{
    return test()->seededTenant()->id;
}

/** CSV com uma regra Nível 3 (todas as UFs) e uma Nível 2 (SP → RJ), do mesmo NCM. */
function rimCsv(float $icmsTodas = 0.18, float $icmsRj = 0.12): string
{
    $cab = implode(',', ImportRegrasCsvService::COLUNAS_OBRIGATORIAS);

    return $cab . "\n"
        . "84439100,SP,,5102,102,,{$icmsTodas},0.0065,0.03,0\n"
        . "84439100,SP,RJ,6102,102,,{$icmsRj},0.0065,0.03,0\n";
}

function rimLimpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $regras = DB::table('nfe_fiscal_rules')->where('business_id', rimBiz())->where('ncm', '84439100')->pluck('id');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('fiscal_rule_id', $regras)->delete();
    }
    if (Schema::hasTable('nfe_revisoes_contador')) {
        DB::table('nfe_revisoes_contador')->whereIn('regra_id', $regras)->delete();
    }
    DB::table('nfe_fiscal_rules')->whereIn('id', $regras)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function rimLogar(): void
{
    test()->actingAs(test()->usuarioComPermissoes(['nfe.tributacao.manage']));
    test()->withSession(['business.id' => rimBiz(), 'user.business_id' => rimBiz()]);
}

/** Confere e aplica o CSV como a tela faz; devolve a mensagem de resumo. */
function rimImportar(string $csv): string
{
    test()->post('/nfe-brasil/tributacao/import/preview', [
        'arquivo' => UploadedFile::fake()->createWithContent('regras.csv', $csv),
    ])->assertSessionHasNoErrors();

    // Pré-condição anti-vácuo: o preview leu as duas linhas.
    expect(session('nfe_import_csv_linhas'))->toHaveCount(2);

    $res = test()->post('/nfe-brasil/tributacao/import/aplicar')->assertRedirect();

    return (string) $res->getSession()->get('success');
}

/** Regras vigentes do tenant (as que o motor enxerga), sem as versões encerradas. */
function rimVigentes(): \Illuminate\Support\Collection
{
    return DB::table('nfe_fiscal_rules')
        ->where('business_id', rimBiz())
        ->where('ncm', '84439100')
        ->whereNull('deleted_at')
        ->when(Schema::hasColumn('nfe_fiscal_rules', 'valida_ate'), fn ($q) => $q->where(
            fn ($w) => $w->whereNull('valida_ate')->orWhere('valida_ate', '>=', now()->toDateString())
        ))
        ->get(['id', 'uf_destino', 'aliquota_icms']);
}

function rimIcms(?string $ufDestino): float
{
    $r = rimVigentes()->firstWhere('uf_destino', $ufDestino);
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

    rimLimpar();

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
    rimLimpar();
    session()->forget('nfe_import_csv_linhas');
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-08 · Reimportar o mesmo CSV não duplica regras  [V0]
// ---------------------------------------------------------------------------------------
it('UC-NFIM-08 · importar o mesmo CSV duas vezes mantém as duas regras e só atualiza', function () {
    rimLogar();

    // CONTROLE POSITIVO — a primeira importação cria as duas (sem isto, "não dobrou" poderia ser o
    // CSV nunca ter sido gravado).
    expect(rimImportar(rimCsv()))->toContain('2 criadas, 0 atualizadas, 0 falharam');
    expect(rimVigentes())->toHaveCount(2);

    expect(rimImportar(rimCsv()))->toContain('0 criadas, 2 atualizadas, 0 falharam');
    expect(rimVigentes())->toHaveCount(2);
    expect(rimIcms(null))->toBe(0.18);
    expect(rimIcms('RJ'))->toBe(0.12);
});

it('UC-NFIM-08 · reimportar com alíquota nova atualiza a regra em vez de criar outra', function () {
    rimLogar();
    rimImportar(rimCsv());

    expect(rimImportar(rimCsv(icmsTodas: 0.17, icmsRj: 0.07)))->toContain('0 criadas, 2 atualizadas');

    expect(rimVigentes())->toHaveCount(2);
    expect(rimIcms(null))->toBe(0.17);
    expect(rimIcms('RJ'))->toBe(0.07);
});
