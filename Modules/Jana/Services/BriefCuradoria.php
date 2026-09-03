<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

/**
 * BriefCuradoria — curadoria DETERMINÍSTICA do texto do brief do negócio.
 *
 * Nasce dos 3 defeitos medidos no smoke real de 2026-08-09 (biz=1, chat
 * `/ia/conversa`), registrados na proposal `2026-08-09-jana-plano-de-teste-de-uso`
 * §5.2. O conserto anterior ([PR #5505](https://github.com/wagnerra23/oimpresso.com/pull/5505))
 * atacou os três NO PROMPT do {@see \Modules\Jana\Ai\Agents\BriefDiarioAgent} — e o
 * teste que o pina (`R-COPI-202-006`) asserta sobre a STRING `instructions()`, isto é,
 * mede a INSTRUÇÃO, nunca a SAÍDA. Instrução de prompt é pedido; o LLM pode não
 * atender, e foi exatamente assim que os 3 chegaram ao cliente.
 *
 * Esta classe é a metade que faltava: roda DEPOIS do LLM, sobre o markdown, sem
 * consultar modelo nenhum. Mesma saída pra mesma entrada.
 *
 * As três regras estão nos métodos. O que vale dizer aqui é o que as une: cada uma é
 * ESCOPADA (a uma seção, a uma palavra-âncora, a uma fonte que mediu), e o escopo é
 * o que segura o falso-positivo — denylist de vocabulário é a família reprovada 5×
 * no §5 de `memory/proibicoes.md`.
 *
 * ⚠️ RESÍDUO DECLARADO: entusiasmo que o LLM escreva FORA do "Destaque do dia" e da
 * "Projeção" (ex.: no "Plano do dia") sobrevive. Cobri-lo exigiria denylist de
 * adjetivo, que apagaria o bloco BOM do brief — num negócio parado, a reativação é
 * justamente o que fala em potencial de retorno. Limite honesto no lugar de
 * cobertura fingida.
 *
 * @see memory/decisions/proposals/2026-08-09-jana-plano-de-teste-de-uso-decisao-w.md §5.2
 * @see resources/js/Pages/Jana/Chat.casos.md — UC-JCHAT-14
 */
class BriefCuradoria
{
    /** Substitui a seção "Ideia da semana" quando não sobra linha com dado. */
    public const SEM_DADO_90D = 'Sem dado suficiente em 90 dias.';

    /** Substitui o "Destaque do dia" quando o período medido não teve venda. */
    public const SEM_MOVIMENTO = 'Sem vendas no período.';

    /**
     * Placeholders LITERAIS do template do agente. São tokens que o modelo deve
     * substituir por dado real — quando vazam, viram "produto" na tela do cliente.
     */
    private const PLACEHOLDERS = [
        'PRODUTO BEST-SELLER',
        'NOME LITERAL',
        'NOME DO PRODUTO',
        'NOME DO CLIENTE',
    ];

    /**
     * Promessa de cadência DESTE artefato. Os três exigem a palavra do artefato —
     * é o que mantém o falso-positivo em zero.
     */
    private const PROMESSAS_CADENCIA = [
        '/pr[oó]xim[oa][^.\n]{0,40}(brief|relat[oó]rio|resumo|panorama)/iu',
        '/(brief|relat[oó]rio|resumo|panorama)[^.\n]{0,40}(amanh[ãa]|todo\s+dia|todos\s+os\s+dias|toda\s+segunda|toda\s+manh[ãa]|diariamente|semanalmente|toda\s+semana)/iu',
        '/(amanh[ãa]|todo\s+dia|todos\s+os\s+dias|toda\s+segunda|toda\s+manh[ãa]|diariamente|semanalmente|toda\s+semana)[^.\n]{0,40}(brief|relat[oó]rio|resumo|panorama)/iu',
    ];

    /**
     * Curadoria completa. `$snapshot` aceita o shape de
     * {@see BriefDiarioService::snapshot()} — só a fonte `vendas` é lida.
     */
    public function curar(string $markdown, array $snapshot = []): string
    {
        $semMovimento = $this->semMovimento($snapshot);

        $blocos = $this->fatiarEmSecoes($markdown);
        $saida = [];

        foreach ($blocos as $bloco) {
            $titulo = $bloco['titulo'];
            $corpo = $bloco['corpo'];

            // (1) LINHA FABRICADA — escopo: só esta seção. Zero é notícia BOA em
            // "Inadimplência | 0"; derrubar zero lá seria o oposto do que se pede.
            if ($this->tituloCasa($titulo, '/ideia\s+da\s+semana/iu')) {
                $corpo = $this->curarIdeiaDaSemana($corpo);
            }

            // (3a) Extrapolar 0 vendas/dia dá "R$ 0,00 (±0%)" — aritmética de zero
            // vestida de análise. A seção inteira sai, e com ela o `---` que a fecha.
            if ($semMovimento && $this->tituloCasa($titulo, '/proje[çc][ãa]o/iu')) {
                continue;
            }

            // (3b) O "Destaque" carrega, por definição, "o número mais relevante".
            // Sem número não há destaque — há animação de compensação.
            if ($semMovimento && $this->tituloCasa($titulo, '/destaque\s+do\s+dia/iu')) {
                $corpo = $this->corpoNeutro($corpo, '> '.self::SEM_MOVIMENTO);
            }

            // (2) Vale em toda seção: promessa de cadência não depende de medir nada.
            $corpo = $this->removerPromessasDeCadencia($corpo);

            $saida[] = ($titulo === null ? '' : $titulo."\n").$corpo;
        }

        return rtrim(implode('', $saida))."\n";
    }

