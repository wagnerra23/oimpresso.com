<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Breadcrumb "onde a venda está" — jornada de negócio da venda de oficina.
 *
 * Wagner 2026-06-05: a venda com veículo precisa ir pra oficina e mostrar um
 * breadcrumb marcando o estágio atual. Há DOIS fluxos (direção pelo `source`):
 *
 *   balcão/orçamento-first : Orçamento → Venda → Oficina → Faturamento → Entrega
 *   oficina-first          :            Oficina → Venda → Faturamento → Entrega
 *
 * ("da oficina pode virar venda também" — OS entregue auto-fatura, ADR 0192,
 *  gerando transaction com source='oficina'.)
 *
 * FUNÇÃO PURA de propósito: recebe escalares já resolvidos pelo Controller e
 * devolve os nós + o estágio atual. Zero acesso a DB/Eloquent → unit-testável
 * no lane SQLite do CI (sem schema UltimatePOS). O Controller faz o mapeamento
 * Transaction→estado; aqui só vive a lógica da jornada.
 *
 * Multi-tenant Tier 0 (ADR 0093): nada de query aqui; o gate `show` é decidido
 * pelo Controller via hasThePermissionInSubscription. Venda de varejo (ROTA
 * LIVRE, sem OficinaAuto) NUNCA chega aqui com show=true.
 *
 * @see app/Http/Controllers/SellController.php (show)
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 */
class SaleJourneyService
{
    public const NODE_ORCAMENTO   = 'orcamento';
    public const NODE_OFICINA     = 'oficina';
    public const NODE_VENDA       = 'venda';
    public const NODE_FATURAMENTO = 'faturamento';
    public const NODE_ENTREGA     = 'entrega';

    private const LABELS = [
        self::NODE_ORCAMENTO   => 'Orçamento',
        self::NODE_OFICINA     => 'Oficina',
        self::NODE_VENDA       => 'Venda',
        self::NODE_FATURAMENTO => 'Faturamento',
        self::NODE_ENTREGA     => 'Entrega',
    ];

    /**
     * @param array{
     *   source?: string|null,
     *   status?: string|null,
     *   has_oficina_auto?: bool,
     *   has_vehicle?: bool,
     *   has_os?: bool,
     *   invoiced?: bool,
     *   delivered?: bool
     * } $state
     *
     * @return array{
     *   show: bool,
     *   direction: string,
     *   current: string|null,
     *   nodes: array<int, array{key:string,label:string,state:string}>
     * }
     */
    public function build(array $state): array
    {
        $source         = (string) ($state['source'] ?? 'balcao');
        $status         = (string) ($state['status'] ?? 'final');
        $hasOficinaAuto = (bool) ($state['has_oficina_auto'] ?? false);
        $hasVehicle     = (bool) ($state['has_vehicle'] ?? false);
        $hasOs          = (bool) ($state['has_os'] ?? false);
        $invoiced       = (bool) ($state['invoiced'] ?? false);
        $delivered      = (bool) ($state['delivered'] ?? false);

        $isOficinaFirst = $source === 'oficina';

        // Gate: o breadcrumb de oficina só aparece em venda relacionada à oficina.
        // Varejo puro (ROTA LIVRE) → show=false → Show.tsx não renderiza nada novo.
        $show = $hasOficinaAuto && ($isOficinaFirst || $hasVehicle || $hasOs);

        // É um orçamento (ainda não virou venda final)?
        $isQuote = in_array($status, ['quotation', 'draft', 'proforma'], true);

        // Ordem dos nós por direção.
        $order = $isOficinaFirst
            ? [self::NODE_OFICINA, self::NODE_VENDA, self::NODE_FATURAMENTO, self::NODE_ENTREGA]
            : [self::NODE_ORCAMENTO, self::NODE_VENDA, self::NODE_OFICINA, self::NODE_FATURAMENTO, self::NODE_ENTREGA];

        // "Alcançado" por nó (monotônico): pega o nó mais avançado satisfeito.
        $reached = [
            self::NODE_ORCAMENTO   => true,                  // sempre o ponto de partida balcão
            self::NODE_OFICINA     => $isOficinaFirst || $hasOs,
            self::NODE_VENDA       => ! $isQuote,            // status final = virou venda
            self::NODE_FATURAMENTO => $invoiced,
            self::NODE_ENTREGA     => $delivered,
        ];

        // Índice atual = posição do nó mais avançado alcançado na ordem da direção.
        $currentIdx = 0;
        foreach ($order as $i => $key) {
            if (! empty($reached[$key])) {
                $currentIdx = $i;
            }
        }

        $nodes = [];
        foreach ($order as $i => $key) {
            $nodes[] = [
                'key'   => $key,
                'label' => self::LABELS[$key],
                'state' => $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'current' : 'todo'),
            ];
        }

        return [
            'show'      => $show,
            'direction' => $isOficinaFirst ? 'oficina' : 'balcao',
            'current'   => $order[$currentIdx] ?? null,
            'nodes'     => $nodes,
        ];
    }
}
