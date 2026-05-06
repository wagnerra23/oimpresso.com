<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fin_contas_bancarias', 'saldo_cached')) {
            return;
        }

        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->decimal('saldo_cached', 15, 2)
                ->nullable()
                ->after('rb_gateway_credential_id')
                ->comment('Saldo sincronizado via API do banco (Inter/Asaas) — null = não sincronizado');

            $table->timestamp('saldo_atualizado_em')
                ->nullable()
                ->after('saldo_cached')
                ->comment('Quando o saldo foi sincronizado pela última vez');

            $table->string('tipo_conta', 20)
                ->default('corrente')
                ->after('saldo_atualizado_em')
                ->comment('corrente | poupanca | virtual_pj');
        });
    }

    public function down(): void
    {
        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->dropColumn(['saldo_cached', 'saldo_atualizado_em', 'tipo_conta']);
        });
    }
};
