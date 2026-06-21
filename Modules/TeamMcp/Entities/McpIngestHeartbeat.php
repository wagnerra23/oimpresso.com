<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * B-LIVE-HB (SDD · ADR 0278) — Heartbeat do ingest.
 *
 * 1 linha por `host`. Escrita (upsert) no caminho de sucesso de
 * POST /api/cc/ingest pelo CcIngestController do TeamMcp. Reader/liveness
 * service é tarefa SEPARADA (não cria aqui).
 *
 * Tier 0 ({@see ADR 0093}): SEM `business_id` e SEM `HasBusinessScope` —
 * cross-tenant by design, espelha {@see \Modules\Jana\Entities\Mcp\McpCcSession}
 * (heartbeat é sinal de infra/máquina, não de tenant). NUNCA adicionar global
 * scope de business aqui.
 *
 * @see Modules\TeamMcp\Http\Controllers\Mcp\CcIngestController (writer)
 *
 * @property string $host
 * @property \Illuminate\Support\Carbon|null $last_ingest_at
 * @property string|null $last_session_uuid
 * @property int $msgs_acc
 */
class McpIngestHeartbeat extends Model
{
    protected $table = 'mcp_ingest_heartbeat';

    protected $fillable = [
        'host',
        'last_ingest_at',
        'last_session_uuid',
        'msgs_acc',
    ];

    protected $casts = [
        'last_ingest_at' => 'datetime',
        'msgs_acc'       => 'integer',
    ];
}
