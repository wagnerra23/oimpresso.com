<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Casos-verdade externos da folha — passo 2 do §9 da ADR-mãe
 * (`memory/decisions/proposals/2026-09-05-folha-com-encargos-modelo-e-fronteira.md`, §5.1).
 *
 * As fixtures em `tests/fixtures/folha-casos-verdade/*.json` vêm de fonte EXTERNA (RFB, INSS,
 * Portaria MPS/MF, Lei 8.036) e cada uma declara de onde veio o número e de que competência é.
 * Ainda NÃO existe motor de folha: este teste não calcula INSS/IRRF/FGTS. Ele confere só
 *   (1) a estrutura — fonte, vigência e competência presentes e bem formadas;
 *   (2) a consistência interna da tabela publicada — faixas contíguas, alíquotas crescentes;
 *   (3) a soma interna que a própria fonte escreveu — base = rendimento − dedução,
 *       imposto = base × alíquota − parcela, devido = imposto − redução.
 * Quando o motor nascer (passo 5), ele é testado CONTRA estas fixtures, nunca o contrário
 * (lápide §5 2026-06-05). Valores em centavos inteiros: float não entra aqui.
 */
class FolhaCasosVerdadeFixturesTest extends TestCase
{
    private const DIR = __DIR__.'/../../fixtures/folha-casos-verdade';

