<?php

namespace Modules\Producao\Services;

use App\Domain\Fsm\Modelos\CatalogoModelos;
use App\Domain\Fsm\Models\SaleProcess;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;

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
 * As programações são cópia (nome, posto, horas, bloco/trilha) do roteiro no instante da aprovação:
 * editar o roteiro depois não muda a OS já criada (UC-PCROT-03). O vínculo produto → roteiro é
 * gravado na tela de Roteiros (`RoteirosController::produtos`).
 *
 * Item sob encomenda com receita reserva o insumo na criação da OS (US-PCP-031, ReservaDeInsumoDaOs).
 *
 * Idempotente: trava a venda (`lockForUpdate`) e a OS é única por empresa + linha. Tier 0: lê e
 * grava só no `$businessId` recebido.
 */
final class CriarOsDaVenda
{
    public const ETAPA_APROVADO = 'aprovado';

    public function __construct(
        private readonly ProcessoDaOrdem $processo,
        private readonly ReservaDeInsumoDaOs $reserva,
        private readonly ProgramacaoDoRoteiro $programacao,
    ) {}

    /** A etapa de destino de uma transição de venda é a que cria as OS? */
    public function deveCriar(int $businessId, ?int $stageId): bool
    {
        if ($stageId === null) {
            return false;
        }
        $etapa = DB::table('sale_process_stages as s')->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('s.id', $stageId)->where('p.business_id', $businessId)->first(['s.key', 'p.dominio', 'p.modelo']);
        if (! $etapa || $etapa->key !== self::ETAPA_APROVADO || $etapa->dominio !== 'venda' || in_array($etapa->modelo, [null, 'legado'], true)) {
            return false;
        }

        return SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
            ->where('key', CatalogoModelos::chaveProcesso((string) $etapa->modelo, 'producao'))->exists();
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
            // O roteiro tem de ser da mesma empresa do vínculo: um vínculo apontando roteiro de outra
            // empresa é tratado como produto sem roteiro (Tier 0, UC-PCROT-04).
            $roteiros = DB::table('pcp_produto_roteiro as pr')
                ->join('pcp_roteiros as r', fn ($j) => $j->on('r.id', '=', 'pr.roteiro_id')->where('r.business_id', $businessId))
                ->where('pr.business_id', $businessId)
                ->whereIn('pr.product_id', $linhas->pluck('product_id'))->pluck('pr.roteiro_id', 'pr.product_id');
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
                    $this->programacao->programar($businessId, Ordem::OS_TIPO, (int) $ordem->id, (int) $roteiroId, $autor);
                    // US-PCP-031: item sob encomenda reserva o insumo da receita (chave da empresa, padrão desligada).
                    $this->reserva->reservar($businessId, $ordem);
                }
                $ids[] = (int) $ordem->id;
            }

            return $ids;
        });
    }
}
