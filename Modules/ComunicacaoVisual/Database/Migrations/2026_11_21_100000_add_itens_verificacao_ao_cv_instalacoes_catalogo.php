<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens de verificação do tipo de instalação (US-COMVIS-007 · UC-PCV-25): a lista que o instalador marca no
 * celular, por tipo — ex.: "Fachada em altura" pede "Andaime e EPI", "Nível e prumo", "Teste de vedação".
 * Fica ao lado de `ferramentas_necessarias_json` (o que levar), que é outra pergunta.
 *
 * Coluna nova e nula: tipos que já existam seguem sem itens. Multi-tenant: a tabela já tem business_id.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cv_instalacoes_catalogo') || Schema::hasColumn('cv_instalacoes_catalogo', 'itens_verificacao_json')) {
            return;
        }
        Schema::table('cv_instalacoes_catalogo', function (Blueprint $table) {
            $table->json('itens_verificacao_json')->nullable()->after('ferramentas_necessarias_json');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cv_instalacoes_catalogo', 'itens_verificacao_json')) {
            Schema::table('cv_instalacoes_catalogo', function (Blueprint $table) {
                $table->dropColumn('itens_verificacao_json');
            });
        }
    }
};
