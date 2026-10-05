<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use App\Contracts\Oficina\AcoesOs;
use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\User;
use Illuminate\Support\Facades\Log;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * Avançar a etapa da OS pelo app das lojas (contrato App\Contracts\Oficina\AcoesOs, tela 03).
 *
 * Mesmas regras da web (ServiceOrderFsmActionController): permissão
 * `oficinaauto.service_order.update` (ou superadmin), papel da ação pela StageActionPolicy,
 * gate do StageGateEvaluator e transição só pelo ExecuteStageActionService (FSM canônica,
 * trilha em sale_stage_history). Diferenças, todas para restringir:
 *  - só as ações de ACOES_DO_APP (avanço) e ACOES_QUE_ENCERRAM (cancelar, recusar orçamento,
 *    acionar garantia — esta com motivo obrigatório, ACOES_COM_MOTIVO);
 *  - sem override do gate;
 *  - ação com side_effect_class ou event_class no banco é recusada (nao_suportada): o
 *    seeder do processo da oficina não tem nenhuma, e o app não pode mover valor nem estoque.
 *
 * Tier 0 (ADR 0093): o escopo global do ServiceOrder lê a sessão, que a API não tem; por isso
 * toda busca filtra business_id explicitamente.
 */
final class AcoesOsDoApp implements AcoesOs
{
    public function __construct(
        private StageActionPolicy $policy,
        private StageGateEvaluator $gate,
        private ServiceOrderPipelineStarter $starter,
        private ExecuteStageActionService $fsm,
    ) {
    }

    public function acoes(User $user, int $businessId, int $osId): ?array
    {
        $os = $this->os($businessId, $osId);
        if ($os === null) {
            return null;
        }
        if ($os->current_stage_id === null) {
            return [];
        }

        $processo = $this->starter->resolveProcessKey($os);
        $podeEditar = $this->podeEditar($user);
        $saida = [];
        foreach ($this->acoesDaEtapa($os) as $a) {
            $gate = $this->gate->evaluate($os, $processo, $a->key);
            $saida[] = [
                'chave' => (string) $a->key,
                'rotulo' => (string) $a->label,
                'tipo' => in_array($a->key, self::ACOES_QUE_ENCERRAM, true) ? 'encerra' : 'avanco',
                'critica' => (bool) ($a->is_critical ?? false) || (bool) $a->requires_confirmation,
                'pode' => $podeEditar && $this->policy->canExecute($user, $os, (string) $a->key),
                'bloqueio' => $gate['satisfied'] ? null : $this->textoBloqueio($gate),
                'motivo_obrigatorio' => in_array($a->key, self::ACOES_COM_MOTIVO, true),
                // Etapa para onde a ação leva, para o app não deduzir pela chave.
                'destino' => $a->targetStage === null ? null : [
                    'chave' => (string) $a->targetStage->key,
                    'rotulo' => (string) $a->targetStage->name,
                ],
            ];
        }

        return $saida;
    }

    public function executar(User $user, int $businessId, int $osId, string $chave, ?string $motivo = null): array
    {
        $os = $this->os($businessId, $osId);
        if ($os === null) {
            return ['resultado' => 'nao_encontrado', 'mensagem' => 'OS não encontrada.'];
        }
        if (! in_array($chave, self::ACOES_DO_APP, true) && ! in_array($chave, self::ACOES_QUE_ENCERRAM, true)) {
            return ['resultado' => 'nao_suportada', 'mensagem' => 'Esta ação só pode ser feita na web.'];
        }
        if (! $this->podeEditar($user)) {
            return ['resultado' => 'sem_permissao', 'mensagem' => 'Seu usuário não pode alterar OS.'];
        }

        $acao = $os->current_stage_id === null ? null : SaleStageAction::query()
            ->where('stage_id', (int) $os->current_stage_id)
            ->where('key', $chave)
            ->first();
        if ($acao === null) {
            return ['resultado' => 'etapa_mudou', 'mensagem' => 'A OS já não está na etapa desta ação. Atualize a tela.'];
        }
        if (! empty($acao->side_effect_class) || ! empty($acao->event_class)) {
            return ['resultado' => 'nao_suportada', 'mensagem' => 'Esta ação só pode ser feita na web.'];
        }

        $gate = $this->gate->evaluate($os, $this->starter->resolveProcessKey($os), $chave);
        if (! $gate['satisfied']) {
            return ['resultado' => 'bloqueado', 'mensagem' => $this->textoBloqueio($gate)];
        }

        try {
            // O motivo vai para a trilha (payload_snapshot), como o payload da web.
            $this->fsm->execute($os, $chave, $user, array_filter(['origem' => 'app', 'motivo' => $motivo], fn ($x) => $x !== null));
        } catch (UnauthorizedActionException $e) {
            return ['resultado' => 'sem_permissao', 'mensagem' => 'Seu usuário não pode executar esta ação.'];
        } catch (InvalidActionForCurrentStageException $e) {
            return ['resultado' => 'etapa_mudou', 'mensagem' => 'A OS já não está na etapa desta ação. Atualize a tela.'];
        }

        return ['resultado' => 'ok', 'mensagem' => null];
    }

