<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-049 — A/B testing variants pra Macros HSM (gap P2 #18).
 *
 * Conceito (Take Blip pattern, comparativo
 * memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12-v2.md):
 *
 *   Cada Macro pode ter N variants (label + body alternativo + weight).
 *   Quando atendente aplica a macro, `MacroVariantPicker` sorteia uma
 *   variante baseado em `weight` ponderado. `Message.macro_variant_id`
 *   grava qual variante foi usada e contadores `sent_count`/`response_count`
 *   permitem medir taxa de resposta por variante.
 *
 *   - sent_count: incrementa ao enviar msg via daemon (status=sent).
 *   - response_count: incrementa quando inbound chega na mesma conv
 *     dentro de 24h da outbound com macro_variant_id (idempotente —
 *     marca message.payload._csat_variant_counted=true pra evitar dup).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`. FK macros.id cascade onDelete pra coerência.
 *
 * Migration idempotente (Schema::hasTable guard).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-049
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12-v2.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('macro_variants')) {
            return;
        }

        Schema::create('macro_variants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('macro_id')->comment('FK macros.id (cascade)');
            $table->string('label', 80)->comment('ex: "Versao A formal"');
            $table->text('body')->comment('override de macros.body — corpo da variante');
            $table->unsignedSmallInteger('weight')
                ->default(50)
                ->comment('peso pra distribuicao ponderada (50/50 = padrao A+B)');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sent_count')
                ->default(0)
                ->comment('contador de envios via daemon (status=sent)');
            $table->unsignedInteger('response_count')
                ->default(0)
                ->comment('contador de inbound em 24h da outbound (idempotente)');
            $table->timestamps();

            // FK explícita pra business (Tier 0) + cascade no delete macro
            // pra evitar variantes órfãs. Pattern alinhado com macros.
            $table->index(['business_id', 'macro_id', 'active'], 'mv_biz_macro_active_idx');

            // FK macros.id — cascade pra limpar variants quando macro removida.
            // Sem FK business pra evitar bloqueio em tests sem table `business`
            // (mesmo pattern do create_macros_table).
        });

        // FK fora do create pra dual-mode SQLite (workaround Pest local).
        // Em MySQL prod a FK é criada normalmente; SQLite tests usam ON
        // DELETE CASCADE com PRAGMA foreign_keys=ON quando configurado.
        if (config('database.default') !== 'sqlite') {
            Schema::table('macro_variants', function (Blueprint $table) {
                $table->foreign('macro_id')
                    ->references('id')->on('macros')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('macro_variants');
    }
};
