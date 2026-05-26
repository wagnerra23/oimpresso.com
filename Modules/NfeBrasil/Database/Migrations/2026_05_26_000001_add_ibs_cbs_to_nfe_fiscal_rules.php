<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-FISCAL-004 / US-FISCAL-021 — Onda 6 IBS/CBS scaffold (Reforma Tributária NT 2025.002).
 *
 * Prazo regulatório (audit sênior 2026-05-25):
 *  - 2026-04-01: validação fields IBS/CBS pela Receita Federal
 *  - 2026-08-01: HIGHLIGHT CBS+IBS OBRIGATÓRIO NFe (CRT 3 Lucro Real/Presumido)
 *  - 2027-01-01: CBS substitui PIS+COFINS integral; IBS começa fase transição ICMS/ISS
 *
 * Esta migration adiciona 5 colunas em `nfe_fiscal_rules` pra suportar
 * cClassTrib + CST IBS + CST CBS + alíquotas IBS/CBS (NT 2025.002).
 *
 * Append-only ADR 0093 Garantia 8 — esquema cresce, nunca encolhe.
 * Idempotência via Schema::hasColumn — re-rodar não duplica.
 *
 * NÃO ativa IBS/CBS em produção — apenas prepara schema. Quando
 * `nfephp-org/sped-nfe` issue #1274 release versão com suporte,
 * MotorTributarioService passa a popular essas colunas.
 *
 * @see memory/requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md §GAP-FISCAL-004
 * @see https://github.com/nfephp-org/sped-nfe/issues/1274
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_fiscal_rules')) {
            return; // tabela base ausente — migration prerequisite não rodou
        }

        Schema::table('nfe_fiscal_rules', function (Blueprint $table) {
            // cClassTrib (Reforma Tributária NT 2025.002 grupo K)
            // Código de classificação tributária CBS/IBS — string 6 chars
            // catalogada CONFAZ. Exemplos: '000001' (Tributação Integral),
            // '100001' (Isenção), '200001' (Suspensão), etc.
            if (! Schema::hasColumn('nfe_fiscal_rules', 'c_class_trib')) {
                $table->char('c_class_trib', 6)->nullable()->after('cst')
                    ->comment('cClassTrib NT 2025.002 — classificação tributária IBS/CBS');
            }

            // CST IBS (Imposto sobre Bens e Serviços — estadual/municipal substitui ICMS+ISS)
            if (! Schema::hasColumn('nfe_fiscal_rules', 'cst_ibs')) {
                $table->char('cst_ibs', 3)->nullable()->after('c_class_trib')
                    ->comment('CST IBS — Código Situação Tributária IBS (NT 2025.002 Anexo I)');
            }

            // CST CBS (Contribuição sobre Bens e Serviços — federal substitui PIS+COFINS)
            if (! Schema::hasColumn('nfe_fiscal_rules', 'cst_cbs')) {
                $table->char('cst_cbs', 3)->nullable()->after('cst_ibs')
                    ->comment('CST CBS — Código Situação Tributária CBS (NT 2025.002 Anexo I)');
            }

            // Alíquotas em decimal (0.18 = 18%) — pattern já estabelecido pelas
            // outras alíquotas (aliquota_icms, aliquota_pis, etc).
            if (! Schema::hasColumn('nfe_fiscal_rules', 'aliquota_ibs')) {
                $table->decimal('aliquota_ibs', 7, 4)->default(0)->after('aliquota_ipi')
                    ->comment('Alíquota IBS — decimal (0.18 = 18%)');
            }

            if (! Schema::hasColumn('nfe_fiscal_rules', 'aliquota_cbs')) {
                $table->decimal('aliquota_cbs', 7, 4)->default(0)->after('aliquota_ibs')
                    ->comment('Alíquota CBS — decimal (0.009 = 0.9% highlight 2026)');
            }
        });
    }

    public function down(): void
    {
        // append-only (ADR 0093 Garantia 8) — não removemos colunas em down().
        // Manter columns mesmo em rollback evita perda de dados em re-up
        // após hotfix. Schema cresce, nunca encolhe.
    }
};
