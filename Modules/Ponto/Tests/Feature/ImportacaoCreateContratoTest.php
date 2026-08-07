<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do ENVIO de arquivo AFD/AFDT (`/ponto/importacoes/novo` → POST).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Importacoes/Create.casos.md → UC-IMPCRE-01..02
 *
 * Os UC derivam do SDD §6.4 (CU-PONTO-10) + US-PONTO-002 + Portaria MTP 671/2021
 * Anexo I + ADR 0093 (storage segregado). NÃO do `.tsx`.
 *
 * ── Não duplica as telas irmãs ─────────────────────────────────────────────
 * UC-IMPSHOW-01 prova o EFEITO da dedup (não duplicou marcação); UC-IMPSHOW-02, a
 * dedup escopada ao business. Aqui: o FEEDBACK ao operador (foi recusado e ele acha
 * o original) e ONDE o arquivo é guardado. Uma dedup silenciosa que aceita o upload
 * e não faz nada passaria naqueles e falharia aqui.
 *
 * ── Por que Storage::fake ──────────────────────────────────────────────────
 * O `store()` grava no disco `local`. Sem fake, cada corrida deixaria arquivo no
 * storage real da lane. O fake também é o que permite ASSERTAR o caminho, que é o
 * objeto do UC-IMPCRE-02.
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101). Sem
 * RefreshDatabase: a lane ponto-pest proíbe.
 *
 * @see \Modules\Ponto\Http\Controllers\ImportacaoController::store
 */

const IMPCRE_MARCADOR = 'SDD-IMPCRE-CONTRATO';

function impCrePrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * Arquivo AFD de conteúdo FIXO — o hash tem de ser o mesmo entre as duas chamadas do
 * UC-IMPCRE-01, senão a dedup (que é por sha256 do conteúdo) não teria o que casar.
 */
function impCreArquivo(string $nome): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $nome,
        "000000001ACJEF FIXTURE SDD CONTRATO — conteudo fixo pra hash estavel\n"
    );
}

afterEach(function () {
    try {
        DB::table('ponto_importacoes')
            ->where('nome_arquivo', 'like', IMPCRE_MARCADOR . '%')
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

// =====================================================================
// Importacoes/Create — o envio
// =====================================================================

it('UC-IMPCRE-01 · reenviar arquivo já importado é recusado identificando o original', function () {
    $this->actAsAdmin();
    impCrePrecisaDe(['ponto_importacoes']);
    Storage::fake('local');

    $nome = IMPCRE_MARCADOR . '-dedup.txt';

    // 1º envio: entra.
    $this->post('/ponto/importacoes', [
        'tipo'    => 'AFD',
        'arquivo' => impCreArquivo($nome),
    ]);

    $depoisDoPrimeiro = DB::table('ponto_importacoes')
        ->where('nome_arquivo', 'like', IMPCRE_MARCADOR . '%')
        ->count();

    // Pré-condição anti-vácuo: se o 1º envio não entrou, "o 2º foi recusado" seria
    // verdade por nada ter funcionado (proibicoes.md §5 2026-07-24 LC-13).
    expect($depoisDoPrimeiro)->toBe(1,
        'O primeiro envio tem de registrar a importação — senão o caso não exerce dedup.'
    );

    // 2º envio: MESMO conteúdo → mesmo sha256 → tem de ser recusado.
    $resp = $this->post('/ponto/importacoes', [
        'tipo'    => 'AFD',
        'arquivo' => impCreArquivo($nome),
    ]);

    // "Não é sucesso" — não um status cravado: trocar o back()->withErrors por outro
    // mecanismo de recusa é correção legítima.
    expect($resp->isSuccessful())->toBeFalse(
        'Reenviar o mesmo arquivo tem de ser RECUSADO (CU-PONTO-10 · US-PONTO-002 '
        . '"importação idempotente").'
    );

    $depoisDoSegundo = DB::table('ponto_importacoes')
        ->where('nome_arquivo', 'like', IMPCRE_MARCADOR . '%')
        ->count();

    expect($depoisDoSegundo)->toBe(1,
        'O reenvio NÃO pode criar uma segunda importação — duplicar arquivo duplicaria a '
        . 'jornada de todo o período.'
    );

    // O operador precisa conseguir achar a original: a recusa cita o registro anterior.
    $erros = session('errors');
    expect($erros)->not->toBeNull('A recusa tem de chegar ao operador como erro de formulário.');
    expect($erros->first('arquivo'))->not->toBeEmpty(
        'A mensagem de recusa tem de identificar a importação original — sem isso o RH não '
        . 'sabe se o arquivo entrou antes ou se algo quebrou.'
    );
});

it('UC-IMPCRE-02 · o arquivo enviado fica guardado em área do meu empregador', function () {
    $this->actAsAdmin();
    impCrePrecisaDe(['ponto_importacoes']);
    Storage::fake('local');

    $this->post('/ponto/importacoes', [
        'tipo'    => 'AFD',
        'arquivo' => impCreArquivo(IMPCRE_MARCADOR . '-tenant.txt'),
    ]);

    $imp = DB::table('ponto_importacoes')
        ->where('nome_arquivo', 'like', IMPCRE_MARCADOR . '%')
        ->first();

    expect($imp)->not->toBeNull('A importação tem de ser registrada para o caso exercer algo.');
    expect($imp->arquivo_path)->not->toBeEmpty('O caminho do arquivo tem de ser gravado.');

    // Contém o id do MEU business — não a string literal inteira: reorganizar a árvore
    // de storage é legítimo; misturar tenants não é.
    $this->assertStringContainsString(
        (string) $this->business->id,
        $imp->arquivo_path,
        'O arquivo tem de ficar em área do MEU empregador. Este é o único isolamento Tier 0 '
        . 'do módulo que NÃO é defendido por global scope — é uma string de path montada à '
        . 'mão no controller, e nenhum scope Eloquent pega se ela virar diretório único '
        . '(ADR 0093 · Portaria 671/2021 Anexo I).'
    );
});
