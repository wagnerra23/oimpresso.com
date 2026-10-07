<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Playbook Fiscal thread 09 · alíquotas INTERESTADUAIS do ICMS por par de UFs (D-UF).
 *
 * Lei 4 do módulo: só entra número com a norma citada literal. A norma é a
 * **Resolução do Senado Federal nº 22, de 19 de maio de 1989** (publicação original:
 * https://www2.camara.leg.br/legin/fed/ressen/1989/resolucao-22-19-maio-1989-481183-publicacaooriginal-1-pl.html):
 *
 *   Art. 1º "A alíquota do Imposto sobre Operações Relativas à Circulação de Mercadorias e sobre
 *   Prestação de Serviços de Transporte Interestadual e Intermunicipal e de Comunicação, nas
 *   operações e prestações interestaduais, será de doze por cento."
 *
 *   Parágrafo único. "Nas operações e prestações realizadas nas Regiões Sul e Sudeste, destinadas às
 *   Regiões Norte, Nordeste e Centro-Oeste e ao Estado do Espírito Santo, as alíquotas serão:
 *   I - em 1989, oito por cento;
 *   II - a partir de 1990, sete por cento."
 *
 * Ou seja: 12% como regra; 7% de origem no Sul ou Sudeste para destino no Norte, Nordeste,
 * Centro-Oeste ou ES. Origem = destino é operação interna: interestadual NULL.
 *
 * NÃO semeia: a alíquota INTERNA nem o FCP (o contador preenche; sem número sem lei) e os 4% da
 * Resolução do Senado nº 13/2012, que dependem da origem do PRODUTO importado, não do par de UFs.
 *
 * Idempotente e append-only: insere só o par que a empresa ainda não tem; NUNCA atualiza linha
 * existente — se o contador já preencheu a interna, o re-seed não a toca.
 */
class NfeIcmsUfSeeder extends Seeder
{
    public const FONTE = 'Resolução do Senado Federal nº 22/1989, art. 1º e parágrafo único, II';

    public const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    /** "Regiões Sul e Sudeste" — origem do parágrafo único. */
    private const SUL_SUDESTE = ['PR', 'SC', 'RS', 'SP', 'RJ', 'MG', 'ES'];

    /** "Regiões Norte, Nordeste e Centro-Oeste e ao Estado do Espírito Santo" — destino. */
    private const DESTINO_7 = [
        'AC', 'AP', 'AM', 'PA', 'RO', 'RR', 'TO',                      // Norte
        'AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE',          // Nordeste
        'DF', 'GO', 'MT', 'MS',                                        // Centro-Oeste
        'ES',                                                          // "e ao Estado do Espírito Santo"
    ];

    public function run(): void
    {
        $ids = DB::table('nfe_business_configs')
            ->join('business', 'business.id', '=', 'nfe_business_configs.business_id')
            ->orderBy('nfe_business_configs.business_id')
            ->pluck('nfe_business_configs.business_id');

        foreach ($ids as $id) {
            $this->semear((int) $id);
        }
    }

    /** Semeia os pares que faltam para a empresa. Devolve quantas linhas inseriu. */
    public function semear(int $businessId): int
    {
        $existentes = DB::table('nfe_icms_uf')
            ->where('business_id', $businessId)
            ->get(['uf_origem', 'uf_destino'])
            ->map(fn ($r) => $r->uf_origem . $r->uf_destino)
            ->flip();

        $agora = now();
        $linhas = [];
        foreach (self::UFS as $origem) {
            foreach (self::UFS as $destino) {
                if (isset($existentes[$origem . $destino])) {
                    continue;
                }
                $linhas[] = [
                    'business_id'            => $businessId,
                    'uf_origem'              => $origem,
                    'uf_destino'             => $destino,
                    'aliquota_interestadual' => self::interestadual($origem, $destino),
                    'aliquota_interna'       => null,
                    'fcp'                    => null,
                    'valida_de'              => null,
                    'valida_ate'             => null,
                    'fonte'                  => $origem === $destino ? null : self::FONTE,
                    'created_at'             => $agora,
                    'updated_at'             => $agora,
                ];
            }
        }

        foreach (array_chunk($linhas, 200) as $lote) {
            DB::table('nfe_icms_uf')->insert($lote);
        }

        return count($linhas);
    }

    /** A regra da resolução, em decimal (0.12 / 0.07). Origem = destino → null. */
    public static function interestadual(string $origem, string $destino): ?float
    {
        if ($origem === $destino) {
            return null;
        }

        return in_array($origem, self::SUL_SUDESTE, true) && in_array($destino, self::DESTINO_7, true)
            ? 0.07
            : 0.12;
    }
}
