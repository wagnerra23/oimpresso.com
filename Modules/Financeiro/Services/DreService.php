<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use App\Business;
use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Titulo;

/**
 * DreService — Demonstração de Resultado hierárquica gerencial.
 *
 * Read-only: agrega Titulo + fin_planos_conta.codigo em estrutura `linhas[]`
 * pronta pro Inertia render. Sem mutação financeira.
 *
 * Espelha o canon Cowork `TelaDRE` em
 * `public/cowork-preview/erp-shell/financeiro-telas-extras.jsx:340-483`.
 *
 * Decisões aplicadas (Wagner aprovou 2026-05-20 — memory/requisitos/Financeiro/
 * dre-visual-comparison.md):
 *  Q1: mapping declarativo PHP via DRE_TEMPLATE (prefixos `fin_planos_conta.codigo`)
 *  Q2: % RL = base Receita Líquida COM SINAL preservado
 *  Q3: Δ% vs mês anterior literal (não média 3m)
 *  Q4: F1 só "mes" funcional; trim/ano/12m label "em breve" (vazio aqui)
 *  Q5: Resultado operacional SEMPRE highlight preto (zero OK)
 *  Q6: meta margem operacional 12% hardcode F1
 *  Q7: Top 3 categorias receita — mesma query do DRE (sem N+1)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): business_id 1º arg sempre.
 * Models usam BusinessScope global scope, mas forçamos `where business_id`
 * defensivo nas queries internas.
 */
class DreService
{
    /**
     * Meta margem operacional (% sobre Receita Líquida) — hardcode F1.
     * F2 vira config `business_settings.dre_margem_meta` (US-FIN-DRE-META).
     */
    private const MARGEM_META_PCT = 12.0;

    /**
     * Template DRE hierárquico canônico — espelha `TelaDRE` linha 340-359.
     *
     * Cada linha:
     *  - type: 'h' (header de seção) | 'i_group' (items expandidos por
     *          categoria/plano_conta) | 'subtotal'
     *  - label: texto exibido (só pra h/subtotal — i_group puxa do nome
     *           da categoria/plano_conta)
     *  - kind: 'rec' (receita) | 'ded' (dedução/custo/despesa) — só pra h
     *  - codigo_prefix: prefix LIKE em `fin_planos_conta.codigo` (h/i_group)
     *  - tipo: 'receita'|'despesa' filtra `fin_titulos.tipo` via plano_conta.tipo
     *  - sign: -1 inverte sinal do valor (deduções/custos/despesas vêm
     *          como SUM positivo no banco; aplicamos -1 pra render)
     *  - key: identifica subtotal pra `calc` referenciar
     *  - calc: fórmula simples (referencia @key anteriores)
     *  - highlight: bool — bg-stone-900 text-white (só Resultado operacional)
     *  - indent: número de níveis indentação visual (i_group)
     *
     * Mapping prefix → plano contas BR (PlanoContasBrSeeder):
     *  Receita bruta  → `3.1.01.001` Receita Bruta de Vendas + `3.1.01.002` Serviços
     *                   → tratado como prefix `3.1.01.` (matches todos itens de receita op.)
     *  Deduções       → `3.1.02.` (-Devoluções e Cancelamentos)
     *  Custos diretos → `4.` (CUSTO + CMV)
     *  Desp. operac.  → `5.` (DESPESA + filhas)
     *
     * Note: o canon visual JSX usa `1.1.`/`1.9.`/`2.1.`/`2.2.` (esquema didático
     * Cowork), mas o seeder real `PlanoContasBrSeeder` segue padrão BR:
     * 3=Receita, 4=Custo, 5=Despesa. Mantemos o seeder como fonte da verdade.
     */
    public const DRE_TEMPLATE = [
        [
            'type'          => 'h',
            'label'         => 'Receita operacional bruta',
            'kind'          => 'rec',
            'codigo_prefix' => '3.1.01.',
            'tipo'          => 'receita',
            'key'           => 'h_rec_bruta',
        ],
        [
            'type'          => 'i_group',
            'codigo_prefix' => '3.1.01.',
            'tipo'          => 'receita',
            'indent'        => 1,
        ],
        [
            'type'          => 'h',
            'label'         => '(−) Deduções',
            'kind'          => 'ded',
            'codigo_prefix' => '3.1.02.',
            'tipo'          => 'receita',
            'sign'          => -1,
            'key'           => 'h_deducoes',
        ],
        [
            'type'          => 'i_group',
            'codigo_prefix' => '3.1.02.',
            'tipo'          => 'receita',
            'sign'          => -1,
            'indent'        => 1,
        ],
        [
            'type' => 'subtotal',
            'label' => 'Receita líquida',
            'key'   => 'receita_liquida',
            'calc'  => '@h_rec_bruta + @h_deducoes',
        ],
        [
            'type'          => 'h',
            'label'         => '(−) Custos diretos',
            'kind'          => 'ded',
            'codigo_prefix' => '4.',
            'tipo'          => 'despesa',
            'sign'          => -1,
            'key'           => 'h_custos',
        ],
        [
            'type'          => 'i_group',
            'codigo_prefix' => '4.',
            'tipo'          => 'despesa',
            'sign'          => -1,
            'indent'        => 1,
        ],
        [
            'type' => 'subtotal',
            'label' => 'Lucro bruto',
            'key'   => 'lucro_bruto',
            'calc'  => '@receita_liquida + @h_custos',
        ],
        [
            'type'          => 'h',
            'label'         => '(−) Despesas operacionais',
            'kind'          => 'ded',
            'codigo_prefix' => '5.',
            'tipo'          => 'despesa',
            'sign'          => -1,
            'key'           => 'h_despesas',
        ],
        [
            'type'          => 'i_group',
            'codigo_prefix' => '5.',
            'tipo'          => 'despesa',
            'sign'          => -1,
            'indent'        => 1,
        ],
        [
            'type'      => 'subtotal',
            'label'     => 'Resultado operacional',
            'key'       => 'resultado_operacional',
            'calc'      => '@lucro_bruto + @h_despesas',
            'highlight' => true,
        ],
    ];

