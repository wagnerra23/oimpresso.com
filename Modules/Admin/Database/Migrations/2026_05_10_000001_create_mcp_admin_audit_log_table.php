<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela mcp_admin_audit_log — append-only audit do Admin Center.
 *
 * ADR 0122 §3 — toda 403 (Tailscale block, gate fail) e toda action mutacional
 * (apply Curador, regenerate token, run-now health) gera linha.
 *
 * Multi-tenant Tier 0 (ADR 0093) preservado: business_id NOT NULL pra ações
 * com user logado. Para Tailscale block sem auth, business_id=0 sentinel.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('mcp_admin_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('business_id')->default(0); // 0 = pre-auth (Tailscale block)
            $table->string('action', 64);                       // 'unauthorized_access' | 'tailscale_block' | etc
            $table->string('route', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'action', 'created_at'], 'idx_admin_audit_biz_action_ts');
            $table->index('user_id', 'idx_admin_audit_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_admin_audit_log');
    }
};
