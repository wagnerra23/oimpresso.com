<?php

declare(strict_types=1);

namespace App\Contracts\Arquivos;

use Illuminate\Http\UploadedFile;

/**
 * Anexos gravados e servidos pelo app das lojas (P2, tela 03). O núcleo pede; o módulo Arquivos
 * implementa (dedupe, Curador, vault, trilha). A seta vai módulo → núcleo
 * (tests/Feature/Architecture/DependencyDirectionTest.php).
 *
 * Tier 0 (ADR 0093): todo método recebe o business explícito — o app chama com token, sem sessão.
 */
interface AnexosDoApp
{
    /**
     * Grava o arquivo no dono ($tipoDono = FQCN gravado em arquivos.arquivable_type).
     * O chamador já conferiu que o dono é do business. Devolve o id em `arquivos`, ou null
     * quando o módulo não está carregado.
     */
    public function anexar(int $businessId, string $tipoDono, int $donoId, UploadedFile $arquivo): ?int;

    /**
     * Conteúdo do anexo, já decifrado quando vem do vault, e registra o consumo na trilha.
     * null = não existe neste business, foi apagado ou o blob sumiu.
     *
     * @return array{corpo: string, tipo: string, nome: string}|null
     */
    public function conteudo(int $businessId, int $arquivoId): ?array;
}
