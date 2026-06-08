<?php

namespace Modules\Jana\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Conversa — Jana chat session.
 *
 * Multi-tenant Tier 0 ([ADR 0093]) — `business_id` global scope via HasBusinessScope.
 *
 * D7 LGPD audit trail — Wave 10 (2026-05-16): LogsActivity registra mudanças
 * estruturais (titulo, status, iniciada_em) — NÃO loga conteúdo livre (que
 * vive em Mensagem). Stack IA canônica preservada ([ADR 0035]).
 */
class Conversa extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'jana_conversas';

    protected $fillable = [
        'business_id', 'user_id', 'titulo', 'status', 'iniciada_em',
    ];

    protected $casts = [
        'iniciada_em' => 'datetime',
    ];

    /**
     * D7 LGPD audit — logga apenas campos estruturais. PII em conteúdo é
     * mantida em Mensagem (append-only); aqui só rastreamos handshake da
     * conversa (titulo, status, iniciada_em). Append-only via activity_log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('jana_conversa')
            ->logOnly(['titulo', 'status', 'iniciada_em'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(Mensagem::class, 'conversa_id');
    }

    public function sugestoes(): HasMany
    {
        return $this->hasMany(Sugestao::class, 'conversa_id');
    }
}
