<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Ragas;

/**
 * JudgeUnavailableException — o juiz RAGAS não produziu um score CONFIÁVEL.
 *
 * Lançada quando a infra do juiz falha (Ollama down, HTTP != 2xx, JSON inválido,
 * resposta sem `score`). Existe pra o consumidor PULAR honestamente em vez de
 * gravar um `0.0` fabricado — que numa queda de infra viraria falso alarme de
 * "resposta totalmente infiel". Honestidade > cobertura:
 * `memory/proibicoes.md` §"Teste que deriva do código" + lápide 2026-07-17
 * ("não polir/gravar score de alarme tautológico").
 */
class JudgeUnavailableException extends \RuntimeException
{
}
