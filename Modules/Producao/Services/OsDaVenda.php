<?php

namespace Modules\Producao\Services;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;

/**
 * As OS por item de uma venda (ADR 0436) e a guarda "Concluir exige OS pronta" do processo de venda
 * do protótipo (fluxos-processos.jsx: Faturado → Entregue, "OS pronta ou entregue").
 *
 * A guarda vale só para venda em processo de MODELO entrando na etapa `entregue`; o legado segue como
 * hoje. Venda sem OS passa. OS cancelada não segura. Tier 0: só as OS da empresa da venda.
 */
final class OsDaVenda
{
    public const ETAPA_ENTREGUE = 'entregue';

    /** @return Collection<int, Ordem> */
    public function daVenda(int $businessId, int $transactionId): Collection
    {
        return Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)->with('currentStage')->orderBy('id')->get();
    }

    /** @return array{total:int, prontas:int} OS da venda e quantas estão concluídas (cancelada não conta) */
    public function placar(int $businessId, int $transactionId): array
    {
        $vivas = $this->daVenda($businessId, $transactionId)->reject(fn (Ordem $o) => $o->currentStage?->key === 'cancelada');

        return ['total' => $vivas->count(), 'prontas' => $vivas->filter(fn (Ordem $o) => $o->currentStage?->key === 'concluida')->count()];
    }

    /** Chamado antes de a venda mudar de etapa: barra Entregue com OS por terminar. */
    public function guardar(int $businessId, int $transactionId, ?int $novaEtapaId): void
    {
        if ($novaEtapaId === null) {
            return;
        }
        $etapa = DB::table('sale_process_stages as s')->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('s.id', $novaEtapaId)->where('p.business_id', $businessId)->first(['s.key', 'p.dominio', 'p.modelo']);
        if (! $etapa || $etapa->key !== self::ETAPA_ENTREGUE || $etapa->dominio !== 'venda' || in_array($etapa->modelo, [null, 'legado'], true)) {
            return;
        }
        ['total' => $total, 'prontas' => $prontas] = $this->placar($businessId, $transactionId);
        if ($prontas < $total) {
            throw new UnauthorizedActionException("A venda só passa para Entregue com as OS prontas: {$prontas} de {$total} prontas.");
        }
    }
}
