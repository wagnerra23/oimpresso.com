<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria\Contextual;

/**
 * DocumentChunker — quebra markdown em chunks otimizados pra retrieval.
 *
 * Heurística canônica (validada Anthropic blog 2024-09-19 + Firecrawl 2026):
 *   - tamanho-alvo: ~800 tokens (~3200 chars) por chunk
 *   - quebra preferencial: headings h2/h3 (mantém coesão semântica)
 *   - fallback: parágrafo (linha em branco)
 *   - overlap: 0 (Contextual Retrieval substitui necessidade — chunks ficam
 *     auto-suficientes via prepend de contexto gerado)
 *
 * Nota: 4 chars ≈ 1 token (heurística PT-BR conservadora) → 3200 chars = 800 tokens.
 *
 * Doc curto (<max_chunk_chars) retorna como 1 chunk único (sem split).
 */
final class DocumentChunker
{
    /**
     * Divide markdown em chunks.
     *
     * @param  string  $markdown  Conteúdo markdown bruto (sem frontmatter ideal)
     * @param  int  $maxChars  Tamanho máximo por chunk em chars
     * @return array<int, string>  Lista de chunks (na ordem do doc)
     */
    public function chunk(string $markdown, int $maxChars = 3200): array
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return [];
        }

        // Doc curto → 1 chunk (não vale split — contexto = doc inteiro).
        if (strlen($markdown) <= $maxChars) {
            return [$markdown];
        }

        // 1. Quebra por h2/h3 — preserva coesão semântica.
        $secoes = $this->quebrarPorHeadings($markdown);

        $chunks = [];
        foreach ($secoes as $secao) {
            if (strlen($secao) <= $maxChars) {
                $chunks[] = $secao;
                continue;
            }
            // 2. Seção ainda gigante → fallback por parágrafo.
            foreach ($this->quebrarPorParagrafo($secao, $maxChars) as $bloco) {
                $chunks[] = $bloco;
            }
        }

        // Limpa chunks vazios + trim.
        return array_values(array_filter(
            array_map(fn ($c) => trim($c), $chunks),
            fn ($c) => $c !== '',
        ));
    }

    /**
     * Quebra texto em seções preservando headings h2/h3 como delimitadores.
     *
     * @return array<int, string>
     */
    private function quebrarPorHeadings(string $markdown): array
    {
        $linhas = explode("\n", $markdown);
        $secoes = [];
        $atual = [];

        foreach ($linhas as $linha) {
            // Heading h2 ou h3 inicia nova seção.
            if (preg_match('/^#{2,3}\s+/', $linha) && ! empty($atual)) {
                $secoes[] = implode("\n", $atual);
                $atual = [$linha];
            } else {
                $atual[] = $linha;
            }
        }
        if (! empty($atual)) {
            $secoes[] = implode("\n", $atual);
        }

        return $secoes;
    }

    /**
     * Fallback: quebra seção grande em sub-blocos por parágrafo (linha em branco).
     *
     * @return array<int, string>
     */
    private function quebrarPorParagrafo(string $texto, int $maxChars): array
    {
        $paragrafos = preg_split('/\n\s*\n/', $texto) ?: [];
        $blocos = [];
        $bufferAtual = '';

        foreach ($paragrafos as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }

            $tentativa = $bufferAtual === '' ? $p : ($bufferAtual."\n\n".$p);

            if (strlen($tentativa) > $maxChars && $bufferAtual !== '') {
                $blocos[] = $bufferAtual;
                $bufferAtual = $p;
            } else {
                $bufferAtual = $tentativa;
            }
        }

        if ($bufferAtual !== '') {
            // Se ainda é maior que max, força split por chars (último recurso).
            if (strlen($bufferAtual) > $maxChars) {
                $blocos = array_merge($blocos, str_split($bufferAtual, $maxChars));
            } else {
                $blocos[] = $bufferAtual;
            }
        }

        return $blocos;
    }
}
