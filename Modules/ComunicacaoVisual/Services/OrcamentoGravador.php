<?php

namespace Modules\ComunicacaoVisual\Services;

use App\Contracts\Producao\CustoHoraDoPosto;
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
     * Custo da hora de máquina do item no dia (UC-FLX-33 · US-COMVIS-019). O custo da hora vem de
     * `pcp_postos.custo_hora` pela interface CustoHoraDoPosto (módulo Producao), sem histórico: o
     * valor guardado no item é o único registro daquele dia. Custo da máquina = horas × custo da
     * hora, arredondado a 2 casas, igual ao custo do material.
     *
     * Nulo (as quatro colunas) quando: o item não tem posto ou horas, as horas são zero, o módulo
     * Producao não está ligado, ou o posto não é de máquina, não tem custo ou é de outra empresa
     * (a interface filtra pelo `$businessId` explícito).
     *
     * @return array{posto_id: int, horas: float, custo_hora: float, custo: float}|null
     */
    private function custoMaquinaDoDia(int $businessId, array $itemCalc, ?CustoHoraDoPosto $custoHora): ?array
    {
        $postoId = (int) ($itemCalc['posto_id'] ?? 0);
        $horas = round((float) ($itemCalc['horas_maquina'] ?? 0), 2, PHP_ROUND_HALF_UP);
        if ($custoHora === null || $postoId <= 0 || $horas <= 0) {
            return null;
        }

        $hora = $custoHora->custoHora($businessId, $postoId);
        if ($hora === null) {
            return null;
        }

        return [
            'posto_id'   => $postoId,
            'horas'      => $horas,
            'custo_hora' => (float) $hora,
            'custo'      => round($horas * (float) $hora, 2, PHP_ROUND_HALF_UP),
        ];
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

            $this->gravarItens($businessId, $orc, $calculado['itens']);

            return $orc->load('itens');
        });
    }

    /**
     * Regrava um orçamento já salvo com os valores recalculados (reabrir e salvar de novo).
     * Mesmo número, mesmo status; os itens antigos saem e os novos entram, na mesma transação.
     * Quem chama garante que o orçamento é do `$businessId` e ainda pode mudar (sem OS).
     */
    public function regravar(int $businessId, Orcamento $orc, array $calculado): Orcamento
    {
        return DB::transaction(function () use ($businessId, $orc, $calculado) {
            $orc->fill([
                'contato_id'       => $calculado['contato_id'],
                'vendedor_id'      => $calculado['vendedor_id'],
                'data_emissao'     => $calculado['data_emissao'],
                'data_validade'    => $calculado['data_validade'],
                'subtotal'         => $calculado['subtotal'],
                'desconto'         => $calculado['desconto'],
                'extras'           => $calculado['extras'],
                'custo_instalacao' => $calculado['custo_instalacao'],
                'custo_entrega'    => $calculado['custo_entrega'],
                'total'            => $calculado['total'],
                'observacoes'      => $calculado['observacoes'],
            ])->save();

            OrcamentoItem::query()->where('business_id', $businessId)->where('orcamento_id', $orc->id)->delete();
            $this->gravarItens($businessId, $orc, $calculado['itens']);

            return $orc->load('itens');
        });
    }

    /**
     * Custo previsto de cada item, pela MESMA regra que a gravação usa (UC-FLX-33): custo do m² do
     * material e hora de máquina do posto, no dia. A calculadora mostra custo e margem a partir
     * daqui (UC-FLX-03), então o que a tela mostra é o que o orçamento grava.
     *
     * @return array<int, array{m2: ?float, material: ?float, maquina: ?array}> por índice do item
     */
    public function custosPrevistos(int $businessId, array $itens): array
    {
        $custoM2 = $this->custosDoDia($businessId, $itens);
        $custoHora = app()->bound(CustoHoraDoPosto::class) ? app(CustoHoraDoPosto::class) : null;

        $saida = [];
        foreach ($itens as $i => $itemCalc) {
            $m2 = $custoM2[(int) ($itemCalc['material_id'] ?? 0)] ?? null;
            $saida[$i] = [
                'm2'       => $m2,
                'material' => $m2 === null ? null : round((float) $itemCalc['area_m2'] * $m2, 2, PHP_ROUND_HALF_UP),
                'maquina'  => $this->custoMaquinaDoDia($businessId, $itemCalc, $custoHora),
            ];
        }

        return $saida;
    }

    private function gravarItens(int $businessId, Orcamento $orc, array $itens): void
    {
        $custos = $this->custosPrevistos($businessId, $itens);
        $hoje = now()->toDateString();

        foreach ($itens as $ordem => $itemCalc) {
            $m2 = $custos[$ordem]['m2'];
            $maquina = $custos[$ordem]['maquina'];
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
                'custo_previsto'    => $custos[$ordem]['material'],
                'custo_previsto_em' => ($m2 === null && $maquina === null) ? null : $hoje,
                // UC-FLX-33: hora de máquina do posto no dia (ver custoMaquinaDoDia).
                'posto_id'                => $maquina['posto_id'] ?? null,
                'horas_maquina_previstas' => $maquina['horas'] ?? null,
                'custo_hora_previsto'     => $maquina['custo_hora'] ?? null,
                'custo_maquina_previsto'  => $maquina['custo'] ?? null,
            ]);
        }
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
