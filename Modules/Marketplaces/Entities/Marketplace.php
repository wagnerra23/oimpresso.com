<?php

namespace Modules\Marketplaces\Entities;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Catálogo GLOBAL de marketplaces (mkt_marketplaces) — sem business_id por desenho: nome e URLs
 * da API são iguais para toda empresa. Nada de negócio de empresa mora aqui (ADR 0439).
 *
 * Exceção da ADR 0093 aprovada por [W] em 2026-10-26 e registrada em
 * `governance/multi-tenant-global-model-contract.json`. Como o catálogo vale para todas as
 * empresas, este Model NÃO escreve: criar, alterar ou apagar lança exceção. Só a migration de
 * seed do catálogo escreve, por `DB::table`, e nenhuma rota do módulo expõe escrita aqui.
 *
 * @property int $id
 * @property string $slug
 * @property string $nome
 * @property string|null $api_base_url
 * @property string|null $oauth_authorize_url
 * @property string|null $oauth_token_url
 * @property string|null $docs_url
 * @property bool $ativo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Marketplace extends Model
{
    public const MERCADO_LIVRE = 'mercado_livre';

    /** Segundo canal (ADR 0443). Token sem vencimento: não passa pela renovação do ML. */
    public const NUVEMSHOP = 'nuvemshop';

    protected $table = 'mkt_marketplaces';

    protected $fillable = ['slug', 'nome', 'api_base_url', 'oauth_authorize_url', 'oauth_token_url', 'docs_url', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    protected static function booted(): void
    {
        $somenteLeitura = static fn () => throw new LogicException('mkt_marketplaces é catálogo global somente leitura; só a migration de seed escreve nele.');
        static::creating($somenteLeitura);
        static::updating($somenteLeitura);
        static::deleting($somenteLeitura);
    }
}
