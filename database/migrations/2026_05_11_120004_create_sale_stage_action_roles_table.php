<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-011 — RBAC join: action × role Spatie (ADR 0129 §Schema).
 *
 * `role_name` é FK lógica pra `roles.name` do spatie/laravel-permission v6.0.
 * Resolução RBAC: `$user->hasAnyRole($action->roles->pluck('role_name'))`.
 * Sem registros aqui → action é PÚBLICA (qualquer user pode executar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_stage_action_roles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('action_id');
            $t->string('role_name', 100);
            $t->timestamps();

            $t->unique(['action_id', 'role_name'], 'sale_action_roles_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_stage_action_roles');
    }
};
