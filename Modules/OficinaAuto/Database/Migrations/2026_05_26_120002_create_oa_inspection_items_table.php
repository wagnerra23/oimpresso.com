<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration `oa_inspection_items` — DVI (Vistoria Digital · Digital Vehicle Inspection).
 *
 * Origem Wave 3 OficinaAuto (CAPTERRA-FICHA Repair gap #3 — wedge competitivo vs
 * RepairShopr/mHelpDesk). Permite mecânico registrar itens vistoriados na OS
 * com semáforo (ok / atencao / critico) + valor recomendado + foto opcional.
 * Renderização visual aprovada por screenshot Wagner 2026-05-26: card "VISTORIA
 * DIGITAL · DVI" com badges contadores + 5 items semáforo + bloco TOTAL.
 *
 * Schema:
 * - categoria: 10 grupos cobrindo manutenção típica (motor/freios/correia/bateria/
 *   pneus/suspensao/direcao/eletrica/fluidos/outro) — extensível futuramente
 * - severity: ok | atencao | critico (semáforo verde/amarelo/vermelho)
 * - valor_recomendado: R$ — usado pra soma "TOTAL RECOMENDADO" agregando
 *   apenas itens severity IN (atencao, critico)
 * - metadata json: extensão livre (vida_util_pct, km_restantes, voltagem, etc)
 * - photo_url: foto vista do item (futuro: storage S3)
 * - sort_order: ordem de display na lista (mecânico controla)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * `business_id` indexado + FK CASCADE pra business.
 *
 * Idempotência: `Schema::hasTable` guard pra rerun safe (padrão hotfix #639/#640).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-035
 * @see memory/requisitos/Repair/CAPTERRA-FICHA.md gap #3 DVI
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oa_inspection_items')) {
            return; // idempotente — rerun safe
        }

        Schema::create('oa_inspection_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned bate com business.id (UltimatePOS legacy FK constraint)
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('service_order_id');

            // enum em vez de string pra forçar conformidade no nível DB
            $table->enum('categoria', [
                'motor',
                'freios',
                'correia',
                'bateria',
                'pneus',
                'suspensao',
                'direcao',
                'eletrica',
                'fluidos',
                'outro',
            ]);
            $table->string('descricao', 150);
            $table->enum('severity', ['ok', 'atencao', 'critico']);

            $table->string('recomendacao', 255)->nullable();
            $table->decimal('valor_recomendado', 10, 2)->nullable();

            // metadata json livre: {"vida_util_pct": 60, "km_restantes": 4500, "voltagem": 12.4}
            $table->json('metadata')->nullable();
            $table->string('photo_url', 500)->nullable();

            $table->smallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes(); // preserva audit append-only

            $table->index(['business_id', 'service_order_id'], 'idx_oai_biz_so');
            $table->index(['business_id', 'severity'], 'idx_oai_biz_sev');
            $table->index(['service_order_id', 'sort_order'], 'idx_oai_so_sort');

            $table->foreign('business_id', 'fk_oai_business')
                  ->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('service_order_id', 'fk_oai_service_order')
                  ->references('id')->on('service_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_inspection_items');
    }
};
