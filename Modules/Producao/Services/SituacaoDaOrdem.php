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

    public function __construct(
        private readonly ExecuteStageActionService $fsm,
        private readonly CustoRealService $custo,
        private readonly SinalDaOrdem $sinal,
        private readonly ProcessoDaOrdem $processo,
    ) {}

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
            'aguardando_material' => 'Esta OS está aguardando material: o PCP confere quando o material chegar.',
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
     * O material entra no congelado: a baixa acontece no apontamento de cada etapa
     * (ConsumoMaterialService, US-PCP-021 · decisão [W] 2026-10-19), e o CustoRealService soma o custo
     * do lote que saiu. Até 2026-10-19 o ERP não registrava o consumo real por OS.
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
     * Com a regra "só libera com o sinal pago" ligada, recusa antes enquanto o sinal da venda não chega
     * ao mínimo — vale também para quem tem `producao.liberar` (US-PCP-025).
     *
     * @param  array<int, int>  $responsaveis
     */
    public function liberar(int $businessId, int $ordemId, User $user, array $responsaveis = []): Ordem
    {
        $ordem = $this->ordem($businessId, $ordemId);
        $this->sinal->conferir($businessId, $ordem);

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
                // UC-FLX-08 · US-PCP-030: posto com operadores cadastrados só aceita um deles.
                $posto = $abertas[$prog]->posto_id !== null ? (int) $abertas[$prog]->posto_id : null;
                if (! app(OperadoresDoPosto::class)->podeAtuar($businessId, $posto, (int) $uid)) {
                    throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO,
                        'Responsável não é operador do posto de '.$abertas[$prog]->nome.'.', ['programacao_id' => $prog, 'posto_id' => $posto]);
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
            $this->aguardarMaterialSeFaltar($businessId, $ordem->fresh(), $user);

            return $ordem->fresh();
        });
    }

    /**
     * Pagamento registrado na venda (US-PCP-025): com a regra ligada e o sinal alcançado, libera
     * sozinho cada OS da venda que está em Aguardando liberação e já tem responsável em todas as
     * etapas abertas. A que falta responsável fica onde está (a pendência é a do Liberar). Devolve
     * quantas liberou. Tier 0: só OS da `$businessId`.
     */
    public function liberarPeloSinalDaVenda(int $businessId, int $transactionId): int
    {
        if ($this->sinal->minimo($businessId, $transactionId) === null) {
            return 0;
        }
        $ids = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)->pluck('id');
        $liberadas = 0;
        foreach ($ids as $id) {
            try {
                $liberadas += $this->liberarPeloSinal($businessId, (int) $id) ? 1 : 0;
            } catch (ApontamentoRecusado) {
                // Sinal abaixo do mínimo ou etapa sem responsável: a OS segue aguardando.
            }
        }

        return $liberadas;
    }

    /**
     * Liberar executado pelo sistema (ação `liberar_pelo_sinal`, sem papel). Trava e relê a OS: só
     * age em Aguardando liberação, então pagamento repetido não libera duas vezes. Confere o sinal e
     * os responsáveis como o Liberar de tela; o histórico guarda `ator = sistema` e o sinal.
     */
    public function liberarPeloSinal(int $businessId, int $ordemId): bool
    {
        $this->processo->garantir($businessId);

        return DB::transaction(function () use ($businessId, $ordemId): bool {
            $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('currentStage')->lockForUpdate()->find($ordemId);
            if (! $ordem || $ordem->currentStage?->key !== 'aguardando_liberacao') {
                return false;
            }
            $this->sinal->conferir($businessId, $ordem);
            $sem = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordem->id)
                ->where('status', 'pendente')->whereNull('user_id')->pluck('nome')->all();
            if ($sem !== []) {
                throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO, 'Escolha o responsável de: '.implode(', ', $sem).'.');
            }
            $s = $this->sinal->situacao($businessId, (int) $ordem->transaction_id, (string) $this->sinal->minimo($businessId, (int) $ordem->transaction_id));
            $this->fsm->execute($ordem, 'liberar_pelo_sinal', null, ['ator' => 'sistema', 'sinal_pago' => $s['pago'], 'sinal_exigido' => $s['exigido']]);
            $this->aguardarMaterialSeFaltar($businessId, $ordem->fresh(), null);

            return true;
        });
    }

    /**
     * Conferir o material da OS que está em Aguardando material (o PCP, depois que a compra chegou):
     * sem falta, ela volta para Na fila. Em qualquer situação devolve o que falta hoje — é a lista
     * que a tela mostra. Só lê estoque (regra mestre). Trava e relê a OS: duas conferências ao mesmo
     * tempo movem uma vez só.
     *
     * @return array{situacao: ?string, falta: list<array<string, mixed>>}
     */
    public function conferirMaterial(int $businessId, int $ordemId, ?User $user): array
    {
        $this->processo->garantir($businessId);

        return DB::transaction(function () use ($businessId, $ordemId, $user): array {
            $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('currentStage')->lockForUpdate()->find($ordemId)
                ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada.');
            $falta = app(FaltaDeMaterialDaOs::class)->daOrdem($businessId, $ordem);
            if ($falta === [] && $ordem->currentStage?->key === 'aguardando_material') {
                $this->fsm->execute($ordem, 'material_disponivel', $user, ['ator' => $user ? 'pcp' : 'sistema']);
                $ordem = $ordem->fresh('currentStage');
            }

            return ['situacao' => $ordem->currentStage?->key, 'falta' => $falta];
        });
    }

    /**
     * Logo depois de liberar (pela tela ou pelo sinal), dentro da mesma transação: com a chave da
     * empresa ligada e faltando material, a OS vai de Na fila para Aguardando material. O histórico
     * do motor guarda o que faltava. Chave desligada: nada muda (D3, revisão de 2026-10-21).
     */
    private function aguardarMaterialSeFaltar(int $businessId, Ordem $ordem, ?User $user): void
    {
        $falta = app(FaltaDeMaterialDaOs::class);
        if (! $falta->ligada($businessId) || $ordem->currentStage?->key !== 'na_fila') {
            return;
        }
        $lista = $falta->daOrdem($businessId, $ordem);
        if ($lista === []) {
            return;
        }
        $this->fsm->execute($ordem, 'aguardar_material', $user, ['ator' => $user ? 'pcp' : 'sistema',
            'falta' => array_map(fn (array $f) => ['variation_id' => $f['variation_id'], 'falta' => $f['falta']], $lista)]);
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

    /** Cancelar item: só antes de começar (aguardando liberação ou material — nada consumido); as etapas programadas são canceladas. */
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
