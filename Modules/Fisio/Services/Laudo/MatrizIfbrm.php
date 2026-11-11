<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Laudo;

use InvalidArgumentException;

/**
 * Matriz de atividades e participação do laudo biopsicossocial de Santa Catarina (IFBr-M / CIF),
 * modelo aprovado pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE
 * nº 001/2026), versão 1.0 de 26/10/2026. Serviço PURO: sem banco, sem sessão.
 *
 * Os 8 domínios e os 30 itens são os do formulário, com o texto dele. Cada item recebe:
 *   100 independente, sem dificuldade
 *    75 dificuldade leve/moderada ou com adaptação/tecnologia assistiva
 *    50 dificuldade grave ou ajuda parcial de terceiros
 *    25 não realiza ou totalmente dependente
 *
 * Pontuação total (texto do formulário): "somatória da linha de menor valor dentro de cada um dos
 * itens de 1 a 8". Logo o total vai de 200 (8 × 25) a 800 (8 × 100). O formulário diz que um
 * "score de até 500 pontos" atesta a deficiência; este serviço só calcula o total. Quem conclui é a
 * equipe, nas três perguntas da conclusão — o software não marca nada ali (a ADI 5033647-81.2025.8.24.0000,
 * citada na deliberação, afasta reconhecimento automático).
 *
 * Contrato: Modules/Fisio/Tests/Unit/MatrizIfbrmTest.php.
 */
final class MatrizIfbrm
{
    public const MODELO = 'ses-sc-cipf-1.0';

    public const VALORES = [100, 75, 50, 25];

    /** @var array<int, array{titulo: string, itens: array<string, string>}> chave = número do domínio (o PHP guarda '1'..'8' como inteiro) */
    public const DOMINIOS = [
        '1' => ['titulo' => 'Aprendizagem e aplicação de conhecimento', 'itens' => [
            '1.1' => 'Ver/ouvir com atenção', '1.2' => 'Concentrar a atenção',
            '1.3' => 'Resolver problemas e tomar decisões', '1.4' => 'Aprender a ler, escrever e calcular',
        ]],
        '2' => ['titulo' => 'Comunicação', 'itens' => [
            '2.1' => 'Compreender mensagens', '2.2' => 'Expressar-se', '2.3' => 'Conversar', '2.4' => 'Usar telefone/dispositivos',
        ]],
        '3' => ['titulo' => 'Mobilidade', 'itens' => [
            '3.1' => 'Mudar/manter posição do corpo', '3.2' => 'Alcançar e manusear objetos', '3.3' => 'Movimentos finos da mão',
            '3.4' => 'Andar dentro de casa', '3.5' => 'Deslocar-se fora / transporte',
        ]],
        '4' => ['titulo' => 'Cuidado Pessoal', 'itens' => [
            '4.1' => 'Lavar-se', '4.2' => 'Vestir-se', '4.3' => 'Comer e beber', '4.4' => 'Cuidar da própria saúde',
        ]],
        '5' => ['titulo' => 'Vida doméstica', 'itens' => [
            '5.1' => 'Preparar refeições', '5.2' => 'Realização das tarefas domésticas', '5.3' => 'Comprar', '5.4' => 'Cuidar do ambiente da casa',
        ]],
        '6' => ['titulo' => 'Relações e interações interpessoais', 'itens' => [
            '6.1' => 'Relacionamentos familiares', '6.2' => 'Relações sociais', '6.3' => 'Regular comportamento nas interações',
        ]],
        '7' => ['titulo' => 'Áreas principais da Vida: educação, trabalho e vida econômica', 'itens' => [
            '7.1' => 'Frequentar/acompanhar educação', '7.2' => 'Obter/manter trabalho', '7.3' => 'Transações econômicas básicas',
        ]],
        '8' => ['titulo' => 'Vida comunitária, social e cívica', 'itens' => [
            '8.1' => 'Recreação e lazer', '8.2' => 'Vida comunitária', '8.3' => 'Vida social, cultural e política',
        ]],
    ];

    /** @return list<string> os 30 códigos de item, na ordem do formulário */
    public static function itens(): array
    {
        return array_merge(...array_map(fn (array $d) => array_keys($d['itens']), array_values(self::DOMINIOS)));
    }

    /**
     * Normaliza a matriz recebida: só itens do formulário e só valores 100/75/50/25.
     *
     * @param  array<string, mixed>  $matriz  código do item => valor (nulo = não pontuado)
     * @return array<string, int>
     *
     * @throws InvalidArgumentException item fora do formulário ou valor fora da escala
     */
    public static function validar(array $matriz): array
    {
        $validos = array_flip(self::itens());
        $limpa = [];
        foreach ($matriz as $item => $valor) {
            if (! isset($validos[(string) $item])) {
                throw new InvalidArgumentException("Item {$item} não existe na matriz do formulário.");
            }
            if ($valor === null || $valor === '') {
                continue;
            }
            $n = filter_var($valor, FILTER_VALIDATE_INT);
            if ($n === false || ! in_array($n, self::VALORES, true)) {
                throw new InvalidArgumentException("Item {$item}: a pontuação é 100, 75, 50 ou 25.");
            }
            $limpa[(string) $item] = $n;
        }

        return $limpa;
    }

    /**
     * Menor valor de cada domínio e a soma deles. Domínio com item sem pontuação não entra:
     * o total só existe com os 30 itens pontuados (`completo`).
     *
     * @param  array<string, int>  $matriz
     * @return array{por_dominio: array<int, ?int>, faltando: list<string>, completo: bool, total: ?int}
     */
    public static function pontuar(array $matriz): array
    {
        $porDominio = [];
        $faltando = [];
        foreach (self::DOMINIOS as $codigo => $d) {
            $valores = [];
            foreach (array_keys($d['itens']) as $item) {
                if (isset($matriz[$item])) {
                    $valores[] = $matriz[$item];
                } else {
                    $faltando[] = $item;
                }
            }
            $porDominio[$codigo] = count($valores) === count($d['itens']) ? min($valores) : null;
        }
        $completo = $faltando === [];

        return ['por_dominio' => $porDominio, 'faltando' => $faltando, 'completo' => $completo,
            'total' => $completo ? array_sum($porDominio) : null];
    }
}
