<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * UC-FLX-35 · US-SELL-069 — permissão de aprovar a conversão de orçamento com margem abaixo do
 * mínimo da empresa.
 *
 * Nasce sem ninguém: o administrador já passa pelo Gate::before, e os outros papéis recebem a
 * permissão pela tela de papéis. Não herda de nenhuma outra (aprovar desconto abaixo da margem
 * é decisão de quem responde pela margem, não um efeito colateral de outra permissão).
 */
return new class extends Migration
{
    private const NOME = 'sell.aprovar_margem_baixa';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::NOME, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::NOME)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
