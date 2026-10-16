<?php

namespace Modules\Producao\Services;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Jobs\PausarEtapasDaVendaJob;

/**
 * Pedido em espera ↔ apontamento (decisão [W] 2026-10-16; no shopVOX, pôr o pedido em espera põe
 * os trabalhos em espera). Só programação `os_tipo = venda` liga o PCP à venda.
 *
 * - Escuta a MUDANÇA de etapa da venda (origem → destino), não a chave da ação: qualquer ação que
 *   leve a `on_hold` pausa; sair de `on_hold` para `in_production` libera a retomada.
 * - Pausa = linha `pausa` com `origem = pedido`, sem motivo do cadastro do posto; o motivo
 *   "pedido em espera" fica no evento `pausado_pedido_em_espera`. Retomar é um `iniciar` novo,
 *   que o `ApontamentoOperadorService` recusa enquanto a venda estiver em espera.
 *
 * `business_id` por parâmetro em toda consulta: o job roda sem sessão (ADR 0093).
 */
class PedidoEmEsperaService
{
    public const EM_ESPERA = 'on_hold';
    public const EM_PRODUCAO = 'in_production';
    public const MOTIVO = 'pedido em espera';
    public const EV_PAUSADO = 'pausado_pedido_em_espera';
    public const EV_RETOMADO = 'pedido_retomado';

    public function __construct(private readonly ApontamentoService $base) {}

    /** Chave da etapa atual da venda na FSM, da própria empresa. */
    public function etapaDaVenda(int $businessId, int $transactionId): ?string
    {
        $stageId = DB::table('transactions')->where('business_id', $businessId)->where('id', $transactionId)->value('current_stage_id');

        return $this->chaveDaEtapa($businessId, $stageId);
    }

    /** Listener de `eloquent.updated: App\Transaction` (registrado no ProducaoServiceProvider). */
    public function aoMudarEtapa(Transaction $venda): void
    {
        $biz = (int) $venda->business_id;
        // Roda em todo update de venda: sai cedo sem mudança de etapa ou sem o PCP instalado.
        if (! $venda->wasChanged('current_stage_id') || ! Schema::hasTable('pcp_programacoes')
            || $this->etapas($biz, (int) $venda->id) === []) {
            return;
        }
        $de = $this->chaveDaEtapa($biz, $venda->getRawOriginal('current_stage_id'));
        $para = $this->chaveDaEtapa($biz, $venda->current_stage_id);

        if ($para === self::EM_ESPERA && $de !== self::EM_ESPERA) {
            PausarEtapasDaVendaJob::dispatch($biz, (int) $venda->id)->afterCommit();
        } elseif ($de === self::EM_ESPERA && $para === self::EM_PRODUCAO) {
            $this->avisarRetomada($biz, (int) $venda->id);
        }
    }

    /** Pausa os apontamentos abertos das etapas da venda. Idempotente pela chave por apontamento. */
    public function pausarEtapas(int $businessId, int $transactionId): int
    {
        if ($this->etapaDaVenda($businessId, $transactionId) !== self::EM_ESPERA) {
            return 0;
        }
        $abertos = $this->base->abertos($businessId)
            ->whereIn('pcp_apontamentos.programacao_id', $this->etapas($businessId, $transactionId))->get();
        foreach ($abertos as $inicio) {
            $pausa = $this->base->pausar($businessId, $inicio->id, (int) $inicio->iniciado_por, "pedido-espera-{$inicio->id}", null, 'pedido');
            Evento::query()->create(['business_id' => $businessId, 'tipo' => self::EV_PAUSADO, 'os_tipo' => $pausa->os_tipo,
                'os_id' => $pausa->os_id, 'posto_id' => $pausa->posto_id, 'user_id' => $inicio->user_id ?? $inicio->iniciado_por,
                'dados' => ['motivo' => self::MOTIVO, 'apontamento_id' => $pausa->id, 'programacao_id' => $pausa->programacao_id]]);
        }

        return $abertos->count();
    }

    /** Aviso ao responsável de cada etapa em andamento: o pedido voltou para produção. */
    private function avisarRetomada(int $businessId, int $transactionId): void
    {
        Programacao::query()->where('business_id', $businessId)->where('os_tipo', Programacao::OS_VENDA)
            ->where('os_id', $transactionId)->where('status', 'iniciada')->whereNotNull('user_id')->get()
            ->each(fn (Programacao $p) => Evento::query()->create(['business_id' => $businessId, 'tipo' => self::EV_RETOMADO,
                'os_tipo' => $p->os_tipo, 'os_id' => $p->os_id, 'posto_id' => $p->posto_id, 'user_id' => $p->user_id,
                'dados' => ['programacao_id' => $p->id]]));
    }

    /** @return list<int> */
    private function etapas(int $businessId, int $transactionId): array
    {
        return Programacao::query()->where('business_id', $businessId)->where('os_tipo', Programacao::OS_VENDA)
            ->where('os_id', $transactionId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function chaveDaEtapa(int $businessId, mixed $stageId): ?string
    {
        if (! $stageId) {
            return null;
        }

        return DB::table('sale_process_stages as s')->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.business_id', $businessId)->where('s.id', $stageId)->value('s.key');
    }
}
