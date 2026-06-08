<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business gate pra emissão automática de NFe/NFC-e.
 *
 * Antes deste fix: flag global `nfebrasil.auto_emission_on_sell_completed`
 * (env NFEBRASIL_AUTO_EMISSION_NFCE) ligava emissão pra TODOS os tenants
 * — viola princípio #6 da Constituição V2 (multi-tenant by default,
 * IRREVOGÁVEL — ADR 0093).
 *
 * Depois deste fix: tenant precisa OPT-IN explícito via
 * nfe_business_configs.auto_emission_enabled=true. Default false.
 *
 * UI toggle em /nfe-brasil/tributacao (Switch shadcn). Backend valida em
 * EmitirNfceAoFinalizarVenda + EmitirNFeAoReceberPagamento.
 *
 * Idempotência: hasColumn check pra rerun safe (ADR tech/0008 NfeBrasil).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_business_configs')) {
            return;
        }
        if (Schema::hasColumn('nfe_business_configs', 'auto_emission_enabled')) {
            return;
        }

        Schema::table('nfe_business_configs', function (Blueprint $table) {
            $table->boolean('auto_emission_enabled')
                ->default(false)
                ->after('regime')
                ->comment('Per-business gate: emite NFe/NFC-e auto quando true. Default false (opt-in explicito Wagner). ADR 0093 multi-tenant Tier 0.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfe_business_configs')) {
            return;
        }
        if (! Schema::hasColumn('nfe_business_configs', 'auto_emission_enabled')) {
            return;
        }

        Schema::table('nfe_business_configs', function (Blueprint $table) {
            $table->dropColumn('auto_emission_enabled');
        });
    }
};
