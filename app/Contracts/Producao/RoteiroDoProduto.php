<?php

declare(strict_types=1);

namespace App\Contracts\Producao;

/**
 * Roteiro de produção ligado ao produto, com o posto e o setor de cada etapa (UC-FLX-07 ·
 * UC-FLX-08), visto pela consulta de produto (`Produto/Index`, painel de detalhe).
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Producao\Services\RoteiroDoProdutoService`, ligado pelo
 * ProducaoServiceProvider. Sem o módulo a interface não está ligada: quem consome confere
 * `app()->bound(RoteiroDoProduto::class)` e não mostra o roteiro.
 */
interface RoteiroDoProduto
{
    /**
     * O roteiro do produto, ou null quando o produto não está ligado a um roteiro da empresa.
     * Só lê; Tier 0: só o `$businessId` recebido.
     *
     * @return array{nome: string, ativo: bool, etapas: list<array{nome: string, posto: ?string, setor: ?string, horas: ?float, opcional: bool, paralela: bool}>}|null
     */
    public function doProduto(int $businessId, int $productId): ?array;
}
