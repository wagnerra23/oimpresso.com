<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0202 (2026-05-27) — DROP tabela whatsapp_baileys_auth_state.
 *
 * Tabela persistia Baileys auth state (Noise keys, signed prekey, signed
 * identity, registration counter, etc.) cifrado AES-256-GCM via daemon Node.
 *
 * Pre-flight Fase 0 (2026-05-27): tabela 0 rows (AUTO_INCREMENT=5804 é só
 * rastro histórico de inserts/deletes). Daemon CT 100 já stopado + removido.
 *
 * Idempotente: dropIfExists.
 *
 * @see memory/decisions/0202-whatsapp-profissionalizacao-baileys-out.md
 * @see memory/sessions/2026-05-27-dossier-whatsapp-profissionalizacao.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('whatsapp_baileys_auth_state');
    }

    public function down(): void
    {
        // Recria schema canônico (raw SQL pq cria UNIQUE composto + KEY index
        // com ON UPDATE CURRENT_TIMESTAMP que Blueprint helper Laravel não
        // expressa idiomaticamente).
        if (!Schema::hasTable('whatsapp_baileys_auth_state')) {
            DB::statement('
                CREATE TABLE whatsapp_baileys_auth_state (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    instance_id VARCHAR(100) NOT NULL,
                    key_id VARCHAR(200) NOT NULL,
                    value_encrypted MEDIUMTEXT NOT NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY wa_auth_state_uniq (instance_id, key_id),
                    KEY wa_auth_state_instance_idx (instance_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        }
    }
};
