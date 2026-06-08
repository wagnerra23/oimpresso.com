<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_emissoes')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('nfe_emissoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('transaction_id')->nullable()
                ->comment('FK transactions.id (UPos legado, int unsigned). Null em emissões manuais sem venda');
            $table->enum('modelo', ['55', '65', '67'])
                ->comment('55=NFe B2B, 65=NFC-e B2C, 67=CT-e (futuro)');
            $table->string('serie', 3);
            $table->unsignedInteger('numero');
            $table->string('chave_44', 44)->nullable()->index()
                ->comment('Chave de acesso 44 dígitos — populada após autorização SEFAZ');
            $table->enum('status', ['pendente', 'autorizada', 'rejeitada', 'cancelada', 'denegada', 'inutilizada'])
                ->default('pendente')->index();
            $table->string('cstat', 5)->nullable()
                ->comment('Código status SEFAZ (100=autorizada, 217=NFe não consta, etc.)');
            $table->text('motivo')->nullable();
            $table->string('xml_path', 255)->nullable();
            $table->string('danfe_path', 255)->nullable();
            $table->decimal('valor_total', 15, 2);
            $table->dateTime('emitido_em')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Idempotência: re-emitir mesma venda = no-op
            $table->unique(['business_id', 'transaction_id'], 'nfe_emissoes_biz_tx_unique');
            // Sequência única por business+modelo+serie+numero (regra fiscal)
            $table->unique(['business_id', 'modelo', 'serie', 'numero'], 'nfe_emissoes_biz_seq_unique');
            $table->index(['business_id', 'status'], 'nfe_emissoes_biz_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_emissoes');
    }
};
