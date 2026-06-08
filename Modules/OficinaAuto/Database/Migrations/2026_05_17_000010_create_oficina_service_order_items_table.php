<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration `oficina_service_order_items` — itens (peças + mão-de-obra + serviços terceiros)
 * de uma OS OficinaAuto.
 *
 * Origem W27 G1 (P0 fatal CAPTERRA-FICHA OficinaAuto Wave 22):
 * cliente final espera linha-a-linha "Pastilha freio dianteira R$ [redacted Tier 0] × 1 = R$ [redacted Tier 0]" +
 * "Troca de pastilha — 0.5h × R$ [redacted Tier 0]/h = R$ [redacted Tier 0]". Sem isso vira "ticket de bilhete único".
 *
 * Schema desenhado pra cobrir 3 tipos com mesma estrutura (DDD agregado):
 * - peca           — produto físico do catálogo `products` (UltimatePOS legacy)
 *                    quantidade decimal (litros óleo, gramas pasta térmica)
 * - mao_obra       — serviço interno (hora-mecânico × valor_hora)
 * - servico_terceiro — pintura/retífica/elétrica terceirizada (sem product_id)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * `business_id` indexado + FK CASCADE pra business.
 *
 * Idempotência: `Schema::hasTable` guard pra rerun safe (padrão hotfix #639/#640).
 *
 * @see memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md G1
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('oficina_service_order_items')) {
            return; // idempotente — rerun safe
        }

        Schema::create('oficina_service_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned bate com business.id (UltimatePOS legacy FK constraint)
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('service_order_id');

            // enum em vez de string pra forçar conformidade no nível DB
            $table->enum('tipo', ['peca', 'mao_obra', 'servico_terceiro']);
            $table->string('descricao', 255);

            // quantidade decimal 10,3 → suporta 0.250 (litros) até 9999999.999
            $table->decimal('quantidade', 10, 3)->default(1);
            $table->decimal('valor_unitario', 10, 2)->default(0);
            // valor_total redundante (= quantidade × valor_unitario) mas materializado
            // pra evitar N+1 em listagens + index em ORDER BY valor_total DESC
            $table->decimal('valor_total', 10, 2)->default(0);

            // product_id nullable — só preenchido pra tipo=peca via catálogo
            $table->unsignedInteger('product_id')->nullable();

            $table->text('notes')->nullable(); // observação livre (lote, marca alternativa, etc)

            $table->timestamps();
            $table->softDeletes(); // preserva audit append-only mesmo em "remoção"

            $table->index(['business_id', 'service_order_id'], 'idx_osi_biz_so');
            $table->index(['business_id', 'tipo'], 'idx_osi_biz_tipo');
            $table->index(['service_order_id', 'tipo'], 'idx_osi_so_tipo');

            $table->foreign('business_id', 'fk_osi_business')
                  ->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('service_order_id', 'fk_osi_service_order')
                  ->references('id')->on('service_orders')->cascadeOnDelete();
            // NOTA: NÃO criamos FK pra products — tabela UltimatePOS legacy varia
            // de schema entre instalações; validação fica no Service layer.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oficina_service_order_items');
    }
};
