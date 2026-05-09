<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-049 · Tabela `nfe_dfe_itens` — itens parseados de NFe recebida.
 *
 * Substitui `item_dfes` legacy (App\ItemDfe).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_dfe_itens')) {
            return;
        }

        Schema::create('nfe_dfe_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('dfe_recebido_id')
                ->constrained('nfe_dfe_recebidos')
                ->cascadeOnDelete();
            $table->string('ncm', 8)->nullable();
            $table->string('cfop', 4)->nullable();
            $table->text('descricao');
            $table->decimal('quantidade', 15, 4)->default(0);
            $table->decimal('valor_unitario', 15, 4)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['business_id', 'dfe_recebido_id'], 'nfe_dfe_itens_biz_dfe_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_dfe_itens');
    }
};
