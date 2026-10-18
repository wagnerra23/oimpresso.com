<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Questionarios;

/**
 * Catálogo dos questionários de uso livre da F1 (ADR 0437 §8; US-FISIO-011).
 *
 * ⚠️ CONTEÚDO CLÍNICO AGUARDA REVISÃO DA DRA. DEJEANE (responsável técnica, ADR 0437).
 *
 * O que está aqui e o que NÃO está:
 *  - ESTRUTURA confirmada em fonte publicada: nº de itens, faixa de cada item, regra de soma/média,
 *    direção (maior = melhor ou pior) e as referências bibliográficas.
 *  - TEXTO DOS ITENS: NÃO está. A ADR exige o texto literal da versão brasileira validada, sem
 *    alteração, e ele não pôde ser conferido contra a publicação nesta entrega (sem acesso ao texto
 *    integral). Por isso cada item sai com `texto => null` e o instrumento com
 *    `texto_pendente => true`: a tela pede para transcrever as respostas do formulário impresso da
 *    versão validada, item por item, pelo número. Inventar ou parafrasear o enunciado é proibido.
 *  - DOI marcado `null` onde não foi confirmado.
 *
 * O software NÃO interpreta a pontuação (sem faixa "melhorou", sem conduta): isso é SaMD
 * (RDC 657/2022, P&R P11/P79; ADR 0437 §5). Ele só soma, como uma calculadora (P34).
 *
 * Bloqueados por licença (ADR 0437 §8): ODI, NDI, KOOS, QuickDASH/DASH e WOMAC — o enum do banco
 * já os recusa; aqui eles só aparecem em `BLOQUEADOS` para a mensagem de recusa.
 */
final class CatalogoQuestionarios
{
    public const VERSAO = 'f1-v1';

    /** Códigos fechados, iguais ao enum de `fisio_questionnaires.instrument`. */
    public const LIVRES = ['eva', 'rmdq', 'psfs', 'lefs', 'spadi'];

