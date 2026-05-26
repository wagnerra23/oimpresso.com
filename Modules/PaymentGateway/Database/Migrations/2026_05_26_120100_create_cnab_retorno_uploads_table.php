<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 4f.0 — ADR 0170 (fundação CNAB compartilhada).
 *
 * Histórico de uploads de arquivo de RETORNO CNAB (240/400) por credencial.
 *
 * Cada linha = 1 upload processado pelo Job `CnabRetornoProcessor`:
 *   - arquivo_path (Storage::disk('local')): caminho relativo do arquivo persistido
 *   - arquivo_nome_original: nome dado pelo upload (display/audit)
 *   - processado_em: when, null = upload feito mas job ainda na fila
 *   - qtd_paga/cancelada/vencida/registrada: contadores extraídos do retorno
 *   - erros_json: array de mensagens (linhas inválidas, nosso_numero órfão, etc)
 *   - processado_por_user_id: user que clicou upload (audit/LGPD)
 *
 * Multi-tenant Tier 0 — global scope via HasBusinessScope (ADR 0093).
 * business_id NOT NULL + index + FK (soft — backend valida).
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.0
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnab_retorno_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->index();

            $table->string('arquivo_path');
            $table->string('arquivo_nome_original');
            $table->unsignedInteger('arquivo_tamanho_bytes')->default(0);

            $table->timestamp('processado_em')->nullable()->index();

            // Contadores extraídos do retorno (preenchidos pelo Job)
            $table->unsignedInteger('qtd_paga')->default(0);
            $table->unsignedInteger('qtd_cancelada')->default(0);
            $table->unsignedInteger('qtd_vencida')->default(0);
            $table->unsignedInteger('qtd_registrada')->default(0);

            $table->text('erros_json')->nullable();
            $table->unsignedBigInteger('processado_por_user_id')->nullable()->index();

            $table->timestamps();

            $table->index(['business_id', 'payment_gateway_credential_id'], 'cnab_ret_biz_cred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnab_retorno_uploads');
    }
};
