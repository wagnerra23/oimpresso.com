<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Knowledge-indexing (2026-07-22, ADR 0053) — adiciona 2 tipos ao ENUM
 * mcp_memory_documents.type pra que o conhecimento GERADO entre no corpus de
 * busca da IA com metadado íntegro (filtro por `type` é o #1 ROI de descoberta,
 * estado-da-arte 2026-07-21):
 *
 *   - 'surface'  → memory/requisitos/<Mod>/SUPERFICIE.md (gerado por
 *                  scripts/governance/module-surface.mjs). Resposta à dor [W]
 *                  "não sei os arquivos de cada contexto". O gerador escreve no
 *                  disco + CI, mas o indexador não coletava → cego pra busca.
 *   - 'briefing' → memory/requisitos/<Mod>/BRIEFING.md. O IndexarMemoryGitParaDb
 *                  insere type='briefing' desde 2026-07-04, MAS o valor nunca
 *                  esteve no enum. Com `strict => false` (config/database.php) o
 *                  MySQL grava '' (string vazia) + warning 1265 em vez de erro,
 *                  então os BRIEFINGs entram no corpus com type='' → o filtro
 *                  `type=briefing` (KbAnswer/decisions-search) nunca casa.
 *
 * Retro-fix incluído: re-tipa os BRIEFINGs já corrompidos (type='' → 'briefing').
 * Só roda DEPOIS do MODIFY (senão o UPDATE re-gravaria '' pelo mesmo motivo).
 *
 * Idempotente: MODIFY seta o mesmo enum em re-run; o UPDATE é WHERE type='' scoped.
 * mcp_memory_documents_history não tem coluna type (snapshot só content/title/meta).
 */
return new class extends Migration
{
    private const ENUM_NOVO = "'adr','session','reference','spec','handoff','current','tasks','other','comparativo','audit','runbook','changelog','briefing','surface'";

    private const ENUM_ANTIGO = "'adr','session','reference','spec','handoff','current','tasks','other','comparativo','audit','runbook','changelog'";

    public function up(): void
    {
        DB::statement('ALTER TABLE mcp_memory_documents MODIFY COLUMN type ENUM(' . self::ENUM_NOVO . ') NOT NULL');

        // Retro-fix: BRIEFINGs indexados com type='' (enum inválido em non-strict)
        // desde 2026-07-04. Slug canônico `briefing:<Modulo>` (IndexarMemoryGitParaDb).
        DB::table('mcp_memory_documents')
            ->where('slug', 'like', 'briefing:%')
            ->where('type', '')
            ->update(['type' => 'briefing']);
    }

    public function down(): void
    {
        // Reverte rows dos tipos novos pra 'other' antes de encolher o enum,
        // senão o MODIFY os truncaria pra '' (mesma armadilha non-strict).
        DB::table('mcp_memory_documents')
            ->whereIn('type', ['briefing', 'surface'])
            ->update(['type' => 'other']);

        DB::statement('ALTER TABLE mcp_memory_documents MODIFY COLUMN type ENUM(' . self::ENUM_ANTIGO . ') NOT NULL');
    }
};
