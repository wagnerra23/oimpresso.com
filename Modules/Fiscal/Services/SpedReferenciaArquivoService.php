<?php

declare(strict_types=1);

namespace Modules\Fiscal\Services;

/**
 * Leitura DERIVADA do arquivo de referência do EFD-ICMS/IPI (o golden file).
 *
 * POR QUE EXISTE
 * --------------
 * A tela SPED precisa dizer duas coisas verdadeiras que ninguém sabia responder:
 *   (Goal 5) quais registros cada bloco do arquivo contém;
 *   (Goal 4) o que já foi validado externamente e o que não foi.
 *
 * As duas tinham a mesma tentação: escrever a resposta à mão na tela. Uma lista de
 * registros escrita à mão apodrece no primeiro ajuste do gerador, e um "golden
 * file: não existe" escrito à mão vira afirmação FALSA no dia em que ele nasce —
 * que é exatamente o que aconteceu: o charter do Cowork (2026-08-24) diz "não
 * existe" e o golden passou a existir em 2026-09-03 (PR #6708).
 *
 * Então aqui nada é afirmado: tudo é MEDIDO no arquivo, a cada request. Se o
 * arquivo sumir, `disponivel` é `false` e a tela declara a ausência — nunca
 * inventa uma estrutura plausível.
 *
 * POR QUE O ARQUIVO MORA EM Tests/Fixtures
 * ----------------------------------------
 * Porque ele é o golden, e o golden tem dono único: `sped-icms-ipi-golden.txt`,
 * com receita de regeração no `.meta.md` ao lado e um bite-test que fica vermelho
 * se ele sumir (`SpedOndaF1Test`, UC-FSF1-05). Copiá-lo para um diretório "de
 * produção" criaria uma segunda cópia que drifta da primeira — a doença que o §5
 * de `proibicoes.md` chama de duplicar régua consolidada. A leitura aqui é
 * read-only, degrada sem exceção, e o arquivo é versionado (chega ao servidor pelo
 * git pull, não pelo `composer install --no-dev`).
 *
 * ⚠️ NÃO é o arquivo do usuário. É referência de LAYOUT: o registro `0000` do
 * golden declara `CI TENANT 98 (FICTICIO)` como emitente, e toda superfície que o
 * exibir tem de dizer isso.
 *
 * @see Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.meta.md (receita + o que ele expõe)
 * @see resources/js/Pages/Fiscal/Sped.charter.md §Contrato destilado
 */
final class SpedReferenciaArquivoService
{
    /** Caminho do golden, relativo à raiz do projeto. Dono único do arquivo. */
    public const CAMINHO_GOLDEN = 'Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.txt';

    /**
     * Recibo do smoke no PVA-EFD (validador oficial da CONFAZ).
     *
     * O arquivo NÃO existe hoje, e essa ausência é a resposta: "nunca executado" é
     * DERIVADO daqui, não escrito na tela. Quem um dia rodar o PVA cria o recibo e
     * a tela deixa de dizer "nunca executado" sozinha — sem editar código.
     */
    public const CAMINHO_RECIBO_PVA = 'Modules/Fiscal/Tests/Fixtures/sped-pva-smoke.recibo.md';

    /**
     * Nome de cada bloco no Guia Prático EFD-ICMS/IPI v3.1.1, perfil A.
     *
     * Isto SIM é escrito: é nomenclatura de norma publicada (EFD instituída pelo
     * Ajuste SINIEF 02/2009), não estado do sistema — não apodrece com o código.
     * Os registros DE CADA bloco, esses são medidos no arquivo.
     */
    private const NOMES_BLOCO = [
        '0' => 'Abertura, identificação e referências',
        'C' => 'Documentos fiscais I — mercadorias (NF-e)',
        'E' => 'Apuração do ICMS e do IPI',
        'H' => 'Inventário físico',
        '9' => 'Controle e encerramento do arquivo',
    ];

    /**
     * Estrutura do arquivo de referência, medida linha a linha.
     *
     * @return array{
     *     disponivel: bool,
     *     origem: string,
     *     bytes: int|null,
     *     linhas: int|null,
     *     sha256: string|null,
     *     blocos: array<int, array{id: string, nome: string, linhas: int, registros: array<int, string>}>
     * }
     */
    public function referencia(): array
    {
        return $this->referenciaDe(base_path(self::CAMINHO_GOLDEN), self::CAMINHO_GOLDEN);
    }

    /**
     * Mesma medição, num caminho dado.
     *
     * Público para que o bite-test possa apontar um caminho AUSENTE e provar que a
     * degradação existe — sem renomear o golden real, que outras sessões leem e que
     * o `SpedOndaF1Test` também exercita. Produção sempre passa por `referencia()`,
     * que é a dona do caminho.
     *
     * @param string $caminho caminho absoluto do arquivo a medir
     * @param string $origem  rótulo do caminho para a tela exibir
     *
     * @return array{
     *     disponivel: bool,
     *     origem: string,
     *     bytes: int|null,
     *     linhas: int|null,
     *     sha256: string|null,
     *     blocos: array<int, array{id: string, nome: string, linhas: int, registros: array<int, string>}>
     * }
     */
    public function referenciaDe(string $caminho, string $origem): array
    {
        if (! is_file($caminho) || ! is_readable($caminho)) {
            return [
                'disponivel' => false,
                'origem' => $origem,
                'bytes' => null,
                'linhas' => null,
                'sha256' => null,
                'blocos' => [],
            ];
        }

        $conteudo = (string) file_get_contents($caminho);

        return [
            'disponivel' => true,
            'origem' => $origem,
            'bytes' => strlen($conteudo),
            'linhas' => count($this->linhas($conteudo)),
            'sha256' => hash('sha256', $conteudo),
            'blocos' => $this->blocos($conteudo),
        ];
    }

