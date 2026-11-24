<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — Import CSV de regras tributárias: o lote conferido não mora na sessão.
// Contrato: resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md — UC-NFIM-14
// (com UC-NFIM-02 e UC-NFIM-04 reafirmados no novo armazenamento).

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvConferencia;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;

uses(Tests\TestCase::class);

/**
 * UC-NFIM-14 · a sessão do usuário não cresce com o tamanho do CSV.
 *
 * Medido no CT 100 em 2026-11-24, antes desta mudança: um CSV de 5 MB (teto do upload, 119.153
 * linhas) virava 30,9 MB de sessão serializada. Produção usa SESSION_DRIVER=file, então esse
 * arquivo era lido e regravado a cada request do usuário enquanto a conferência estivesse
 * pendente. As linhas passaram para um arquivo em storage; a sessão guarda só a referência.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a outra empresa. Nunca biz=1 nem biz=4.
 * NCMs com prefixo 8443 próprios deste arquivo — a limpeza apaga só eles.
 */

const NFSS_NCM_PREFIXO = '8443';

function nfssCsv(int $linhas): string
{
    $csv = implode(',', ImportRegrasCsvService::COLUNAS_OBRIGATORIAS) . "\n";
    for ($i = 0; $i < $linhas; $i++) {
        $csv .= NFSS_NCM_PREFIXO . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . ',SP,,5102,102,,0.18,0.0065,0.03,0' . "\n";
    }

    return $csv;
}

function nfssArquivo(int $linhas): UploadedFile
{
    return UploadedFile::fake()->createWithContent('regras.csv', nfssCsv($linhas));
}

function nfssContar(int $businessId): int
{
    return (int) DB::table('nfe_fiscal_rules')
        ->where('business_id', $businessId)
        ->where('ncm', 'like', NFSS_NCM_PREFIXO . '%')
        ->whereNull('deleted_at')
        ->count();
}

/** Tamanho da sessão como o driver `file` a grava: o array inteiro serializado. */
function nfssTamanhoSessao(): int
{
    return strlen(serialize(session()->all()));
}

function nfssLimpar(array $bizs): void
{
    $ids = DB::table('nfe_fiscal_rules')
        ->whereIn('business_id', $bizs)
        ->where('ncm', 'like', NFSS_NCM_PREFIXO . '%')
        ->pluck('id');

    if (Schema::hasTable('nfe_revisoes_contador')) {
        DB::table('nfe_revisoes_contador')->whereIn('regra_id', $ids)->delete();
    }
    DB::table('nfe_fiscal_rules')->whereIn('id', $ids)->delete();

    foreach ($bizs as $biz) {
        File::deleteDirectory(ImportRegrasCsvConferencia::raizAbsoluta() . '/' . $biz);
    }
}

/** Arquivos de lote pendentes deste usuário nesta empresa. */
function nfssLotes(int $businessId, int $userId): array
{
    $pasta = ImportRegrasCsvConferencia::raizAbsoluta() . "/{$businessId}/{$userId}";

    return is_dir($pasta) ? glob($pasta . '/*.json') : [];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('MySQL com nfe_fiscal_rules (ADR 0358; ver nfebrasil-pest.yml).');
    }
    try {
        $this->biz = $this->seededTenant()->id;
        $this->outra = $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: ' . $e->getMessage());
    }

    nfssLimpar([$this->biz, $this->outra]);

    // O bridge SyncFiscalRuleToTaxRate tem cobertura própria; aqui só adicionaria ruído.
    Event::fake([
        \Modules\NfeBrasil\Events\FiscalRuleCreated::class,
        \Modules\NfeBrasil\Events\FiscalRuleUpdated::class,
        \Modules\NfeBrasil\Events\FiscalRuleDeleted::class,
    ]);

    $this->user = $this->usuarioComPermissoes(['nfe.tributacao.manage']);
    $this->actingAs($this->user);
    $this->withSession(['business.id' => $this->biz, 'user.business_id' => $this->biz]);
});

afterEach(function () {
    if (isset($this->biz, $this->outra)) {
        nfssLimpar([$this->biz, $this->outra]);
    }
});

it('UC-NFIM-14 · a sessão não cresce com o tamanho do CSV', function () {
    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfssArquivo(10)])
        ->assertRedirect()->assertSessionHasNoErrors();
    $pequeno = nfssTamanhoSessao();

    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfssArquivo(3000)])
        ->assertRedirect()->assertSessionHasNoErrors();
    $grande = nfssTamanhoSessao();

    // Pré-condição anti-vácuo, pelo resumo da conferência (vale com ou sem esta mudança): o preview
    // grande de fato conferiu as 3000 linhas — senão a sessão pequena seria por o CSV não ter sido lido.
    expect(session('preview.total_validas'))->toBe(3000);

    // 300× mais linhas e a sessão praticamente igual (os dois previews levam 10 amostras no flash).
    // Com as linhas na sessão, a diferença medida aqui era de centenas de KB.
    expect($grande - $pequeno)->toBeLessThan(200);
    expect(session()->has('nfe_import_csv_linhas'))->toBeFalse();
    expect(session(ImportRegrasCsvConferencia::SESSAO_REF))->toBeString();

    // As linhas foram para o arquivo, e o preview novo substituiu o pendente anterior.
    $lotes = nfssLotes($this->biz, $this->user->id);
    expect($lotes)->toHaveCount(1);
    expect(json_decode((string) file_get_contents($lotes[0]), true))->toHaveCount(3000);
});