    /** Bloqueados até haver licença comercial (ADR 0437 §8). */
    public const BLOQUEADOS = ['odi', 'ndi', 'koos', 'quickdash', 'dash', 'womac'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function todos(): array
    {
        return [
            'eva' => [
                'codigo' => 'eva',
                'nome' => 'EVA/END — intensidade da dor',
                'tipo' => 'escala',
                'regiao' => 'geral',
                'itens' => [self::item(1)],
                'faixa_item' => [0, 10],
                'pontuacao' => 'valor marcado (0 a 10)',
                'faixa_escore' => [0, 10],
                'direcao' => 'maior = pior',
                'texto_pendente' => true,
                'referencias' => [
                    // Domínio público (etapas/05 §3.4). Versão BR do enunciado e das âncoras: a confirmar.
                    ['tipo' => 'brasileira', 'citacao' => 'Versão brasileira do enunciado e das âncoras: pendente de confirmação pela responsável técnica.', 'doi' => null],
                ],
            ],
            'rmdq' => [
                'codigo' => 'rmdq',
                'nome' => 'Roland-Morris (RMDQ) — incapacidade por dor lombar',
                'tipo' => 'sim_nao',
                'regiao' => 'lombar',
                'itens' => self::itens(24),
                'faixa_item' => [0, 1],
                'pontuacao' => 'soma dos itens marcados "sim" (0 a 24)',
                'faixa_escore' => [0, 24],
                'direcao' => 'maior = pior',
                'texto_pendente' => true,
                'referencias' => [
                    ['tipo' => 'original', 'citacao' => 'Roland M, Morris R. Spine, 1983 (volume e páginas a conferir).', 'doi' => null],
                    ['tipo' => 'brasileira', 'citacao' => 'Nusbaum L, Natour J, Ferraz MB, Goldenberg J. Translation, adaptation and validation of the Roland-Morris questionnaire — Brazil Roland-Morris. Braz J Med Biol Res. 2001;34(2):203-10.', 'doi' => null],
                ],
            ],
            'psfs' => [
                'codigo' => 'psfs',
                'nome' => 'PSFS — Escala Funcional Específica do Paciente',
                'tipo' => 'atividades',
                'regiao' => 'qualquer',
                // Itens = atividades escritas pelo PRÓPRIO paciente (1 a 5), cada uma de 0 a 10.
                'itens' => [],
                'max_atividades' => 5,
                'faixa_item' => [0, 10],
                'pontuacao' => 'soma das notas das atividades ÷ nº de atividades',
                'faixa_escore' => [0, 10],
                'direcao' => 'maior = melhor',
                'texto_pendente' => true,
                'referencias' => [
                    ['tipo' => 'original', 'citacao' => 'Stratford P, Gill C, Westaway M, Binkley J. Assessing disability and change on individual patients: a report of a patient specific measure. Physiother Can. 1995;47:258-63.', 'doi' => null],
                    // Fonte BR ainda não confirmada: Costa et al. 2008 (Spine 33(22):2459-63) — não confirmado que inclui a PSFS;
                    // Balbi et al. 2019 (Acta Fisiátrica 26(2):66-70, PSFS-Br, membro superior). Decisão da responsável técnica.
                    ['tipo' => 'brasileira', 'citacao' => 'Versão brasileira validada: pendente de confirmação pela responsável técnica.', 'doi' => null],
                ],
            ],
            'lefs' => [
                'codigo' => 'lefs',
                'nome' => 'LEFS — Escala Funcional de Membros Inferiores',
                'tipo' => 'likert',
                'regiao' => 'membro inferior',
                'itens' => self::itens(20),
                'faixa_item' => [0, 4],
                'pontuacao' => 'soma dos 20 itens (0 a 80)',
                'faixa_escore' => [0, 80],
                'direcao' => 'maior = melhor',
                'texto_pendente' => true,
                'referencias' => [
                    ['tipo' => 'original', 'citacao' => 'Binkley JM, Stratford PW, Lott SA, Riddle DL. The Lower Extremity Functional Scale (LEFS). Phys Ther. 1999;79(4):371-83.', 'doi' => '10.1093/ptj/79.4.371'],
                    ['tipo' => 'brasileira', 'citacao' => 'Pereira LM, Dias JM, Mazuquin BF, Castanhas LG, Menacho MO, Cardoso JR. Translation, cross-cultural adaptation and analysis of the psychometric properties of the Lower Extremity Functional Scale (LEFS): LEFS-BRAZIL. Braz J Phys Ther. 2013;17(3):272-80.', 'doi' => '10.1590/S1413-35552012005000091'],
                ],
            ],
            'spadi' => [
                'codigo' => 'spadi',
                'nome' => 'SPADI — Índice de Dor e Incapacidade do Ombro',
                'tipo' => 'numerica',
                'regiao' => 'ombro',
                'itens' => self::itens(13),
                'subescalas' => ['dor' => [1, 5], 'incapacidade' => [6, 13]],
                'faixa_item' => [0, 10],
                'pontuacao' => 'dor = soma 1–5 ÷ 50 × 100; incapacidade = soma 6–13 ÷ 80 × 100; total = soma 1–13 ÷ 130 × 100',
                'faixa_escore' => [0, 100],
                'direcao' => 'maior = pior',
                'texto_pendente' => true,
                'referencias' => [
                    ['tipo' => 'original', 'citacao' => 'Roach KE, Budiman-Mak E, Songsiridej N, Lertratanakul Y. Development of a shoulder pain and disability index. Arthritis Care Res. 1991;4(4):143-9.', 'doi' => null],
                    ['tipo' => 'brasileira', 'citacao' => 'Martins J, Napoles BV, Hoffman CB, Oliveira AS. Versão brasileira do Shoulder Pain and Disability Index: tradução, adaptação cultural e confiabilidade. Rev Bras Fisioter. 2010;14(6):527-36.', 'doi' => '10.1590/S1413-35552010000600012'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function definicao(string $codigo): ?array
    {
        return self::todos()[$codigo] ?? null;
    }

    /** @return array{numero: int, texto: null} */
    private static function item(int $numero): array
    {
        // Texto literal da versão brasileira validada: pendente (ver docblock). Não preencher sem a fonte.
        return ['numero' => $numero, 'texto' => null];
    }

    /** @return list<array{numero: int, texto: null}> */
    private static function itens(int $quantos): array
    {
        return array_map(fn (int $n) => self::item($n), range(1, $quantos));
    }
}
