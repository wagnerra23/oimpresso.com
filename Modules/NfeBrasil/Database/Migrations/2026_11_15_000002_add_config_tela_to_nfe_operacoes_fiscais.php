<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naturezas de operação, onda 3 (RUNBOOK-tributacao §Naturezas): `config_tela` guarda os tributos
 * como a tela os edita — códigos e a ORIGEM de cada alíquota (pela UF, TIPI, regime, fixa, nota de
 * origem), no formato do protótipo aprovado.
 *
 * Coluna separada de `regra_geral` de propósito: `regra_geral` é lida pelo motor e guarda números.
 * Gravar a forma da tela ali mudaria imposto antes da onda 4, que é onde o motor passa a ler a origem
 * com prova de valor. Coluna nova, nula, nenhuma linha muda (append-only, ADR 0093 G8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfe_operacoes_fiscais', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_operacoes_fiscais', 'config_tela')) {
                $table->json('config_tela')->nullable()->after('regra_geral')
                    ->comment('tributos como a tela edita (origem das alíquotas); o motor lê a partir da onda 4');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfe_operacoes_fiscais', function (Blueprint $table) {
            if (Schema::hasColumn('nfe_operacoes_fiscais', 'config_tela')) {
                $table->dropColumn('config_tela');
            }
        });
    }
};
