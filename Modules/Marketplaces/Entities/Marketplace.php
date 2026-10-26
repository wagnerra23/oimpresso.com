<?php

namespace Modules\Marketplaces\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo GLOBAL de marketplaces (mkt_marketplaces) — sem business_id por desenho: nome e URLs
 * da API são iguais para toda empresa. Nada de negócio de empresa mora aqui (ADR 0439).
 */
class Marketplace extends Model
{
    public const MERCADO_LIVRE = 'mercado_livre';

    protected $table = 'mkt_marketplaces';

    protected $fillable = ['slug', 'nome', 'api_base_url', 'oauth_authorize_url', 'oauth_token_url', 'docs_url', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];
}
