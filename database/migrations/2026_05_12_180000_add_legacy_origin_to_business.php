<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-015 — Coluna `business.legacy_origin` (VARCHAR 32 nullable + INDEX).
 *
 * Marca a procedência do business pra customizar UX por origem legacy.
 * Valores enumerados (não FK — só metadata documental):
 *   - 'officeimpresso' — migrou do Delphi WR Comercial (gráficas legacy)
 *   - 'wr2'            — migrou do PontoWr2 legacy
 *   - 'cowork'         — onboarding via prototipo cowork (futuro)
 *   - null             — cliente novo, nasceu no oimpresso (default)
 *
 * Uso primário: HandleInertiaRequests::share('sells.viewMode.default')
 * roteia 'grade-avancada' (densa DevExpress-style) pra OfficeImpresso vs
 * 'lista' (Cockpit V2 enxuta) pra demais — ADR 0136.
 *
 * Coexiste com `business.is_officeimpresso` (boolean, mig 2024_11_07) que
 * controla acesso/bloqueio de licença do módulo desktop Officeimpresso —
 * conceitos diferentes:
 *   - is_officeimpresso          → cliente paga licença módulo desktop?
 *   - legacy_origin = 'officeimpresso' → veio do legado WR Comercial?
 *
 * Multi-tenant Tier 0 (ADR 0093): coluna fica em `business`, scope global
 * automático ao consultar via session('user.business_id').
 *
 * Migration idempotente (Schema::hasColumn guard) — ADR 0061 estilo.
 *
 * Refs:
 *   - ADR 0136 (Sells: split Lista vs Grade Avançada toggle)
 *   - ADR 0121 (modular especializado por vertical)
 *   - ADR 0105 (cliente como sinal qualificado)
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('business', 'legacy_origin')) {
            Schema::table('business', function (Blueprint $table) {
                $table->string('legacy_origin', 32)
                    ->nullable()
                    ->after('officeimpresso_bloqueado')
                    ->comment("Procedência legacy: 'officeimpresso'|'wr2'|'cowork'|null. Usado por HandleInertiaRequests pra default de viewMode da Lista de Vendas (ADR 0136).");

                $table->index('legacy_origin', 'business_legacy_origin_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('business', 'legacy_origin')) {
            Schema::table('business', function (Blueprint $table) {
                $table->dropIndex('business_legacy_origin_idx');
                $table->dropColumn('legacy_origin');
            });
        }
    }
};