    public function criar(User $user, int $businessId, array $dados): int
    {
        // business_id explícito: o `creating` do model só lê a sessão, que a API não tem.
        $os = ServiceOrder::create([
            'business_id' => $businessId,
            'vehicle_id' => $dados['vehicle_id'],
            'contact_id' => $dados['contact_id'],
            'order_type' => 'mecanica',
            'status' => 'aberta',
            'entered_at' => now(),
            'mileage_at_service' => $dados['mileage_at_service'],
            'box_label' => $dados['box_label'],
            'notes' => $dados['notes'],
        ]);

        // Como a web: a OS vira o documento vivo do veículo no quadro, se ele estiver livre.
        Vehicle::query()
            ->where('business_id', $businessId)
            ->whereKey($dados['vehicle_id'])
            ->whereNull('current_rental_id')
            ->update(['current_rental_id' => $os->id]);

        // Como a web: falha no início do pipeline não desfaz a OS (fica fora do quadro, e a
        // web tem o botão manual); só registra.
        try {
            $this->starter->start($os, null, (int) $user->id);
        } catch (\Throwable $e) {
            Log::warning('AcoesOsDoApp@criar: início do pipeline falhou', [
                'business_id' => $businessId, 'service_order_id' => $os->id, 'error' => $e->getMessage(),
            ]);
        }

        return (int) $os->id;
    }

    private function os(int $businessId, int $osId): ?ServiceOrder
    {
        return ServiceOrder::query()
            ->where('service_orders.business_id', $businessId)
            ->whereKey($osId)
            ->first();
    }

    private function podeEditar(User $user): bool
    {
        return $user->can('superadmin') || $user->can('oficinaauto.service_order.update');
    }

    /**
     * Ações do app que saem da etapa atual: as de avanço na ordem da linha principal, depois as
     * que encerram.
     *
     * @return list<SaleStageAction>
     */
    private function acoesDaEtapa(ServiceOrder $os): array
    {
        $daEtapa = SaleStageAction::query()
            ->where('stage_id', (int) $os->current_stage_id)
            ->whereIn('key', [...self::ACOES_DO_APP, ...self::ACOES_QUE_ENCERRAM])
            ->with('targetStage')
            ->get()
            ->filter(fn (SaleStageAction $a) => empty($a->side_effect_class) && empty($a->event_class))
            ->keyBy('key');

        $ordem = [];
        foreach ([...self::ACOES_DO_APP, ...self::ACOES_QUE_ENCERRAM] as $chave) {
            if ($daEtapa->has($chave)) {
                $ordem[] = $daEtapa->get($chave);
            }
        }

        return $ordem;
    }

    /** "Falta: foto da vistoria; orçamento aprovado" — os requisitos bloqueantes pendentes. */
    private function textoBloqueio(array $gate): string
    {
        $faltam = [];
        foreach ($gate['requirements'] ?? [] as $r) {
            if (($r['blocking'] ?? false) && ! ($r['ok'] ?? false)) {
                $faltam[] = (string) ($r['label'] ?? $r['key'] ?? '');
            }
        }

        return $faltam === [] ? 'Checklist da etapa incompleto.' : 'Falta: ' . implode('; ', $faltam) . '.';
    }
}
