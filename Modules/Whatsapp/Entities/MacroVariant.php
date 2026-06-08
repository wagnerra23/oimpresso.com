<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MacroVariant — variante A/B de uma Macro HSM (US-WA-049, gap P2 #18).
 *
 * Pattern Take Blip A/B testing — cada Macro pode ter N variants
 * (label + body override + weight). `MacroVariantPicker` sorteia variante
 * por weighted random no apply, e métricas `sent_count`/`response_count`
 * permitem comparar taxa de resposta entre variantes.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`.
 *
 * Forward-compat:
 *   - `weight=0` permite "pausar" variante sem deletar (sorteio nunca pega).
 *   - `active=false` exclui da loteria mas preserva histórico de uso.
 *   - response_rate é derivada (sent>0 ? response/sent : null) — não persistida.
 *
 * @property int $id
 * @property int $business_id
 * @property int $macro_id
 * @property string $label
 * @property string $body
 * @property int $weight                 1-100 (0=pausa)
 * @property bool $active
 * @property int $sent_count
 * @property int $response_count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class MacroVariant extends Model
{
    use HasBusinessScope;

    protected $table = 'macro_variants';

    /** Limites canônicos pra validação Controller + UI. */
    public const WEIGHT_MIN = 0;
    public const WEIGHT_MAX = 100;

    /** Janela de tracking de response em segundos (24h). */
    public const RESPONSE_WINDOW_SECONDS = 24 * 3600;

    protected $fillable = [
        'business_id',
        'macro_id',
        'label',
        'body',
        'weight',
        'active',
        'sent_count',
        'response_count',
    ];

    protected $casts = [
        'weight' => 'integer',
        'active' => 'boolean',
        'sent_count' => 'integer',
        'response_count' => 'integer',
    ];

    public function macro(): BelongsTo
    {
        return $this->belongsTo(Macro::class, 'macro_id');
    }

    /**
     * Taxa de resposta derivada (não persistida).
     * Retorna float 0.0-1.0 OU null quando sent_count=0 (sem amostra).
     */
    public function responseRate(): ?float
    {
        if ($this->sent_count <= 0) {
            return null;
        }
        return $this->response_count / $this->sent_count;
    }
}
