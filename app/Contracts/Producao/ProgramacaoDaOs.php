<?php

declare(strict_types=1);

namespace App\Contracts\Producao;

/**
 * Programa as etapas de um roteiro do PCP para uma OS de qualquer dono (CV, venda), visto de fora
 * do Modules/Producao.
 *
 * O núcleo e os módulos verticais não importam o Producao (tests/Feature/Architecture/
 * DependencyDirectionTest). Quem implementa é `Modules\Producao\Services\ProgramacaoDoRoteiro`,
 * ligado pelo ProducaoServiceProvider. Sem o módulo, a interface não está ligada: quem consome
 * confere `app()->bound(ProgramacaoDaOs::class)` e segue sem programar.
 */
interface ProgramacaoDaOs
{
    /**
     * Uma programação (`pcp_programacoes`) por etapa não opcional do roteiro, com o `os_tipo` e o
     * `os_id` dados. Devolve quantas etapas criou: 0 quando o roteiro não é da empresa ou
     * não tem etapa (ativo ou não é decisão de quem chama). Tier 0: lê e grava só no `$businessId` recebido.
     */
    public function programar(int $businessId, string $osTipo, int $osId, int $roteiroId, int $autor): int;

    /**
     * Roteiros ativos da empresa, para escolher o padrão: id, nome e família, por nome.
     *
     * @return list<array{id: int, nome: string, familia: string}>
     */
    public function roteiros(int $businessId): array;
}
