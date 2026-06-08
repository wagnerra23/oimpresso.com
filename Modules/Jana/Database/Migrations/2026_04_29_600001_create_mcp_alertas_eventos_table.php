<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-TEAM-1 Fase 4 (ADR 0055) — Eventos de alerta disparados.
 *
 * Distinguir de `mcp_alertas` (regras/configs):
 *   - `mcp_alertas` = "regra: avise se quota > 80%"
 *   - `mcp_alertas_eventos` = "instância: user X passou de 80% no dia Y"
 *
 * Idempotência via `chave_idempotencia` UNIQUE — evita spam de alertas
 * (ex: user atinge 80%, depois 81%, 82%... só dispara 1× para 80%).
 *
 * Notificação: status=aberto inicial; quando notificado, status=notificado.
 * Quando user vê na dashboard ou ack via API, status=ack.
 */
class CreateMcpAlertasEventosTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_alertas_eventos', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('business_id')->nullable();
            $t->string('tipo', 50)
                ->comment('quota_threshold|tool_destrutiva|cross_tenant|taxa_errors|...');
            $t->string('severidade', 20)->default('medium')
                ->comment('low|medium|high|critical');
            $t->string('titulo', 200);
            $t->text('descricao')->nullable();
            $t->string('chave_idempotencia', 200)->unique()
                ->comment('Hash semântico — evita dispatch duplicado');
            $t->json('metadata')->nullable();
            $t->enum('status', ['aberto', 'notificado', 'ack', 'arquivado'])->default('aberto');
            $t->timestamp('criado_em')->useCurrent();
            $t->timestamp('notificado_em')->nullable();
            $t->timestamp('ack_em')->nullable();
            $t->unsignedInteger('ack_by_user_id')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'criado_em'], 'mae_user_criado_idx');
            $t->index(['tipo', 'severidade'], 'mae_tipo_sev_idx');
            $t->index(['status', 'criado_em'], 'mae_status_criado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_alertas_eventos');
    }
}