    /**
     * Monta DRE hierárquica.
     *
     * @param  int          $businessId   Tier 0 IRREVOGÁVEL (ADR 0093) — sempre via argumento
     * @param  string       $periodoTipo  'mes' (F1 funcional); 'trimestre'|'ano'|'12m' renderizam vazios (US-FIN-DRE-PERIODOS)
     * @param  string|null  $anchorMes    formato 'YYYY-MM'. Default: now()->format('Y-m')
     *
     * @return array{
     *   meta: array{
     *     periodo_tipo: string,
     *     periodo_label: string,
     *     periodo_label_prev: string,
     *     anchor_mes: string,
     *     prev_mes: string,
     *     base_rl: float,
     *     business_name: string,
     *     business_id: int,
     *     aviso_sem_mapping: bool,
     *   },
     *   linhas: array,
     *   margem_operacional: array{atual_pct: float, meta_pct: float, prev_pct: float, delta_pp: float},
     *   top_categorias_receita: array<int, array{label: string, valor: float, pct: float}>,
     * }
     */
    public function montar(int $businessId, string $periodoTipo = 'mes', ?string $anchorMes = null): array
    {
        return OtelHelper::spanBiz('financeiro.dre.montar', function () use ($businessId, $periodoTipo, $anchorMes): array {
            return $this->montarInternal($businessId, $periodoTipo, $anchorMes);
        }, [
            'business_id' => $businessId,
            'periodo_tipo' => $periodoTipo,
        ]);
    }

