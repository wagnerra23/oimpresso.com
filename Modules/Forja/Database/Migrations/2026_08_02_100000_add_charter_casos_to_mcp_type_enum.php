<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B3 do Plano B (proposal 2026-08-01) — adiciona 2 tipos ao ENUM
 * `mcp_memory_documents.type` para que o **trio de tela** entre no corpus de busca
 * da IA sem sair do lugar onde mora:
 *
 *   - 'charter' → resources/js/Pages/<Mod>/<Tela>.charter.md  (a lei da tela)
 *   - 'casos'   → resources/js/Pages/<Mod>/<Tela>.casos.md    (o contrato UC)
 *
 * ## Por que isto vem ANTES da coleta, no mesmo PR
 *
 * `config/database.php` roda com `strict => false`. Nesse modo o MySQL **não** dá erro
 * ao gravar valor fora do enum: grava `''` (string vazia) + warning 1265. Foi
 * exatamente assim que os BRIEFINGs entraram no índice com `type=''` por 18 dias e o
 * filtro `type=briefing` nunca casou (migration 2026_07_22_100000). Indexar charter
 * e casos sem expandir o enum repetiria o mesmo bug, em silêncio.
 *
 * ## O que isto NÃO faz
 *
 * Não move arquivo. A [ADR 0364] queria levar o trio pra `memory/_telas/` justamente
 * porque o indexador só varre `memory/**`; a reversão (Opção B, [F] 2026-08-01
 * *"eu quero como no fonte"*) mantém o trio colado ao `.tsx` — e este enum + o glob
 * aditivo em `IndexarMemoryGitParaDb` são o que torna a Opção B viável sem perder RAG.
 *
 * Idempotente: `MODIFY` seta o mesmo enum em re-run. `mcp_memory_documents_history`
 * não tem coluna `type` (snapshot só content/title/meta), então nada a fazer lá.
 *
 * @see memory/decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md (B3)
 * @see Modules/Forja/Database/Migrations/2026_07_22_100000_add_briefing_surface_to_mcp_type_enum.php
 */
return new class extends Migration
{
    private const ENUM_NOVO = "'adr','session','reference','spec','handoff','current','tasks','other','comparativo','audit','runbook','changelog','briefing','surface','charter','casos'";

    private const ENUM_ANTIGO = "'adr','session','reference','spec','handoff','current','tasks','other','comparativo','audit','runbook','changelog','briefing','surface'";

    public function up(): void
    {
        DB::statement('ALTER TABLE mcp_memory_documents MODIFY COLUMN type ENUM(' . self::ENUM_NOVO . ') NOT NULL');

        // Retro-fix defensivo: se alguma coleta anterior tiver gravado charter/casos
        // com type='' (o modo de falha non-strict acima), re-tipa pelo slug canônico.
        // Em banco limpo isto não afeta nenhuma linha — é seguro por construção.
        DB::table('mcp_memory_documents')->where('type', '')->where('slug', 'like', 'charter:%')->update(['type' => 'charter']);
        DB::table('mcp_memory_documents')->where('type', '')->where('slug', 'like', 'casos:%')->update(['type' => 'casos']);
    }

    public function down(): void
    {
        // Reverte pra 'other' ANTES de encolher o enum — senão o MODIFY trunca pra ''
        // (a mesma armadilha non-strict que motivou esta migration).
        DB::table('mcp_memory_documents')
            ->whereIn('type', ['charter', 'casos'])
            ->update(['type' => 'other']);

        DB::statement('ALTER TABLE mcp_memory_documents MODIFY COLUMN type ENUM(' . self::ENUM_ANTIGO . ') NOT NULL');
    }
};
