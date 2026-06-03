<?php

declare(strict_types=1);

namespace Modules\Jana\Support;

/**
 * ClarifyResult — DTO imutável do resultado da cascata Decidir → Clarificar → Responder
 * (Jana "Modo Consultor" / Advisor — Metade A, proposta §10.4).
 *
 * Decoupla os dois erros nº1 dos LLMs (INTENT-SIM, NAACL 2025):
 *   - AMBÍGUO de intenção → várias leituras plausíveis → PERGUNTAR (a IA não chuta).
 *   - FALTA de dado       → resposta única, só precisa buscar → NÃO perguntar, responde via tool/RAG.
 *   - CLARO               → entrada acionável direto → responde.
 *
 * `acao`:
 *   - 'responder'  → segue o pipeline normal (ChatCopilotoAgent). Default seguro.
 *   - 'clarificar' → a IA devolve `pergunta` (a de MAIOR ganho de informação) em vez de responder.
 *
 * `custoLlm` registra se a decisão consumiu uma chamada ao disambiguador (frontier) — só ~20%
 * "cinza" devem pagar isso (cascata p/ latência). Serve à medição de false-clarify / gray-hit.
 */
final class ClarifyResult
{
    public function __construct(
        public readonly string $acao,            // 'responder' | 'clarificar'
        public readonly ?string $pergunta,       // pergunta de maior ganho (só quando 'clarificar')
        public readonly string $tipo,            // 'claro' | 'ambiguo' | 'falta_dado'
        public readonly string $motivo,          // proveniência da decisão (heurística/llm/erro)
        public readonly bool $custoLlm,          // true se rodou o disambiguador frontier
        public readonly ?float $confianca = null,
        /** @var string[] leituras candidatas que o disambiguador enxergou (debug/observability) */
        public readonly array $intencoes = [],
    ) {
    }

    public static function responder(string $tipo, string $motivo, bool $custoLlm = false, ?float $confianca = null): self
    {
        return new self('responder', null, $tipo, $motivo, $custoLlm, $confianca);
    }

    public static function clarificar(string $pergunta, string $motivo, ?float $confianca = null, array $intencoes = []): self
    {
        return new self('clarificar', $pergunta, 'ambiguo', $motivo, true, $confianca, $intencoes);
    }

    public function deveClarificar(): bool
    {
        return $this->acao === 'clarificar' && $this->pergunta !== null && trim($this->pergunta) !== '';
    }
}
