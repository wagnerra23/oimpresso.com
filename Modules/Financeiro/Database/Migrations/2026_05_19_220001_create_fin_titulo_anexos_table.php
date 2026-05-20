<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 20 (2026-05-19) #50 — Anexos NF / comprovante por título.
 *
 * Anexos relacionados a Titulo (NFe PDF, recibo, boleto pago, comprovante TED).
 * Storage local (não Spatie Media — UltimatePOS não tem instalado).
 *
 * Tier 0 (R-FIN-001 multi-tenant + LGPD storage com retention).
 */
class CreateFinTituloAnexosTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_titulo_anexos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('titulo_id')->unsigned();
            $table->string('nome', 255)->comment('nome original do arquivo');
            $table->string('path', 500)->comment('caminho relativo em storage/app/private/financeiro/anexos');
            $table->string('mime', 100)->nullable();
            $table->integer('tamanho_bytes')->unsigned()->nullable();
            $table->string('hash_sha256', 64)->nullable()->comment('idempotência de upload');
            $table->integer('uploaded_by')->unsigned();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'titulo_id']);
            $table->index(['business_id', 'hash_sha256']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('titulo_id')->references('id')->on('fin_titulos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_titulo_anexos');
    }
}
