<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E5 da deprecação do Modules/SRS — drop das 7 tabelas `docs_*` (ADR 0357).
 *
 * ## Por que é seguro
 *
 * Medido em PRODUÇÃO em 2026-07-29 (`APP_ENV=live`, database `u906587222_oimpresso`,
 * 392 tabelas), com controle positivo provando que era o banco vivo — `business=82`,
 * `transactions=75.254`, ROTA LIVRE `biz=4` com 21.029 vendas até 2026-07-28:
 *
 *   docs_sources          0      docs_links             0
 *   docs_evidences        0      docs_chat_messages     0
 *   docs_requirements     0      docs_validation_runs   0
 *   docs_pages           14   <- seed único de 2026-04-26 apontando pra rotas
 *                                que já não existem (`/docs/*`, `/copiloto/*`)
 *
 * Também medido no `information_schema` do banco vivo:
 *   - FKs SAINDO das docs_*  : NENHUMA
 *   - FKs ENTRANDO nas docs_*: NENHUMA  → nada de fora depende; o DROP não é bloqueado
 *   - triggers nas docs_*    : NENHUM   → não há imutabilidade append-only a preservar
 *
 * Os riscos Tier 0 que o DEPRECATION-PLAN previa **não existem**: sem linhas em
 * `docs_chat_messages` não há PII pra redigir (R1/LGPD Art. 16); sem linhas nas
 * tenant-scoped não há o que vazar entre business (R2); sem linhas em
 * `docs_evidences` não há FULLTEXT pra reconstruir em `mcp_memory_documents` (R4).
 *
 * ## Ordem
 *
 * Sem FK declarada, a ordem é indiferente pro banco. Mantida a ordem lógica
 * (dependente antes do dependido) por clareza de leitura.
 *
 * ## Rollback
 *
 * `down()` recria a estrutura vazia lendo o DDL canônico de
 * `database/schema/mysql-schema.sql` — não duplica definição à mão, então não
 * pode divergir do baseline. Dados não voltam (eram 0 linhas + um seed morto).
 * Rollback completo do E5 exige também restaurar o código de `Modules/SRS/`,
 * que migration nenhuma faz — é `git revert` do PR.
 *
 * @see memory/decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md
 * @see memory/requisitos/SRS/DEPRECATION-PLAN.md §Reconciliação 2026-07-29
 */
return new class extends Migration
{
    /** Ordem lógica: dependente → dependido (não há FK; é clareza, não exigência). */
    private const TABELAS = [
        'docs_links',            // pivot evidence × requirement
        'docs_evidences',        // apontava (logicamente) pra docs_sources
        'docs_requirements',
        'docs_sources',
        'docs_chat_messages',
        'docs_pages',
        'docs_validation_runs',
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
                . 'O DDL das tabelas docs_* vive lá; sem ele não há como recriar a estrutura.'
            );
        }

        $sql = (string) file_get_contents($baseline);

        // Recria na ordem inversa do drop (dependido → dependente).
        foreach (array_reverse(self::TABELAS) as $tabela) {
            if (Schema::hasTable($tabela)) {
                continue; // idempotente: já existe, não recria
            }

            $ddl = $this->extrairCreateTable($sql, $tabela);

            if ($ddl === null) {
                throw new \RuntimeException(
                    "Rollback impossível: não achei o CREATE TABLE de `{$tabela}` em "
                    . self::SCHEMA_BASELINE . '.'
                );
            }

            DB::statement($ddl);
        }
    }

    /**
     * Extrai o bloco `CREATE TABLE ... ) ENGINE=...;` do dump baseline.
     *
     * Busca por índice, não por regex: o dump tem diretivas `/*!40101 ... *\/`
     * entre os blocos que tornam um regex ganancioso frágil.
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
