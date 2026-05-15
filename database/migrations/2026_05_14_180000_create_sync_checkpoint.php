<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela `sync_checkpoint` — estado de cada importer dual-sync por (business_id, sync_type).
 *
 * Suporta o **daemon dual-sync** (Fase 1 MVP — Martinho Caçambas biz=164, semana 19/maio).
 *
 * Cada (business_id, sync_type) tem 1 linha que registra:
 *   - last_sync_at        : timestamp da última leitura Firebird (filtro WHERE DT_ALTERACAO > X)
 *   - last_codigo_processed: chunk resumability (importer pode retomar de onde parou)
 *   - last_status         : success/partial/failed/running (heartbeat semântico)
 *   - rows_processed      : contagem absoluta da última rodada (KPI daemon)
 *   - error_msg           : se last_status='failed', diagnóstico curto pra Wagner
 *
 * Lookup pattern do daemon:
 *   SELECT last_sync_at FROM sync_checkpoint WHERE business_id=? AND sync_type=?
 *   ↓
 *   importer aplica filtro Firebird: AND DT_ALTERACAO > 'YYYY-MM-DD HH:MM:SS'
 *   ↓
 *   sucesso: UPDATE sync_checkpoint SET last_sync_at=NOW(), last_status='success', rows_processed=N
 *
 * Default safe: se tabela não existe (migration não rodada ainda em prod), o importer faz
 * FULL SYNC com warn (não quebra — ADR proposal §3 default safe).
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` obrigatório + UNIQUE composto +
 * index `last_sync_at` pra dashboard. SEM FK pra business — daemon roda offline e migration
 * deve ser reversível sem cascade.
 *
 * Idempotente — `Schema::hasTable` guard.
 *
 * Refs:
 *   - memory/decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md §3 (architecture)
 *   - scripts/legacy-migration/daemon-sync-martinho.py (consumidor canônico)
 *   - ADR 0093 (multi-tenant Tier 0)
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('sync_checkpoint')) {
            Schema::create('sync_checkpoint', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id');
                $table->enum('sync_type', [
                    'contacts',
                    'contacts-fornecedores-nfe',
                    'financeiro',
                    'vendas',
                    'produtos',
                    'estoque',
                    'compras',
                    'vehicles',
                ]);
                $table->timestamp('last_sync_at')->nullable()
                    ->comment('Timestamp último sucesso — filtro Firebird WHERE DT_ALTERACAO > este valor');
                $table->string('last_codigo_processed', 64)->nullable()
                    ->comment('Chunk resumability — daemon retoma de CODIGO > last_codigo_processed após restart');
                $table->enum('last_status', ['success', 'partial', 'failed', 'running'])
                    ->default('success');
                $table->unsignedInteger('rows_processed')->default(0)
                    ->comment('Quantas rows o importer processou na última rodada (KPI dashboard)');
                $table->text('error_msg')->nullable()
                    ->comment('Diagnóstico curto se last_status=failed (Wagner debug)');
                $table->timestamps();

                $table->unique(['business_id', 'sync_type'], 'uniq_biz_type');
                $table->index('last_sync_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_checkpoint');
    }
};
