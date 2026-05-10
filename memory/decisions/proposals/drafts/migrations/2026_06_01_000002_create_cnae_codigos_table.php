<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Proposta: Modules/Insights — tabela `cnae_codigos` (1.330 códigos IBGE CNAE 2.3).
 * Origem: memory/decisions/proposals/gap-schema-oimpresso-multi-cliente-multi-vertical.md
 *
 * Ordem de execução: 2 de 4 (depende da tabela `verticals` existir — FK opcional).
 *
 * Backwards compat:
 *   - Tabela nova, zero risco.
 *   - Primary key string `codigo` (ex: "1813-0/01") em vez de auto-increment — match natural com CNPJ.
 *
 * Felipe: validar collation do codigo (sugestão utf8mb4_bin pra match exato em LIKE).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnae_codigos', function (Blueprint $table) {
            $table->string('codigo', 9)->primary()->comment('Ex: 1813-0/01 (formato IBGE CNAE 2.3)');
            $table->string('descricao', 500);
            $table->string('secao', 1)->index()->comment('Letra A-U (Seção CNAE)');
            $table->string('divisao', 2)->index()->comment('2 dígitos');
            $table->string('grupo', 3)->index()->comment('3 dígitos');
            $table->string('classe', 5)->index()->comment('Classe CNAE (4+1 dígitos)');
            $table->string('subclasse', 7)->index()->comment('Subclasse CNAE (formato XXXX-X/XX)');
            $table->foreignId('vertical_id')->nullable()->constrained('verticals')->nullOnDelete()
                ->comment('Mapeamento padrão CNAE → vertical oimpresso (override possível por business)');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnae_codigos');
    }
};
