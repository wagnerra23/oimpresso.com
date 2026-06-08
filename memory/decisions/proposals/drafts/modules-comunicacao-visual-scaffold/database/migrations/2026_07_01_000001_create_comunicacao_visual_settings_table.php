<?php

/**
 * DRAFT — Migration scaffold inicial Modules/ComunicacaoVisual.
 *
 * Sprint 1 NAO cria tabelas de dominio (materiais, orcamentos, OS) — essas migrations
 * entram em PRs especificos quando US-COMVIS-001/002/003 forem implementadas.
 *
 * Esta migration cria APENAS a tabela `comvis_settings` (config por business) usada
 * pelo wizard onboarding (US-COMVIS-006 — tributaria CNAE 1813) e pra guardar
 * preferencias de cada gráfica (etapas customizadas do PCP, comissao padrao, etc).
 *
 * ⚠️ Multi-tenant Tier 0 IRREVOGAVEL (ADR 0093):
 *  - business_id indexado + FK obrigatoria
 *  - Eloquent Model `Modules\ComunicacaoVisual\Entities\Settings` PRECISA aplicar
 *    BusinessIdScope global (skill multi-tenant-patterns Tier A)
 *  - Felipe: NAO mexer em scope/Controller/Model multi-tenant sem rodar Pest local
 *    primeiro (regra Wagner 2026-05-09 — feedback_tenancy_changes_require_pest_local)
 *
 * Imitar padrao Modules/ADS/Database/Migrations/2026_05_03_000001_create_mcp_file_locks_table.php.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comvis_settings', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Multi-tenant Tier 0 — OBRIGATORIO (ADR 0093)
            $table->unsignedBigInteger('business_id');
            $table->index('business_id');
            $table->foreign('business_id')
                ->references('id')->on('business')
                ->cascadeOnDelete();

            // Configuracoes por business (jsonb-style)
            // Felipe: chaves esperadas (documentar em Config/config.php):
            //  - cnae_principal       (string, default '1813-0/01')
            //  - regime_tributario    (simples|presumido|real)
            //  - etapas_pcp           (array — design,prepress,impressao,acabamento,instalacao,entrega)
            //  - comissao_venda_pct   (decimal default — override por funcionario)
            //  - markup_publico_pct   (decimal — US-COMVIS-010 provador online)
            //  - whatsapp_template_orcamento (string — template envio)
            $table->json('settings')->nullable();

            // Onboarding wizard (US-COMVIS-006) marcou como completo?
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamp('onboarding_completed_at')->nullable();

            $table->timestamps();

            // 1 settings por business (one-to-one)
            $table->unique('business_id', 'comvis_settings_biz_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comvis_settings');
    }
};
