<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_fiscal_rules')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('nfe_fiscal_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();

            // Classificação fiscal
            $table->char('ncm', 8)
                ->comment('Nomenclatura Comum do Mercosul — 8 dígitos');
            $table->char('uf_origem', 2);
            $table->char('uf_destino', 2)->nullable()
                ->comment('NULL = "todas as UFs destino" (nível 3 do cascade ADR 0006)');

            // CFOP (4 dígitos como string pra preservar zero à esquerda)
            $table->char('cfop', 4);

            // Códigos tributários — só um aplica por regime
            $table->char('csosn', 3)->nullable()
                ->comment('Simples Nacional (CRT 1)');
            $table->char('cst', 3)->nullable()
                ->comment('Regime Normal (CRT 3)');

            // Alíquotas — em decimal (0.18 = 18%) pra evitar ambiguidade
            $table->decimal('aliquota_icms', 7, 4)->default(0);
            $table->decimal('aliquota_pis', 7, 4)->default(0);
            $table->decimal('aliquota_cofins', 7, 4)->default(0);
            $table->decimal('aliquota_ipi', 7, 4)->default(0);

            // Opcional: MVA (ICMS-ST), FCP (Fundo Combate Pobreza)
            $table->decimal('mva', 7, 4)->nullable();
            $table->decimal('fcp', 7, 4)->nullable();

            // Schema flexível ARQ-0004 (CBS/IBS reforma tributária etc.)
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Unique por combinação (business, ncm, uf_origem, uf_destino) — MySQL trata NULL
            // como distinct entre si, então idempotência fica no service (firstOrCreate)
            $table->index(['business_id', 'ncm'], 'nfe_fiscal_rules_biz_ncm_idx');
            $table->index(
                ['business_id', 'ncm', 'uf_origem', 'uf_destino'],
                'nfe_fiscal_rules_cascade_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_fiscal_rules');
    }
};
