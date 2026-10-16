<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Support;

use Illuminate\Support\Facades\DB;

/**
 * Passo de cada etapa dos processos FSM canônicos (decisão [W] 2026-10-16: os passos do
 * pedido no app vêm do processo, não de uma lista fixa na API).
 *
 * O passo mora na própria etapa (`sale_process_stages.passo/passo_rotulo/passo_ordem`), junto
 * com `is_cancelled` (fora dos passos) e `is_producao` (coluna da aba Produção do app). Este
 * catálogo é a fonte que a migration e os seeders aplicam; depois de aplicado, quem lê é o
 * cadastro — um processo novo ou editado não precisa passar por aqui.
 */
final class PassosDoProcesso
{
    /** chave do passo => rótulo, na ordem em que aparecem. */
    public const PASSOS = [
        'venda_com_producao' => [
            'orcamento' => 'Orçamento',
            'aprovado' => 'Aprovado',
            'producao' => 'Produção',
            'faturamento' => 'Faturamento',
            'entrega' => 'Entrega',
            'concluido' => 'Concluído',
        ],
        'os_comunicacao_visual' => [
            'orcamento' => 'Orçamento',
            'aprovado' => 'Aprovado',
            'arte' => 'Arte',
            'producao' => 'Produção',
            'instalacao' => 'Instalação',
            'entregue' => 'Entregue',
        ],
    ];

    /** chave da etapa => chave do passo (null = fora dos passos). */
    public const ETAPAS = [
        'venda_com_producao' => [
            'quote_draft' => 'orcamento', 'quote_sent' => 'orcamento',
            'quote_approved' => 'aprovado',
            'in_production' => 'producao', 'on_hold' => 'producao',
            'ready_for_invoice' => 'faturamento', 'invoiced' => 'faturamento', 'paid' => 'faturamento',
            'delivered' => 'entrega',
            'completed' => 'concluido',
            'cancelled' => null,
        ],
        'os_comunicacao_visual' => [
            'quote_draft' => 'orcamento', 'quote_sent' => 'orcamento',
            'quote_approved' => 'aprovado',
            'arte_em_aprovacao' => 'arte', 'arte_aprovada' => 'arte',
            'aguardando_maquina' => 'producao', 'em_impressao' => 'producao',
            'impressao_concluida' => 'producao', 'aguardando_acabamento' => 'producao',
            'acabamento_concluido' => 'producao',
            'aguardando_instalacao' => 'instalacao', 'em_instalacao' => 'instalacao',
            'instalado_aguardando_aprovacao_final' => 'instalacao',
            // Garantia é aberta depois da entrega: o pedido segue mostrado como entregue.
            'entregue_completo' => 'entregue', 'garantia_acionada' => 'entregue',
            'cancelado' => null,
        ],
    ];

    /** Etapas canceladas (filtro `cancelados` do app, fora dos passos). */
    public const CANCELADAS = ['cancelled', 'cancelado'];

    /** Etapas que viram coluna na aba Produção do app. */
    public const PRODUCAO = [
        'venda_com_producao' => ['quote_approved', 'in_production', 'on_hold', 'ready_for_invoice'],
        'os_comunicacao_visual' => [
            'arte_aprovada', 'aguardando_maquina', 'em_impressao', 'impressao_concluida',
            'aguardando_acabamento', 'acabamento_concluido',
        ],
    ];

    /**
     * Grava passo/cancelada/produção nas etapas dos processos `$processKey` de todas as
     * empresas (ou só de `$processId`). Idempotente; não mexe em nome, ordem nem cor da etapa.
     */
    public static function aplicar(string $processKey, ?int $processId = null): void
    {
        $passos = self::PASSOS[$processKey] ?? null;
        if ($passos === null) {
            return;
        }
        $ordem = array_flip(array_keys($passos));
        $ids = $processId !== null
            ? [$processId]
            : DB::table('sale_processes')->where('key', $processKey)->pluck('id')->all();

        foreach (self::ETAPAS[$processKey] as $etapa => $passo) {
            DB::table('sale_process_stages')
                ->whereIn('process_id', $ids)
                ->where('key', $etapa)
                ->update([
                    'passo' => $passo,
                    'passo_rotulo' => $passo !== null ? $passos[$passo] : null,
                    'passo_ordem' => $passo !== null ? $ordem[$passo] : null,
                    'is_cancelled' => in_array($etapa, self::CANCELADAS, true),
                    'is_producao' => in_array($etapa, self::PRODUCAO[$processKey], true),
                ]);
        }
    }
}
