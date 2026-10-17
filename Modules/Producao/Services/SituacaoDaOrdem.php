<?php

namespace Modules\Producao\Services;

use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Scopes\ScopeByBusiness;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Situação da OS por item (ADR 0436) — liga o apontamento do operador e as ações de tela ao processo
 * "OS e produção por item" do motor FSM. Fonte: fluxos-processos.jsx (id producao) + #9517.
 *
 * Apontamento (só para `os_tipo = pcp_ordem`): iniciar na fila → Iniciar etapa; iniciar pausada →
 * Retomar; pausar a última etapa rodando → Pausar; concluir etapa → Concluir etapa (registra, não muda
 * a situação). OS não liberada, em retrabalho ou encerrada não aponta (409). Etapa de um bloco só
 * começa quando o bloco anterior inteiro (as duas trilhas) terminou.
 *
 * Tela: Liberar, Reprovar no controle (reabre a etapa de origem como etapa nova — "retrabalho no mesmo
 * posto é outra etapa", ApontamentoService), Refazer, Cancelar item. Tier 0: tudo por `$businessId`.
 */
final class SituacaoDaOrdem
{
    public const EV_RETRABALHO = 'retrabalho_aberto';

    public function __construct(private readonly ExecuteStageActionService $fsm, private readonly CustoRealService $custo) {}

    public function ordem(int $businessId, int $ordemId): Ordem
    {
        return Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('currentStage')->find($ordemId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada.');
    }

    /** Antes de iniciar uma etapa da OS: recusa o que o protótipo não deixa apontar. */
    public function antesDeIniciar(int $businessId, Programacao $etapa): void
    {
        if ($etapa->os_tipo !== Ordem::OS_TIPO) {
            return;
        }
        $situacao = $this->ordem($businessId, (int) $etapa->os_id)->currentStage?->key;
        $recusa = match ($situacao) {
            'aguardando_liberacao' => 'Esta OS ainda não foi liberada para a produção.',
            'retrabalho' => 'Esta OS está em retrabalho: o PCP precisa mandar refazer.',
            'concluida', 'cancelada' => 'Esta OS já foi encerrada.',
            default => null,
        };
        if ($recusa !== null) {
            throw new ApontamentoRecusado(ApontamentoRecusado::OS_NAO_LIBERADA, $recusa, ['programacao_id' => $etapa->id, 'situacao' => $situacao]);
        }
        $antes = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $etapa->os_id)
            ->where('bloco', '<', (int) $etapa->bloco)->whereNotIn('status', ['concluida', 'cancelada'])->orderBy('bloco')->value('nome');
        if ($antes !== null) {
            throw new ApontamentoRecusado(ApontamentoRecusado::OS_NAO_LIBERADA, "Falta terminar a etapa anterior: {$antes}.", ['programacao_id' => $etapa->id]);
        }
    }

    public function aoIniciar(int $businessId, Programacao $etapa, int $userId): void
    {
        if ($etapa->os_tipo !== Ordem::OS_TIPO) {
            return;
        }
        $ordem = $this->ordem($businessId, (int) $etapa->os_id);
        $acao = ['na_fila' => 'iniciar_etapa', 'pausada' => 'retomar'][$ordem->currentStage?->key] ?? null;
        if ($acao !== null) {
            $this->fsm->execute($ordem, $acao, User::find($userId), ['programacao_id' => $etapa->id]);
        }
    }

    /** A OS só fica Pausada quando nenhuma etapa dela segue rodando (a outra trilha pode continuar). */
    public function aoPausar(int $businessId, Apontamento $pausa, int $userId): void
    {
        if ($pausa->os_tipo !== Ordem::OS_TIPO) {
            return;
        }
        $ordem = $this->ordem($businessId, (int) $pausa->os_id);
        if ($ordem->currentStage?->key !== 'em_execucao' || $this->rodando($businessId, (int) $ordem->id)) {
            return;
        }
        $this->fsm->execute($ordem, 'pausar', User::find($userId), ['motivo_id' => $pausa->motivo_id, 'programacao_id' => $pausa->programacao_id]);
    }

    public function aoConcluirEtapa(int $businessId, Programacao $etapa, int $userId): void
    {
        if ($etapa->os_tipo !== Ordem::OS_TIPO) {
            return;
        }
        $ordem = $this->ordem($businessId, (int) $etapa->os_id);
        if ($ordem->currentStage?->key === 'em_execucao') {
            $this->fsm->execute($ordem, 'concluir_etapa', User::find($userId), ['programacao_id' => $etapa->id]);
        }
        $this->concluirItemSeTerminou($businessId, (int) $ordem->id, $userId);
    }

    /**
     * Concluir item (última etapa do roteiro feita): a OS fica Concluída, pronta, com o custo real
     * CONGELADO (CustoRealService — horas apontadas × custo-hora copiado no início; regra mestre de
     * valor). Trava a OS e relê a situação: duas conclusões ao mesmo tempo concluem uma vez só.
     * A baixa do material real NÃO acontece aqui: o ERP ainda não registra o consumo real por OS
     * (decisão pendente do [W], ADR 0436).
     */
    public function concluirItemSeTerminou(int $businessId, int $ordemId, int $userId): bool
    {
        return DB::transaction(function () use ($businessId, $ordemId, $userId): bool {
            $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->lockForUpdate()->find($ordemId);
            $status = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordemId)->pluck('status');
            if (! $ordem || $ordem->currentStage?->key !== 'em_execucao' || ! $status->contains('concluida')
                || $status->contains(fn ($s) => in_array($s, ['pendente', 'iniciada'], true))) {
                return false;
            }
            $custo = $this->custo->daOs($businessId, Ordem::OS_TIPO, $ordemId);
            $this->fsm->execute($ordem, 'concluir_item', User::find($userId), ['custo' => $custo]);
            $ordem->forceFill(['pronta_em' => now(), 'custo_real' => $custo['total'], 'custo_congelado_em' => now()])->save();

            return true;
        });
    }

    /**
     * Liberar: o PCP escolhe o responsável de cada etapa aberta (decisão [W] 16/10, opção c; #9530).
     * `$responsaveis` = programação → usuário. Etapa já com responsável o mantém se não vier no mapa;
     * etapa aberta sem responsável recusa a liberação. Responsável tem de ser da MESMA empresa (Tier 0).
     *
     * @param  array<int, int>  $responsaveis
     */
    public function liberar(int $businessId, int $ordemId, User $user, array $responsaveis = []): Ordem
    {
        $ordem = $this->ordem($businessId, $ordemId);

        return DB::transaction(function () use ($businessId, $ordem, $user, $responsaveis) {
            $abertas = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordem->id)
                ->where('status', 'pendente')->get()->keyBy('id');
            $fora = array_diff(array_keys($responsaveis), $abertas->keys()->all());
            if ($fora !== []) {
                throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO, 'Etapa que não é desta OS ou já começou.', ['programacao_id' => array_values($fora)]);
            }
            $validos = DB::table('users')->where('business_id', $businessId)->whereIn('id', array_values($responsaveis))->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($responsaveis as $prog => $uid) {
                if (! in_array((int) $uid, $validos, true)) {
                    throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO, 'Responsável não encontrado nesta empresa.', ['programacao_id' => $prog]);
                }
            }
            $sem = $abertas->filter(fn (Programacao $p) => ! isset($responsaveis[$p->id]) && $p->user_id === null)->pluck('nome')->all();
            if ($sem !== []) {
                throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO, 'Escolha o responsável de: '.implode(', ', $sem).'.');
            }

            $this->fsm->execute($ordem, 'liberar', $user, ['responsaveis' => $responsaveis]);
            foreach ($responsaveis as $prog => $uid) {
                $abertas[$prog]->forceFill(['user_id' => (int) $uid])->save();
            }

            return $ordem->fresh();
        });
    }

    /** Reprovar no controle: motivo de refugo do cadastro + etapa de origem, que volta como etapa nova. */
    public function reprovar(int $businessId, int $ordemId, int $origemId, int $motivoId, User $user): Ordem
    {
        $ordem = $this->ordem($businessId, $ordemId);
        $origem = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordem->id)
            ->where('status', 'concluida')->find($origemId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'A etapa de origem tem de ser uma etapa já feita desta OS.');
        // Lista fechada por posto (UC-PCV-33): o motivo é do posto da etapa de origem.
        $motivo = DB::table('pcp_motivos')->where('business_id', $businessId)->where('tipo', 'refugo')->where('ativo', true)->where('id', $motivoId)
            ->when($origem->posto_id, fn ($q) => $q->where('posto_id', $origem->posto_id))->value('nome')
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::MOTIVO_OBRIGATORIO, 'Escolha um motivo de refugo do posto da etapa.');

        return DB::transaction(function () use ($businessId, $ordem, $origem, $motivo, $motivoId, $user) {
            $this->fsm->execute($ordem, 'reprovar', $user, ['origem_programacao_id' => $origem->id, 'motivo_id' => $motivoId]);
            $nova = $origem->replicate(['status', 'created_at', 'updated_at']);
            $nova->fill(['status' => 'pendente', 'nome' => $origem->nome.' (retrabalho)', 'inicio_previsto' => now(),
                'fim_previsto' => now()->addSeconds($origem->inicio_previsto->diffInSeconds($origem->fim_previsto)), 'created_by' => $user->id])->save();
            Evento::create(['business_id' => $businessId, 'tipo' => self::EV_RETRABALHO, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $ordem->id,
                'posto_id' => $origem->posto_id, 'user_id' => $user->id, 'dados' => ['origem_programacao_id' => $origem->id, 'nova_programacao_id' => $nova->id, 'motivo' => $motivo]]);

            return $ordem->fresh();
        });
    }

    public function refazer(int $businessId, int $ordemId, User $user): Ordem
    {
        $ordem = $this->ordem($businessId, $ordemId);
        $this->fsm->execute($ordem, 'refazer', $user);

        return $ordem->fresh();
    }

    /** Cancelar item: só antes de liberar (nada consumido); as etapas programadas são canceladas. */
    public function cancelarItem(int $businessId, int $ordemId, User $user): Ordem
    {
        $ordem = $this->ordem($businessId, $ordemId);

        return DB::transaction(function () use ($businessId, $ordem, $user) {
            $this->fsm->execute($ordem, 'cancelar_item', $user);
            Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordem->id)
                ->whereIn('status', ['pendente', 'iniciada'])->update(['status' => 'cancelada']);

            return $ordem->fresh();
        });
    }

    private function rodando(int $businessId, int $ordemId): bool
    {
        return Apontamento::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordemId)
            ->where('tipo', 'inicio')->whereNotExists(fn ($q) => $q->from('pcp_apontamentos as f')->whereColumn('f.inicio_id', 'pcp_apontamentos.id'))->exists();
    }
}
