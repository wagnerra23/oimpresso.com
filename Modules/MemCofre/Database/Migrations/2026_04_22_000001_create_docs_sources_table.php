<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs_sources — material bruto ingerido.
 *
 * Cada linha = uma "coisa" que chegou: screenshot, chat log, pdf, erro, texto.
 * Arquivo físico fica no storage (public disk). A linha é o metadata.
 */
class CreateDocsSourcesTable extends Migration
{
    public function up(): void
    {
        Schema::create('docs_sources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index(); // users.id
            $table->string('module_target', 64)->nullable()->index();     // 'Essentials', 'PontoWr2', etc
            $table->string('type', 16);                                   // screenshot, chat, error, file, text, url
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();                       // nota humana de contexto
            $table->string('storage_path', 500)->nullable();               // public/memcofre/xxx.png
            $table->string('source_url', 500)->nullable();                 // se for url ingerida
            $table->text('body_text')->nullable();                         // conteúdo extraído (OCR, plaintext chat, etc)
            $table->json('meta')->nullable();                              // extra: browser, URL original, stack trace, etc
            $table->timestamps();

            $table->index(['business_id', 'module_target']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_sources');
    }
}
