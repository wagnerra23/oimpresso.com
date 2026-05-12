<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Macro — superset de quick reply + ações múltiplas (US-WA-048).
 *
 * Pattern Chatwoot: vira funde "quick replies puras" (templates de
 * resposta rápida) e "automation actions" (tag/status/assign) num
 * único objeto operacional disparado via dropdown `/` no composer.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`.
 *
 * Schema de `actions_json` (suportado pelo `MacroExecutor`):
 *   [
 *     {"type": "add_tag", "tag_id": 3},
 *     {"type": "set_status", "status": "awaiting_human"},
 *     {"type": "assign_user", "user_id": 42}
 *   ]
 *
 * Tipos NÃO reconhecidos são silently dropped (forward-compat).
 *
 * @property int $id
 * @property int $business_id
 * @property string $label
 * @property ?string $shortcut         atalho slash opcional (ex: cnpj)
 * @property string $body              corpo cliente-facing
 * @property ?array $actions_json      ações pós-envio
 * @property ?int $created_by_user_id
 * @property int $used_count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Macro extends Model
{
    use HasBusinessScope;

    protected $table = 'macros';

    public const ACTION_ADD_TAG = 'add_tag';
    public const ACTION_SET_STATUS = 'set_status';
    public const ACTION_ASSIGN_USER = 'assign_user';

    public const ACTION_TYPES = [
        self::ACTION_ADD_TAG,
        self::ACTION_SET_STATUS,
        self::ACTION_ASSIGN_USER,
    ];

    protected $fillable = [
        'business_id',
        'label',
        'shortcut',
        'body',
        'actions_json',
        'created_by_user_id',
        'used_count',
    ];

    protected $casts = [
        'actions_json' => 'array',
        'used_count' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    /**
     * Normaliza shortcut — remove `/` líder, lowercases, trim. Permite
     * Wagner digitar `/cnpj`, `CNPJ` ou ` cnpj ` no form e gravar `cnpj`
     * canônico.
     */
    public static function normalizeShortcut(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $clean = ltrim(trim($raw), '/');
        $clean = mb_strtolower($clean);
        return $clean === '' ? null : $clean;
    }
}
