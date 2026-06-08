<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration vestuario_settings — primeira migration Modules/Vestuario.
 *
 * ADR 0121 §P7: ROTA LIVRE biz=4 (Larissa) roda há 2+ anos via núcleo
 * UltimatePOS + customizações pontuais espalhadas pelo código. Esta tabela
 * estabelece o ponto canônico pra encapsular quirks específicos do vertical
 * Vestuario progressivamente (sem quebrar prod).
 *
 * Sprint 1: schema mínimo (id + business_id + settings JSON + timestamps).
 * Sprint 2+: cada quirk migrado vira chave em settings JSON ou coluna dedicada.
 *
 * Quirks ROTA LIVRE conhecidos (NÃO migrar agora — mas mapear):
 * - format_date shift +3h ([ADR 0066](memory/decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md))
 *   PRESERVAR INTENCIONALMENTE — Larissa decorou. Migrar requer dados históricos.
 *   Sprint 5+ pode encapsular via JSON `format_date_shift_hours: 3` se ADR 0066
 *   for superseded.
 * - Monitor 1280px (designs precisam caber)
 * - format_date locale (auto-mem cliente_rotalivre.md)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * UNIQUE(business_id) — 1 row per business. FK cascade preserva integridade.
 *
 * @see memory/requisitos/Vestuario/SPEC.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P7
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('vestuario_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->unique();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('business_id', 'idx_vestuario_settings_business');

            // FK cascade — se business deletar, settings vão junto.
            // Comentado caso schema de business no UltimatePOS seja diferente
            // (Felipe valida em homolog).
            // $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vestuario_settings');
    }
};
