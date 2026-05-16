<?php

namespace Modules\SRS\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Wave 12 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 *
 * Tabela `docs_chat_messages` tem coluna `business_id` (migration
 * 2026_04_22_000005). Trait `HasBusinessScope` aplica global scope automático.
 * Conteúdo de chat passa por PiiRedactor antes de gravar (ADR 0094 §4).
 */
class DocChatMessage extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'docs_chat_messages';

    protected $fillable = [
        'business_id',
        'user_id',
        'session_id',
        'role',
        'content',
        'module_context',
        'sources',
        'mode',
        'tokens_used',
    ];

    protected $casts = [
        'sources' => 'array',
    ];

    /**
     * Auditoria LGPD — registra mudanças em mensagens de chat SRS.
     *
     * D7 LGPD compliance (audit trail append-only via activity_log).
     * Mensagens são append-only por design, mas log captura ediçōes/deleções
     * acidentais (anomalia auditável). Conteúdo full sanitizado via PiiRedactor
     * antes de chegar aqui (defesa em profundidade no ChatAssistant).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['business_id', 'user_id', 'session_id', 'role', 'module_context', 'mode', 'tokens_used'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
