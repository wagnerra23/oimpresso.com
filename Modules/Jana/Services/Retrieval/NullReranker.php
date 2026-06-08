<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Retrieval;

/**
 * NullReranker — passthrough quando reranker está desabilitado.
 *
 * Retorna `array_slice($candidatos, 0, $topK)` adicionando `score_rerank=1.0/(rank+1)`
 * pra preservar contrato (todo candidato retornado tem `score_rerank`).
 *
 * Usado quando `config('copiloto.reranker.enabled') === false` OU driver=`null`.
 */
class NullReranker implements Reranker
{
    public function reranquear(string $query, array $candidatos, int $topK = 5): array
    {
        if (empty($candidatos) || $topK <= 0) {
            return [];
        }

        $sliced = array_slice($candidatos, 0, $topK);

        foreach ($sliced as $rank => &$c) {
            $c['score_rerank'] = round(1.0 / ($rank + 1), 4);
        }

        return $sliced;
    }
}
