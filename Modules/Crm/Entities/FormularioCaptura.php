<?php

declare(strict_types=1);

namespace Modules\Crm\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Formulário público de captura de lead (US-CRM-096).
 *
 * Global scope de business (Tier 0, ADR 0093) para a leitura logada. Na rota PÚBLICA não há
 * usuário logado, o scope não filtra, e a empresa é resolvida pela `chave` — que é única.
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
    use HasBusinessScope;

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
