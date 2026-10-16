<?php

namespace Modules\Producao\Services;

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Processo "OS e produção por item" no motor FSM (ADR 0436), fiel a `fluxos-processos.jsx` (id
 * `producao`). Igual para todos os modelos: o motor guarda só a SITUAÇÃO do item; o roteiro anda
 * nas programações do PCP.
 *
 * `garantir()` é idempotente (firstOrCreate) e Tier 0: só toca o processo do `$businessId`.
 */
final class ProcessoDaOrdem
{
    public const CHAVE = 'pcp_ordem_item';

    /** [chave, nome, inicial, terminal, cancelada] na ordem do protótipo. */
    public const ESTADOS = [
        ['aguardando_liberacao', 'Aguardando liberação', true, false, false],
        ['na_fila', 'Na fila', false, false, false],
        ['em_execucao', 'Em execução', false, false, false],
        ['pausada', 'Pausada', false, false, false],
        ['retrabalho', 'Retrabalho', false, false, false],
        ['concluida', 'Concluída', false, true, false],
        ['cancelada', 'Cancelada', false, true, true],
    ];

    /**
     * [de, chave da ação, rótulo, para (null = fica na etapa), crítica, papel]. "Concluir etapa"
     * não muda a situação: passa o roteiro adiante.
     *
     * Papel null: a ação é disparada pelo apontamento do operador, que já confere se ele é o
     * responsável da etapa (ApontamentoOperadorService). Um segundo portão no motor barraria o
     * celular. As ações de tela (Liberar, Reprovar, Refazer, Cancelar) exigem papel.
     */
    public const ACOES = [
        ['aguardando_liberacao', 'liberar', 'Liberar', 'na_fila', false, 'producao.liberar'],
        ['na_fila', 'iniciar_etapa', 'Iniciar etapa', 'em_execucao', false, null],
        ['em_execucao', 'pausar', 'Pausar', 'pausada', false, null],
        ['pausada', 'retomar', 'Retomar', 'em_execucao', false, null],
        ['em_execucao', 'concluir_etapa', 'Concluir etapa', null, false, null],
        ['em_execucao', 'reprovar', 'Reprovar no controle', 'retrabalho', true, 'producao.qualidade'],
        ['retrabalho', 'refazer', 'Refazer', 'em_execucao', false, 'producao.liberar'],
        ['em_execucao', 'concluir_item', 'Concluir item', 'concluida', false, null],
        ['aguardando_liberacao', 'cancelar_item', 'Cancelar item', 'cancelada', true, 'vendas.gerente'],
    ];

    public function garantir(int $businessId): SaleProcess
    {
        return DB::transaction(function () use ($businessId) {
            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->firstOrCreate(
                ['business_id' => $businessId, 'key' => self::CHAVE],
                [
                    'name' => 'OS e produção por item',
                    'description' => 'Situação da OS por item (ADR 0436)',
                    'dominio' => 'producao',
                    'default_for_contact_type' => 'any',
                    'active' => true,
                ],
            );

            $stages = [];
            foreach (self::ESTADOS as $i => [$chave, $nome, $inicial, $terminal, $cancelada]) {
                $stages[$chave] = SaleProcessStage::firstOrCreate(
                    ['process_id' => $process->id, 'key' => $chave],
                    ['name' => $nome, 'sort_order' => $i, 'is_initial' => $inicial, 'is_terminal' => $terminal, 'is_cancelled' => $cancelada],
                );
            }

            foreach (self::ACOES as [$de, $chave, $rotulo, $para, $critica, $papel]) {
                $acao = SaleStageAction::firstOrCreate(
                    ['stage_id' => $stages[$de]->id, 'key' => $chave],
                    ['label' => $rotulo, 'target_stage_id' => $para !== null ? $stages[$para]->id : null,
                        'requires_confirmation' => $critica, 'is_critical' => $critica],
                );
                if ($papel === null) {
                    continue;
                }
                $nome = $this->garantirPapel($businessId, $papel);
                if (! $acao->roles()->where('role_name', $nome)->exists()) {
                    SaleStageActionRole::create(['action_id' => $acao->id, 'role_name' => $nome]);
                }
            }

            return $process;
        });
    }

    /** Etapa inicial do processo da empresa — onde a OS nasce. */
    public function etapaInicial(int $businessId): SaleProcessStage
    {
        $process = $this->garantir($businessId);

        return SaleProcessStage::where('process_id', $process->id)->where('is_initial', true)->firstOrFail();
    }

    /** Convenção UltimatePOS: sufixo `#{biz}` quando `roles.business_id` existe (ver AplicarModelo). */
    private function garantirPapel(int $businessId, string $papel): string
    {
        $porBiz = Schema::hasColumn('roles', 'business_id');
        $nome = $porBiz ? "{$papel}#{$businessId}" : $papel;
        $attrs = ['name' => $nome, 'guard_name' => 'web'];
        if ($porBiz) {
            $attrs['business_id'] = $businessId;
        }
        Role::firstOrCreate($attrs);

        return $nome;
    }
}
