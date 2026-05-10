<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contrato canônico de side-effect FSM (ADR 0129 §Service).
 *
 * Implementações vivem em App\Domain\Fsm\SideEffects\* (ReservarEstoque,
 * ConsumirEstoque, EmitirNFeJob, etc — entregues em US-SELL-013/014/059).
 *
 * Multi-tenant Tier 0 (ADR 0093) obrigatório — side-effect que cria registros
 * em outras tabelas SEMPRE recebe `$businessId` no payload (nunca `session()`),
 * ou usa `$subject->business_id` direto.
 */
interface SideEffectInterface
{
    /**
     * Executa o efeito colateral. Roda dentro de `DB::transaction()` junto
     * com a mudança de stage (atomicidade). Lance exception pra abortar
     * transição (rollback automático).
     *
     * @param  Model  $subject  Entidade FSM (Transaction/JobSheet/McpTask/...)
     * @param  array<string, mixed>  $payload  Merge de side_effect_payload + extras runtime
     */
    public function execute(Model $subject, array $payload = []): void;
}
