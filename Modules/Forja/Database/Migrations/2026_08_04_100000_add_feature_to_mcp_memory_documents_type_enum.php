<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona `'feature'` ao ENUM `mcp_memory_documents.type` para que o **trio de
 * feature** entre no acervo que alimenta `/documentacao`:
 *
 *   memory/requisitos/<Mod>/features/<slug>/{requirements,plan,tasks}.md
 *
 * (proposal `feature-trio-requirements-plan-tasks.md`, 2026-07-09 — o degrau
 * "spec por feature" entre a US do SPEC e a task do MCP).
 *
 * ## Por que isto vem ANTES da coleta, no mesmo PR
 *
 * `config/database.php` roda com `strict => false`. Nesse modo o MySQL **não** dá erro
 * ao gravar valor fora do enum: grava `''` (string vazia) + warning 1265. Foi
 * exatamente assim que os BRIEFINGs entraram no índice com `type=''` por 18 dias e o
 * filtro `type=briefing` nunca casou (migration 2026_07_22_100000). Indexar o trio de
 * feature sem expandir o enum repetiria o mesmo bug, em silêncio.
 *
 * ## O NOME deste arquivo é load-bearing
 *
 * `DocumentacaoRouteTest` resolve o enum vigente varrendo
 * `Modules/Jana/Database/Migrations/*mcp_memory_documents*.php`. As duas migrations
 * anteriores de enum (`*_to_mcp_type_enum.php`) ficam FORA desse glob — o teste seguia
 * verde só porque nenhum tipo delas tinha entrado em `TIPOS_DOC`. `feature` entra,
 * então o nome aqui inclui `mcp_memory_documents` de propósito.
 *
 * Idempotente: `MODIFY` seta o mesmo enum em re-run. `mcp_memory_documents_history`
 * não tem coluna `type` (snapshot só content/title/meta), então nada a fazer lá.
 *
 * @see Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb::coletarArquivos() (glob aditivo)
 * @see App\Http\Controllers\DocumentacaoController::TIPOS_DOC (o outro lado do par)
 * @see Modules/Jana/Database/Migrations/2026_08_02_100000_add_charter_casos_to_mcp_type_enum.php
 */
return new class extends Migration
{
    private const ENUM_NOVO = "'adr','session','reference','spec','handoff','current','tasks','other','comparativo','audit','runbook','changelog','briefing','surface','charter','casos','feature'";

    private const ENUM_ANTIGO = "'adr','session','reference','spec','handoff','current','tasks','other','comparativo','audit','runbook','changelog','briefing','surface','charter','casos'";

    public function up(): void
    {
        DB::statement('ALTER TABLE mcp_memory_documents MODIFY COLUMN type ENUM(' . self::ENUM_NOVO . ') NOT NULL');

        // Retro-fix defensivo: se alguma coleta anterior tiver gravado o trio de feature
        // com type='' (o modo de falha non-strict acima), re-tipa pelo slug canônico.
        // Em banco limpo isto não afeta nenhuma linha — é seguro por construção.
        DB::table('mcp_memory_documents')->where('type', '')->where('slug', 'like', 'feature-%')->update(['type' => 'feature']);
    }

    public function down(): void
    {
        // Reverte pra 'other' ANTES de encolher o enum — senão o MODIFY trunca pra ''
        // (a mesma armadilha non-strict que motivou esta migration).
        DB::table('mcp_memory_documents')
            ->where('type', 'feature')
            ->update(['type' => 'other']);

        DB::statement('ALTER TABLE mcp_memory_documents MODIFY COLUMN type ENUM(' . self::ENUM_ANTIGO . ') NOT NULL');
    }
};