    /**
     * O que foi validado FORA daqui, e o que não foi.
     *
     * Cada item devolve o estado + a origem da medida; nenhum é constante de texto
     * sobre o estado do sistema. O backlog é a única lista escrita, e ela espelha
     * os Non-Goals que [W] aprovou no charter da tela — escopo declarado, não
     * medida que possa divergir do código.
     *
     * @return array{
     *     golden: array{presente: bool, bytes: int|null, linhas: int|null, sha256: string|null, origem: string},
     *     pvaSmoke: array{executado: bool, origem: string},
     *     apuracaoIcms: array{noArquivo: bool},
     *     backlog: array<int, string>
     * }
     */
    public function validacaoExterna(): array
    {
        return $this->validacaoExternaDe(
            $this->referencia(),
            base_path(self::CAMINHO_RECIBO_PVA),
            self::CAMINHO_RECIBO_PVA,
        );
    }

    /**
     * Mesma leitura, com a referência e o recibo dados.
     *
     * Público pelo mesmo motivo do `referenciaDe`: sem isto, provar que "nunca
     * executado" é DERIVADO da ausência do recibo exigiria criar e apagar um
     * arquivo no diretório de fixtures durante o teste.
     *
     * @param array{disponivel: bool, origem: string, bytes: int|null, linhas: int|null, sha256: string|null, blocos: array<int, array{id: string, nome: string, linhas: int, registros: array<int, string>}>} $referencia
     *
     * @return array{
     *     golden: array{presente: bool, bytes: int|null, linhas: int|null, sha256: string|null, origem: string},
     *     pvaSmoke: array{executado: bool, origem: string},
     *     apuracaoIcms: array{noArquivo: bool},
     *     backlog: array<int, string>
     * }
     */
    public function validacaoExternaDe(array $referencia, string $caminhoRecibo, string $origemRecibo): array
    {
        $blocosPresentes = array_column($referencia['blocos'], 'id');

        return [
            'golden' => [
                'presente' => $referencia['disponivel'],
                'bytes' => $referencia['bytes'],
                'linhas' => $referencia['linhas'],
                'sha256' => $referencia['sha256'],
                'origem' => $referencia['origem'],
            ],
            'pvaSmoke' => [
                // Ausência do recibo = nunca executado. Derivado, não afirmado.
                'executado' => is_file($caminhoRecibo),
                'origem' => $origemRecibo,
            ],
            'apuracaoIcms' => [
                // O Bloco E É a apuração do ICMS/IPI. Medido no arquivo, não prometido.
                'noArquivo' => in_array('E', $blocosPresentes, true),
            ],
            // Non-Goals do charter (decisão [W]), não medida de sistema.
            'backlog' => [
                'Apuração de ISS',
                'EFD-Contribuições (PIS/COFINS — arquivo separado)',
                'Conciliação SEFAZ × ERP',
                'Entradas (DF-e manifestada)',
            ],
        ];
    }

    /**
     * Linhas úteis do arquivo.
     *
     * O layout CONFAZ exige CRLF e toda linha abre e fecha em pipe; a última quebra
     * deixa um elemento vazio que não é registro. Separar por CRLF e descartar o
     * vazio é o mesmo critério do `SpedOndaF1Test` — se um dia divergirem, o teste
     * é quem tem razão.
     *
     * @return array<int, string>
     */
    private function linhas(string $conteudo): array
    {
        return array_values(array_filter(
            explode("\r\n", $conteudo),
            static fn (string $linha): bool => $linha !== '',
        ));
    }

    /**
     * Agrupa os registros do arquivo por bloco, na ordem em que o layout os emite.
     *
     * @return array<int, array{id: string, nome: string, linhas: int, registros: array<int, string>}>
     */
    private function blocos(string $conteudo): array
    {
        $porBloco = [];

        foreach ($this->linhas($conteudo) as $linha) {
            // |REG|campo|campo|…| → o REG é o primeiro campo depois do pipe inicial.
            $campos = explode('|', $linha);
            $reg = $campos[1] ?? '';

            if ($reg === '') {
                continue;
            }

            $bloco = substr($reg, 0, 1);

            if (! isset($porBloco[$bloco])) {
                $porBloco[$bloco] = ['linhas' => 0, 'registros' => []];
            }

            $porBloco[$bloco]['linhas']++;

            if (! in_array($reg, $porBloco[$bloco]['registros'], true)) {
                $porBloco[$bloco]['registros'][] = $reg;
            }
        }

        $saida = [];

        foreach ($porBloco as $id => $dados) {
            sort($dados['registros']);

            $saida[] = [
                'id' => (string) $id,
                // Bloco fora da norma conhecida não ganha nome inventado — fica com o próprio id.
                'nome' => self::NOMES_BLOCO[$id] ?? "Bloco {$id}",
                'linhas' => $dados['linhas'],
                'registros' => $dados['registros'],
            ];
        }

        return $saida;
    }
}