    /**
     * @return array{
     *   meta: array{periodo_tipo: string, periodo_label: string, periodo_label_prev: string, anchor_mes: string, prev_mes: string, base_rl: float, business_name: string, business_id: int, aviso_sem_mapping: bool},
     *   linhas: array,
     *   margem_operacional: array{atual_pct: float, meta_pct: float, prev_pct: float, delta_pp: float},
     *   top_categorias_receita: array<int, array{label: string, valor: float, pct: float}>,
     * }
     */
    private function montarInternal(int $businessId, string $periodoTipo, ?string $anchorMes): array
    {
        // ───────── 1) Resolver período (atual + mês anterior) ─────────
        [$anchor, $prev] = $this->resolverMeses($anchorMes);
        $meses = [$anchor->format('Y-m'), $prev->format('Y-m')];

        // ───────── 2) Carregar todos os Titulo do período agrupados ─────────
        // 1 query: GROUP BY (competencia_mes, plano_conta_id). Cobre atual + prev.
        // LEFT JOIN fin_planos_conta pra ter codigo + nome. Filtra business_id
        // defensivo (defesa em profundidade; BusinessScope já filtra).
        $rows = Titulo::query()
            ->leftJoin('fin_planos_conta', 'fin_titulos.plano_conta_id', '=', 'fin_planos_conta.id')
            ->where('fin_titulos.business_id', $businessId)
            ->whereIn('fin_titulos.competencia_mes', $meses)
            ->where('fin_titulos.status', '!=', 'cancelado')
            ->select(
                'fin_titulos.competencia_mes AS competencia_mes',
                'fin_titulos.tipo AS titulo_tipo',
                'fin_titulos.plano_conta_id AS plano_conta_id',
                'fin_planos_conta.codigo AS plano_codigo',
                'fin_planos_conta.nome AS plano_nome',
                'fin_planos_conta.tipo AS plano_tipo',
                DB::raw('SUM(fin_titulos.valor_total) AS total'),
            )
            ->groupBy(
                'fin_titulos.competencia_mes',
                'fin_titulos.tipo',
                'fin_titulos.plano_conta_id',
                'fin_planos_conta.codigo',
                'fin_planos_conta.nome',
                'fin_planos_conta.tipo',
            )
            ->get();

        // ───────── 3) Particionar por mês ─────────
        $rowsAtual = $rows->where('competencia_mes', $anchor->format('Y-m'));
        $rowsPrev = $rows->where('competencia_mes', $prev->format('Y-m'));

        // ───────── 4) Detectar mapping ─────────
        // Se nenhum titulo tem plano_codigo OU nenhum codigo bate com prefixos
        // do DRE_TEMPLATE, ligamos aviso_sem_mapping (frontend mostra banner).
        $avisoSemMapping = $this->detectarSemMapping($rows);

        // ───────── 5) Materializar linhas ─────────
        [$linhas, $headerTotais] = $this->materializarLinhas($rowsAtual, $rowsPrev);

        // ───────── 6) Receita líquida (base RL) ─────────
        $baseRL = (float) ($headerTotais['receita_liquida']['atual'] ?? 0.0);
        $baseRLPrev = (float) ($headerTotais['receita_liquida']['prev'] ?? 0.0);

        // ───────── 6.5) Enriquecer linhas com pct_rl + delta_pct ─────────
        // Hotfix 2026-05-20: frontend (Pages/Financeiro/Dre/Index.tsx) acessa
        // `l.pct_rl.toFixed(1)` e `l.delta_pct.toFixed(0)` em CADA linha.
        // `materializarLinhas()` só popula `v` + `prev` — sem isso, prod gera
        // "TypeError: can't access property 'toFixed', t.pct_rl is undefined".
        // Q2 (aprovada Wagner 2026-05-20): % RL com sinal preservado do numerador,
        // base = Receita Líquida (subtotal). Q3: Δ% vs mês anterior literal.
        //
        // Hotfix 2026-05-20 #2: variáveis locais com nome distinto pra NÃO
        // shadow `$prev` (Carbon do escopo `montarInternal()`) e `$v` (caso
        // seja usado depois). O bug "mesLabelPtBr float given" vinha de
        // `$prev` ter sido sobrescrita pelo último iteration deste loop.
        foreach ($linhas as &$linhaRef) {
            $linhaV = (float) ($linhaRef['v'] ?? 0.0);
            $linhaPrev = (float) ($linhaRef['prev'] ?? 0.0);
            $linhaRef['pct_rl'] = $baseRL != 0.0 ? round(($linhaV / $baseRL) * 100.0, 2) : 0.0;
            $linhaRef['delta_pct'] = $linhaPrev != 0.0 ? round((($linhaV - $linhaPrev) / abs($linhaPrev)) * 100.0, 2) : 0.0;
        }
        unset($linhaRef);

        // ───────── 7) Margem operacional ─────────
        $resOpAtual = (float) ($headerTotais['resultado_operacional']['atual'] ?? 0.0);
        $resOpPrev = (float) ($headerTotais['resultado_operacional']['prev'] ?? 0.0);

        $margemAtual = $baseRL > 0.0 ? round(($resOpAtual / $baseRL) * 100.0, 2) : 0.0;
        $margemPrev = $baseRLPrev > 0.0 ? round(($resOpPrev / $baseRLPrev) * 100.0, 2) : 0.0;

        $margemOperacional = [
            'atual_pct' => $margemAtual,
            'meta_pct'  => self::MARGEM_META_PCT,
            'prev_pct'  => $margemPrev,
            'delta_pp'  => round($margemAtual - $margemPrev, 2),
        ];

        // ───────── 8) Top 3 categorias receita ─────────
        // Reaproveita rowsAtual já carregadas. Top 3 da seção "Receita
        // operacional bruta" (codigo prefix `3.1.01.`) por valor desc.
        $topCategorias = $this->topCategoriasReceita($rowsAtual, $baseRL);

        // ───────── 9) Meta ─────────
        $businessName = (string) ($this->resolverBusinessName($businessId) ?? '');

        return [
            'meta'                   => [
                'periodo_tipo'        => $periodoTipo,
                'periodo_label'       => $this->mesLabelPtBr($anchor),
                'periodo_label_prev'  => $this->mesLabelPtBr($prev),
                'anchor_mes'          => $anchor->format('Y-m'),
                'prev_mes'            => $prev->format('Y-m'),
                'base_rl'             => $baseRL,
                'business_name'       => $businessName,
                'business_id'         => $businessId,
                'aviso_sem_mapping'   => $avisoSemMapping,
            ],
            'linhas'                 => $linhas,
            'margem_operacional'     => $margemOperacional,
            'top_categorias_receita' => $topCategorias,
        ];
    }

