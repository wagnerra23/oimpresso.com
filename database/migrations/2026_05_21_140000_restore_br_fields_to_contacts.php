<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore campos fiscais BR perdidos no upgrade UPOS 6.4 -> 6.7
 *
 * Investigacao em memory/sessions/2026-05-21-investigar-campos-br-cliente.md
 *   - Baseline v3.7: commit 7ab688162 ("Versao 3.7 Original com as modificacoes brasil")
 *   - Upgrade UPOS 6.4->6.7: commits 62e66cad7 / ad58b9907 / f9930aa37
 *   - 4 campos v3.7 ainda existem em prod (mas dropam em deploy limpo): cpf_cnpj, consumidor_final, contribuinte, regime
 *   - 1 migration orfa: 2022_12_23_150311_is_sincronizado_contacts (removida do tree)
 *   - 1 migration quebrada: 2021_01_26_155423_add_regime_table_contacts (usa after('contribuinte') -- coluna que pode nao existir)
 *
 * Esta migration e IDEMPOTENTE -- Schema::hasColumn check antes de cada add.
 * Em prod (que ja tem alguns campos v3.7) so adiciona o que falta.
 * Em dev limpo (sem campos v3.7) adiciona todos.
 *
 * LGPD: cpf_cnpj e PII. NAO entra em activity_log.properties (ja respeitado em
 * app/Contact.php logOnly). Tambem nao deve aparecer em log de aplicacao plain
 * text -- usar accessor mascarado (maskTaxNumber existente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Identidade fiscal -- canon BR. v3.7 ja tinha.
            if (! Schema::hasColumn('contacts', 'cpf_cnpj')) {
                $table->string('cpf_cnpj', 20)->nullable()->index();
            }

            // RG -- documento PF
            if (! Schema::hasColumn('contacts', 'rg')) {
                $table->string('rg', 20)->nullable();
            }

            // Inscricoes (NFe SEFAZ)
            if (! Schema::hasColumn('contacts', 'inscricao_estadual')) {
                $table->string('inscricao_estadual', 30)->nullable();
            }
            if (! Schema::hasColumn('contacts', 'inscricao_municipal')) {
                $table->string('inscricao_municipal', 30)->nullable();
            }

            // Indicador IE -- NFe SEFAZ codigo 1/2/9
            //   1 = contribuinte ICMS
            //   2 = contribuinte isento de inscricao
            //   9 = nao contribuinte
            if (! Schema::hasColumn('contacts', 'indicador_ie')) {
                $table->unsignedTinyInteger('indicador_ie')->nullable();
            }

            // Nome fantasia (PJ) -- diferente de supplier_business_name (razao social)
            if (! Schema::hasColumn('contacts', 'nome_fantasia')) {
                $table->string('nome_fantasia', 150)->nullable();
            }

            // Consumidor final (NFe) -- v3.7 era integer default 1; modernizamos pra boolean default false.
            // Default mudou intencionalmente: empresa cadastrada deve ser flagada explicitamente.
            if (! Schema::hasColumn('contacts', 'consumidor_final')) {
                $table->boolean('consumidor_final')->default(false);
            }

            // Contribuinte ICMS -- v3.7 era integer default 1; modernizamos pra boolean default true.
            if (! Schema::hasColumn('contacts', 'contribuinte')) {
                $table->boolean('contribuinte')->default(true);
            }

            // Regime tributario -- v3.7 tem migration 2021_01_26_155423 mas usa after('contribuinte')
            // que falha em deploy limpo. Aqui garantimos idempotencia.
            //   simples = Simples Nacional
            //   presumido = Lucro Presumido
            //   real = Lucro Real
            //   mei = MEI
            if (! Schema::hasColumn('contacts', 'regime')) {
                $table->string('regime', 30)->nullable();
            }

            // SUFRAMA -- inscricao Zona Franca de Manaus
            if (! Schema::hasColumn('contacts', 'suframa')) {
                $table->string('suframa', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        // ATENCAO: down() so dropa campos novos (slice 1 wave 2026-05-21).
        // Campos v3.7 (cpf_cnpj, consumidor_final, contribuinte, regime) NAO sao dropados
        // mesmo em rollback -- prod tem dados que sobreviveriam ao upgrade UPOS 6.7
        // e usuarios podem ter populado. Drop manual via SQL se realmente necessario.
        Schema::table('contacts', function (Blueprint $table) {
            $cols = ['rg', 'inscricao_estadual', 'inscricao_municipal', 'indicador_ie', 'nome_fantasia', 'suframa'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
