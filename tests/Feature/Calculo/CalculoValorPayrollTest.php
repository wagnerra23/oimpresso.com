<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use App\Utils\Util;
use Modules\Essentials\Services\PayrollTotalCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dente de cálculo do módulo FOLHA (`transactions.type = 'payroll'`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COMPARAR-NÃO-DUPLICAR — por que este arquivo existe
 * ─────────────────────────────────────────────────────────────────────────────
 * `CalculoValorSellsTest` cobre o totalizador de VENDAS e `CalculoValorComprasTest` o de
 * COMPRAS. Nenhum dos dois toca a FOLHA, que até 2026-09-05 não tinha totalizador de servidor
 * nenhum: o total de cada contracheque era somado no navegador
 * (`form_script.blade.php`: `var gross_amount = total + total_allowance - total_deduction`),
 * escrito num hidden com `.val()` cru, e persistido pelo `PayrollController` como veio
 * (`$payroll['total_before_tax'] = $payroll['final_total']`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A DUPLA CONFIRMAÇÃO exigida pela regra mestre de VALOR
 * ─────────────────────────────────────────────────────────────────────────────
 * `memory/proibicoes.md` §"CÁLCULO DE VALOR ou ESTOQUE" exige provar o resultado por DOIS
 * caminhos independentes. Os dois estão aqui e NÃO compartilham implementação:
 *
 *   CAMINHO A — caso-verdade de fonte EXTERNA ao nosso código: a aritmética é conferida
 *               contra o literal esperado, escrito à mão a partir do enunciado do caso
 *               (`teste_a_*`). O esperado nunca é derivado do que o serviço devolve — é a
 *               lápide de 2026-06-05 (teste tautológico) aplicada aqui.
 *
 *   CAMINHO B — recomputação independente: o total é remontado somando as linhas devolvidas
 *               (`base + Σ adicionais − Σ descontos`) e confrontado com o campo `total` do
 *               serviço, centavo a centavo (`teste_b_*`). Dois números, igualdade provada.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A DISCRIMINAÇÃO RED — o que estes testes provam que NÃO acontece
 * ─────────────────────────────────────────────────────────────────────────────
 * `numUf_infla_1000x_um_float_cru_de_3_casas` é o controle POSITIVO do vetor: prova, contra o
 * `Util::num_uf` REAL de produção, que "1234.567" vira 1234567. É por causa dessa medição que
 * o `PayrollTotalCalculator` NUNCA passa o `final_total` recebido por `num_uf` — a "correção"
 * ingênua teria criado o incidente de 2026-06-05 na folha. Se alguém um dia acrescentar esse
 * `num_uf`, `reconciliar_nao_parseia_o_recebido_com_num_uf` fica vermelho.
 *
 * ⛔ Estes testes são de UNIDADE do calculador: exercitam o serviço e o `num_uf` reais, sem
 *    tocar o banco. O tenant canônico de teste é o 98 ([ADR 0358](memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md));
 *    aqui nenhum tenant é lido, o que é a forma mais forte de não encostar em biz=1 nem biz=4.
 */
class CalculoValorPayrollTest extends TestCase
{
    private PayrollTotalCalculator $calc;

    private Util $util;

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = new Util;
        $this->calc = new PayrollTotalCalculator($this->util);
    }

    /**
     * Monta o item de `payrolls[]` como o formulário o envia.
     *
     * @param  list<array{0:string,1:string,2:string,3:string}>  $adicionais  [nome, valor, tipo, percentual]
     * @param  list<array{0:string,1:string,2:string,3:string}>  $descontos
     * @return array<string,mixed>
     */
    private function itemDoFormulario(
        string $duracao,
        string $porUnidade,
        array $adicionais = [],
        array $descontos = [],
        ?string $finalTotalDoNavegador = null,
    ): array {
        return [
            'essentials_duration' => $duracao,
            'essentials_amount_per_unit_duration' => $porUnidade,
            'allowance_names' => array_column($adicionais, 0),
            'allowance_amounts' => array_column($adicionais, 1),
            'allowance_types' => array_column($adicionais, 2),
            'allowance_percent' => array_column($adicionais, 3),
            'deduction_names' => array_column($descontos, 0),
            'deduction_amounts' => array_column($descontos, 1),
            'deduction_types' => array_column($descontos, 2),
            'deduction_percent' => array_column($descontos, 3),
            'final_total' => $finalTotalDoNavegador,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CAMINHO A — casos-verdade: o esperado é escrito à mão a partir do enunciado
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0:string,1:string,2:list<array{0:string,1:string,2:string,3:string}>,3:list<array{0:string,1:string,2:string,3:string}>,4:float}>
     */
    public static function casosVerdade(): array
    {
        return [
            // enunciado: 1 mês × 3.000,00, sem verbas  =>  3.000,00
            'mensalista sem verba' => ['1', '3.000,00', [], [], 3000.00],

            // enunciado: 1 × 3.000,00 + vale de 250,00 fixo  =>  3.250,00
            'adicional fixo' => ['1', '3.000,00', [['Vale', '250,00', 'fixed', '']], [], 3250.00],

            // enunciado: 1 × 3.000,00 − 250,00 fixo  =>  2.750,00
            'desconto fixo' => ['1', '3.000,00', [], [['Adiantamento', '250,00', 'fixed', '']], 2750.00],

            // enunciado: 1 × 2.000,00 + 10% sobre a base (200,00)  =>  2.200,00
            'adicional percentual' => ['1', '2.000,00', [['Comissão', '0', 'percent', '10']], [], 2200.00],

            // enunciado: 1 × 2.000,00 − 8% sobre a base (160,00)  =>  1.840,00
            'desconto percentual' => ['1', '2.000,00', [], [['INSS', '0', 'percent', '8']], 1840.00],

            // enunciado: 22 diárias de 100,00 + 50,00 fixo − 5% da base (110,00) => 2.200 + 50 − 110 = 2.140,00
            'diarista com verbas dos dois lados' => [
                '22', '100,00',
                [['Diária extra', '50,00', 'fixed', '']],
                [['Vale transporte', '0', 'percent', '5']],
                2140.00,
            ],

            // enunciado: 1 × 1.000,10 + 33% (330,033 -> arredonda a 330,03) => 1.330,13
            // É o caso que produz 3 casas decimais — a faixa que o num_uf trataria como milhar.
            'percentual que cai em 3 casas' => [
                '1', '1.000,10', [['Bonus', '0', 'percent', '33']], [], 1330.13,
            ],
        ];
    }

    #[Test]
    #[DataProvider('casosVerdade')]
    public function teste_a_caso_verdade_bate_com_o_esperado_escrito_a_mao(
        string $duracao,
        string $porUnidade,
        array $adicionais,
        array $descontos,
        float $esperado,
    ): void {
        $r = $this->calc->calcular($this->itemDoFormulario($duracao, $porUnidade, $adicionais, $descontos), 2);

        $this->assertSame(
            $esperado,
            $r['total'],
            'o total do servidor divergiu do caso-verdade escrito a partir do enunciado'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CAMINHO B — recomputação independente das linhas devolvidas
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('casosVerdade')]
    public function teste_b_recomputacao_independente_bate_centavo_a_centavo(
        string $duracao,
        string $porUnidade,
        array $adicionais,
        array $descontos,
        float $esperado,
    ): void {
        $r = $this->calc->calcular($this->itemDoFormulario($duracao, $porUnidade, $adicionais, $descontos), 2);

        // Segundo caminho: soma as linhas devolvidas, sem reusar 'total'/'total_adicionais'.
        $somaAdicionais = 0.0;
        foreach ($r['adicionais'] as $linha) {
            $somaAdicionais += $linha['valor'];
        }
        $somaDescontos = 0.0;
        foreach ($r['descontos'] as $linha) {
            $somaDescontos += $linha['valor'];
        }
        $recomputado = round($r['base'] + $somaAdicionais - $somaDescontos, 2);

        $this->assertSame(
            $r['total'],
            $recomputado,
            'o total do serviço não bate com a soma das linhas que ele mesmo devolveu'
        );

        // E o fecho da dupla confirmação: o caminho B chega ao MESMO número que o caso-verdade
        // externo do caminho A. Dois caminhos, dois números, igualdade provada.
        $this->assertSame(
            $esperado,
            $recomputado,
            'a recomputação independente divergiu do caso-verdade escrito a partir do enunciado'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // O buraco que este trabalho fecha
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function o_total_do_servidor_ignora_o_final_total_que_o_formulario_mandou(): void
    {
        // O navegador manda um total inflado; os insumos dizem outra coisa.
        $item = $this->itemDoFormulario('1', '3.000,00', [], [], '999999.99');

        $r = $this->calc->calcular($item, 2);

        $this->assertSame(3000.00, $r['total'], 'o servidor deixou o formulário escolher o total');
    }

    #[Test]
    public function divergencia_com_forma_de_inflacao_e_recusada(): void
    {
        $rec = $this->calc->reconciliar(3000.00, '3000000.00');

        $this->assertTrue($rec['divergente']);
        $this->assertTrue($rec['recusar'], 'uma inflação de 1000x passou sem recusa');
    }

    #[Test]
    public function divergencia_de_arredondamento_nao_recusa_nem_alarma(): void
    {
        $rec = $this->calc->reconciliar(3000.00, '3000.00');

        $this->assertFalse($rec['divergente'], 'igualdade exata foi tratada como divergência');
        $this->assertFalse($rec['recusar']);
    }

    #[Test]
    public function divergencia_pequena_e_registrada_mas_nao_recusa(): void
    {
        // 5 centavos: acima da tolerância (1 centavo), longe da forma de inflação.
        $rec = $this->calc->reconciliar(3000.00, '3000.05');

        $this->assertTrue($rec['divergente'], 'uma divergência de 5 centavos passou como ruído');
        $this->assertFalse($rec['recusar'], '5 centavos não podem derrubar o salvamento');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Discriminação RED — o vetor do incidente 2026-06-05, medido contra o num_uf REAL
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function numUf_infla_1000x_um_float_cru_de_3_casas(): void
    {
        // Controle POSITIVO: prova que o vetor existe no primitivo de produção. Se este
        // assert ficar verde por outro motivo (num_uf mudou), a proteção abaixo perde sentido
        // e precisa ser reexaminada — não silenciada.
        $this->assertSame(1234567.0, (float) $this->util->num_uf('1234.567'));
        $this->assertSame(330033.0, (float) $this->util->num_uf('330.033'));

        // Controle NEGATIVO: 2 e 5 casas atravessam intactos — o problema é a faixa de 3.
        $this->assertSame(3456.79, (float) $this->util->num_uf('3456.79'));
        $this->assertSame(204.99605, (float) $this->util->num_uf('204.99605'));
    }

    #[Test]
    public function reconciliar_nao_parseia_o_recebido_com_num_uf(): void
    {
        // "1234.567" é o que o `.val(float)` do navegador produz para mil duzentos e trinta e
        // quatro reais e meio milésimo. Se `reconciliar` usasse `num_uf`, leria um milhão
        // duzentos e trinta e quatro mil quinhentos e sessenta e sete — inflação de 1000x
        // num contracheque correto — recusando o salvamento de uma folha legítima.
        $rec = $this->calc->reconciliar(1234.567, '1234.567');

        $this->assertFalse(
            $rec['divergente'],
            'reconciliar() parseou o valor cru do navegador com a heurística pt-BR — é o vetor de 2026-06-05'
        );
        $this->assertFalse($rec['recusar']);
    }

    #[Test]
    public function linha_percentual_e_derivada_do_percentual_nao_do_valor_enviado(): void
    {
        // O formulário manda o percentual certo (10%) e um valor adulterado (9.999,00).
        $item = $this->itemDoFormulario('1', '2.000,00', [['Comissão', '9.999,00', 'percent', '10']], []);

        $r = $this->calc->calcular($item, 2);

        $this->assertSame(200.00, $r['adicionais'][0]['valor'], 'o valor enviado venceu o percentual');
        $this->assertSame(2200.00, $r['total']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Integração Controller ↔ Service: o holerite precisa fechar
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function os_blobs_gravados_somam_o_total_gravado(): void
    {
        // A garantia de negócio: as verbas serializadas em `essentials_allowances` /
        // `essentials_deductions` — que são o que o holerite imprime — têm de somar o mesmo
        // total que vai para `final_total`. Antes disto o total vinha do navegador e as verbas
        // do servidor, então nada obrigava os dois a fecharem.
        $item = $this->itemDoFormulario(
            '1',
            '2.000,00',
            [['Comissão', '0', 'percent', '10'], ['Vale', '150,00', 'fixed', '']],
            [['INSS', '0', 'percent', '8'], ['Adiantamento', '100,00', 'fixed', '']],
        );

        $controller = app(\Modules\Essentials\Http\Controllers\PayrollController::class);
        $metodo = new \ReflectionMethod($controller, 'getAllowanceAndDeductionJson');
        $metodo->setAccessible(true);
        $blobs = $metodo->invoke($controller, $item);

        $adicionais = json_decode($blobs['essentials_allowances'], true);
        $descontos = json_decode($blobs['essentials_deductions'], true);
        $total = $this->calc->calcular($item, 2)['total'];

        // base 2.000 + 200 (10%) + 150 − 160 (8%) − 100 = 2.090,00
        $this->assertSame(2090.00, $total);
        $this->assertSame(
            $total,
            round(2000.00 + array_sum($adicionais['allowance_amounts']) - array_sum($descontos['deduction_amounts']), 2),
            'as verbas do holerite não somam o total que vai para final_total'
        );
    }

    #[Test]
    public function linha_sem_nome_nao_entra_no_total(): void
    {
        // Mesma guarda do getAllowanceAndDeductionJson: linha sem nome é linha vazia da tela.
        $item = $this->itemDoFormulario('1', '1.000,00', [['', '500,00', 'fixed', '']], []);

        $r = $this->calc->calcular($item, 2);

        $this->assertSame([], $r['adicionais']);
        $this->assertSame(1000.00, $r['total']);
    }
}
