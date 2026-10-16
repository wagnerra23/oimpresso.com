<?php

namespace Modules\Producao\Services;

use App\Domain\Fsm\Modelos\CatalogoModelos;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;

/**
 * "Cliente aprova" cria a OS por item (ADR 0436, UC-FLX-01 + UC-FLX-07).
 *
 * Quando: a venda entra na etapa "Aprovado" de um processo de venda de MODELO (não do legado) e a
 * empresa tem o processo de produção do mesmo modelo. O legado `venda_com_producao` continua como
 * hoje (ADR 0435 item 7): a produção dele é etapa da própria venda.
 *
 * O quê: uma OS por linha da venda cujo produto tem roteiro ligado (`pcp_produto_roteiro`), na
 * situação inicial do processo do item, e uma programação do PCP por etapa do roteiro (etapa
 * opcional fica de fora até o orçamento guardar a opção). Linha sem roteiro não gera OS aqui.
 *
 * Idempotente: trava a venda (`lockForUpdate`) e a OS é única por empresa + linha. Tier 0: lê e
 * grava só no `$businessId` recebido.
 */
final class CriarOsDaVenda
{
    public const ETAPA_APROVADO = 'aprovado';

    public function __construct(private readonly ProcessoDaOrdem $processo) {}

    /** A etapa de destino de uma transição de venda é a que cria as OS? */
    public function deveCriar(int $businessId, ?int $stageId): bool
    {
        if ($stageId === null) {
            return false;
        }
        $stage = SaleProcessStage::with('process')->find($stageId);
        $process = $stage?->process;
        if (! $process || (int) $process->business_id !== $businessId || $stage->key !== self::ETAPA_APROVADO
            || $process->dominio !== 'venda' || in_array($process->modelo, [null, 'legado'], true)) {
            return false;
        }

        return SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
            ->where('key', CatalogoModelos::chaveProcesso($process->modelo, 'producao'))->exists();
    }

    /** @return list<int> ids das OS da venda (as que já existiam e as criadas agora) */
    public function criar(int $businessId, int $transactionId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($businessId, $transactionId, $userId) {
            $venda = DB::table('transactions')->where('business_id', $businessId)->where('id', $transactionId)
                ->where('type', 'sell')->lockForUpdate()->first();
            if (! $venda) {
                return [];
            }
            $linhas = DB::table('transaction_sell_lines')->where('transaction_id', $transactionId)
                ->whereNull('parent_sell_line_id')->orderBy('id')->get();
            $roteiros = DB::table('pcp_produto_roteiro')->where('business_id', $businessId)
                ->whereIn('product_id', $linhas->pluck('product_id'))->pluck('roteiro_id', 'product_id');
            $inicial = $this->processo->etapaInicial($businessId);
            $autor = $userId ?? (int) $venda->created_by;

            $ids = [];
            foreach ($linhas as $linha) {
                $roteiroId = $roteiros[$linha->product_id] ?? null;
                if ($roteiroId === null) {
                    continue;
                }
                $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->firstOrCreate(
                    ['business_id' => $businessId, 'transaction_sell_line_id' => $linha->id],
                    ['transaction_id' => $transactionId, 'product_id' => $linha->product_id, 'variation_id' => $linha->variation_id,
                        'quantidade' => $linha->quantity, 'roteiro_id' => $roteiroId, 'current_stage_id' => $inicial->id, 'created_by' => $autor],
                );
                if ($ordem->wasRecentlyCreated) {
                    $this->programar($businessId, $ordem, (int) $roteiroId, $autor);
                }
                $ids[] = (int) $ordem->id;
            }

            return $ids;
        });
    }

    /**
     * Uma programação por etapa do roteiro. Previsão simples, sem fila (UC-FLX-06 fica fora): cada
     * bloco começa quando o anterior termina; as duas trilhas de um bloco começam juntas.
     */
    private function programar(int $businessId, Ordem $ordem, int $roteiroId, int $autor): void
    {
        $etapas = DB::table('pcp_roteiro_etapas')->where('business_id', $businessId)->where('roteiro_id', $roteiroId)
            ->whereNull('opcional_por')->orderBy('bloco')->orderBy('trilha')->orderBy('ordem')->get();
        $cursor = Carbon::now();
        foreach ($etapas->groupBy('bloco') as $bloco) {
            $fimBloco = $cursor->copy();
            foreach ($bloco->groupBy('trilha') as $trilha) {
                $inicio = $cursor->copy();
                foreach ($trilha as $e) {
                    $fim = $inicio->copy()->addMinutes((int) round(((float) $e->horas) * 60));
                    Programacao::withoutGlobalScope(ScopeByBusiness::class)->create([
                        'business_id' => $businessId, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $ordem->id,
                        'posto_id' => $e->posto_id, 'roteiro_etapa_id' => $e->id, 'bloco' => $e->bloco, 'trilha' => $e->trilha,
                        'ordem' => $e->ordem, 'nome' => $e->nome, 'junta' => $e->junta,
                        'inicio_previsto' => $inicio, 'fim_previsto' => $fim, 'status' => 'pendente', 'created_by' => $autor,
                    ]);
                    $inicio = $fim;
                }
                $fimBloco = $fimBloco->max($inicio);
            }
            $cursor = $fimBloco;
        }
    }
}
