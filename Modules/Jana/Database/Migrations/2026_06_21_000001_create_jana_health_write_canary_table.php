<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela-alvo do write-canary do jana:health-check (incidente 2026-06-21).
 *
 * O sentinela de saúde NÃO tinha nenhum check que provasse PRIVILÉGIO DE ESCRITA —
 * quando o usuário de DB de prod (`u906587222_oimpresso`) perdeu INSERT/UPDATE no
 * Hostinger (GRANT revogado ~20/jun 22:50), os 17 checks seguiram VERDES enquanto
 * toda gravação do app falhava com MySQL 1142 (~8.9k erros em 2h). Anti-padrão
 * "a suíte mente" caçado pela auditoria de sentinelas (2026-06-20).
 *
 * Esta tabela é só o alvo de um INSERT de prova (em transação + rollback — nunca
 * persiste linha). Sem dados de negócio → sem business_id (tabela de sistema, como
 * jobs/migrations/mcp_audit_log).
 *
 * @see Modules\Jana\Console\Commands\HealthCheckCommand::checkDbWriteCanary()
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jana_health_write_canary')) {
            return;
        }

        Schema::create('jana_health_write_canary', function (Blueprint $table) {
            $table->id();
            $table->string('probe', 64);
            $table->timestamp('pinged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jana_health_write_canary');
    }
};
