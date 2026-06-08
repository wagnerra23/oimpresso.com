<?php

namespace Modules\ComunicacaoVisual\Services;

use App\Util\OtelHelper;
use Modules\Jana\Services\Privacy\PiiRedactor; // Wave 26 D7.a — redacta texto livre observacoes antes de log/span (PII-LGPD.md §2)
use InvalidArgumentException;
use Modules\ComunicacaoVisual\Entities\Material;

/**
 * OrcamentoCalculator — cálculo authoritative server-side de orçamentos de comunicação visual.
 *
 * US-COMVIS-001: O backend é a fonte de verdade. Valores calculados pelo frontend
 * (area_m2, subtotal, total) são SEMPRE descartados e recalculados aqui.
 *
 * Fórmulas canônicas:
 *   area_m2  = largura_m × altura_m × quantidade  (round 3 casas PHP_ROUND_HALF_UP)
 *   subtotal_item = area_m2 × preco_unitario_m2   (round 2 casas PHP_ROUND_HALF_UP)
 *   subtotal = SUM(item.subtotal)
 *   total    = subtotal - desconto + extras + custo_instalacao + custo_entrega
 *
 * Resolução de preço:
 *   1. input.preco_unitario_m2 (override do operador)
 *   2. material->preco_venda_m2 (catálogo — se material_id passado)
 *   3. throw InvalidArgumentException (sem preço disponível)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * lookup de Material via Model com global scope ativo (filtra business_id da sessão).
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 */
class OrcamentoCalculator
{
    /**
     * Calcula orçamento completo a partir do payload validado.
     *
     * @param  array{
     *   data_emissao: string,
     *   data_validade?: string|null,
     *   contato_id?: int|null,
     *   vendedor_id?: int|null,
     *   desconto?: float,
     *   extras?: float,
     *   custo_instalacao?: float,
     *   custo_entrega?: float,
     *   observacoes?: string|null,
     *   itens: array<int, array{
     *     material_id?: int|null,
     *     descricao: string,
     *     largura_m: float,
     *     altura_m: float,
     *     quantidade: int,
     *     preco_unitario_m2?: float|null,
     *     observacoes?: string|null,
     *   }>
     * } $payload
     * @return array Resultado authoritative com todos os campos calculados
     * @throws InvalidArgumentException Se validação de negócio falhar
     */
    public function calcular(array $payload): array
    {
        // Wave 26 D7.a — observacoes é texto livre potencialmente PII (nome/telefone cliente
        // digitado pelo vendedor). Redactamos ANTES de qualquer span/log/audit pra evitar
        // vazamento de PII em telemetria. Persistência em DB mantém valor original (RTBF
        // anonimiza depois via retention.php right_to_be_forgotten).
        $observacoesBruto = (string) ($payload['observacoes'] ?? '');
        $observacoesRedacted = $observacoesBruto === ''
            ? ''
            : app(PiiRedactor::class)->redact($observacoesBruto);

        return OtelHelper::spanBiz('comvis.orcamento.calcular', function () use ($payload) {
            return $this->calcularInterno($payload);
        }, [
            'itens_count'        => count($payload['itens'] ?? []),
            'observacoes_redact' => $observacoesRedacted, // log sem PII
        ]);
    }

    /**
     * Implementação interna de calcular — envolvida pelo span OTel acima (D9 observability).
     */
    private function calcularInterno(array $payload): array
    {
        // Campos de cabeçalho com defaults seguros
        $desconto         = round((float) ($payload['desconto']          ?? 0), 2, PHP_ROUND_HALF_UP);
        $extras           = round((float) ($payload['extras']            ?? 0), 2, PHP_ROUND_HALF_UP);
        $custoInstalacao  = round((float) ($payload['custo_instalacao']  ?? 0), 2, PHP_ROUND_HALF_UP);
        $custoEntrega     = round((float) ($payload['custo_entrega']     ?? 0), 2, PHP_ROUND_HALF_UP);

        $itensCalculados = [];
        $subtotalGeral   = 0.0;

        foreach ($payload['itens'] as $indice => $item) {
            $itensCalculados[] = $calculado = $this->calcularItem($item, $indice);
            $subtotalGeral    += $calculado['subtotal'];
        }

        $subtotalGeral = round($subtotalGeral, 2, PHP_ROUND_HALF_UP);
        $total         = round(
            $subtotalGeral - $desconto + $extras + $custoInstalacao + $custoEntrega,
            2,
            PHP_ROUND_HALF_UP
        );

        return [
            'data_emissao'    => $payload['data_emissao'],
            'data_validade'   => $payload['data_validade']    ?? null,
            'contato_id'      => $payload['contato_id']       ?? null,
            'vendedor_id'     => $payload['vendedor_id']      ?? null,
            'observacoes'     => $payload['observacoes']      ?? null,
            'subtotal'        => $subtotalGeral,
            'desconto'        => $desconto,
            'extras'          => $extras,
            'custo_instalacao' => $custoInstalacao,
            'custo_entrega'   => $custoEntrega,
            'total'           => $total,
            'itens'           => $itensCalculados,
        ];
    }

