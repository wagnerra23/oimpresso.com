<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0175 — Fix arquitetural Observer Financeiro:
 * permite baixa + caixa movimento sem fin_contas_bancarias cadastrada.
 *
 * Remove dependência implícita do Observer (TituloAutoService::registrarPagamento)
 * com fin_contas_bancarias. Cliente pode lançar pagamento via Sells UltimatePOS
 * sem precisar cadastrar conta bancária no Financeiro antes (cenário PME que opera
 * só PIX/dinheiro — caso real Larissa biz=4 ROTA LIVRE, sessão 2026-05-20).
 *
 * @see memory/decisions/0175-fix-observer-conta-bancaria-opcional.md
 * @see memory/requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md
 * @see memory/reference/feedback-fin-bridge-no-op-account-gap.md
 */
return new class extends Migration
{
    public function up(): void
    {
        // ALTER fin_titulo_baixas.conta_bancaria_id NULLABLE
        // ALGORITHM=INSTANT em MariaDB 11.8 — sem lock contention.
        Schema::table('fin_titulo_baixas', function (Blueprint $table) {
            $table->unsignedInteger('conta_bancaria_id')->nullable()->change();
        });

        // ALTER fin_caixa_movimentos.conta_bancaria_id NULLABLE
        // Necessário em paralelo — CaixaMovimento é criado lado-a-lado com TituloBaixa.
        Schema::table('fin_caixa_movimentos', function (Blueprint $table) {
            $table->unsignedInteger('conta_bancaria_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reverte pra NOT NULL — mas só funciona se zero rows tiverem conta_bancaria_id=NULL.
        // Caller é responsável por backfill manual antes de rollback:
        //   UPDATE fin_titulo_baixas SET conta_bancaria_id = <stub_default_per_biz> WHERE conta_bancaria_id IS NULL;
        //   UPDATE fin_caixa_movimentos SET conta_bancaria_id = <stub_default_per_biz> WHERE conta_bancaria_id IS NULL;
        Schema::table('fin_titulo_baixas', function (Blueprint $table) {
            $table->unsignedInteger('conta_bancaria_id')->nullable(false)->change();
        });

        Schema::table('fin_caixa_movimentos', function (Blueprint $table) {
            $table->unsignedInteger('conta_bancaria_id')->nullable(false)->change();
        });
    }
};
