<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\SupportAccessLog;
use App\User;

/**
 * Modo Suporte (ADR 0305) — grava a trilha APPEND-ONLY de acesso do suporte (RF3).
 *
 * Cada entrada num tenant-cliente, e cada NEGAÇÃO (tentativa contra a operadora ou sem
 * capability), vira uma linha imutável em support_access_logs.
 *
 * A decisão PODE/NÃO-PODE é do {@see SupportAccessService} (service-direct, NÃO via Gate):
 * o `Gate::before` do superadmin (App\Providers\AuthServiceProvider) devolve `true` a
 * QUALQUER ability não-superadmin quando o usuário é `Admin#<business>` — então decidir
 * acesso de suporte por Gate vazaria a operadora pra qualquer admin de business. Por isso a
 * autoridade vive no service, e este audita o resultado.
 *
 * @see App\Services\Support\SupportAccessService
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */
class SupportAuditService
{
    public const ACTION_ENTROU = 'entrou';

    public const ACTION_NEGADO = 'negado';

    /** Fase A (ADR 0308): o agente passou a operar COMO um usuário do cliente (login-as). */
    public const ACTION_ACESSOU_COMO = 'acessou_como';

    public function record(
        User|int $supportUser,
        int $businessId,
        string $action,
        ?string $route = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?int $targetUserId = null,
    ): SupportAccessLog {
        $userId = $supportUser instanceof User ? (int) $supportUser->id : $supportUser;

        return SupportAccessLog::create([
            'support_user_id' => $userId,
            'business_id'     => $businessId,
            'target_user_id'  => $targetUserId,
            'action'          => $action,
            'route'           => $route,
            'ip'              => $ip,
            'user_agent'      => $userAgent,
        ]);
    }

    /** Entrada bem-sucedida num tenant-cliente. */
    public function recordAccess(User|int $supportUser, int $businessId, ?string $route = null, ?string $ip = null, ?string $userAgent = null): SupportAccessLog
    {
        return $this->record($supportUser, $businessId, self::ACTION_ENTROU, $route, $ip, $userAgent);
    }

    /** Tentativa NEGADA (ex. operadora ou sem capability) — também é auditada. */
    public function recordDenied(User|int $supportUser, int $businessId, ?string $route = null, ?string $ip = null, ?string $userAgent = null): SupportAccessLog
    {
        return $this->record($supportUser, $businessId, self::ACTION_NEGADO, $route, $ip, $userAgent);
    }

    /**
     * Fase A (ADR 0308): registra o início de uma impersonação (login-as) — quem · qual
     * usuário-alvo · qual empresa · quando. Gravado ANTES de trocar a identidade.
     */
    public function recordImpersonation(User|int $supportUser, int $businessId, int $targetUserId, ?string $route = null, ?string $ip = null, ?string $userAgent = null): SupportAccessLog
    {
        return $this->record($supportUser, $businessId, self::ACTION_ACESSOU_COMO, $route, $ip, $userAgent, $targetUserId);
    }
}
