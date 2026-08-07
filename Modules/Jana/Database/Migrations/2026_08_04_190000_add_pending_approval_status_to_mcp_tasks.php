<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR 0368 §3 — estado de espera por DECISÃO HUMANA, distinto de `blocked`.
 *
 * O funil de admissão de feature (pesquisa propõe → [W] admite ou recusa) não tinha onde
 * guardar a espera: o enum ia de `backlog` a `cancelled` sem um estado que dissesse
 * "isto aguarda uma pessoa". O que se usava era o proxy `blocked` + `owner: wagner`, que
 * mistura duas coisas de ação oposta — "alguém precisa decidir" some quando [W] responde;
 * "travado por dependência" some quando a dependência resolve. Com as duas no mesmo balde,
 * nenhum relatório conseguia dizer o que de fato esperava por uma pessoa.
 *
 * ADITIVA por construção: `pending_approval` entra no FIM do enum, então nenhum registro
 * existente muda de valor e a ordem dos demais é preservada (reordenar forçaria o MySQL a
 * reescrever a tabela — 1.124 linhas em prod).
 */
class AddPendingApprovalStatusToMcpTasks extends Migration
{
    private const ENUM_NOVO = "ENUM('backlog','todo','doing','review','done','blocked','cancelled','pending_approval')";

    private const ENUM_ANTIGO = "ENUM('backlog','todo','doing','review','done','blocked','cancelled')";

    public function up(): void
    {
        DB::statement('ALTER TABLE mcp_tasks MODIFY status ' . self::ENUM_NOVO . " NOT NULL DEFAULT 'todo'");
    }

    public function down(): void
    {
        // Encolher enum com linha usando o valor removido faria o MySQL truncar para ''
        // em silêncio (ou falhar em modo estrito). Devolve ao balde de onde veio — `blocked`
        // era o proxy antes desta migration, então o rollback restaura o comportamento
        // anterior em vez de perder a informação de que aquela task esperava alguém.
        DB::table('mcp_tasks')->where('status', 'pending_approval')->update(['status' => 'blocked']);

        DB::statement('ALTER TABLE mcp_tasks MODIFY status ' . self::ENUM_ANTIGO . " NOT NULL DEFAULT 'todo'");
    }
}
