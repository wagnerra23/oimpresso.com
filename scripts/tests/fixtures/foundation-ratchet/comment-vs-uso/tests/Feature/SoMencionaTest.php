<?php

// FIXTURE (comment-vs-uso) do foundation-ratchet selftest — MENÇÃO sem USO.
// Espelha o padrão era-sqlite REAL: monta schema à mão e EXPLICA por que EVITA o
// trait. A palavra RefreshDatabase aparece só em docblock e em string literal —
// o detector corrigido (FV-Q1, ADR 0275) NÃO pode contar isto.
// Vive fora dos roots reais (tests/ · Modules/*/Tests/) de propósito.

namespace FoundationRatchetFixtures\CommentVsUso;

/**
 * Este teste NÃO usa RefreshDatabase: a suite inteira com RefreshDatabase puxaria
 * migrations MySQL-only que quebram no sqlite :memory: da lane per-PR — por isso
 * monta-se o schema sintético na mão.
 */
class SoMencionaTest
{
    public function test_skip_menciona_a_palavra_em_string(): void
    {
        // string de skip do mundo real — a palavra sobrevive ao strip de comentário
        // mas não casa `uses(`/`use …;`, então continua não contando:
        $motivoSkip = 'requer MySQL — RefreshDatabase quebra com sqlite na migration legacy';
        $this->assertNotEmpty($motivoSkip);
    }
}
