<?php

declare(strict_types=1);

namespace App\Services\AppLojas;

use App\Domain\Fsm\Models\SaleStageAction;

/**
 * Ações da FSM do pedido que o app das lojas pode executar (P1 · tela 22).
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-22-avancar-etapa.md. Regra mestre (valor ou
 * estoque): a 1ª entrega libera SÓ ações sem efeito em valor, estoque, cobrança ou fiscal. A lista
 * vem do processo `venda_com_producao` (FsmProcessoVendaComProducaoSeeder), ação por ação:
 *
 *  - iniciar_producao   quote_approved → in_production   sem side effect
 *  - pausar_producao    in_production  → on_hold         sem side effect
 *  - retomar_producao   on_hold        → in_production   sem side effect (ADR 0434)
 *  - concluir           delivered      → completed       sem side effect
 *
 * Ficam FORA (só na web): cliente_aprovou (ReservarEstoque), reabrir_para_revisao (LiberarReserva),
 * concluir_producao (ConsumirEstoque), cancelar_venda (CancelarVendaCascade), faturar, emitir_nfe e
 * marcar_pago (valor/fiscal), cliente_rejeitou (cancela sem a cascata), entregar (é do PR da
 * entrega, P3, que exige protocolo e assinatura conforme o ajuste) e enviar_orcamento (orçamento em
 * rascunho não aparece em Pedidos).
 *
 * Mesmo dentro da lista, ação que no banco tenha side_effect_class ou event_class é recusada: o
 * cadastro do business pode ter mudado o que o seed criou.
 *
 * O ajuste `app_acoes_fsm_liberadas` (AjustesDaEmpresa) só ESTREITA esta lista: vazio = todas as
 * seguras; com itens = a interseção. Chave fora da lista segura nunca é liberada.
 */
final class AcoesDoPedido
{
    /** @var list<string> */
    public const SEGURAS = [
        'iniciar_producao',
        'pausar_producao',
        'retomar_producao',
        'concluir',
    ];

    /**
     * Ações em que o app oferece o campo `motivo` (opcional; vai para sale_stage_history.payload_snapshot
     * pelo POST /pedidos/{id}/acao). A lista de ações do detalhe expõe isso como `pede_motivo`.
     *
     * @var list<string>
     */
    public const PEDEM_MOTIVO = [
        'pausar_producao',
        'retomar_producao',
    ];

    public static function pedeMotivo(string $chave): bool
    {
        return in_array($chave, self::PEDEM_MOTIVO, true);
    }

    /** @return list<string> ações que este business libera no app */
    public static function liberadas(int $bizId): array
    {
        $ajuste = AjustesDaEmpresa::valor($bizId, 'app_acoes_fsm_liberadas');
        if (! is_array($ajuste) || $ajuste === []) {
            return self::SEGURAS;
        }

        return array_values(array_intersect(self::SEGURAS, $ajuste));
    }

    /** A ação, como está cadastrada, pode sair pelo app? */
    public static function podeNoApp(SaleStageAction $acao, array $liberadas): bool
    {
        return in_array((string) $acao->key, $liberadas, true)
            && empty($acao->side_effect_class)
            && empty($acao->event_class);
    }
}
