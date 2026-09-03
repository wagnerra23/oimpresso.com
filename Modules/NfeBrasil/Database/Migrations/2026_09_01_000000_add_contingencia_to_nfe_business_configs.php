<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-006 / ADR TECH-0002 (NfeBrasil) — estado de contingência POR BUSINESS.
 *
 * A ADR corta o problema em dois: a OBSERVAÇÃO de que a SEFAZ caiu é global
 * (vive em `nfe_sefaz_status`, por UF), mas a DECISÃO de emitir em contingência
 * é do tenant — porque a ADR rejeitou auto-ativação ("pode ativar em falsa-detecção:
 * rede do servidor caiu, não SEFAZ"). Por isso o flag mora aqui, não lá.
 *
 * `contingencia_motivo` é exigência de auditoria: o fiscal pergunta POR QUE a nota
 * saiu com tpEmis != 1, e "porque o sistema achou" não é resposta.
 *
 * Idempotente (Schema::hasColumn) + down() preserva colunas (append-only, ADR 0093 G8):
 * `em_contingencia=false` é inerte, não altera emissão de quem nunca ativou.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_business_configs')) {
            return;
        }

        Schema::table('nfe_business_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_business_configs', 'em_contingencia')) {
                $table->boolean('em_contingencia')
                    ->default(false)
                    ->comment('US-NFE-006: tenant ativou contingência manualmente. Emissão passa a gravar tpEmis 4 (NF-e) ou 9 (NFC-e) e NÃO chama SEFAZ.');
            }

            if (! Schema::hasColumn('nfe_business_configs', 'contingencia_ativada_em')) {
                $table->timestamp('contingencia_ativada_em')
                    ->nullable()
                    ->comment('Quando entrou em contingência. Alimenta o banner persistente "ATIVA há N dias" (ADR TECH-0002, risco operacional).');
            }

            if (! Schema::hasColumn('nfe_business_configs', 'contingencia_motivo')) {
                $table->string('contingencia_motivo', 255)
                    ->nullable()
                    ->comment('Justificativa declarada pelo tenant. Exigência de auditoria fiscal — não é campo livre decorativo.');
            }
        });
    }

    public function down(): void
    {
        // Append-only (ADR 0093 Garantia 8): colunas preservadas no rollback.
        // em_contingencia=false é o estado neutro — quem nunca ativou não sente diferença.
    }
};
