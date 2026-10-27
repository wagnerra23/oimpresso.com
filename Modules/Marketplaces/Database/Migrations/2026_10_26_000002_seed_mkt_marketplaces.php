<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de marketplaces (US-MKT-002 · ADR 0439). Dado de referência global (igual para toda
 * empresa), por isso entra por migration idempotente e não por seeder manual: o deploy já o
 * aplica. Mercado Livre ativo; Shopee e Amazon cadastrados e inativos até seus drivers (D1/§2).
 */
return new class extends Migration
{
    private const CATALOGO = [
        [
            'slug' => 'mercado_livre',
            'nome' => 'Mercado Livre',
            'api_base_url' => 'https://api.mercadolibre.com',
            'oauth_authorize_url' => 'https://auth.mercadolivre.com.br/authorization',
            'oauth_token_url' => 'https://api.mercadolibre.com/oauth/token',
            'docs_url' => 'https://developers.mercadolivre.com.br',
            'ativo' => true,
        ],
        [
            'slug' => 'shopee',
            'nome' => 'Shopee',
            'api_base_url' => 'https://partner.shopeemobile.com',
            'oauth_authorize_url' => null,
            'oauth_token_url' => null,
            'docs_url' => 'https://open.shopee.com',
            'ativo' => false,
        ],
        [
            'slug' => 'amazon_br',
            'nome' => 'Amazon Brasil',
            'api_base_url' => 'https://sellingpartnerapi-na.amazon.com',
            'oauth_authorize_url' => null,
            'oauth_token_url' => 'https://api.amazon.com/auth/o2/token',
            'docs_url' => 'https://developer-docs.amazon.com/sp-api',
            'ativo' => false,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('mkt_marketplaces')) {
            return;
        }
        foreach (self::CATALOGO as $linha) {
            // Só insere: não sobrescreve `ativo` que o superadmin tenha mudado depois.
            if (! DB::table('mkt_marketplaces')->where('slug', $linha['slug'])->exists()) {
                DB::table('mkt_marketplaces')->insert($linha + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mkt_marketplaces')) {
            DB::table('mkt_marketplaces')->whereIn('slug', array_column(self::CATALOGO, 'slug'))->delete();
        }
    }
};
