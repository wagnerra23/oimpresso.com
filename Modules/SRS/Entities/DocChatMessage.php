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
 *
 * @deprecated since 2026-07-29 (ADR 0357) — módulo em deprecação, remoção prevista em E5.
 *             Sucessor: `Modules\Jana` — `docs_chat_messages` MIGRA em E3 (tabela T3).
 *             ⚠️ Risco Tier 0 LGPD Art. 16: linhas legadas podem ter PII sem redação;
 *             a migração exige re-rodar o PiiRedactor antes de mover/arquivar.
 *             DEPRECATION-PLAN §Fase 2 item 17 + §Fase 3 T3. Não abrir feature nova aqui.
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
