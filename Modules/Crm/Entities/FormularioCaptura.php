<?php

declare(strict_types=1);

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Formulário público de captura de lead (US-CRM-096).
 *
 * Sem global scope de business de propósito: a leitura acontece numa rota PÚBLICA, sem sessão,
 * e a empresa é resolvida pela `chave`. Quem consultar fora dessa rota filtra `business_id`
 * explicitamente (Tier 0, ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $chave
 * @property string $nome
 * @property int $owner_user_id
 * @property string|null $obrigado_url
 * @property bool $ativo
 */
class FormularioCaptura extends Model
{
    protected $table = 'crm_formularios_captura';

    protected $fillable = ['business_id', 'chave', 'nome', 'owner_user_id', 'obrigado_url', 'ativo'];

    protected $casts = ['business_id' => 'integer', 'owner_user_id' => 'integer', 'ativo' => 'boolean'];

    public static function novaChave(): string
    {
        return Str::lower(Str::random(40));
    }

    public static function ativoPelaChave(string $chave): ?self
    {
        if (! preg_match('/^[a-z0-9]{40}$/', $chave)) {
            return null;
        }

        return self::query()->where('chave', $chave)->where('ativo', true)->first();
    }
}
