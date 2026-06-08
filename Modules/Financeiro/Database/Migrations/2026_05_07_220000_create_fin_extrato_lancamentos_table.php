<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lançamentos de extrato bancário sincronizados via API do banco
 * (Inter v2 hoje; Sicoob/BTG/Cora futuro).
 *
 * Idempotente por `(conta_bancaria_id, idempotency_key)` — re-sync seguro,
 * o `idempotency_key` vem de `idTransacao` ou `endToEndId` do banco.
 *
 * `business_id` indexed pra global scope multi-tenant Tier 0 (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_extrato_lancamentos')) {
            return;
        }

        Schema::create('fin_extrato_lancamentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('conta_bancaria_id');
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->char('tipo', 1)->comment('C = credito, D = debito');
            $table->string('descricao', 500);
            $table->string('contraparte_documento', 20)->nullable();
            $table->string('contraparte_nome', 255)->nullable();
            $table->string('idempotency_key', 100)
                ->comment('Inter: idTransacao ou endToEndId; fallback hash do payload');
            $table->json('raw_payload')->comment('Response bruto do banco pra análise futura');
            $table->timestamps();

            $table->unique(
                ['conta_bancaria_id', 'idempotency_key'],
                'fin_extrato_idem_unique'
            );
            $table->index(['business_id', 'data'], 'fin_extrato_biz_data_idx');

            $table->foreign('business_id', 'fin_extrato_biz_fk')
                ->references('id')->on('business')->onDelete('cascade');
            $table->foreign('conta_bancaria_id', 'fin_extrato_conta_fk')
                ->references('id')->on('fin_contas_bancarias')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_extrato_lancamentos');
    }
};