    /** @return array<string, array<string, mixed>> */
    private function fixtures(): array
    {
        $out = [];
        foreach (glob(self::DIR.'/*.json') ?: [] as $f) {
            $out[basename($f)] = json_decode((string) file_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
        }

        return $out;
    }

    private static function cents(string $v): int
    {
        if (! preg_match('/^(\d+)\.(\d{2})$/', $v, $m)) {
            throw new \InvalidArgumentException("valor fora do formato 0.00: {$v}");
        }

        return (int) $m[1] * 100 + (int) $m[2];
    }

    /** cents × taxa decimal, arredondado meio-para-cima, só com inteiros. */
    private static function mul(int $cents, string $rate): int
    {
        [$int, $frac] = array_pad(explode('.', $rate), 2, '');
        $den = 10 ** strlen($frac);
        $num = (int) $int * $den + (int) ($frac === '' ? '0' : $frac);

        return intdiv(2 * $cents * $num + $den, 2 * $den);
    }

    /** @return list<string> erros de estrutura de uma fixture */
    public static function errosEstrutura(array $fx): array
    {
        $e = [];
        $ym = '/^\d{4}-(0[1-9]|1[0-2])$/';
        foreach (['id', 'tipo', 'vigencia', 'fonte'] as $k) {
            if (! isset($fx[$k])) {
                $e[] = "sem {$k}";
            }
        }
        if (! preg_match($ym, (string) ($fx['vigencia']['inicio'] ?? ''))) {
            $e[] = 'vigencia.inicio fora de AAAA-MM';
        }
        foreach (['orgao', 'documento', 'url', 'consultado_em'] as $k) {
            if (empty($fx['fonte'][$k])) {
                $e[] = "fonte.{$k} vazio";
            }
        }
        if (! preg_match('#^https://www\.(gov\.br|planalto\.gov\.br)/#', (string) ($fx['fonte']['url'] ?? ''))) {
            $e[] = 'fonte.url não é gov.br/planalto';
        }
        $ini = $fx['vigencia']['inicio'] ?? '';
        $fim = $fx['vigencia']['fim'] ?? null;
        foreach ($fx['casos'] ?? [] as $c) {
            $comp = (string) ($c['competencia'] ?? '');
            if (! preg_match($ym, $comp)) {
                $e[] = "caso {$c['id']}: competência fora de AAAA-MM";
            } elseif ($comp < $ini || ($fim !== null && $comp > $fim)) {
                $e[] = "caso {$c['id']}: competência fora da vigência";
            }
        }
        $prev = null;
        foreach ($fx['faixas'] ?? [] as $i => $f) {
            if ($i === 0 && self::cents($f['de']) !== 0) {
                $e[] = 'primeira faixa não começa em 0.00';
            }
            if ($prev !== null) {
                if (self::cents($f['de']) !== self::cents($prev['ate']) + 1) {
                    $e[] = "faixa {$i} não é contígua à anterior";
                }
                if ((float) $f['aliquota'] <= (float) $prev['aliquota']) {
                    $e[] = "faixa {$i}: alíquota não é crescente";
                }
            }
            $prev = $f;
        }

        return $e;
    }

    /** @return list<string> erros da soma interna de um caso de IRRF */
    public static function errosCasoIrrf(array $fx, array $c): array
    {
        $e = [];
        $rend = self::cents($c['rendimento']);
        $legais = self::cents($c['deducoes_legais']);
        $simpl = self::cents($fx['desconto_simplificado']);
        $esperada = $simpl > $legais ? 'simplificado' : 'legais';
        if ($c['deducao_usada'] !== $esperada) {
            $e[] = "{$c['id']}: dedução usada não é a mais vantajosa";
        }
        $base = self::cents($c['base']);
        if ($base !== $rend - ($c['deducao_usada'] === 'simplificado' ? $simpl : $legais)) {
            $e[] = "{$c['id']}: base ≠ rendimento − dedução";
        }
        $linha = array_values(array_filter($fx['faixas'], fn ($f) => $f['aliquota'] === $c['aliquota'] && $f['deducao'] === $c['deducao_faixa']));
        if (count($linha) !== 1) {
            $e[] = "{$c['id']}: alíquota/parcela não é uma linha da tabela";
        } elseif ($base < self::cents($linha[0]['de']) || ($linha[0]['ate'] !== null && $base > self::cents($linha[0]['ate']))) {
            $e[] = "{$c['id']}: base fora da faixa declarada";
        }
        $tabela = self::cents($c['imposto_tabela']);
        if ($tabela !== max(0, self::mul($base, $c['aliquota']) - self::cents($c['deducao_faixa']))) {
            $e[] = "{$c['id']}: imposto_tabela ≠ base × alíquota − parcela";
        }
        $red = self::cents($c['reducao']);
        if ($red > $tabela || $red > self::cents($fx['reducao_lei_15270']['reducao_maxima'])) {
            $e[] = "{$c['id']}: redução acima do limite";
        }
        if ($rend > self::cents($fx['reducao_lei_15270']['transicao_ate']) && $red !== 0) {
            $e[] = "{$c['id']}: redução acima do teto da transição";
        }
        if (self::cents($c['imposto_devido']) !== $tabela - $red) {
            $e[] = "{$c['id']}: devido ≠ tabela − redução";
        }

        return $e;
    }

    #[Test]
    public function as_fixtures_existem_e_tem_casos(): void
    {
        $fx = $this->fixtures();
        $this->assertGreaterThanOrEqual(4, count($fx), 'fixtures da folha sumiram');
        $casos = array_sum(array_map(fn ($f) => count($f['casos'] ?? []), $fx));
        $this->assertGreaterThanOrEqual(12, $casos, 'casos-verdade sumiram');
    }

    #[Test]
    public function toda_fixture_declara_fonte_vigencia_e_competencia(): void
    {
        foreach ($this->fixtures() as $nome => $fx) {
            $this->assertSame([], self::errosEstrutura($fx), $nome);
        }
    }

    #[Test]
    public function tabela_irrf_publicada_e_internamente_coerente(): void
    {
        $fx = $this->fixtures()['irrf-2026.json'];
        $f = $fx['faixas'];
        // simplificado = 25% do limite da faixa isenta (texto da RFB, Exemplo 1)
        $this->assertSame(self::cents($fx['desconto_simplificado']), self::mul(self::cents($f[0]['ate']), '0.25'));
        // parcela a deduzir_i = parcela_{i-1} + limite_{i-1} × Δalíquota; a RFB publica arredondado (±1 centavo)
        for ($i = 1; $i < count($f); $i++) {
            $delta = self::mul(self::cents($f[$i - 1]['ate']), $f[$i]['aliquota']) - self::mul(self::cents($f[$i - 1]['ate']), $f[$i - 1]['aliquota']);
            $this->assertLessThanOrEqual(1, abs(self::cents($f[$i]['deducao']) - self::cents($f[$i - 1]['deducao']) - $delta), "parcela da faixa {$i}");
        }
    }

    #[Test]
    public function casos_irrf_fecham_a_aritmetica_escrita_pela_rfb(): void
    {
        $fx = $this->fixtures()['irrf-2026.json'];
        foreach ($fx['casos'] as $c) {
            $this->assertSame([], self::errosCasoIrrf($fx, $c), $c['id']);
        }
    }

    #[Test]
    public function casos_fgts_sao_a_aliquota_legal_exata(): void
    {
        $fx = $this->fixtures()['fgts.json'];
        foreach ($fx['casos'] as $c) {
            $r = self::cents($c['remuneracao']);
            $this->assertSame(0, ($r * 8) % 100, "{$c['id']}: o resultado não é exato em centavos");
            $this->assertSame(self::mul($r, $fx['aliquota']), self::cents($c['deposito']), $c['id']);
        }
    }

    #[Test]
    public function casos_inss_caem_dentro_da_tabela_da_competencia(): void
    {
        $fx = $this->fixtures()['inss-2025.json'];
        $teto = self::cents(end($fx['faixas'])['ate']);
        foreach ($fx['casos'] as $c) {
            $s = self::cents($c['salario_contribuicao']);
            $this->assertTrue($s > 0 && $s <= $teto, "{$c['id']}: salário fora da tabela");
            $this->assertGreaterThan(0, self::cents($c['contribuicao']), $c['id']);
        }
    }

    /** Controle negativo: as checagens mordem quando a fixture é adulterada. */
    #[Test]
    public function checagens_mordem_fixture_adulterada(): void
    {
        $fx = $this->fixtures()['irrf-2026.json'];
        $c = $fx['casos'][3];
        $c['imposto_devido'] = '382.87';
        $this->assertNotSame([], self::errosCasoIrrf($fx, $c));
        $c = $fx['casos'][3];
        $c['base'] = '5350.41';
        $this->assertNotSame([], self::errosCasoIrrf($fx, $c));

        $inss = $this->fixtures()['inss-2025.json'];
        $inss['faixas'][2]['de'] = '2793.90';
        $this->assertNotSame([], self::errosEstrutura($inss));
        $inss = $this->fixtures()['inss-2025.json'];
        $inss['casos'][0]['competencia'] = '2026-01';
        $this->assertNotSame([], self::errosEstrutura($inss));
        $inss['fonte']['url'] = 'https://exemplo.com/tabela';
        $this->assertNotSame([], self::errosEstrutura($inss));
    }
}
