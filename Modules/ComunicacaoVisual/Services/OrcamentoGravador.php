<?php

namespace Modules\ComunicacaoVisual\Services;

use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;

/**
 * OrcamentoGravador — grava o orçamento JÁ calculado pelo OrcamentoCalculator.
 *
 * Um só caminho de gravação para a web (`/comunicacao-visual/api/orcamentos`, sessão) e o app das
 * lojas (`/api/app/orcamentos`, token). Nasceu da extração do `OrcamentoController::store` no PR 16
 * do playbook propostas-app (P4): duas cópias da gravação divergiriam por construção.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` chega EXPLÍCITO. Os global scopes de Orcamento e
 * Material leem a sessão e não filtram nada quando ela não existe (caso do token), então ninguém
 * aqui depende deles.
 *
 * Grava numa transação própria; chamado dentro de outra (o app grava a reserva de idempotência na
 * mesma transação), vira savepoint e o rollback de fora desfaz tudo.
 */
class OrcamentoGravador
{
    /**
     * Custo por m² de cada material dos itens, lido do cadastro no momento da gravação
     * (UC-FLX-33 · US-COMVIS-019). O cadastro não tem histórico, então o valor guardado no item é
     * o único registro do custo daquele dia. Custo zero conta como "não informado" (é o default
     * da coluna) e fica nulo. Filtra pelo `$businessId` explícito, sem depender do global scope.
     *
     * @return array<int, float> material_id => custo por m²
     */
    private function custosDoDia(int $businessId, array $itens): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn ($i) => (int) ($i['material_id'] ?? 0), $itens))));
        if ($ids === []) {
            return [];
        }

        return DB::table('comvis_materiais')
            ->where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->where('preco_custo_m2', '>', 0)
            ->pluck('preco_custo_m2', 'id')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /**
     * @param  array  $calculado  saída do OrcamentoCalculator::calcular()
     */
    public function gravar(int $businessId, array $calculado, string $status = 'rascunho'): Orcamento
    {
        return DB::transaction(function () use ($businessId, $calculado, $status) {
            /** @var Orcamento $orc */
            $orc = Orcamento::create([
                'business_id'      => $businessId,
                'numero'           => $this->proximoNumero($businessId),
                'contato_id'       => $calculado['contato_id'],
                'vendedor_id'      => $calculado['vendedor_id'],
                'data_emissao'     => $calculado['data_emissao'],
                'data_validade'    => $calculado['data_validade'],
                'status'           => $status,
                'subtotal'         => $calculado['subtotal'],
                'desconto'         => $calculado['desconto'],
                'extras'           => $calculado['extras'],
                'custo_instalacao' => $calculado['custo_instalacao'],
                'custo_entrega'    => $calculado['custo_entrega'],
                'total'            => $calculado['total'],
                'observacoes'      => $calculado['observacoes'],
            ]);

            $custoM2 = $this->custosDoDia($businessId, $calculado['itens']);
            $hoje = now()->toDateString();

            foreach ($calculado['itens'] as $ordem => $itemCalc) {
                $m2 = $custoM2[(int) ($itemCalc['material_id'] ?? 0)] ?? null;
                OrcamentoItem::create([
                    'orcamento_id'      => $orc->id,
                    'business_id'       => $businessId,
                    'material_id'       => $itemCalc['material_id'],
                    'descricao'         => $itemCalc['descricao'],
                    'largura_m'         => $itemCalc['largura_m'],
                    'altura_m'          => $itemCalc['altura_m'],
                    'quantidade'        => $itemCalc['quantidade'],
                    'area_m2'           => $itemCalc['area_m2'],
                    'preco_unitario_m2' => $itemCalc['preco_unitario_m2'],
                    'subtotal'          => $itemCalc['subtotal'],
                    'observacoes'       => $itemCalc['observacoes'],
                    'ordem'             => $ordem + 1,
                    // UC-FLX-33: custo do material no dia do orçamento (ver custosDoDia).
                    'custo_previsto_m2' => $m2,
                    'custo_previsto'    => $m2 === null ? null : round((float) $itemCalc['area_m2'] * $m2, 2, PHP_ROUND_HALF_UP),
                    'custo_previsto_em' => $m2 === null ? null : $hoje,
                ]);
            }

            return $orc->load('itens');
        });
    }

    /**
     * Número sequencial ORC-YYYY-NNNNN por business; recomeça a cada ano-civil.
     * Filtra business_id explicitamente (Tier 0).
     */
    public function proximoNumero(int $businessId): string
    {
        $prefixo = 'ORC-' . now()->year . '-';

        // SUPERADMIN: withoutGlobalScopes aqui pois precisamos do MAX real no biz (global scope ativo geraria ambiguidade)
        $ultimo = Orcamento::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('numero', 'LIKE', "{$prefixo}%")
            ->max('numero');

        $proximo = 1;
        if ($ultimo) {
            $partes  = explode('-', $ultimo);
            $proximo = ((int) end($partes)) + 1;
        }

        return $prefixo . str_pad((string) $proximo, 5, '0', STR_PAD_LEFT);
    }
}
