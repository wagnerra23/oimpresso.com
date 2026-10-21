<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-071 · UC-FLX-32 — modo da contingência escolhido na ativação.
 *
 * `epec` | `svc-an` | `svc-rs` (Modules\NfeBrasil\Services\ModoContingencia). Nulo é o
 * comportamento de antes (EPEC na NF-e), para quem ativou antes de a escolha existir.
 *
 * Idempotente (Schema::hasColumn) + down() preserva a coluna (append-only, ADR 0093 G8).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_business_configs')) {
            return;
        }

        Schema::table('nfe_business_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_business_configs', 'contingencia_modo')) {
                $table->string('contingencia_modo', 10)
                    ->nullable()
                    ->comment('US-NFE-071: epec (tpEmis 4) | svc-an (6) | svc-rs (7). Nulo = epec. NFC-e segue off-line (9).');
            }
        });
    }

    public function down(): void
    {
        // Append-only (ADR 0093 Garantia 8): coluna preservada no rollback.
    }
};
