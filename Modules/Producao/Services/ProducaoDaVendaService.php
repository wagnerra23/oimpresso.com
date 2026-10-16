<?php

namespace Modules\Producao\Services;

use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Models\StockReservation;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Scopes\ScopeByBusiness;
use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Programacao;

/**
 * Ponte PCP → etapa da venda (FSM `venda_com_producao`, ADR 0129). Decisão [W] 2026-10-16:
 * quando a última etapa do PCP da venda é concluída, o ERP executa `concluir_producao` pelo
 * executor normal — com o side effect `ConsumirEstoque` e a trilha em `sale_stage_history`.
 *
 * - Só a programação `os_tipo = venda` (os_id = transactions.id) liga o PCP à venda. `comvis_os`
 *   e os demais tipos não mexem na venda. Venda sem programação `venda` segue manual na web.
 * - "Última" = nenhuma etapa da venda em `pendente`/`iniciada` e ao menos uma `concluida`
 *   (venda com vários itens/OPs só conclui quando todas acabaram; `cancelada` não segura).
 * - Só roda com a venda em `in_production`. A linha da venda fica travada (`lockForUpdate`) e a
 *   etapa é relida dentro da trava: duas conclusões simultâneas nunca baixam estoque duas vezes.
 * - O ator é o operador que concluiu. Sem permissão para `concluir_producao` o RBAC NÃO é
 *   contornado: grava `conclusao_pendente` na trilha da venda, visível no histórico do pedido.
 * - Regra mestre de estoque: a trilha guarda o saldo antes → depois de cada variação baixada.
 *
 * `business_id` por parâmetro em toda consulta: roda em fila, sem sessão (ADR 0093).
 */
class ProducaoDaVendaService
{
    public const ORIGEM = 'pcp_automatico';
    public const TIPO_PENDENTE = 'conclusao_pendente';
    public const TIPO_IMPACTO = 'impacto_estoque';

    public function __construct(
        private readonly ExecuteStageActionService $fsm,
        private readonly StageActionPolicy $policy,
    ) {}

    /** Todas as etapas da venda no PCP terminaram? (sem nenhuma programação `venda`: false) */
    public function producaoTerminada(int $businessId, int $transactionId): bool
    {
        $status = Programacao::query()->withoutGlobalScope(ScopeByBusiness::class) // SUPERADMIN: fila sem sessão; business_id explícito
            ->where('business_id', $businessId)->where('os_tipo', Programacao::OS_VENDA)->where('os_id', $transactionId)
            ->pluck('status');

        return $status->contains('concluida') && ! $status->contains(fn ($s) => in_array($s, ['pendente', 'iniciada'], true));
    }

    /**
     * Executa `concluir_producao` se a produção da venda terminou e a venda está em produção.
     * Devolve o que fez: `concluida`, `pendente` (sem permissão) ou `nada`.
     */
    public function concluirSeTerminou(int $businessId, int $transactionId, int $userId, ?int $programacaoId = null): string
    {
        return DB::transaction(function () use ($businessId, $transactionId, $userId, $programacaoId): string {
            $venda = Transaction::query()->where('business_id', $businessId)->where('type', 'sell')
                ->lockForUpdate()->find($transactionId);
            if (! $venda || $this->etapaDaVenda($businessId, $venda) !== 'in_production'
                || ! $this->producaoTerminada($businessId, $transactionId)) {
                return 'nada';
            }

            $ator = User::query()->where('business_id', $businessId)->find($userId);
            $origem = ['origem' => self::ORIGEM, 'programacao_id' => $programacaoId, 'operador_id' => $userId];

            if (! $ator || ! $this->policy->canExecute($ator, $venda, 'concluir_producao')) {
                $this->pendente($businessId, $venda, $userId, $origem);

                return 'pendente';
            }

            $antes = $this->saldos($businessId, $transactionId);
            $h = $this->fsm->execute($venda, 'concluir_producao', $ator, $origem + [
                'motivo' => 'Concluída automaticamente: todas as etapas do PCP terminaram.',
            ]);
            $this->impacto($businessId, $venda, $h, $userId, $antes, $origem);

            return 'concluida';
        });
    }

