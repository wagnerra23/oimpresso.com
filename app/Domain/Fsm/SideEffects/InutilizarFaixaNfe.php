<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;

/**
 * US-SELL-030 — Side-effect "Inutilizar faixa de números fiscais NFe (SEFAZ)".
 *
 * Wrapper FSM que delega pro {@see NfeInutilizacaoService::inutilizar()} já
 * existente (231 linhas, completo). Razão de existir como SideEffect: permitir
 * que UI Sells dispare a inutilização via FSM action (botão dinâmico no
 * SaleSheet drawer com auditoria em sale_stage_history).
 *
 * Quando emissão `rejeitada`/`denegada`/`erro_envio` gera "buraco" no sequencial
 * fiscal (número foi pego no banco mas não autorizado pela SEFAZ), o caminho
 * fiscal correto é INUTILIZAR a faixa via processo SEFAZ próprio (cstat=102).
 * Sem isso, fechamento anual fiscal acusa gaps e gera multa.
 *
 * Payload obrigatório (validado pelo Service):
 *   - modelo: '55' | '65'
 *   - serie: string (1-3 chars)
 *   - numero_de: int ≥ 1
 *   - numero_ate: int ≥ numero_de
 *   - justificativa: string 15-255 chars (regra SEFAZ)
 *
 * Resolução de business_id (Tier 0 ADR 0093):
 *   - Se subject tem `business_id` (caso normal — Transaction via FSM): usa.
 *   - Senão: fallback pra `auth()->user()->business_id` (caso admin standalone
 *     via UI fiscal não-FSM — ainda assim Service valida cross-tenant guard
 *     contra `session('user.business_id')`).
 *
 * Multi-tenant Tier 0:
 *   Service já tem cross-tenant guard via session('user.business_id') —
 *   tentativa biz=99 inutilizar faixa biz=1 lança UnauthorizedActionException.
 *
 * Side-effects (delegados pro Service):
 *   - Persiste NfeInutilizacao (status=autorizado se cstat=102)
 *   - Marca emissões da faixa em nfe_emissoes como 'inutilizada' (preserva
 *     registro pra rastreabilidade — NUNCA forceDelete)
 *
 * Pain point Wagner 2026-05-12:
 *   "cancelam nota perdem número pula sequencial"
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - SPEC.md US-SELL-030
 *   - CONFAZ Ajuste SINIEF 07/2005 Art. 14
 *   - memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-01 G2
 */
class InutilizarFaixaNfe implements SideEffectInterface
{
    public function __construct(
        private readonly ?NfeInutilizacaoService $inutilizacaoService = null,
    ) {
        // Permite injeção (Container) E uso direto (new InutilizarFaixaNfe)
        // pra compat com chamadas via FSM ExecuteStageActionService.
    }

    public function execute(Model $subject, array $payload = []): void
    {
        $businessId = $this->resolveBusinessId($subject);

        // Validação rasa do payload — Service valida em profundidade e lança
        // InvalidArgumentException com mensagens instrutivas (regra SEFAZ).
        $modelo = (string) ($payload['modelo'] ?? '');
        $serie = (string) ($payload['serie'] ?? '');
        $numeroDe = isset($payload['numero_de']) ? (int) $payload['numero_de'] : 0;
        $numeroAte = isset($payload['numero_ate']) ? (int) $payload['numero_ate'] : 0;
        $justificativa = (string) ($payload['justificativa'] ?? '');

        if ($modelo === '' || $serie === '' || $numeroDe < 1 || $numeroAte < 1) {
            throw new InvalidArgumentException(
                'InutilizarFaixaNfe: payload incompleto. Obrigatórios: modelo, serie, ' .
                'numero_de, numero_ate, justificativa. Recebido: ' . json_encode(array_keys($payload))
            );
        }

        $service = $this->inutilizacaoService ?? app(NfeInutilizacaoService::class);

        try {
            $inutilizacao = $service->inutilizar(
                businessId: $businessId,
                modelo: $modelo,
                serie: $serie,
                numeroDe: $numeroDe,
                numeroAte: $numeroAte,
                justificativa: $justificativa,
            );

            Log::info('SideEffect InutilizarFaixaNfe: faixa processada', [
                'business_id' => $businessId,
                'subject_class' => $subject::class,
                'subject_id' => $subject->getKey(),
                'modelo' => $modelo,
                'serie' => $serie,
                'numero_de' => $numeroDe,
                'numero_ate' => $numeroAte,
                'inutilizacao_id' => $inutilizacao->id,
                'cstat' => $inutilizacao->cstat,
                'status' => $inutilizacao->status,
            ]);
        } catch (UnauthorizedActionException|InvalidArgumentException $e) {
            // Re-lança preservando exception type — caller (FSM Service) faz
            // rollback automático da transition via DB::transaction.
            Log::warning('SideEffect InutilizarFaixaNfe: validação rejeitada', [
                'business_id' => $businessId,
                'subject_id' => $subject->getKey(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Resolve business_id seguindo prioridade Tier 0:
     *   1. subject->business_id (caso FSM normal)
     *   2. auth()->user()->business_id (caso admin standalone via UI fiscal)
     *
     * Lança InvalidArgumentException se nenhum disponível — fail-fast pra
     * evitar inutilização sem tenancy resolvida.
     */
    private function resolveBusinessId(Model $subject): int
    {
        $bizFromSubject = $subject->business_id ?? null;
        if ($bizFromSubject !== null) {
            return (int) $bizFromSubject;
        }

        $bizFromAuth = auth()->user()?->business_id ?? null;
        if ($bizFromAuth !== null) {
            return (int) $bizFromAuth;
        }

        throw new InvalidArgumentException(
            'InutilizarFaixaNfe: business_id não resolvido. Subject sem business_id ' .
            'e nenhum user autenticado. Multi-tenant Tier 0 (ADR 0093) exige tenancy explícita.'
        );
    }
}