    /**
     * Resolve mês âncora + mês anterior.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolverMeses(?string $anchorMes): array
    {
        if ($anchorMes && preg_match('/^\d{4}-\d{2}$/', $anchorMes)) {
            try {
                $anchor = Carbon::createFromFormat('Y-m', $anchorMes)->startOfMonth();
            } catch (\Throwable $e) {
                $anchor = Carbon::now()->startOfMonth();
            }
        } else {
            $anchor = Carbon::now()->startOfMonth();
        }

        $prev = $anchor->copy()->subMonthNoOverflow();

        return [$anchor, $prev];
    }

    /**
     * Detecta se o business tem mapping hierárquico nos planos de conta.
     * Verdadeiro = não há nenhum titulo apontando pra plano_conta com codigo
     * que dê match em prefix do template.
     */
    private function detectarSemMapping(\Illuminate\Support\Collection $rows): bool
    {
        if ($rows->isEmpty()) {
            return false; // sem dados ainda; não é "sem mapping", é "sem movimento"
        }

        $prefixes = [];
        foreach (self::DRE_TEMPLATE as $line) {
            if (! empty($line['codigo_prefix'])) {
                $prefixes[$line['codigo_prefix']] = true;
            }
        }

        foreach ($rows as $r) {
            $codigo = (string) ($r->plano_codigo ?? '');
            if ($codigo === '') {
                continue;
            }
            foreach (array_keys($prefixes) as $prefix) {
                if (str_starts_with($codigo, $prefix)) {
                    return false; // achou pelo menos 1 match
                }
            }
        }

        return true; // tem titulos mas nenhum bate com prefixos
    }

