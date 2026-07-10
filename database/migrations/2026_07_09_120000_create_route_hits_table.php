<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * route_hits — agregado diário de hits por rota (sinal "servido" de runtime).
 *
 * Alimentada em BATCH por `route-hits:flush` (cache → cá) — NUNCA por write
 * síncrono no request (contrato do middleware ContadorHitsRota). Consumida por
 * `route-hits:export` → governance/route-hits.json (ledger versionável) →
 * anchor-lint (4º veredito advisory `servido`) + charter-live-signal.
 *
 * SEM `business_id` — DELIBERADO, não esquecimento: telemetria OPERACIONAL
 * agregada (rota + data + contagem), zero dado de negócio, zero PII, zero
 * dimensão de tenant por decisão explícita (o export é público no git; tenant
 * na chave viraria vetor de vazamento de comportamento por cliente). Não é
 * "tabela de negócio" no sentido da regra Tier 0 (ADR 0093) — mesma classe
 * de `mcp_briefs`/telemetria. Idempotente + down() por contrato de migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('route_hits')) {
            return;
        }

        Schema::create('route_hits', function (Blueprint $table) {
            $table->id();
            $table->date('data');
            // identidade da rota: nome canônico Laravel OU URI-pattern
            // (`repair/{id}/edit`) — nunca URL resolvida (sem IDs/PII).
            $table->string('rota', 191);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();

            // nome explícito (<64 chars — regra identificadores MySQL)
            $table->unique(['data', 'rota'], 'route_hits_data_rota_unique');
            $table->index('rota', 'route_hits_rota_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_hits');
    }
};
