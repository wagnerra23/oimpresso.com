<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3 OS-V2-3 — Rastreamento do gate de aprovação no service_orders.
 *
 * O drawer (DviInlineEditor · `DviGateFoot`) ganha 4 estados (none → pending →
 * approved | declined). Esses estados DERIVAM do backend, não de simulação:
 *
 *   - `approval_requested_at` → carimbado em `enviarAprovacao` (status → orcamento +
 *     re-envio "Cobrar"). Distingue `none` de `pending`.
 *   - `approval_decided_at` + `approval_decision` → carimbados pelo fluxo público
 *     `AprovacaoOsController` quando o cliente aprova/recusa via WhatsApp + PIN.
 *     `approval_decision ∈ {approved, declined}` (NULL = sem decisão ainda).
 *
 * Antes desta migration o estado só dava pra inferir do `status` (orcamento/aprovada),
 * sem timestamp de "WhatsApp há X" nem caminho de `declined` persistido. O serviço
 * AprovacaoOsService já existia (token+PIN via cache); estas colunas complementam com
 * a TRILHA de aprovação que a UI precisa.
 *
 * Idempotente (pattern padrão módulo Wave 7+) + nullable (OS antigas ficam `none`).
 *
 * Multi-tenant Tier 0 [ADR 0093]: colunas per-OS, herdam o business_id da OS.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php::enviarAprovacao
 * @see Modules/OficinaAuto/Http/Controllers/Public/AprovacaoOsController.php
 * @see resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviInlineEditor.tsx
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('service_orders', 'approval_requested_at')) {
                $table->timestamp('approval_requested_at')
                    ->nullable()
                    ->after('delivered_at')
                    ->comment('F3 OS-V2-3 — quando o orçamento foi enviado pro cliente (gate pending). Re-carimbado a cada "Cobrar".');
            }

            if (! Schema::hasColumn('service_orders', 'approval_decided_at')) {
                $table->timestamp('approval_decided_at')
                    ->nullable()
                    ->after('approval_requested_at')
                    ->comment('F3 OS-V2-3 — quando o cliente decidiu (aprovou/recusou) via link público + PIN.');
            }

            if (! Schema::hasColumn('service_orders', 'approval_decision')) {
                $table->string('approval_decision', 20)
                    ->nullable()
                    ->after('approval_decided_at')
                    ->comment('F3 OS-V2-3 — decisão do cliente: approved | declined | NULL (sem decisão).');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            foreach (['approval_decision', 'approval_decided_at', 'approval_requested_at'] as $col) {
                if (Schema::hasColumn('service_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
