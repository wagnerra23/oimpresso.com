<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use App\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ClientFeedback — captura canônica de feedback de cliente real via WhatsApp inbox.
 *
 * Wagner 2026-05-27. Refs ADR UI-0016 (design contextualizado por persona),
 * ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL), ADR 0105 (cliente-como-sinal).
 *
 * Capture flow:
 *   1. Wagner clica "📋 Feedback" em mensagem do inbox
 *   2. Sheet 760px abre pré-preenchido (persona detectada via phone match)
 *   3. Wagner edita 1-2 campos (severity, JTBD) e salva
 *   4. Severity ≥ 3 → cria MCP task automaticamente
 *   5. Job semanal exporta digest pra git canon (memory/clientes/<x>/feedback/*.md)
 *
 * Source-of-truth: MySQL (real-time low latency).
 * Propagação MCP: digest semanal via Schedule.
 *
 * PII Tier 0 (literal contém texto cru do cliente — LGPD).
 *
 * @property int $id
 * @property int $business_id
 * @property ?int $contact_id
 * @property ?int $source_message_id
 * @property ?int $conversation_id
 * @property ?string $persona_slug
 * @property ?string $cliente_slug
 * @property string $canal
 * @property string $literal
 * @property ?string $contexto
 * @property ?string $modulo_afetado
 * @property ?string $tela_afetada
 * @property ?string $acao_afetada
 * @property ?string $job
 * @property ?string $motivacao_tipo
 * @property ?string $workaround_o_que_faz
 * @property ?string $workaround_custo
 * @property int $severity_nng
 * @property bool $primeira_vez
 * @property int $recorrente_count
 * @property bool $pattern_emergente
 * @property string $status
 * @property ?string $responder_cliente
 * @property ?string $mcp_task_id
 * @property bool $dev_task_requested
 * @property ?string $signature
 * @property float $relevance_score
 * @property ?\Carbon\Carbon $relevance_score_at
 * @property ?\Carbon\Carbon $last_seen_at
 * @property ?\Carbon\Carbon $data_resolvido
 * @property ?string $pr_link
 * @property ?bool $cliente_confirmou
 * @property bool $re_reclamacao
 * @property ?int $created_by
 */
class ClientFeedback extends Model
{
    use HasBusinessScope;       // ADR 0093 global scope business_id
    use LogsActivity;           // D7 LGPD audit Spatie
    use SoftDeletes;

    protected $table = 'clients_feedbacks';

    public const STATUS_NOVO = 'novo';
    public const STATUS_TRIAGED = 'triaged';
    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_NOVO, self::STATUS_TRIAGED, self::STATUS_BACKLOG,
        self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED,
    ];

    public const SEVERITY_LABELS = [
        0 => 'Não é problema (wish-list)',
        1 => 'Cosmético (chato mas convive)',
        2 => 'Minor (problema real, tem workaround)',
        3 => 'Major (impede tarefa frequente)',
        4 => 'Catastrófico (bloqueia uso)',
    ];

    protected $fillable = [
        'business_id', 'contact_id', 'source_message_id', 'conversation_id',
        'persona_slug', 'cliente_slug',
        'canal', 'literal', 'contexto',
        'modulo_afetado', 'tela_afetada', 'acao_afetada',
        'job', 'motivacao_tipo',
        'workaround_o_que_faz', 'workaround_custo',
        'severity_nng', 'primeira_vez', 'recorrente_count', 'pattern_emergente',
        'status', 'responder_cliente',
        'mcp_task_id', 'dev_task_requested',
        'signature', 'relevance_score', 'relevance_score_at', 'last_seen_at',
        'data_resolvido', 'pr_link', 'cliente_confirmou', 're_reclamacao',
        'created_by',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'contact_id' => 'integer',
        'source_message_id' => 'integer',
        'conversation_id' => 'integer',
        'severity_nng' => 'integer',
        'primeira_vez' => 'boolean',
        'recorrente_count' => 'integer',
        'pattern_emergente' => 'boolean',
        'data_resolvido' => 'datetime',
        'cliente_confirmou' => 'boolean',
        're_reclamacao' => 'boolean',
        'dev_task_requested' => 'boolean',
        'relevance_score' => 'decimal:2',
        'relevance_score_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'created_by' => 'integer',
    ];

    /**
     * D7 LGPD audit (Spatie\Activitylog).
     * Loga mudanças de status, severity, resolução — campos que afetam decisão.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'severity_nng', 'cliente_confirmou', 're_reclamacao',
                'data_resolvido', 'pr_link', 'mcp_task_id', 'dev_task_requested',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('client_feedback');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * Severity label PT-BR pra UI.
     */
    public function getSeverityLabelAttribute(): string
    {
        return self::SEVERITY_LABELS[$this->severity_nng] ?? 'Desconhecido';
    }

    /**
     * Quando severity ≥ 3, MCP task deve existir.
     */
    public function shouldHaveMcpTask(): bool
    {
        return $this->severity_nng >= 3;
    }

    /**
     * Scope HOT: relevance_score >= 70 (camada in-context, INDEX.md).
     */
    public function scopeHot($query)
    {
        return $query->where('relevance_score', '>=', 70);
    }

    /**
     * Scope WARM: 30 <= relevance_score < 70.
     */
    public function scopeWarm($query)
    {
        return $query->whereBetween('relevance_score', [30, 69.99]);
    }

    /**
     * Scope COLD: relevance_score < 30 OU resolved/closed >= 90d.
     */
    public function scopeCold($query)
    {
        return $query->where(function ($q) {
            $q->where('relevance_score', '<', 30)
              ->orWhere(function ($qq) {
                  $qq->whereIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED])
                     ->where('updated_at', '<', now()->subDays(90));
              });
        });
    }

    /**
     * Sinal qualificado ADR 0105: feedback elegível pra virar dev task.
     *
     * Regra: contact_id deve apontar pra um Contact com type ∈ {customer, both}.
     * Lead não qualifica (ADR 0105 — backlog só recebe sinal de quem paga ou
     * tem track-record). Severity mínima 2 (Minor — problema real com workaround).
     */
    public function qualifiesForDevTask(): array
    {
        if ($this->severity_nng < 2) {
            return [
                'ok' => false,
                'reason' => 'severity_below_threshold',
                'message' => 'Severity < 2 — backlog não recebe wish-list sem dor real.',
            ];
        }

        if (! $this->contact_id) {
            return [
                'ok' => false,
                'reason' => 'no_contact_linked',
                'message' => 'Mensagem sem Contact vinculado — vincule a um cliente antes.',
            ];
        }

        // SUPERADMIN: qualificação pode rodar via CLI/job sem session — resolve o
        // Contact vinculado (relação FK do próprio feedback, mesmo business) pra
        // checar type. Lookup pela relação contact_id, sem leak cross-tenant. ADR 0093.
        $contact = $this->contact()->withoutGlobalScopes()->first();
        if (! $contact) {
            return [
                'ok' => false,
                'reason' => 'contact_not_found',
                'message' => 'Contact vinculado não encontrado.',
            ];
        }

        if (! in_array($contact->type, ['customer', 'both'], true)) {
            return [
                'ok' => false,
                'reason' => 'not_paying_customer',
                'message' => 'ADR 0105 — backlog só recebe sinal de cliente pagante (Contact.type ∈ customer|both).',
            ];
        }

        return ['ok' => true, 'reason' => null, 'message' => null];
    }
}
