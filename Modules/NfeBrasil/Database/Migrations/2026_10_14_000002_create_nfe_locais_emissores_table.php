<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 26 · filial em outro estado (R-NFE-031 · R-NFE-032 · UC-TRB-27).
 *
 * - `nfe_locais_emissores`: tabela-ponte que marca um `business_locations` (core UltimatePOS, não
 *   alterado) como estabelecimento emissor próprio, com as séries dele. Local sem linha aqui emite
 *   como hoje (UF e dados do business, série padrão). UF, CNPJ, IE e razão social vêm do
 *   próprio `business_locations`; rua, número, bairro, CEP e código IBGE, que ele não tem, vêm daqui.
 *   A série é única dentro da empresa, então a UNIQUE `nfe_emissoes_biz_seq_unique` (business + modelo + série + número) já impede número
 *   repetido entre matriz e filial.
 * - `nfe_emissoes.location_id`: de qual local saiu a nota (nulo nas notas de antes).
 *
 * Só acrescenta (ADR 0093 G8): nenhuma linha existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_locais_emissores')) {
            Schema::create('nfe_locais_emissores', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id');
                $table->string('serie_nfe', 3)->nullable()->comment('Série da NF-e (55) deste local');
                $table->string('serie_nfce', 3)->nullable()->comment('Série da NFC-e (65) deste local');
                // Endereço do emitente: `business_locations` não tem rua/número/bairro nem código
                // IBGE, e o XML exige. O contador informa aqui; sem eles a emissão recusa.
                $table->string('logradouro', 60)->nullable();
                $table->string('numero', 60)->nullable();
                $table->string('bairro', 60)->nullable();
                $table->char('cod_municipio', 7)->nullable()->comment('Código IBGE do município do local');
                // `business_locations.zip_code` é char(7): não cabe o CEP de 8 dígitos.
                $table->char('cep', 8)->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'location_id'], 'nfe_locais_emissores_biz_loc_unique');
                $table->unique(['business_id', 'serie_nfe'], 'nfe_locais_emissores_biz_serie55_unique');
                $table->unique(['business_id', 'serie_nfce'], 'nfe_locais_emissores_biz_serie65_unique');
                $table->foreign('business_id', 'nfe_locais_emissores_business_fk')
                    ->references('id')->on('business')->cascadeOnDelete();
                $table->foreign('location_id', 'nfe_locais_emissores_location_fk')
                    ->references('id')->on('business_locations')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('nfe_emissoes') && ! Schema::hasColumn('nfe_emissoes', 'location_id')) {
            Schema::table('nfe_emissoes', function (Blueprint $table) {
                $table->unsignedInteger('location_id')->nullable()->after('transaction_id')
                    ->comment('Local emissor (nfe_locais_emissores); nulo = business');
                $table->index(['business_id', 'location_id'], 'nfe_emissoes_biz_location_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nfe_emissoes') && Schema::hasColumn('nfe_emissoes', 'location_id')) {
            Schema::table('nfe_emissoes', function (Blueprint $table) {
                $table->dropIndex('nfe_emissoes_biz_location_idx');
                $table->dropColumn('location_id');
            });
        }

        Schema::dropIfExists('nfe_locais_emissores');
    }
};
