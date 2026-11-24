<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — o upload do CSV de regras tem limite de 5 MB e recusa arquivo malformado sem gravar.
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md — UC-NFIM-10 · UC-NFIM-11 · UC-NFIM-12
// Âncora: ImportCsv.charter.md §Goals ("máx 5 MB, `.csv`/`.txt`") e §Pendências ("Confirmar limite
// de 5 MB e comportamento com CSV malformado (encoding/separador)") + SDD CU-NFE-07.

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;

uses(Tests\TestCase::class);

/** MySQL-only, tenant fictício 98 (ADR 0358), usuário novo só com `nfe.tributacao.manage`. */
function arqBiz(): int
{
    return test()->seededTenant()->id;
}

function arqLogar(): void
{
    test()->actingAs(test()->usuarioComPermissoes(['nfe.tributacao.manage']));
    test()->withSession(['business.id' => arqBiz(), 'user.business_id' => arqBiz()]);
}

function arqCabecalho(string $sep = ','): string
{
    return implode($sep, ImportRegrasCsvService::COLUNAS_OBRIGATORIAS);
}

function arqContar(): int
{
    return (int) DB::table('nfe_fiscal_rules')->where('business_id', arqBiz())->where('ncm', 'like', '8443%')->count();
}

function arqLimpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $regras = DB::table('nfe_fiscal_rules')->where('business_id', arqBiz())->where('ncm', 'like', '8443%')->pluck('id');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('fiscal_rule_id', $regras)->delete();
    }
    if (Schema::hasTable('nfe_revisoes_contador')) {
        DB::table('nfe_revisoes_contador')->whereIn('regra_id', $regras)->delete();
    }
    DB::table('nfe_fiscal_rules')->whereIn('id', $regras)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

/** O resumo como a página recebe (prop `preview` do Inertia), lido no GET seguinte à conferência. */
function arqResumoNaPagina(): ?array
{
    return test()->get('/nfe-brasil/tributacao/import')->assertOk()->viewData('page')['props']['preview'] ?? null;
}

function arqPreview(string $conteudo, string $nome = 'regras.csv')
{
    return test()->post('/nfe-brasil/tributacao/import/preview', [
        'arquivo' => UploadedFile::fake()->createWithContent($nome, $conteudo),
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: grava e confere nfe_fiscal_rules no schema real.');
    }
    if (! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('Tabela nfe_fiscal_rules ausente — rode as migrations do módulo.');
    }
    arqLimpar();
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
    arqLimpar();
    session()->forget('nfe_import_csv_linhas');
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-10 · Arquivo acima de 5 MB é recusado antes de ser lido
// ---------------------------------------------------------------------------------------
it('UC-NFIM-10 · CSV de mais de 5 MB é recusado com aviso e nada vai para a conferência', function () {
    arqLogar();

    $linha = "84439100,SP,,5102,102,,0.18,0.0065,0.03,0\n";
    $grande = arqCabecalho() . "\n" . str_repeat($linha, intdiv(5 * 1024 * 1024, strlen($linha)) + 100);
    expect(strlen($grande))->toBeGreaterThan(5 * 1024 * 1024);

    arqPreview($grande)->assertSessionHasErrors(['arquivo' => 'Arquivo muito grande (máx 5 MB).']);
    expect(session('nfe_import_csv_linhas'))->toBeNull();

    // CONTROLE POSITIVO — o mesmo conteúdo, pequeno, passa na conferência.
    arqPreview(arqCabecalho() . "\n" . $linha)->assertSessionHasNoErrors();
    expect(session('nfe_import_csv_linhas'))->toHaveCount(1);
    expect(arqContar())->toBe(0);
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-11 · CSV malformado é recusado na conferência, sem erro na tela e sem gravar
// ---------------------------------------------------------------------------------------
it('UC-NFIM-11 · separador ponto e vírgula não passa da conferência e o aviso fala do separador', function () {
    arqLogar();

    $res = arqPreview(arqCabecalho(';') . "\n" . "84439100;SP;;5102;102;;0,18;0,0065;0,03;0\n")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $preview = $res->getSession()->get('preview');
    expect($preview['total_validas'])->toBe(0);
    expect($preview['erros'][0]['motivo'])->toContain('ponto e vírgula');
    expect(session('nfe_import_csv_linhas'))->toBe([]);

    // Aplicar depois não grava nada.
    $this->post('/nfe-brasil/tributacao/import/aplicar')->assertSessionHasErrors('arquivo');
    expect(arqContar())->toBe(0);
});

it('UC-NFIM-11 · arquivo em Latin-1 com acento numa coluna extra é lido e o resumo chega à página', function () {
    arqLogar();

    // Coluna extra "descricao" com texto acentuado em Windows-1252 (o "CSV" antigo do Excel no
    // Windows) e uma linha com NCM inválido, para o texto ir parar na lista de erros do resumo.
    $csv = arqCabecalho() . ",descricao\n"
        . "84439100,SP,,5102,102,,0.18,0.0065,0.03,0,Impressão\n"
        . "8443,SP,,5102,102,,0.18,0.0065,0.03,0,Peça\n";
    $latin1 = mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');
    expect(mb_check_encoding($latin1, 'UTF-8'))->toBeFalse(); // pré-condição: o arquivo NÃO é UTF-8

    arqPreview($latin1)->assertRedirect()->assertSessionHasNoErrors();

    $resumo = arqResumoNaPagina();
    expect($resumo['total_validas'])->toBe(1);
    expect($resumo['total_erros'])->toBe(1);
    expect($resumo['erros'][0]['raw']['descricao'])->toBe('Peça'); // convertido, não corrompido
    expect(arqContar())->toBe(0);
});

it('UC-NFIM-11 · arquivo em UTF-16 (o "Texto Unicode" do Excel) é lido como o UTF-8', function () {
    arqLogar();

    $utf16 = "\xFF\xFE" . mb_convert_encoding(arqCabecalho() . "\n84439100,SP,,5102,102,,0.18,0.0065,0.03,0\n", 'UTF-16LE', 'UTF-8');

    arqPreview($utf16)->assertRedirect()->assertSessionHasNoErrors();

    $resumo = arqResumoNaPagina();
    expect($resumo['total_validas'])->toBe(1);
    expect($resumo['amostras'][0]['ncm'])->toBe('84439100');
    expect((float) $resumo['amostras'][0]['aliquota_icms'])->toBe(0.18);
    expect(arqContar())->toBe(0); // conferir não grava
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-12 · O resumo da conferência aparece na tela
// ---------------------------------------------------------------------------------------
it('UC-NFIM-12 · depois de conferir, a página recebe o resumo com as linhas válidas', function () {
    arqLogar();

    // Controle: sem conferência, a página abre sem resumo.
    expect(arqResumoNaPagina())->toBeNull();

    arqPreview(arqCabecalho() . "\n84439100,SP,,5102,102,,0.18,0.0065,0.03,0\n8443,SP,,5102,102,,0.18,0,0,0\n")
        ->assertSessionHasNoErrors();

    $resumo = arqResumoNaPagina();
    expect($resumo['total_validas'])->toBe(1);
    expect($resumo['total_erros'])->toBe(1);
    expect($resumo['amostras'][0]['ncm'])->toBe('84439100');
});
