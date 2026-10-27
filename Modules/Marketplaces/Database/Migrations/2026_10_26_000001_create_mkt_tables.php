<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fundo do Modules/Marketplaces (US-MKT-001 · ADR 0439 · SPEC §3.1–3.4 e §3.6).
 *
 * Cria as 5 tabelas que o primeiro pedido ponta a ponta usa (catálogo, conta, anúncio, pedido,
 * log de webhook). As outras 4 do SPEC (§3.5 envios, §3.7 disputas, §3.8 regras de preço,
 * §3.9 reputação) nascem com as US que as usam (010, 014, 017, 016).
 *
 * Regras fixadas no banco:
 *  - `mkt_marketplaces` é catálogo GLOBAL (sem business_id): nome e URLs da API, iguais para
 *    toda empresa. O que é da empresa (token, anúncio, pedido) mora nas outras tabelas.
 *  - Nas demais, `business_id` NOT NULL, indexado e com FK (ADR 0093).
 *  - Tokens do vendedor em TEXT: o Model cifra com o cast `encrypted` (nunca em claro no banco).
 *  - Pedido único por (empresa, marketplace, id externo): webhook repetido não duplica venda.
 *  - `mkt_webhook_log` é APPEND-ONLY (trigger MySQL recusa UPDATE/DELETE). Processar um
 *    webhook grava linha nova com `superseded_id`, não altera a recebida.
 */
return new class extends Migration
{
    private const TABELAS = ['mkt_webhook_log', 'mkt_orders', 'mkt_listings', 'mkt_account_credentials', 'mkt_marketplaces'];

    public function up(): void
    {
        if (! Schema::hasTable('mkt_marketplaces')) {
            Schema::create('mkt_marketplaces', function (Blueprint $t) {
                $t->id();
                $t->string('slug', 50)->unique();
                $t->string('nome', 100);
                $t->string('api_base_url', 500)->nullable();
                $t->string('oauth_authorize_url', 500)->nullable();
                $t->string('oauth_token_url', 500)->nullable();
                $t->string('docs_url', 500)->nullable();
                $t->boolean('ativo')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('mkt_account_credentials')) {
            Schema::create('mkt_account_credentials', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('marketplace_id');
                $t->string('seller_id_external', 191);
                $t->string('seller_nickname', 191)->nullable();
                $t->text('access_token')->nullable();
                $t->text('refresh_token')->nullable();
                $t->dateTime('access_token_expires_at')->nullable();
                $t->dateTime('refresh_token_expires_at')->nullable();
                $t->json('oauth_state')->nullable();
                $t->dateTime('last_sync_at')->nullable();
                $t->dateTime('last_refresh_at')->nullable();
                $t->string('status', 20)->default('active')->comment('active|token_expired|revoked|suspended');
                $t->timestamps();
                $t->unique(['business_id', 'marketplace_id', 'seller_id_external'], 'mkt_acc_biz_mkt_seller_unique');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('marketplace_id')->references('id')->on('mkt_marketplaces');
            });
        }

        if (! Schema::hasTable('mkt_listings')) {
            Schema::create('mkt_listings', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('account_id');
                $t->unsignedBigInteger('marketplace_id');
                $t->unsignedInteger('product_id');
                $t->unsignedInteger('variation_id')->nullable();
                $t->string('item_id_external', 191);
                $t->string('title', 500)->nullable();
                $t->string('status', 20)->default('active')->comment('active|paused|closed|under_review|rejected');
                $t->string('listing_type', 50)->nullable();
                $t->decimal('price_marketplace', 15, 4)->nullable();
                $t->integer('stock_marketplace')->nullable();
                $t->string('category_external', 100)->nullable();
                $t->decimal('fee_estimate_pct', 5, 2)->nullable();
                $t->string('permalink', 500)->nullable();
                $t->json('attributes')->nullable();
                $t->dateTime('last_sync_at')->nullable();
                $t->text('sync_error')->nullable();
                $t->timestamps();
                $t->unique(['business_id', 'marketplace_id', 'item_id_external'], 'mkt_list_biz_mkt_item_unique');
                $t->index(['business_id', 'marketplace_id', 'status'], 'mkt_list_biz_mkt_status_idx');
                $t->index(['business_id', 'product_id'], 'mkt_list_biz_product_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('account_id')->references('id')->on('mkt_account_credentials');
                $t->foreign('marketplace_id')->references('id')->on('mkt_marketplaces');
                $t->foreign('product_id')->references('id')->on('products');
            });
        }

        if (! Schema::hasTable('mkt_orders')) {
            Schema::create('mkt_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('account_id');
                $t->unsignedBigInteger('marketplace_id');
                $t->string('order_id_external', 191);
                $t->unsignedInteger('transaction_id')->nullable()->index();
                $t->string('status_external', 100)->nullable();
                // Comprador só em hash (PII nunca em claro em log/PR — SPEC §13.5).
                $t->json('buyer_external')->nullable();
                $t->decimal('total_amount', 15, 4)->nullable();
                $t->decimal('total_fee', 15, 4)->nullable();
                $t->decimal('shipping_cost', 15, 4)->nullable();
                $t->string('shipping_type', 50)->nullable()->comment('me1|me2|me2_full|fba (D3 da ADR 0439)');
                $t->string('shipping_paid_by', 20)->nullable();
                $t->string('payment_method', 50)->nullable();
                $t->dateTime('date_created_external')->nullable();
                $t->dateTime('date_closed_external')->nullable();
                $t->dateTime('money_release_date')->nullable()->comment('quando o Mercado Pago libera (D6 da ADR 0439)');
                $t->json('raw_payload')->nullable();
                $t->dateTime('processed_at')->nullable();
                $t->timestamps();
                $t->unique(['business_id', 'marketplace_id', 'order_id_external'], 'mkt_ord_biz_mkt_ext_unique');
                $t->index(['business_id', 'status_external'], 'mkt_ord_biz_status_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('account_id')->references('id')->on('mkt_account_credentials');
                $t->foreign('marketplace_id')->references('id')->on('mkt_marketplaces');
                $t->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('mkt_webhook_log')) {
            Schema::create('mkt_webhook_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('marketplace_id');
                $t->string('topic', 100);
                $t->string('resource_external', 500)->nullable();
                $t->json('payload')->nullable();
                $t->boolean('signature_valid')->default(false);
                $t->string('processing_status', 20)->default('pending')->comment('pending|processed|failed|skipped');
                $t->text('error_message')->nullable();
                $t->unsignedBigInteger('superseded_id')->nullable()->comment('linha anterior que esta substitui');
                $t->dateTime('received_at');
                $t->timestamps();
                $t->index(['business_id', 'marketplace_id', 'processing_status'], 'mkt_wh_biz_mkt_status_idx');
                $t->index('received_at');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('marketplace_id')->references('id')->on('mkt_marketplaces');
            });
        }

        // Trigger só existe em MySQL; na lane sqlite o contrato do módulo faz skip (ADR 0358).
        if (DB::getDriverName() === 'mysql') {
            foreach (['UPDATE', 'DELETE'] as $op) {
                $nome = 'trg_mkt_webhook_log_no_'.strtolower($op);
                DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON mkt_webhook_log FOR EACH ROW
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'mkt_webhook_log e append-only. Correcao e linha nova com superseded_id.'");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_mkt_webhook_log_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_mkt_webhook_log_no_delete');
        }
        foreach (self::TABELAS as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