    /**
     * O período medido não teve UMA venda sequer.
     *
     * Retorna `false` quando a fonte não mediu (`ok !== true`) — colapsar
     * "não consegui medir" em "não teve movimento" é o defeito do §5 2026-07-29.
     */
    public function semMovimento(array $snapshot): bool
    {
        $vendas = $snapshot['sources']['vendas'] ?? $snapshot['vendas'] ?? null;

        if (! is_array($vendas) || ($vendas['ok'] ?? false) !== true) {
            return false;
        }

        foreach (['hoje', 'ontem', 'semana_atual', 'mes_corrente'] as $janela) {
            if ((int) ($vendas[$janela]['count'] ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Quebra o markdown em [preâmbulo, seção `## `, seção `## `, ...]. O corpo de
     * cada seção carrega o `---` que a separa da próxima — então descartar a seção
     * descarta o separador junto, sem deixar régua órfã.
     *
     * @return list<array{titulo: ?string, corpo: string}>
     */
    private function fatiarEmSecoes(string $markdown): array
    {
        $linhas = preg_split('/\R/u', $markdown) ?: [];
        $blocos = [];
        $tituloAtual = null;
        $corpoAtual = [];

        foreach ($linhas as $linha) {
            if (preg_match('/^##\s+\S/u', $linha) === 1) {
                $blocos[] = ['titulo' => $tituloAtual, 'corpo' => $this->juntar($corpoAtual)];
                $tituloAtual = $linha;
                $corpoAtual = [];

                continue;
            }
            $corpoAtual[] = $linha;
        }
        $blocos[] = ['titulo' => $tituloAtual, 'corpo' => $this->juntar($corpoAtual)];

        return $blocos;
    }

    private function juntar(array $linhas): string
    {
        return $linhas === [] ? '' : implode("\n", $linhas)."\n";
    }

    private function tituloCasa(?string $titulo, string $padrao): bool
    {
        return $titulo !== null && preg_match($padrao, $titulo) === 1;
    }

    /**
     * Defeito 1 — derruba linha de tabela com placeholder ou contagem 0; se não
     * sobrar linha com dado, a seção inteira vira a frase neutra (leva junto o
     * conselho que tinha sido escrito por cima da linha vazia).
     */
    private function curarIdeiaDaSemana(string $corpo): string
    {
        $linhas = preg_split('/\R/u', rtrim($corpo, "\n")) ?: [];
        $mantidas = [];
        $linhasDeDado = 0;
        $derrubou = false;

        foreach ($linhas as $i => $linha) {
            // Cabeçalho e separador são reconhecidos pela POSIÇÃO (o cabeçalho é a
            // linha imediatamente antes do `|---|`), nunca pelo texto da célula:
            // casar vocabulário confundiria um produto chamado "Produto X" com o
            // cabeçalho — é a família de guard sintático reprovada no §5.
            $ehSeparador = $this->ehSeparadorDeTabela($linha);
            $ehCabecalho = $this->ehLinhaDeTabela($linha)
                && $this->ehSeparadorDeTabela($linhas[$i + 1] ?? '');

            if (! $this->ehLinhaDeTabela($linha) || $ehSeparador || $ehCabecalho) {
                $mantidas[] = $linha;

                continue;
            }

            if ($this->linhaSemDado($linha)) {
                $derrubou = true;

                continue;
            }

            $linhasDeDado++;
            $mantidas[] = $linha;
        }

        if ($linhasDeDado === 0) {
            return $this->corpoNeutro($corpo, self::SEM_DADO_90D);
        }

        return $derrubou ? $this->juntar($mantidas) : $corpo;
    }

    private function ehLinhaDeTabela(string $linha): bool
    {
        return str_starts_with(ltrim($linha), '|');
    }

    /** Linha `|---|---:|` que separa cabeçalho de dados numa tabela markdown. */
    private function ehSeparadorDeTabela(string $linha): bool
    {
        return preg_match('/^\s*\|[\s:|-]+\|\s*$/u', $linha) === 1;
    }

    /**
     * Linha de tabela sem dado: primeira célula é placeholder, OU alguma célula é
     * o número 0 (com ou sem casas decimais PT-BR).
     */
    private function linhaSemDado(string $linha): bool
    {
        $celulas = array_map(
            static fn (string $c): string => trim(str_replace(['**', '`', '*'], '', $c)),
            array_slice(explode('|', trim($linha)), 1, -1)
        );

        if ($celulas === []) {
            return false;
        }

        $primeira = $celulas[0];
        foreach (self::PLACEHOLDERS as $token) {
            if (mb_stripos($primeira, $token) !== false) {
                return true;
            }
        }
        if (preg_match('/^\[[^\]]*\]$/u', $primeira) === 1) {
            return true;
        }

        foreach ($celulas as $celula) {
            if (preg_match('/^0(?:[.,]0+)?$/u', $celula) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Substitui o corpo da seção por uma frase, preservando tudo a partir do `---`
     * que a fecha.
     *
     * A cauda importa mais do que parece: a ÚLTIMA seção do brief carrega o rodapé
     * depois do seu `---`, e ele não pertence à seção. Substituir o corpo inteiro
     * apagaria o rodapé junto — a curadoria comeria o vizinho (§5 2026-08-02).
     * O `|---|---:|` de tabela não confunde: só linha que, aparada, é exatamente `---`.
     */
    private function corpoNeutro(string $corpo, string $frase): string
    {
        $linhas = preg_split('/\R/u', $corpo) ?: [];
        $cauda = [];

        foreach ($linhas as $i => $linha) {
            if (trim($linha) === '---') {
                $cauda = array_slice($linhas, $i);
                break;
            }
        }

        $texto = "\n".$frase."\n";
        if ($cauda !== []) {
            $texto .= "\n".implode("\n", $cauda);
        }

        // Fecha com linha em branco: quando a seção SEGUINTE é descartada (a Projeção
        // de zero), o `---` desta encostaria no título da próxima sem respiro.
        return rtrim($texto, "\n")."\n\n";
    }

    /**
     * Defeito 2 — remove o trecho que promete entrega futura deste artefato.
     * Opera por SEGMENTO (frase ou item separado por `·`), não por linha inteira:
     * o rodapé medido em prod era `*JANA PRO · gerado agora · próximo brief:
     * amanhã, 8h*`, e só o terceiro item era mentira.
     */
    private function removerPromessasDeCadencia(string $corpo): string
    {
        // Early return DELIBERADO: sem promessa, devolve o corpo BYTE-IDÊNTICO. Passar
        // pelo split/join normalizaria linhas em branco do fim e faria a curadoria
        // reescrever texto que ela não tinha motivo pra tocar.
        if (! $this->prometeCadencia($corpo)) {
            return $corpo;
        }

        $linhas = preg_split('/\R/u', rtrim($corpo, "\n")) ?: [];
        $saida = [];

        foreach ($linhas as $linha) {
            if (! $this->prometeCadencia($linha)) {
                $saida[] = $linha;

                continue;
            }

            $limpa = $this->limparLinha($linha);
            if (trim($limpa) !== '') {
                $saida[] = $limpa;
            }
        }

        return $this->juntar($saida);
    }

    private function prometeCadencia(string $texto): bool
    {
        foreach (self::PROMESSAS_CADENCIA as $padrao) {
            if (preg_match($padrao, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tira os segmentos mentirosos e recompõe a linha. Preserva o itálico de linha
     * inteira (`*...*`) — remover o segmento final levava o `*` de fechamento junto
     * e deixava a ênfase aberta.
     */
    private function limparLinha(string $linha): string
    {
        $italico = preg_match('/^(\s*)\*([^*].*[^*])\*(\s*)$/u', $linha, $m) === 1;
        $conteudo = $italico ? $m[2] : $linha;

        $partes = preg_split('/(\s+·\s+|(?<=[.!?])\s+)/u', $conteudo, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $mantidas = [];

        for ($i = 0; $i < count($partes); $i += 2) {
            $segmento = $partes[$i];
            if ($segmento === '' || $this->prometeCadencia($segmento)) {
                continue;
            }
            $mantidas[] = [$segmento, $partes[$i + 1] ?? ''];
        }

        if ($mantidas === []) {
            return '';
        }

        $texto = '';
        foreach ($mantidas as $indice => [$segmento, $separador]) {
            $texto .= $segmento;
            if ($indice < count($mantidas) - 1) {
                $texto .= $separador !== '' ? $separador : ' ';
            }
        }
        $texto = rtrim($texto);

        return $italico ? $m[1].'*'.$texto.'*'.$m[3] : $texto;
    }
}