    /**
     * Calcula um item individual: area_m2 + preço resolvido + subtotal.
     *
     * @param  array  $item    Dados brutos do item
     * @param  int    $indice  Posição no array (para mensagem de erro)
     * @return array  Item com area_m2, preco_unitario_m2 e subtotal calculados
     * @throws InvalidArgumentException Se dimensão inválida ou preço não resolvível
     */
    private function calcularItem(array $item, int $indice): array
    {
        $largura  = (float) ($item['largura_m'] ?? 0);
        $altura   = (float) ($item['altura_m']  ?? 0);
        $qtd      = (int)   ($item['quantidade'] ?? 1);

        // Validações de negócio (devem ser reforçadas pela Form Request antes, mas
        // o Service é authoritative e valida de novo por segurança)
        if ($largura <= 0) {
            throw new InvalidArgumentException(
                "Item #{$indice}: largura_m deve ser maior que zero (recebido: {$largura})."
            );
        }
        if ($altura <= 0) {
            throw new InvalidArgumentException(
                "Item #{$indice}: altura_m deve ser maior que zero (recebido: {$altura})."
            );
        }
        if ($qtd < 1) {
            throw new InvalidArgumentException(
                "Item #{$indice}: quantidade deve ser pelo menos 1 (recebido: {$qtd})."
            );
        }

        // area_m2 = largura × altura × quantidade (3 casas decimais)
        $areaMeta = round($largura * $altura * $qtd, 3, PHP_ROUND_HALF_UP);

        // Resolução de preco_unitario_m2: input override > material > throw
        $precoUnitario = $this->resolverPreco($item, $indice);

        // subtotal = area_m2 × preco_unitario_m2 (2 casas decimais)
        $subtotalItem = round($areaMeta * $precoUnitario, 2, PHP_ROUND_HALF_UP);

        return [
            'material_id'      => $item['material_id']  ?? null,
            'descricao'        => $item['descricao'],
            'largura_m'        => $largura,
            'altura_m'         => $altura,
            'quantidade'       => $qtd,
            'observacoes'      => $item['observacoes']  ?? null,
            'area_m2'          => $areaMeta,
            'preco_unitario_m2' => $precoUnitario,
            'subtotal'         => $subtotalItem,
        ];
    }

    /**
     * Resolve preco_unitario_m2 com prioridade:
     *   1. input override (preco_unitario_m2 passado no payload)
     *   2. material->preco_venda_m2 (se material_id válido)
     *   3. throw — sem preço disponível
     *
     * Multi-tenant Tier 0: Material::find() usa global scope, filtrando business_id da sessão.
     *
     * @throws InvalidArgumentException
     */
    private function resolverPreco(array $item, int $indice): float
    {
        // Prioridade 1: override explícito do operador
        if (isset($item['preco_unitario_m2']) && $item['preco_unitario_m2'] !== null) {
            $preco = (float) $item['preco_unitario_m2'];
            if ($preco <= 0) {
                throw new InvalidArgumentException(
                    "Item #{$indice}: preco_unitario_m2 deve ser positivo (recebido: {$preco})."
                );
            }
            return round($preco, 2, PHP_ROUND_HALF_UP);
        }

        // Prioridade 2: busca no catálogo de materiais (multi-tenant via global scope)
        if (! empty($item['material_id'])) {
            $material = Material::find((int) $item['material_id']);

            if ($material === null) {
                throw new InvalidArgumentException(
                    "Item #{$indice}: material_id {$item['material_id']} não encontrado ou não pertence a este business."
                );
            }

            $precoMaterial = (float) $material->preco_venda_m2;
            if ($precoMaterial <= 0) {
                throw new InvalidArgumentException(
                    "Item #{$indice}: material '{$material->nome}' não possui preco_venda_m2 configurado."
                );
            }

            return round($precoMaterial, 2, PHP_ROUND_HALF_UP);
        }

        // Prioridade 3: sem preço — erro
        throw new InvalidArgumentException(
            "Item #{$indice}: preco_unitario_m2 é obrigatório quando material_id não é informado."
        );
    }
}
