<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 09 · tabela ICMS/FCP por UF, curada (D-UF · R-NFE-021).
 *
 * Uma linha por (empresa, UF de origem, UF de destino, vigência):
 * - `aliquota_interestadual`: semeada pelo `NfeIcmsUfSeeder`, só com a Resolução do Senado
 *   nº 22/1989 citada literal. NULL quando origem = destino (operação interna).
 * - `aliquota_interna` e `fcp`: da UF de DESTINO, preenchidas pelo contador. Nascem NULL — sem
 *   número sem lei (lei 4 do módulo). Quem precisar da interna e achar NULL recebe erro, nunca 0.
 * - `valida_de`/`valida_ate`: mesmo padrão de vigência das regras (thread 07); NULL = sem limite.
 *
 * Por empresa (`business_id` + FK): é o contador de cada empresa que preenche a interna.
 * Só cria tabela — nenhuma tabela existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_icms_uf')) {
            return;
        }

        Schema::create('nfe_icms_uf', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->char('uf_origem', 2);
            $table->char('uf_destino', 2);
            $table->decimal('aliquota_interestadual', 7, 4)->nullable();
            $table->decimal('aliquota_interna', 7, 4)->nullable()
                ->comment('da UF de destino — o contador preenche');
            $table->decimal('fcp', 7, 4)->nullable()
                ->comment('FCP da UF de destino — o contador preenche');
            $table->date('valida_de')->nullable();
            $table->date('valida_ate')->nullable();
            $table->string('fonte', 255)->nullable()
                ->comment('norma que sustenta o número');
            $table->timestamps();

            $table->index(['business_id', 'uf_origem', 'uf_destino'], 'nfe_icms_uf_biz_par_idx');
            $table->foreign('business_id', 'nfe_icms_uf_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_icms_uf');
    }
};
