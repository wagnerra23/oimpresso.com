<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona identidade fiscal por location.
 *
 * Motivacao: empresas no Brasil costumam operar varios CNPJs sob a mesma
 * "operacao" (vendas/produtos/financeiro sao um so) para reduzir imposto.
 * UltimatePOS ja tem N business_locations por business mas nao separa
 * CNPJ por location — essa migration cria essa separacao.
 *
 * O HD da licenca_computador continua amarrado a business_id (autorizacao
 * por operacao, nao por CNPJ fiscal). O business_location_id e usado apenas
 * pra resolver qual CNPJ o Delphi esta enviando no body e logar isso
 * como contexto.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('business_locations', function (Blueprint $table) {
            $table->string('cnpj', 20)->nullable()->after('name');
            $table->string('razao_social', 150)->nullable()->after('cnpj');
            $table->string('nome_fantasia', 150)->nullable()->after('razao_social');
            $table->string('inscricao_estadual', 30)->nullable()->after('nome_fantasia');
            $table->string('inscricao_municipal', 30)->nullable()->after('inscricao_estadual');

            $table->index('cnpj');
        });

        // Backfill: copia cnpj do business pai quando a location ainda nao tem.
        // business.cnpj existe no schema UltimatePOS brasileiro (coluna custom
        // adicionada no 3.7). Se falhar por alguma coluna ausente, segue sem
        // backfill — nada bloqueia o update de tabela.
        try {
            DB::statement("
                UPDATE business_locations bl
                INNER JOIN business b ON b.id = bl.business_id
                SET bl.cnpj = b.cnpj,
                    bl.razao_social = COALESCE(b.razao_social, b.name),
                    bl.nome_fantasia = b.name
                WHERE bl.cnpj IS NULL
                  AND b.cnpj IS NOT NULL
                  AND b.cnpj <> ''
            ");
        } catch (\Throwable $e) {
            \Log::warning('[migration] backfill business_locations fiscais falhou: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('business_locations', function (Blueprint $table) {
            $table->dropIndex(['cnpj']);
            $table->dropColumn([
                'cnpj',
                'razao_social',
                'nome_fantasia',
                'inscricao_estadual',
                'inscricao_municipal',
            ]);
        });
    }
};
