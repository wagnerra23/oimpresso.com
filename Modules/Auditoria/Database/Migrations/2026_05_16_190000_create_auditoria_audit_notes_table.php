<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D7.b — auditoria-de-auditoria via tabela propria do modulo Auditoria.
 *
 * Tabela `auditoria_audit_notes` armazena anotacoes internas (PT-BR) sobre
 * entries do `activity_log` (Spatie shared UltimatePOS — Auditoria NUNCA toca).
 *
 * Multi-tenant Tier 0 IRREVOGAVEL (ADR 0093): business_id NOT NULL + index
 * composto (business_id, activity_id) para queries scoped.
 *
 * Retention: 2555d (7 anos) per config/auditoria/retention.php — alinha
 * audit fiscal CONFAZ + LGPD Art. 16 Janela conservadora pois esta tabela
 * registra DECISOES humanas sobre dados sensiveis.
 *
 * NAO ha FK formal para activity_log.id porque activity_log e shared core
 * UltimatePOS (Spatie) e modificar engine/FK pode bloquear upgrade futuro.
 * Soft reference apenas (validacao via Service).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auditoria_audit_notes')) {
            return; // idempotente
        }

        Schema::create('auditoria_audit_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id'); // Tier 0 obrigatorio
            $table->unsignedBigInteger('activity_id'); // soft FK activity_log.id
            $table->unsignedBigInteger('user_id');     // autor da nota
            $table->text('note');                       // PT-BR, max 5000 chars validacao Service
            $table->timestamps();

            $table->index(['business_id', 'activity_id'], 'idx_auditoria_notes_biz_act');
            $table->index('user_id', 'idx_auditoria_notes_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_audit_notes');
    }
};
