<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela user_lockouts — registro de "trancas" de usuário com snapshot.
 *
 * Caso de uso (Wagner): quando um funcionário rouba/é demitido, queremos
 *   1. Revogar acesso imediato (status='inactive', tokens revogados, sessions matadas)
 *   2. Preservar TUDO que ele tinha (roles+permissions+tokens+scopes) num snapshot
 *      JSON, pra rastrear depois "o que ele tinha acesso na hora do incidente"
 *   3. Permitir destrancar (status='active') restaurando o que dá pra restaurar
 *      sem reimprimir tokens (segurança).
 *
 * Append-only por governança: um lockout marca unlocked_at em vez de DELETE.
 * Múltiplos lockouts no mesmo user são permitidos (histórico cronológico).
 *
 * @see app/Services/UserLockoutService.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lockouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('business_id')->nullable();
            $table->timestamp('locked_at')->useCurrent();
            $table->unsignedInteger('locked_by');
            $table->string('reason', 500);
            $table->json('snapshot')->nullable()
                ->comment('Snapshot do estado: roles, permissions, mcp_tokens, mcp_user_scopes ANTES do lock.');
            $table->timestamp('unlocked_at')->nullable();
            $table->unsignedInteger('unlocked_by')->nullable();
            $table->string('unlock_note', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'locked_at'], 'ulck_user_locked_idx');
            $table->index(['business_id', 'locked_at'], 'ulck_biz_locked_idx');
            $table->index(['user_id', 'unlocked_at'], 'ulck_user_unlocked_idx');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('unlocked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lockouts');
    }
};
