<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela arquivos_dedupe — hash global cross-business (ADR 0123).
 *
 * Atenção (ADR 0123 §3 Tradeoffs + Agent E security review): NÃO armazena
 * business_id aqui pra evitar side-channel (business A enumerar MD5s e
 * descobrir se business B tem mesmo arquivo).
 *
 * Side-effect: dedupe é por (md5 + business_id) no nível Service, não DB.
 * Esta tabela é só metadata global pra estatística/observability.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('arquivos_dedupe', function (Blueprint $table) {
            $table->char('md5', 32)->primary();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->unsignedInteger('occurrences')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivos_dedupe');
    }
};
