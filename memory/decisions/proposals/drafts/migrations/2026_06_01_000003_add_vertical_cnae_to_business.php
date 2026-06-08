<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Proposta: adiciona colunas `vertical_id`, `cnae_principal`, `cnae_secundarios`,
 * `vertical_attributes` à tabela CORE `business` do UltimatePOS.
 *
 * ⚠️ TENANCY CRITICAL — leitura obrigatória pra Felipe:
 *   - `business` é a tabela RAIZ do multi-tenant (ADR 0093 Tier 0).
 *   - Mudança aqui afeta TODOS os 56 clientes em produção (incluindo ROTA LIVRE biz=4, 99% volume).
 *   - Wagner regra (memory/proibicoes.md): NÃO modificar tabelas core UltimatePOS sem bridge table.
 *
 * Por que mexer direto em vez de bridge:
 *   - Vertical é atributo INTRÍNSECO do business (1:1, não 1:N) — bridge seria over-engineering.
 *   - 100% NULLABLE — zero impacto pros 56 businesses existentes (continuam vertical=NULL até backfill).
 *   - Padrão UltimatePOS já tem ~50 colunas em `business`; adicionar 4 não muda perfil de risco.
 *
 * Antes do PR — Felipe deve:
 *   1) Rodar Pest local com biz=4 pra confirmar que `Business::find(4)` continua funcionando.
 *   2) Confirmar que `BusinessUtil::createNewBusiness()` no UltimatePOS não precisa setar
 *      esses campos (devem ficar NULL na criação — backfill via command depois).
 *   3) Validar Eloquent Model `App\Business`: $fillable não precisa incluir os campos novos
 *      (UltimatePOS não usa fillable estrito) — mas $casts pode precisar pra cnae_secundarios/vertical_attributes JSON.
 *   4) Smoke biz=4 ROTA LIVRE: criar venda → confirmar transação OK → rollback se quebrar.
 *
 * Ordem de execução: 3 de 4 (depende de `verticals` existir).
 *
 * Rollback: down() limpa tudo. Mas se já tiver rodado backfill em prod, o
 * down ZERA `cnae_principal` populado — Felipe avalia se quer rollback destrutivo
 * ou rollback "soft" (drop FK mas preserva colunas).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->foreignId('vertical_id')->nullable()->after('id')
                ->constrained('verticals')->nullOnDelete()
                ->comment('Vertical oimpresso Insights — NULL até backfill via insights:backfill-vertical');
            $table->string('cnae_principal', 9)->nullable()->after('vertical_id')
                ->comment('CNAE primary do CNPJ — populated via BrasilAPI no backfill');
            $table->json('cnae_secundarios')->nullable()->after('cnae_principal')
                ->comment('Lista CNAEs secundários do CNPJ');
            $table->json('vertical_attributes')->nullable()->after('cnae_secundarios')
                ->comment('Atributos custom por vertical (m² produzidos, # boxes, etc) — schema definido em verticals.attributes_schema');

            $table->index('vertical_id');
            $table->index('cnae_principal');
        });
    }

    public function down(): void
    {
        Schema::table('business', function (Blueprint $table) {
            // Drop indexes antes de columns (MySQL exige).
            $table->dropIndex(['vertical_id']);
            $table->dropIndex(['cnae_principal']);
            $table->dropConstrainedForeignId('vertical_id');
            $table->dropColumn(['cnae_principal', 'cnae_secundarios', 'vertical_attributes']);
        });
    }
};
