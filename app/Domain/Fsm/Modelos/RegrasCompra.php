<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

use InvalidArgumentException;

/**
 * Regras das etapas da Compra — o card "configurável" da aba Fluxos da empresa (ADR 0435).
 *
 * Fonte: `prototipo-ui/cowork/Wagner/fsm-regras.jsx` (PADRAO.compra e OPC). Cada regra dispara
 * ao entrar numa etapa da compra; a de custo dos insumos tem parâmetros (as perguntas viram
 * configuração da etapa). Vale para todas as compras da empresa.
 *
 * Até 2026-10-19 era só configuração gravada. Desde então o parâmetro `ops` da regra de custo é lido
 * pela OS aberta da Produção (Modules/Producao PrevisaoCustoDaOrdem, US-PCP-022), que mostra custo da
 * abertura × hoje e escolhe a previsão. Desde 2026-10-20 o recebimento da compra lê `metodo` e
 * `preco` da regra de custo quando a empresa a gravou (Modules/Compras AplicarCustoDoRecebimento,
 * US-COM-026); `regraEm` e `aprovacao` ainda não são lidos.
 * A regra `trava` não pode ser desligada.
 */
final class RegrasCompra
{
    /** Parâmetro => [rótulo, [valor => rótulo]] — `fsm-regras.jsx` OPC. */
    public const OPCOES = [
        'etapa' => ['Dispara na etapa', ['recebido' => 'Recebido — mercadoria entrou no estoque', 'conferido' => 'Conferido — NF-e conferida']],
        'metodo' => ['Novo custo do insumo', ['medio' => 'Custo médio ponderado', 'ultimo' => 'Último preço de compra', 'por_insumo' => 'Definido em cada insumo']],
        'preco' => ['Preço de venda do produto', ['so_custo' => 'Só recalcula o custo', 'propor' => 'Propõe novo preço — alguém aprova', 'aplicar' => 'Aplica o novo preço sozinho']],
        'regraEm' => ['Quais produtos seguem', ['produto' => 'Política de preço do produto', 'receita' => 'Marcação na receita', 'insumo' => 'Marcação na linha do insumo', 'categoria' => 'Categoria do produto']],
        'aprovacao' => ['Quem aprova e onde', ['recebimento' => 'No próprio recebimento', 'fabricacao' => 'Fila de reajustes da Fabricação', 'pendencias' => 'Pendências da Visão geral']],
        'ops' => ['Ordens de produção abertas', ['congela' => 'Mantêm o custo da abertura', 'vivo' => 'Passam a usar o custo novo']],
    ];

    /** Regra do custo dos insumos — a que "Usar modelo" ajusta com a `compra` do modelo. */
    public const CUSTO = 'insumo.custo';

    /** `fsm-regras.jsx` PADRAO.compra, na ordem. */
    public const PADRAO = [
        ['id' => 'pedido.email', 'etapa' => 'pedido', 'titulo' => 'Enviar o pedido ao fornecedor por e-mail', 'desc' => 'PDF do pedido com prazo e condição de pagamento.', 'ativo' => true],
        ['id' => 'recebido.estoque', 'etapa' => 'recebido', 'titulo' => 'Dar entrada no estoque', 'desc' => 'Soma a quantidade recebida ao saldo do local da compra.', 'ativo' => true, 'trava' => true],
        ['id' => self::CUSTO, 'etapa' => 'recebido', 'titulo' => 'Atualizar o custo dos insumos', 'desc' => 'Recalcula o custo de cada item comprado e propaga para os produtos fabricados que o consomem.', 'ativo' => true,
            'params' => ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'propor', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        ['id' => 'conferido.pagar', 'etapa' => 'conferido', 'titulo' => 'Gerar conta a pagar no Financeiro', 'desc' => 'Um título por parcela da condição de pagamento.', 'ativo' => true, 'params' => ['tolerancia' => 2]],
        ['id' => 'pago.baixa', 'etapa' => 'pago', 'titulo' => 'Baixar o título e conciliar', 'desc' => 'Liquida no Financeiro e fecha a compra.', 'ativo' => true],
    ];

    /**
     * Regras com o que a empresa gravou por cima do padrão (`fsm-regras.jsx` regrasDe).
     * `etapa_efetiva` = onde a regra dispara (a de custo pode mudar para Conferido).
     *
     * @param  array<string, array{ativo?:bool, params?:array}>|null  $salvo
     * @return list<array<string, mixed>>
     */
    public static function daEmpresa(?array $salvo): array
    {
        $salvo ??= [];

        return array_map(function (array $r) use ($salvo) {
            $o = $salvo[$r['id']] ?? [];
            $params = isset($r['params']) ? array_merge($r['params'], array_intersect_key($o['params'] ?? [], $r['params'])) : null;
            $trava = $r['trava'] ?? false;

            return [
                'id' => $r['id'],
                'titulo' => $r['titulo'],
                'desc' => $r['desc'],
                'trava' => $trava,
                'ativo' => $trava ? true : (bool) ($o['ativo'] ?? $r['ativo']),
                'params' => $params,
                'etapa_efetiva' => $params['etapa'] ?? $r['etapa'],
            ];
        }, self::PADRAO);
    }

    /**
     * Junta uma mudança ao que a empresa já gravou, validando contra o padrão.
     *
     * @param  array<string, array{ativo?:bool, params?:array}>|null  $salvo
     * @param  array<string, mixed>  $params
     * @return array<string, array{ativo?:bool, params?:array}>
     */
    public static function mudar(?array $salvo, string $id, ?bool $ativo, array $params): array
    {
        $padrao = collect(self::PADRAO)->firstWhere('id', $id);
        if ($padrao === null) {
            throw new InvalidArgumentException("Regra da compra desconhecida: {$id}");
        }
        if ($ativo === false && ($padrao['trava'] ?? false)) {
            throw new InvalidArgumentException('Esta regra é obrigatória e não pode ser desligada.');
        }
        foreach ($params as $k => $v) {
            if (! array_key_exists($k, $padrao['params'] ?? [])) {
                throw new InvalidArgumentException("Parâmetro {$k} não existe na regra {$id}.");
            }
            if ($k === 'tolerancia') {
                if (! is_numeric($v) || $v < 0 || $v > 100) {
                    throw new InvalidArgumentException('A tolerância vai de 0 a 100%.');
                }
                $params[$k] = (float) $v;
            } elseif (! isset(self::OPCOES[$k][1][$v])) {
                throw new InvalidArgumentException("Valor {$v} não vale para {$k}.");
            }
        }

        $salvo ??= [];
        $atual = $salvo[$id] ?? [];
        if ($ativo !== null) {
            $atual['ativo'] = $ativo;
        }
        if ($params !== []) {
            $atual['params'] = array_merge($atual['params'] ?? [], $params);
        }
        $salvo[$id] = $atual;

        return $salvo;
    }

    /** @return array<string, array{rotulo:string, opcoes:list<array{value:string,label:string}>}> */
    public static function opcoes(): array
    {
        return array_map(fn (array $o) => [
            'rotulo' => $o[0],
            'opcoes' => array_map(fn ($v, $l) => ['value' => (string) $v, 'label' => $l], array_keys($o[1]), $o[1]),
        ], self::OPCOES);
    }
}