    /**
     * Materializa o array `linhas[]` percorrendo DRE_TEMPLATE + agregando rows.
     *
     * Retorna [linhas, headerTotais] onde headerTotais é mapa key=>['atual'=>x,'prev'=>y]
     * usado pelo `calc` dos subtotais.
     *
     * @return array{0: array, 1: array<string, array{atual: float, prev: float}>}
     */
    private function materializarLinhas(\Illuminate\Support\Collection $rowsAtual, \Illuminate\Support\Collection $rowsPrev): array
    {
        $linhas = [];
        $totaisPorKey = [];

        foreach (self::DRE_TEMPLATE as $tpl) {
            $type = $tpl['type'];

            if ($type === 'h') {
                $sign = (int) ($tpl['sign'] ?? 1);
                $valAtual = $this->somarPorPrefix($rowsAtual, $tpl['codigo_prefix'], $tpl['tipo']) * $sign;
                $valPrev = $this->somarPorPrefix($rowsPrev, $tpl['codigo_prefix'], $tpl['tipo']) * $sign;

                $linhas[] = [
                    'type'  => 'h',
                    'label' => $tpl['label'],
                    'kind'  => $tpl['kind'] ?? 'rec',
                    'v'     => round($valAtual, 2),
                    'prev'  => round($valPrev, 2),
                ];

                if (! empty($tpl['key'])) {
                    $totaisPorKey[$tpl['key']] = ['atual' => $valAtual, 'prev' => $valPrev];
                }
            } elseif ($type === 'i_group') {
                $sign = (int) ($tpl['sign'] ?? 1);
                $indent = (int) ($tpl['indent'] ?? 1);
                $items = $this->itemsPorPrefix($rowsAtual, $rowsPrev, $tpl['codigo_prefix'], $tpl['tipo'], $sign);

                foreach ($items as $it) {
                    $linhas[] = [
                        'type'   => 'i',
                        'label'  => $it['label'],
                        'v'      => round($it['v'], 2),
                        'prev'   => round($it['prev'], 2),
                        'indent' => $indent,
                    ];
                }
            } elseif ($type === 'subtotal') {
                [$valAtual, $valPrev] = $this->avaliarCalc($tpl['calc'] ?? '', $totaisPorKey);

                $linha = [
                    'type'  => 'subtotal',
                    'label' => $tpl['label'],
                    'key'   => $tpl['key'] ?? '',
                    'v'     => round($valAtual, 2),
                    'prev'  => round($valPrev, 2),
                ];
                if (! empty($tpl['highlight'])) {
                    $linha['highlight'] = true;
                }
                $linhas[] = $linha;

                if (! empty($tpl['key'])) {
                    $totaisPorKey[$tpl['key']] = ['atual' => $valAtual, 'prev' => $valPrev];
                }
            }
        }

        return [$linhas, $totaisPorKey];
    }

    /**
     * SUM(total) das rows que tem plano_codigo começando com $prefix E
     * plano_tipo = $tipo (receita/despesa). Tratamento de NULL plano_tipo
     * cai pra `tipo` do título (receber/pagar).
     */
    private function somarPorPrefix(\Illuminate\Support\Collection $rows, string $prefix, string $tipo): float
    {
        $sum = 0.0;
        foreach ($rows as $r) {
            $codigo = (string) ($r->plano_codigo ?? '');
            if ($codigo === '' || ! str_starts_with($codigo, $prefix)) {
                continue;
            }
            // Validação de tipo: plano_tipo (do plano_conta) OU fallback titulo_tipo
            if (! $this->planoCasaTipo($r, $tipo)) {
                continue;
            }
            $sum += (float) $r->total;
        }

        return $sum;
    }

    /**
     * Items agrupados por plano_conta — pega rowsAtual e rowsPrev, faz outer
     * join por (codigo, nome) e retorna lista ordenada por valor atual desc.
     *
     * @return array<int, array{label: string, v: float, prev: float}>
     */
    private function itemsPorPrefix(\Illuminate\Support\Collection $rowsAtual, \Illuminate\Support\Collection $rowsPrev, string $prefix, string $tipo, int $sign): array
    {
        $buckets = [];

        $append = function (\Illuminate\Support\Collection $rows, string $field) use ($prefix, $tipo, $sign, &$buckets): void {
            foreach ($rows as $r) {
                $codigo = (string) ($r->plano_codigo ?? '');
                if ($codigo === '' || ! str_starts_with($codigo, $prefix)) {
                    continue;
                }
                if (! $this->planoCasaTipo($r, $tipo)) {
                    continue;
                }
                $label = (string) ($r->plano_nome ?? '(sem categoria)');
                $key = $codigo.'|'.$label;
                if (! isset($buckets[$key])) {
                    $buckets[$key] = ['label' => $label, 'v' => 0.0, 'prev' => 0.0];
                }
                $buckets[$key][$field] += ((float) $r->total) * $sign;
            }
        };

        $append($rowsAtual, 'v');
        $append($rowsPrev, 'prev');

        $list = array_values($buckets);

        // Ordena por abs(valor atual) desc — mais relevante primeiro
        usort($list, fn ($a, $b) => abs($b['v']) <=> abs($a['v']));

        return $list;
    }