    private function etapaDaVenda(int $businessId, Transaction $venda): ?string
    {
        if (! $venda->current_stage_id) {
            return null;
        }

        return DB::table('sale_process_stages as s')->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.business_id', $businessId)->where('s.id', $venda->current_stage_id)->value('s.key');
    }

    /** Uma só pendência por espera: se a última linha da trilha já é a pendência, não repete. */
    private function pendente(int $businessId, Transaction $venda, int $userId, array $origem): void
    {
        $ultima = SaleStageHistory::query()->withoutGlobalScope(ScopeByBusiness::class) // SUPERADMIN: fila sem sessão; business_id explícito
            ->where('business_id', $businessId)->where('transaction_id', $venda->id)->latest('id')->first();
        if (($ultima?->payload_snapshot['tipo'] ?? null) === self::TIPO_PENDENTE) {
            return;
        }

        SaleStageHistory::query()->withoutGlobalScope(ScopeByBusiness::class)->create([ // SUPERADMIN: fila sem sessão; business_id explícito
            'business_id' => $businessId, 'transaction_id' => $venda->id, 'action_id' => null,
            'from_stage_id' => $venda->current_stage_id, 'to_stage_id' => $venda->current_stage_id,
            'user_id' => $userId, 'executed_at' => now(),
            'payload_snapshot' => $origem + ['tipo' => self::TIPO_PENDENTE,
                'motivo' => 'Produção terminou no PCP; falta concluir a produção (o operador não tem permissão para concluir).'],
        ]);
    }

    /**
     * Saldo de cada variação que a baixa vai mexer (reservas ativas da venda).
     *
     * @return array<string, array{variation_id:int, location_id:int, reservado:float, antes:?float}>
     */
    private function saldos(int $businessId, int $transactionId): array
    {
        $linhas = [];
        $reservas = StockReservation::query()->withoutGlobalScope(ScopeByBusiness::class) // SUPERADMIN: fila sem sessão; business_id explícito
            ->where('business_id', $businessId)->where('transaction_id', $transactionId)
            ->where('status', StockReservation::STATUS_ACTIVE)->get();
        foreach ($reservas as $r) {
            $k = $r->variation_id.'|'.$r->location_id;
            $linhas[$k] ??= ['variation_id' => (int) $r->variation_id, 'location_id' => (int) $r->location_id, 'reservado' => 0.0,
                'antes' => $this->qtd((int) $r->variation_id, (int) $r->location_id)];
            $linhas[$k]['reservado'] += (float) $r->qty_reserved;
        }

        return $linhas;
    }

    private function qtd(int $variationId, int $locationId): ?float
    {
        if (! Schema::hasTable('variation_location_details')) {
            return null;
        }
        $v = DB::table('variation_location_details')->where('variation_id', $variationId)->where('location_id', $locationId)->value('qty_available');

        return $v === null ? null : (float) $v;
    }

    /** Linha à parte (a trilha é append-only): saldo medido antes e depois da baixa. */
    private function impacto(int $businessId, Transaction $venda, SaleStageHistory $h, int $userId, array $antes, array $origem): void
    {
        $itens = array_values(array_map(fn ($l) => $l + ['depois' => $this->qtd($l['variation_id'], $l['location_id'])], $antes));

        SaleStageHistory::query()->withoutGlobalScope(ScopeByBusiness::class)->create([ // SUPERADMIN: fila sem sessão; business_id explícito
            'business_id' => $businessId, 'transaction_id' => $venda->id, 'action_id' => null,
            'from_stage_id' => $h->to_stage_id, 'to_stage_id' => $h->to_stage_id,
            'user_id' => $userId, 'executed_at' => now(),
            'payload_snapshot' => $origem + ['tipo' => self::TIPO_IMPACTO, 'historico_id' => $h->id, 'estoque' => $itens,
                'motivo' => $itens === [] ? 'Conclusão automática sem baixa de estoque (venda sem reserva ativa).'
                    : 'Baixa de estoque da conclusão automática: saldo antes → depois por variação.'],
        ]);
    }
}
