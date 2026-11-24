<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria RecurringBilling b6 (2026-11-19; decisão técnica registrada em 2026-11-23): apagar um
 * contato apagava em cascata as assinaturas e as faturas dele — e, pelas assinaturas, as notas, a
 * timeline e os favoritos. Apagar um plano fazia o mesmo com as assinaturas do plano. Num ERP o
 * dado financeiro tem dever de guarda (lápide 2026-07-27 em memory/proibicoes.md): a FK passa a
 * RECUSAR a exclusão (ON DELETE RESTRICT) em vez de levar as faturas junto.
 *
 * Alcance: `contacts` e `rb_plans` usam soft delete; as telas marcam `deleted_at` e não disparam
 * a FK. O RESTRICT morde no hard delete (SQL direto, forceDelete, limpeza manual). A tela de
 * contato já recusa a exclusão com assinatura ou fatura (ContactController::vinculosDoContato).
 *
 * Idempotente: FK que já está RESTRICT não é recriada. Tabela com linha órfã não ganha a FK nova
 * (o ALTER falharia e travaria o deploy) — fica com a antiga e o caso sai no log. Produção medida
 * em 2026-11-23: 0 órfãos nas três colunas. Só MySQL: o SQLite dos testes não altera FK existente.
 */
return new class extends Migration
{
    /** [tabela, coluna, nome da FK, tabela referenciada] */
    private const FKS = [
        ['rb_subscriptions', 'contact_id', 'rb_subs_contact_fk', 'contacts'],
        ['rb_invoices', 'contact_id', 'rb_invoices_contact_fk', 'contacts'],
        ['rb_subscriptions', 'plan_id', 'rb_subscriptions_plan_id_foreign', 'rb_plans'],
    ];

    public function up(): void
    {
        $this->trocar('restrict');
    }

    public function down(): void
    {
        $this->trocar('cascade');
    }

    private function trocar(string $regra): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::FKS as [$tabela, $coluna, $fk, $ref]) {
            if (! Schema::hasTable($tabela) || ! Schema::hasTable($ref)) {
                continue;
            }

            $atual = $this->fkDaColuna($tabela, $coluna);
            if ($atual !== null && strtolower((string) ($atual['on_delete'] ?? '')) === $regra) {
                continue;
            }

            $orfaos = DB::table($tabela)->whereNotNull($coluna)
                ->whereNotIn($coluna, DB::table($ref)->select('id'))->count();
            if ($orfaos > 0) {
                Log::warning('rb.fk_restrict.orfaos', ['tabela' => $tabela, 'coluna' => $coluna, 'linhas' => $orfaos]);
                fwrite(STDERR, "  ! {$tabela}.{$coluna}: {$orfaos} linha(s) órfã(s) — FK não alterada\n");

                continue;
            }

            Schema::table($tabela, function (Blueprint $t) use ($atual, $coluna, $fk, $ref, $regra) {
                if ($atual !== null) {
                    $t->dropForeign($atual['name']);
                }
                $nova = $t->foreign($coluna, $fk)->references('id')->on($ref);
                $regra === 'restrict' ? $nova->restrictOnDelete() : $nova->cascadeOnDelete();
            });
        }
    }

    /** @return array<string, mixed>|null */
    private function fkDaColuna(string $tabela, string $coluna): ?array
    {
        foreach (Schema::getForeignKeys($tabela) as $chave) {
            if (($chave['columns'] ?? null) === [$coluna]) {
                return $chave;
            }
        }

        return null;
    }
};
