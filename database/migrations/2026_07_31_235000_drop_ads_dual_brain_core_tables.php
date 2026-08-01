<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E5 da deprecação do Modules/ADS — drop do núcleo dual-brain (ADR 0363).
 *
 * ## O que sai, e o que NÃO sai
 *
 * O módulo criou **11** tabelas. Esta migration dropa **5**. As outras **6** ficam
 * porque ganharam consumidor vivo FORA do ADS quando Forja e Governance herdaram
 * peças — dropá-las converteria tela viva em `SQLSTATE 42S02` → 500:
 *
 *   FICA  mcp_decision_links      Forja/Services/DecisionLinksService (escreve a cada decompose)
 *   FICA  mcp_governance_rules    Modules/Governance é o dono agora (ADR 0363 §1)
 *   FICA  mcp_projects            Forja/Services/ProjectService (insertGetId)
 *   FICA  mcp_project_parts       Forja/Services/ProjectDecomposerService
 *   FICA  mcp_tool_executions     Forja/Http/Controllers/Admin/ToolsController (INSERT por execução)
 *   FICA  mcp_user_module_access  Forja/Services/UserScopeService (updateOrInsert)
 *
 * As duas últimas são achado desta etapa — o plano as marcava "DROP (0 linhas)"
 * porque o consumidor de então (TeamMcp) morreu, mas `UserScopeService` e
 * `ToolsController` foram pra Forja no PR #5131/#5132 e alimentam duas das quatro
 * rotas que o smoke da parte 6 registrou VIVAS (302): `/ads/admin/tools` e
 * `/ads/admin/team-scopes`. São a 4ª e a 5ª instância do padrão que a errata C5
 * do plano nomeou: *antes de dropar tabela do ADS, procurar o consumidor fora dele*.
 *
 * ## Por que é seguro (medido em PRODUÇÃO, 2026-07-31, `APP_ENV=live`, `u906587222_oimpresso`)
 *
 * Controle positivo provando que era o banco vivo: `business=83`, `users=125`.
 *
 *   mcp_dual_brain_decisions  36.986 linhas (87,75 MB)  <- ARCHIVE feito, ver abaixo
 *   mcp_decision_thresholds        1 linha  (config)    <- ARCHIVE feito
 *   mcp_confidence_scores          0
 *   mcp_decision_patterns          0
 *   mcp_file_locks                 0
 *
 * No `information_schema` do mesmo banco:
 *   - FKs ENTRANDO nas 5 : NENHUMA  → nada de fora depende; o DROP não é bloqueado
 *   - FKs SAINDO das 5   : 2, ambas `business_id -> business`, removidas junto com a tabela
 *   - triggers           : NENHUM   → não há imutabilidade append-only a preservar
 *   - views              : NENHUMA cita as 5
 *
 * A escrita está PARADA desde `2026-07-31 15:50:26` — os 5 crons e o daemon
 * `ads-brain-a` do CT 100 foram desligados no PR #5127, com smoke real.
 *
 * ## O dado foi arquivado ANTES (é o que torna o DROP aceitável)
 *
 * Dump em `/root/archive/ads-2026-07-31/` no **CT 100**, nunca em git (regra do
 * DEPRECATION-PLAN — 87,75 MB). Conferido nas duas pontas por SHA-256 e por
 * contagem de INSERT dentro do artefato:
 *
 *   ads-mcp_dual_brain_decisions-FULL-2026-07-31.sql.gz        36.986 linhas
 *   ads-mcp_dual_brain_decisions-resolved_by-2026-07-31.sql         41 linhas
 *   ads-mcp_decision_thresholds-2026-07-31.sql                       1 linha
 *
 * As **41** são as que tiveram decisão humana (`resolved_by` preenchido) — a fração
 * que a ADR 0363 mandou preservar nominalmente. Arquivamos a tabela inteira mesmo
 * assim: o DROP é irreversível e 10 MB comprimidos são mais baratos que uma perda
 * mal medida. Ver `MANIFEST.md` ao lado dos dumps.
 *
 * ## Rollback
 *
 * `down()` recria a estrutura VAZIA lendo o DDL de `database/schema/mysql-schema.sql`,
 * em vez de duplicar a definição à mão — assim não pode divergir do baseline.
 * **Os dados não voltam pelo `down()`**: quem quiser as 36.986 linhas restaura o dump
 * do CT 100 (`zcat …FULL….sql.gz | mysql <database>`). E o `down()` deixa de
 * funcionar no dia em que o baseline for regenerado por `schema:dump` sem estas
 * tabelas — limitação herdada do precedente do SRS, registrada aqui em vez de
 * descoberta depois.
 *
 * @see memory/decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md
 * @see memory/requisitos/ADS/DEPRECATION-PLAN.md §Roadmap E5 + errata 2026-07-31 (parte 6)
 * @see database/migrations/2026_07_29_160000_drop_docs_tables_srs_deprecacao.php (precedente SRS)
 */
return new class extends Migration
{
    /**
     * Só o núcleo dual-brain. Sem FK entre elas, então a ordem é indiferente pro
     * banco; mantida a ordem lógica (dependente → dependido) por clareza.
     */
    private const TABELAS = [
        'mcp_confidence_scores',     // score por decisão
        'mcp_decision_patterns',     // padrões aprendidos pelo PatternLearning
        'mcp_dual_brain_decisions',  // a fila — 36.986 linhas, arquivada
        'mcp_decision_thresholds',   // config do DecisionRouter — 1 linha, arquivada
        'mcp_file_locks',            // mutex por arquivo entre Brain A/B do daemon
    ];

    private const SCHEMA_BASELINE = 'database/schema/mysql-schema.sql';

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }

    public function down(): void
    {
        $baseline = base_path(self::SCHEMA_BASELINE);

        if (! is_file($baseline)) {
            throw new \RuntimeException(
                'Rollback impossível: ' . self::SCHEMA_BASELINE . ' não encontrado. '
                . 'O DDL destas tabelas vive lá (as migrations do ADS saíram do repo '
                . 'no PR #5135); sem ele não há como recriar a estrutura.'
            );
        }

        $sql = (string) file_get_contents($baseline);

        foreach (array_reverse(self::TABELAS) as $tabela) {
            if (Schema::hasTable($tabela)) {
                continue; // idempotente: já existe, não recria
            }

            $ddl = $this->extrairCreateTable($sql, $tabela);

            if ($ddl === null) {
                throw new \RuntimeException(
                    "Rollback impossível: não achei o CREATE TABLE de `{$tabela}` em "
                    . self::SCHEMA_BASELINE . '. Se o baseline foi regenerado depois do '
                    . 'DROP, a estrutura só existe no dump do CT 100.'
                );
            }

            DB::statement($ddl);
        }
    }

    /**
     * Extrai o bloco `CREATE TABLE ... ) ENGINE=...;` do dump baseline.
     *
     * Busca por índice, não por regex: o dump tem diretivas `/*!40101 ... *\/` entre
     * os blocos que tornam um regex ganancioso frágil.
     */
    private function extrairCreateTable(string $sql, string $tabela): ?string
    {
        $inicio = strpos($sql, "CREATE TABLE `{$tabela}` (");
        if ($inicio === false) {
            return null;
        }

        $engine = strpos($sql, ') ENGINE=', $inicio);
        if ($engine === false) {
            return null;
        }

        $fim = strpos($sql, ';', $engine);
        if ($fim === false) {
            return null;
        }

        return substr($sql, $inicio, $fim - $inicio);
    }
};
