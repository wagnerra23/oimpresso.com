<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * CU-PROD-20 (proposta #10765, autorizada pelo [F] em 2026-11-12) — permissão de editar só os locais
 * de armazenagem do produto: o endereço em cada local e o local padrão.
 *
 * Existe para o estoquista acertar a prateleira sem receber acesso a preço e imposto. Nasce sem
 * ninguém: o administrador já passa pelo Gate::before, quem tem `product.update` continua editando os
 * locais, e cada empresa marca a permissão nova no papel que quiser pela tela de papéis.
 */
return new class extends Migration
{
    private const NOME = 'product.armazenagem';

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
