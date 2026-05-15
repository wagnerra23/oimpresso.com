<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * US-WA-VOZ-003 — Performance scorecard de cada atendente WhatsApp.
 *
 * Eloquent Model da tabela `employee_performance`. Recompila daily via
 * `EmployeePerformanceRebuilder` agregando volume + velocidade + qualidade
 * + cobertura + nota 0-100 ponderada.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md))
 * via `HasBusinessScope`.
 *
 * Identidade flexível:
 *   - PRIMÁRIO: `user_id` (FK users.id) quando atendente responde via UI Inbox
 *   - FALLBACK: `heuristic_name` (string) detectado via `messages.body LIKE '*Nome:*'`
 *
 * @property int $id
 * @property int $business_id
 * @property ?int $user_id
 * @property ?string $heuristic_name
 * @property ?string $display_name
 * @property int $n_msgs_total
 * @property int $n_conversations_atendidas
 * @property int $n_clientes_diferentes
 * @property ?int $tempo_resposta_mediana_s
 * @property ?int $tempo_resposta_p90_s
 * @property int $sla_breach_count
 * @property int $reclamacoes_recebidas
 * @property ?float $csat_avg
 * @property int $horas_ativas_distintas
 * @property ?int $hora_pico
 * @property int $dias_ativos_30d
 * @property ?\Illuminate\Support\Carbon $primeira_atividade_at
 * @property ?\Illuminate\Support\Carbon $ultima_atividade_at
 * @property ?array $temas_dominantes
 * @property ?int $nota_geral
 * @property ?array $nota_breakdown
 * @property ?\Illuminate\Support\Carbon $nota_calculada_em
 * @property ?array $flags
 * @property ?\Illuminate\Support\Carbon $last_rebuilt_at
 * @property ?string $rebuilt_via
 */
class EmployeePerformance extends Model
{
    use HasBusinessScope;

    protected $table = 'employee_performance';

    // SLA padrão atendimento (segundos)
    public const SLA_FIRST_RESPONSE_SECONDS = 14400; // 4h

    // Origens rebuild
    public const REBUILT_VIA_BACKFILL = 'backfill';
    public const REBUILT_VIA_CRON_DAILY = 'cron_daily';
    public const REBUILT_VIA_MANUAL = 'manual';

    // Classificação da nota (faixas)
    public const NOTA_FAIXA_EXCELENTE = 90;
    public const NOTA_FAIXA_BOM = 70;
    public const NOTA_FAIXA_REGULAR = 50;

    protected $fillable = [
        'business_id', 'user_id', 'heuristic_name', 'display_name',
        'n_msgs_total', 'n_conversations_atendidas', 'n_clientes_diferentes',
        'tempo_resposta_mediana_s', 'tempo_resposta_p90_s', 'sla_breach_count',
        'reclamacoes_recebidas', 'csat_avg',
        'horas_ativas_distintas', 'hora_pico', 'dias_ativos_30d',
        'primeira_atividade_at', 'ultima_atividade_at',
        'temas_dominantes',
        'nota_geral', 'nota_breakdown', 'nota_calculada_em',
        'flags',
        'last_rebuilt_at', 'rebuilt_via',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'user_id' => 'integer',
        'n_msgs_total' => 'integer',
        'n_conversations_atendidas' => 'integer',
        'n_clientes_diferentes' => 'integer',
        'tempo_resposta_mediana_s' => 'integer',
        'tempo_resposta_p90_s' => 'integer',
        'sla_breach_count' => 'integer',
        'reclamacoes_recebidas' => 'integer',
        'csat_avg' => 'float',
        'horas_ativas_distintas' => 'integer',
        'hora_pico' => 'integer',
        'dias_ativos_30d' => 'integer',
        'primeira_atividade_at' => 'datetime',
        'ultima_atividade_at' => 'datetime',
        'temas_dominantes' => 'array',
        'nota_geral' => 'integer',
        'nota_breakdown' => 'array',
        'nota_calculada_em' => 'datetime',
        'flags' => 'array',
        'last_rebuilt_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Faixa textual da nota — pra UI Sidebar mostrar badge.
     */
    public function faixa(): string
    {
        $n = (int) ($this->nota_geral ?? 0);
        return match (true) {
            $n >= self::NOTA_FAIXA_EXCELENTE => 'excelente',
            $n >= self::NOTA_FAIXA_BOM => 'bom',
            $n >= self::NOTA_FAIXA_REGULAR => 'regular',
            default => 'abaixo',
        };
    }

    /**
     * Identidade legível pra logs e UI.
     */
    public function identidade(): string
    {
        if ($this->display_name) {
            return $this->display_name;
        }
        if ($this->user_id) {
            return "user_id={$this->user_id}";
        }
        if ($this->heuristic_name) {
            return "heur:{$this->heuristic_name}";
        }
        return 'unknown';
    }
}
