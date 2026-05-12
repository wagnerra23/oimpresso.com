<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JanaCorrecao — sinal de treino (RLHF/few-shot) gerado pelo atendente
 * humano via `/corrigir` em nota interna (US-WA-075, ADR 0142 §3a).
 *
 * Cada row é um par (mensagem-errada do bot → correção-humana) pra:
 *   - export JSONL pra fine-tuning OpenAI/Anthropic
 *   - retrieval few-shot em tempo de inferência (`expected_response`)
 *   - dashboard `/copiloto/admin/correcoes-jana` (observability)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait HasBusinessScope. NUNCA usar `withoutGlobalScope` sem comentário
 * justificando.
 *
 * Append-only por convenção (correção é fato histórico — `training_status`
 * é a única coluna que muda durante o lifecycle export/applied).
 *
 * @property int $id
 * @property int $business_id
 * @property int $conversation_id
 * @property int $message_id_errada
 * @property string $correcao_texto
 * @property ?int $contact_id
 * @property int $atendente_user_id
 * @property string $training_status
 * @property ?array $metadata
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md §3a
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-075
 */
class JanaCorrecao extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_jana_correcoes';

    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_EXPORTED_FOR_FINE_TUNE = 'exported_for_fine_tune';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPLIED = 'applied';

    public const STATUSES = [
        self::STATUS_PENDING_REVIEW,
        self::STATUS_EXPORTED_FOR_FINE_TUNE,
        self::STATUS_REJECTED,
        self::STATUS_APPLIED,
    ];

    protected $fillable = [
        'business_id',
        'conversation_id',
        'message_id_errada',
        'correcao_texto',
        'contact_id',
        'atendente_user_id',
        'training_status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'business_id' => 'integer',
        'conversation_id' => 'integer',
        'message_id_errada' => 'integer',
        'contact_id' => 'integer',
        'atendente_user_id' => 'integer',
    ];

    public function mensagemErrada(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id_errada');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendente_user_id');
    }
}
