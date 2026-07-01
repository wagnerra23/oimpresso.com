<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comentários inline por título financeiro (Onda 6+ — Comments + Audit DB).
 *
 * Espelha o FinCommentsThread do mock Cowork canon (prototipo-ui/cowork/
 * financeiro-curation.jsx) — antes persistia em localStorage por device,
 * agora persiste em DB sincronizado pra Eliana ↔ Wagner ↔ Bruna.
 *
 * Append-only por design (ledger). Removal é UI-only (filtra deleted_at) caso
 * adicionemos soft-delete futuro — por enquanto, comments criados são imutáveis.
 *
 * Tier 0 multi-tenant: business_id NOT NULL + FK ON DELETE CASCADE.
 * Index (business_id, titulo_id) pra query "lista comments de um título".
 *
 * Idempotente: re-run não duplica schema (verifica Schema::hasTable).
 */
class CreateFinTituloCommentsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_titulo_comments')) {
            return;
        }

        Schema::create('fin_titulo_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('business_id')->unsigned();
            $table->integer('titulo_id')->unsigned();
            $table->integer('user_id')->unsigned()
                ->comment('FK users.id — quem comentou (Eliana / Wagner / Bruna / ...)');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'titulo_id'], 'idx_business_titulo');
            $table->index(['business_id', 'created_at'], 'idx_business_created');

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('titulo_id')->references('id')->on('fin_titulos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_titulo_comments');
    }
}
