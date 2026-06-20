<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * HistoricoMemoriaService — TIME-TRAVEL na memoria Jana (ADR 0295, T4 slice 2).
 *
 * Responde "quais fatos eram EVENT-validos no instante T?" usando o event-time
 * (event_valid_from/until) adicionado no slice 1. O predicado puro vive em
 * BiTemporalResolver::vigenteEm() (testado no slice 1); aqui ele vira SQL.
 *
 * Por que Eloquent/SQL DIRETO e NUNCA Scout: MemoriaFato::shouldBeSearchable()
 * so indexa fatos ativos (valid_until = NULL). Time-travel busca justamente os
 * fatos SUPERSEDED (valid_until preenchido), que ficam FORA do index Meilisearch
 * — buscar via Scout voltaria vazio.
 */
class HistoricoMemoriaService
{
    /**
     * Fatos EVENT-validos em $asOf, scoped por business + user.
     *
     * Predicado event-time (espelha BiTemporalResolver::vigenteEm em SQL):
     *   (event_valid_from IS NULL OR event_valid_from <= asOf)   -- inicio inclusivo
     *   AND (event_valid_until IS NULL OR event_valid_until > asOf) -- fim exclusivo
     * null-from = "desde sempre"; null-until = "ainda vale"; legado (ambos null)
     * = sempre event-valido.
     *
     * Multi-tenant Tier 0 (ADR 0093): escapamos o ScopeByBusiness (MCP/CLI nao tem
     * session de business) e filtramos business_id + user_id EXPLICITOS. SoftDeletes
     * permanece (fatos esquecidos via LGPD ficam fora).
     *
     * @return Collection<int, MemoriaFato>
     */
    public function buscarHistorico(int $businessId, int $userId, mixed $asOf, int $limit = 5): Collection
    {
        $ts = self::normalizarAsOf($asOf);
        $limit = max(1, min(50, $limit));

        // SUPERADMIN/MCP: sem session de business, escape consciente do scope +
        // filtro manual business_id+user_id (ADR 0093). Ver HasBusinessScope.
        return MemoriaFato::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where(function ($q) use ($ts) {
                $q->whereNull('event_valid_from')
                    ->orWhere('event_valid_from', '<=', $ts);
            })
            ->where(function ($q) use ($ts) {
                $q->whereNull('event_valid_until')
                    ->orWhere('event_valid_until', '>', $ts);
            })
            ->orderByDesc('event_valid_from')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Normaliza o as_of pra Carbon. FAILSAFE — nunca lanca; null/''/lixo => agora
     * (time-travel sem instante de referencia = estado atual). Espelha o parse
     * tolerante do BiTemporalResolver.
     */
    public static function normalizarAsOf(mixed $asOf): Carbon
    {
        if ($asOf instanceof \DateTimeInterface) {
            return Carbon::instance($asOf);
        }
        if ($asOf === null || $asOf === '') {
            return Carbon::now();
        }
        try {
            return Carbon::parse((string) $asOf);
        } catch (\Throwable $e) {
            return Carbon::now();
        }
    }

    /**
     * Cross-tenant guard (espelha MemoriaSearchTool): true = VIOLACAO.
     * User comum so acessa o proprio business; superadmin acessa qualquer (ADR 0093).
     */
    public static function violacaoCrossTenant(?int $userBusinessId, int $alvoBusinessId, bool $isSuperadmin): bool
    {
        if ($isSuperadmin) {
            return false;
        }

        return $alvoBusinessId !== (int) $userBusinessId;
    }
}
