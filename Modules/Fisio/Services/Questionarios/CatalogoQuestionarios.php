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
     * Instrumentos de fibromialgia (etapa 07 §4.1, P-03). Estão no enum do banco, mas cada um só
     * aparece e só aceita resposta se estiver em `config('fisio.instrumentos_liberados')`: ligado em
     * local e staging para teste, desligado em produção até a licença ser confirmada (P-01). O FIQR,
     * em especial, exige licença da Mapi Research Trust para uso comercial.
     */
    public const FIBROMIALGIA = ['acr2016', 'fiqr', 'pgic', 'sentar_levantar_30s'];

    /** WPI do ACR 2016: número => área e região (Wolfe et al. 2016, ordem da publicação original). */
    public const ACR2016_AREAS = [
        1 => ['area' => 'mandibula_esquerda', 'regiao' => 'superior_esquerda'],
        2 => ['area' => 'cintura_escapular_esquerda', 'regiao' => 'superior_esquerda'],
        3 => ['area' => 'braco_esquerdo', 'regiao' => 'superior_esquerda'],
        4 => ['area' => 'antebraco_esquerdo', 'regiao' => 'superior_esquerda'],
        5 => ['area' => 'mandibula_direita', 'regiao' => 'superior_direita'],
        6 => ['area' => 'cintura_escapular_direita', 'regiao' => 'superior_direita'],
        7 => ['area' => 'braco_direito', 'regiao' => 'superior_direita'],
        8 => ['area' => 'antebraco_direito', 'regiao' => 'superior_direita'],
        9 => ['area' => 'quadril_esquerdo', 'regiao' => 'inferior_esquerda'],
        10 => ['area' => 'coxa_esquerda', 'regiao' => 'inferior_esquerda'],
        11 => ['area' => 'perna_esquerda', 'regiao' => 'inferior_esquerda'],
        12 => ['area' => 'quadril_direito', 'regiao' => 'inferior_direita'],
        13 => ['area' => 'coxa_direita', 'regiao' => 'inferior_direita'],
        14 => ['area' => 'perna_direita', 'regiao' => 'inferior_direita'],
        15 => ['area' => 'pescoco', 'regiao' => 'axial'],
        16 => ['area' => 'parte_superior_das_costas', 'regiao' => 'axial'],
        17 => ['area' => 'parte_inferior_das_costas', 'regiao' => 'axial'],
        18 => ['area' => 'torax', 'regiao' => 'axial'],
        19 => ['area' => 'abdome', 'regiao' => 'axial'],
    ];

    /** Áreas que contam no WPI mas não na dor generalizada (Wolfe et al. 2016). */
    public const ACR2016_FORA_DA_DOR_GENERALIZADA = ['mandibula_esquerda', 'mandibula_direita', 'torax', 'abdome'];

    /** @return list<string> instrumentos de fibromialgia liberados nesta instalação */
    public static function fibromialgiaLiberados(): array
    {
        $lista = config('fisio.instrumentos_liberados', []);

        return array_values(array_intersect(self::FIBROMIALGIA, is_array($lista) ? $lista : []));
    }

    /** Pode aparecer na ficha e receber resposta: de uso livre, ou de fibromialgia liberado. */
    public static function disponivel(string $codigo): bool
    {
        return in_array($codigo, self::LIVRES, true) || in_array($codigo, self::fibromialgiaLiberados(), true);
    }

    /**
     * Catálogo que a ficha recebe: os de uso livre e os de fibromialgia liberados. Desligado, o
     * instrumento não aparece (P-03).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function disponiveis(): array
    {
        return array_filter(self::todos(), fn (array $d) => self::disponivel((string) $d['codigo']));
    }

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
        ] + self::fibromialgia();
    }

    /**
     * Instrumentos da etapa 07 (pesquisa em fibromialgia, P-03). Mesma regra dos de cima: texto dos
     * itens pendente (`texto => null`); o profissional transcreve do formulário impresso da versão
     * validada, pelo número. `versao` vai gravada junto da resposta, para o estudo não misturar versões.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function fibromialgia(): array
    {
        $acrItens = [];
        foreach (self::ACR2016_AREAS as $n => $a) {
            $acrItens[] = ['numero' => $n, 'texto' => null, 'bloco' => 'wpi', 'area' => $a['area'], 'regiao' => $a['regiao'], 'faixa' => [0, 1]];
        }
        foreach ([20 => 'fadiga', 21 => 'sono_nao_reparador', 22 => 'sintomas_cognitivos'] as $n => $s) {
            $acrItens[] = ['numero' => $n, 'texto' => null, 'bloco' => 'sss_gravidade', 'sintoma' => $s, 'faixa' => [0, 3]];
        }
        foreach ([23 => 'cefaleia', 24 => 'dor_ou_colica_abdominal_baixa', 25 => 'depressao'] as $n => $s) {
            $acrItens[] = ['numero' => $n, 'texto' => null, 'bloco' => 'sss_ultimos_6_meses', 'sintoma' => $s, 'faixa' => [0, 1]];
        }
        $acrItens[] = ['numero' => 26, 'texto' => null, 'bloco' => 'duracao', 'sintoma' => 'sintomas_ha_3_meses_ou_mais', 'faixa' => [0, 1]];

        return [
            'acr2016' => [
                'codigo' => 'acr2016',
                'nome' => 'Critérios ACR 2016 — dor generalizada (WPI) e gravidade dos sintomas (SSS)',
                'tipo' => 'acr2016',
                'regiao' => 'geral',
                'origem' => 'paciente',
                'versao' => 'acr2016-fsq-brasil-2020',
                'itens' => $acrItens,
                'faixa_item' => [0, 3],
                'pontuacao' => 'WPI = nº de áreas com dor (0 a 19); SSS = fadiga + sono + cognição (0 a 3 cada) + cefaleia, dor abdominal baixa e depressão nos últimos 6 meses (0 ou 1 cada), de 0 a 12; escore = WPI + SSS (0 a 31). Critérios: (WPI ≥ 7 e SSS ≥ 5, ou WPI 4–6 e SSS ≥ 9), dor em pelo menos 4 de 5 regiões (mandíbula, tórax e abdome não contam) e sintomas há 3 meses ou mais. Informação para o profissional, não diagnóstico.',
                'faixa_escore' => [0, 31],
                'direcao' => 'maior = pior',
                'texto_pendente' => true,
                'licenca' => 'a conferir (P-01): uso em software',
                'referencias' => [
                    ['tipo' => 'original', 'citacao' => 'Wolfe F, Clauw DJ, Fitzcharles MA, et al. 2016 Revisions to the 2010/2011 fibromyalgia diagnostic criteria. Semin Arthritis Rheum. 2016;46(3):319-29.', 'doi' => '10.1016/j.semarthrit.2016.08.012'],
                    ['tipo' => 'brasileira', 'citacao' => 'FSQ-Brasil: validação brasileira do Fibromyalgia Survey Questionnaire. Adv Rheumatol. 2020 (autores, volume e páginas a conferir).', 'doi' => '10.1186/s42358-020-00139-3'],
                ],
            ],
            'fiqr' => [
                'codigo' => 'fiqr',
                'nome' => 'FIQR — Questionário de Impacto da Fibromialgia Revisado',
                'tipo' => 'numerica',
                'regiao' => 'geral',
                'origem' => 'paciente',
                'versao' => 'fiqr-br-lupi-2017',
                'itens' => self::itens(21),
                'subescalas' => ['funcao' => [1, 9], 'impacto' => [10, 11], 'sintomas' => [12, 21]],
                'faixa_item' => [0, 10],
                'pontuacao' => 'função = soma 1–9 ÷ 3 (0 a 30); impacto geral = soma 10–11 (0 a 20); sintomas = soma 12–21 ÷ 2 (0 a 50); total = soma dos três (0 a 100)',
                'faixa_escore' => [0, 100],
                'direcao' => 'maior = pior',
                'texto_pendente' => true,
                'licenca' => 'uso comercial exige licença da Mapi Research Trust; desligado em produção até confirmar',
                'referencias' => [
                    ['tipo' => 'original', 'citacao' => 'Bennett RM, Friend R, Jones KD, Ward R, Han BK, Ross RL. The Revised Fibromyalgia Impact Questionnaire (FIQR): validation and psychometric properties. Arthritis Res Ther. 2009;11(4):R120.', 'doi' => '10.1186/ar2783'],
                    ['tipo' => 'brasileira', 'citacao' => 'Lupi JB et al. FIQR-Br: versão brasileira do Revised Fibromyalgia Impact Questionnaire. Disabil Rehabil. 2017 (volume e páginas a conferir).', 'doi' => '10.1080/09638288.2016.1207106'],
                ],
            ],
            'pgic' => [
                'codigo' => 'pgic',
                'nome' => 'PGIC — Impressão Global de Mudança do Paciente',
                'tipo' => 'escala',
                'regiao' => 'geral',
                'origem' => 'paciente',
                'versao' => 'pgic-7-categorias',
                'itens' => [self::item(1)],
                'faixa_item' => [1, 7],
                'inteiro' => true,
                'pontuacao' => 'categoria marcada (1 a 7)',
                'faixa_escore' => [1, 7],
                // A direção depende da versão adotada (numa, 1 = muito melhor; noutra, 1 = sem
                // mudança). A definir com a universidade (P-01); o software só guarda a categoria.
                'direcao' => null,
                'texto_pendente' => true,
                'licenca' => 'a conferir (P-01)',
                'referencias' => [
                    ['tipo' => 'original', 'citacao' => 'Guy W. ECDEU Assessment Manual for Psychopharmacology. Rockville: US Department of Health, Education, and Welfare; 1976. Versão do paciente e âncoras: a definir com a universidade.', 'doi' => null],
                    ['tipo' => 'brasileira', 'citacao' => 'Versão brasileira validada: pendente de confirmação (P-01).', 'doi' => null],
                ],
            ],
            'sentar_levantar_30s' => [
                'codigo' => 'sentar_levantar_30s',
                'nome' => 'Sentar e levantar em 30 segundos',
                'tipo' => 'escala',
                'regiao' => 'membro inferior',
                'origem' => 'profissional',
                'versao' => 'jones-rikli-beam-1999',
                'itens' => [self::item(1)],
                'faixa_item' => [0, 60],
                'inteiro' => true,
                'pontuacao' => 'nº de vezes que a pessoa levantou por completo em 30 s, contado pelo profissional',
                'faixa_escore' => [0, 60],
                'direcao' => 'maior = melhor',
                // Teste de desempenho: não há texto de questionário a transcrever.
                'texto_pendente' => false,
                'licenca' => 'teste de desempenho publicado; sem questionário',
                'referencias' => [
                    ['tipo' => 'original', 'citacao' => 'Jones CJ, Rikli RE, Beam WC. A 30-s chair-stand test as a measure of lower body strength in community-residing older adults. Res Q Exerc Sport. 1999;70(2):113-9.', 'doi' => '10.1080/02701367.1999.10608028'],
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