    /**
     * Verifica se o plano_tipo da row casa com $tipo do template.
     *
     * Plano de contas pode ter tipos: receita, custo, despesa, ativo, passivo,
     * patrimonio. Mapping pro template:
     *   - 'receita'  → plano.tipo = 'receita'
     *   - 'despesa'  → plano.tipo IN ('custo','despesa')
     *
     * Fallback (plano_tipo NULL): usa titulo.tipo (receber → receita; pagar →
     * despesa). Casos legacy onde titulo não tem plano_conta_id.
     */
    private function planoCasaTipo(object $r, string $tipo): bool
    {
        $planoTipo = $r->plano_tipo ?? null;

        if ($planoTipo === null) {
            // Fallback titulo.tipo
            $tituloTipo = (string) ($r->titulo_tipo ?? '');
            if ($tipo === 'receita') {
                return $tituloTipo === 'receber';
            }

            return $tituloTipo === 'pagar';
        }

        if ($tipo === 'receita') {
            return $planoTipo === 'receita';
        }

        // despesa = custo OR despesa
        return in_array($planoTipo, ['custo', 'despesa'], true);
    }

    /**
     * Avalia uma fórmula simples no formato `@key1 + @key2 + @key3` (só `+` e `-`).
     *
     * Retorna [atual, prev].
     *
     * @return array{0: float, 1: float}
     */
    private function avaliarCalc(string $calc, array $totais): array
    {
        $calc = trim($calc);
        if ($calc === '') {
            return [0.0, 0.0];
        }

        $atual = 0.0;
        $prev = 0.0;
        $sign = 1;
        $i = 0;
        $len = strlen($calc);

        while ($i < $len) {
            $ch = $calc[$i];

            if ($ch === ' ') {
                $i++;
                continue;
            }
            if ($ch === '+') {
                $sign = 1;
                $i++;
                continue;
            }
            if ($ch === '-') {
                $sign = -1;
                $i++;
                continue;
            }
            if ($ch === '@') {
                $j = $i + 1;
                while ($j < $len && (ctype_alnum($calc[$j]) || $calc[$j] === '_')) {
                    $j++;
                }
                $key = substr($calc, $i + 1, $j - $i - 1);
                $atual += $sign * (float) ($totais[$key]['atual'] ?? 0.0);
                $prev += $sign * (float) ($totais[$key]['prev'] ?? 0.0);
                $sign = 1;
                $i = $j;
                continue;
            }

            // char inesperado — skip
            $i++;
        }

        return [$atual, $prev];
    }

    /**
     * Top 3 categorias de receita (codigo prefix `3.1.01.`) por valor atual desc.
     *
     * @return array<int, array{label: string, valor: float, pct: float}>
     */
    private function topCategoriasReceita(\Illuminate\Support\Collection $rowsAtual, float $baseRL): array
    {
        $items = $this->itemsPorPrefix($rowsAtual, collect(), '3.1.01.', 'receita', 1);

        $top = array_slice($items, 0, 3);

        return array_map(function ($it) use ($baseRL) {
            $valor = (float) $it['v'];
            $pct = $baseRL > 0.0 ? round(($valor / $baseRL) * 100.0, 1) : 0.0;

            return [
                'label' => $it['label'],
                'valor' => round($valor, 2),
                'pct'   => $pct,
            ];
        }, $top);
    }

    /**
     * Resolve business.name. Usa cache local — não é hot path.
     */
    private function resolverBusinessName(int $businessId): ?string
    {
        try {
            return Business::query()
                ->where('id', $businessId)
                ->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * "2026-05" → "Maio 2026" (PT-BR Carbon).
     */
    private function mesLabelPtBr(Carbon $date): string
    {
        $meses = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];

        return ($meses[(int) $date->format('n')] ?? '') . ' ' . $date->format('Y');
    }
}
