<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nuvemshop no catálogo de canais (ADR 0443, fatia a). Entra INATIVA: quem liga a integração é a
 * flag MARKETPLACES_NUVEMSHOP_ATIVO, decisão do [W] depois de criar o app de parceiro.
 * Idempotente e só insere, como a migration do catálogo (000002).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mkt_marketplaces') || DB::table('mkt_marketplaces')->where('slug', 'nuvemshop')->exists()) {
            return;
        }
        DB::table('mkt_marketplaces')->insert([
            'slug' => 'nuvemshop',
            'nome' => 'Nuvemshop',
            'api_base_url' => 'https://api.nuvemshop.com.br/2025-03',
            'oauth_authorize_url' => 'https://www.nuvemshop.com.br/apps/{app_id}/authorize',
            'oauth_token_url' => 'https://www.nuvemshop.com.br/apps/authorize/token',
            'docs_url' => 'https://tiendanube.github.io/api-documentation',
            'ativo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('mkt_marketplaces')) {
            DB::table('mkt_marketplaces')->where('slug', 'nuvemshop')->delete();
        }
    }
};
