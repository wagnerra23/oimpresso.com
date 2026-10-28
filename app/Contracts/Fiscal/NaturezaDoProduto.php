<?php

declare(strict_types=1);

namespace App\Contracts\Fiscal;

/**
 * Natureza fiscal do produto (mercadoria · serviço · pendente) decidida pelo contador
 * (US-NFE-068 · UC-FLX-31), vista pela consulta de produto (`Produto/Index`).
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\NfeBrasil\Services\Tributacao\NaturezaDoProdutoConsulta`, ligado
 * pelo NfeBrasilServiceProvider. Sem o módulo a interface não está ligada: quem consome confere
 * `app()->bound(NaturezaDoProduto::class)` e não mostra a natureza.
 *
 * Produto sem decisão registrada é mercadoria (é o que a emissão faz), por isso não aparece nas
 * respostas abaixo. Só leitura; Tier 0: só o `$businessId` recebido.
 */
interface NaturezaDoProduto
{
    /**
     * A natureza registrada de cada produto que tem uma (os outros são mercadoria).
     *
     * @param  list<int>  $productIds
     * @return array<int, array{natureza: string, codigo_servico: ?string}>
     */
    public function dosProdutos(int $businessId, array $productIds): array;

    /**
     * Os produtos da empresa com a natureza registrada `servico` ou `pendente`.
     *
     * @return list<int>
     */
    public function produtosCom(int $businessId, string $natureza): array;
}
