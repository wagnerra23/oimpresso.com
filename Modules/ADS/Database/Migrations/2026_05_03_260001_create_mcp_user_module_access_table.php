<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle granular de acesso por (usuário × módulo Laravel).
 *
 * Caso Maiara: pode escrever em Modules/Compras + Modules/Crm,
 *             pode LER NFSe e Financeiro mas NÃO escrever,
 *             não pode tocar Modules/ADS, Copiloto, NfeBrasil.
 *
 * Server-side enforcement: WriteFileTool e endpoint /api/ads/scope/check
 * consultam essa tabela ANTES de qualquer modificação.
 */
class CreateMcpUserModuleAccessTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_user_module_access', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('module', 50);                  // ex: 'Compras', 'Crm', 'NFSe'

            $table->boolean('can_read')->default(true);
            $table->boolean('can_write')->default(false);
            $table->boolean('can_execute_tools')->default(false);
            $table->boolean('can_commit')->default(false); // git commit (wip-* branch)

            $table->string('granted_by', 50);              // username de quem concedeu
            $table->text('reason')->nullable();
            $table->timestamp('expires_at')->nullable();   // acesso temporário

            $table->timestamps();

            $table->unique(['user_id', 'module'], 'uk_user_module');
            $table->index('module', 'idx_module_lookup');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_user_module_access');
    }
}
