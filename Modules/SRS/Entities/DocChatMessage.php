<?php

namespace Modules\SRS\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocChatMessage extends Model
{
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