it('UC-NFIM-14 · conferir e aplicar grava as mesmas linhas do CSV e apaga o lote', function () {
    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfssArquivo(3)])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(nfssLotes($this->biz, $this->user->id))->toHaveCount(1);
    expect(nfssContar($this->biz))->toBe(0); // UC-NFIM-01 segue: conferir não grava

    $this->post('/nfe-brasil/tributacao/import/aplicar')->assertRedirect()->assertSessionHasNoErrors();

    // Caminho 1: a contagem. Caminho 2: os valores lidos de volta batem com o CSV — o lote passou
    // por JSON no disco e as alíquotas não podem mudar no trajeto.
    expect(nfssContar($this->biz))->toBe(3);
    $regra = DB::table('nfe_fiscal_rules')
        ->where('business_id', $this->biz)->where('ncm', NFSS_NCM_PREFIXO . '0001')->first();
    expect((float) $regra->aliquota_icms)->toBe(0.18)
        ->and((float) $regra->aliquota_pis)->toBe(0.0065)
        ->and((float) $regra->aliquota_cofins)->toBe(0.03)
        ->and($regra->cfop)->toBe('5102')
        ->and($regra->csosn)->toBe('102');

    // O temporário some depois de aplicar, e a referência sai da sessão.
    expect(nfssLotes($this->biz, $this->user->id))->toBe([]);
    expect(session()->has(ImportRegrasCsvConferencia::SESSAO_REF))->toBeFalse();
});

it('UC-NFIM-14 · o JSON do lote devolve exatamente as linhas que o parse produziu', function () {
    $service = app(ImportRegrasCsvService::class);
    $linhas = $service->parse(nfssCsv(4))['linhas'];
    expect($linhas)->toHaveCount(4);

    $conf = app(ImportRegrasCsvConferencia::class);
    $ref = $conf->guardar($this->biz, $this->user->id, $linhas);

    expect($conf->ler($this->biz, $this->user->id, $ref))->toBe($linhas);
});

it('UC-NFIM-04 · lote conferido noutra empresa é recusado e descartado', function () {
    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfssArquivo(2)])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(nfssLotes($this->biz, $this->user->id))->toHaveCount(1);

    $this->withSession(['business.id' => $this->outra, 'user.business_id' => $this->outra]);
    $this->post('/nfe-brasil/tributacao/import/aplicar')->assertRedirect()->assertSessionHasErrors('arquivo');

    expect(nfssContar($this->outra))->toBe(0);
    expect(nfssContar($this->biz))->toBe(0);
    expect(nfssLotes($this->biz, $this->user->id))->toBe([]); // recusa também apaga o arquivo
});

it('UC-NFIM-04 · mesmo com o carimbo trocado, o lote de outra empresa não é achado', function () {
    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfssArquivo(2)])
        ->assertRedirect()->assertSessionHasNoErrors();

    // Simula o carimbo falhando: a sessão inteira passa a dizer "empresa 99", com a referência do
    // lote conferido na 98. A segunda barreira é o caminho do arquivo, montado com a empresa de
    // quem aplica.
    $this->withSession([
        'business.id' => $this->outra,
        'user.business_id' => $this->outra,
        ImportRegrasCsvConferencia::SESSAO_BUSINESS => $this->outra,
    ]);
    $this->post('/nfe-brasil/tributacao/import/aplicar')->assertRedirect()->assertSessionHasErrors('arquivo');

    expect(nfssContar($this->outra))->toBe(0);
});

it('UC-NFIM-02 · referência ausente ou forjada não grava nada', function () {
    $this->post('/nfe-brasil/tributacao/import/aplicar')->assertRedirect()->assertSessionHasErrors('arquivo');

    $this->withSession([
        ImportRegrasCsvConferencia::SESSAO_REF => '../../../99/1/x',
        ImportRegrasCsvConferencia::SESSAO_BUSINESS => $this->biz,
    ]);
    $this->post('/nfe-brasil/tributacao/import/aplicar')->assertRedirect()->assertSessionHasErrors('arquivo');

    expect(nfssContar($this->biz))->toBe(0);
});

it('UC-NFIM-14 · lote abandonado há mais de 24h é podado no próximo preview', function () {
    $conf = app(ImportRegrasCsvConferencia::class);
    $velho = $conf->guardar($this->outra, $this->user->id, [['ncm' => NFSS_NCM_PREFIXO . '9999']]);
    $caminhoVelho = ImportRegrasCsvConferencia::raizAbsoluta() . '/' . $conf->caminho($this->outra, $this->user->id, $velho);
    $recente = $conf->guardar($this->outra, $this->user->id + 1, [['ncm' => NFSS_NCM_PREFIXO . '9998']]);
    $caminhoRecente = ImportRegrasCsvConferencia::raizAbsoluta() . '/' . $conf->caminho($this->outra, $this->user->id + 1, $recente);

    // Envelhece DEPOIS de guardar o recente: quem tem de podar é o preview abaixo, não o guardar
    // deste próprio teste.
    touch($caminhoVelho, now()->subHours(ImportRegrasCsvConferencia::VALIDADE_HORAS + 1)->getTimestamp());
    clearstatcache();
    expect(file_exists($caminhoVelho))->toBeTrue();

    $this->post('/nfe-brasil/tributacao/import/preview', ['arquivo' => nfssArquivo(1)])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(file_exists($caminhoVelho))->toBeFalse();
    expect(file_exists($caminhoRecente))->toBeTrue(); // lote dentro da validade de outro usuário fica
});
