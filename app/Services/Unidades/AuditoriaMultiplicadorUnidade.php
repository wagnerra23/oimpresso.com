<?php

declare(strict_types=1);

namespace App\Services\Unidades;

use App\Unit;
use App\Utils\Util;
use Illuminate\Support\Facades\DB;

/**
 * Mede o dano JÁ GRAVADO pelo bug do modal "Editar unidade" (PR #8394, decisão [W] 2026-10-01 item 11).
 *
 * O bug: `resources/views/unit/edit.blade.php` exibia `base_unit_multiplier` com `number_format()`
 * SEM casas decimais; salvar sem mexer passava esse texto por `Util::num_uf()` e gravava:
 *   1000 → "1,000" → 1        0,5 → "1" → 1        2,5 → "3" → 3        1500,75 → "1,501" → 1,501
 * E cada novo save sem mexer re-corrompe (1500,75 → 1,501 → 2). Em paralelo, a AUSÊNCIA de
 * `define_base_unit` no POST zerava base e multiplicador.
 *
 * Fonte do valor anterior: `activity_log` — `App\Unit` usa `LogsActivity` (logAll + logOnlyDirty,
 * log_name `unit`) desde 2026-05-16 (#959). Antes disso NÃO há log: só sobra heurística, sem
 * valor proposto (decisão [W]).
 *
 * Duas provas por unidade recuperável (REGRA MESTRE de estoque, memory/proibicoes.md):
 *   prova 1 — o VALOR: o `old` do activity_log, snapshot cru do banco antes do save.
 *   prova 2 — o MECANISMO: reaplicar a função do bug ao valor proposto, passo a passo pela cadeia,
 *             reproduz EXATAMENTE o valor atual. Edição manual comum não reproduz.
 *   (extra) — o valor do evento `created`, quando existir, é uma 3ª fonte independente.
 *
 * ⚠️ Por que não reusar `Modules\Auditoria\Services\RevertService`: ele restaura TODOS os campos
 * `old` de UMA entrada (cadeia 1500,75→1,501→2 exigiria reverter N entradas e arrastaria nome/
 * símbolo) e exige `activity_log.business_id` = business do usuário — o trait `LogsActivity` não
 * preenche essa coluna para `Unit`, então ele negaria todas.
 *
 * Só LÊ. Multi-tenant Tier 0: toda query escopada por `business_id` (ADR 0093); o `activity_log`
 * é filtrado pelos ids de unidade do próprio business.
 */
class AuditoriaMultiplicadorUnidade
{
    public const RECUPERAVEL = 'RECUPERAVEL';

    public const BASE_REMOVIDA = 'BASE_REMOVIDA';

    public const CORROMPIDA_DEPOIS_EDITADA = 'CORROMPIDA_DEPOIS_EDITADA';

    public const SUSPEITA_SEM_PROVA = 'SUSPEITA_SEM_PROVA';

    /** Início do log de `Unit` (#959). Edição anterior a isto não tem valor recuperável. */
    public const INICIO_DO_LOG = '2026-05-16';

    public function __construct(private Util $util) {}

    /**
     * Valores que o bug produz a partir de `$valor` (um save sem mexer).
     * Dois parsers históricos: o `num_uf` atual (vírgula = decimal) e o legado com separador
     * en-US (vírgula = milhar, que só sofre o arredondamento).
     *
     * @return list<float>
     */
    public function corromper(float $valor): array
    {
        $texto = number_format($valor);
        $saidas = [
            round((float) $this->util->num_uf($texto), 4),
            round((float) str_replace(',', '', $texto), 4),
        ];

        return array_values(array_unique(array_filter($saidas, fn (float $s) => ! $this->igual($s, $valor))));
    }

    public function igual(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return abs(round($a, 4) - round($b, 4)) < 0.00005;
    }

    /**
     * @return list<array<string,mixed>> uma linha por unidade com achado (vazio = nada suspeito)
     */
    public function auditar(int $businessId): array
    {
        $unidades = Unit::where('business_id', $businessId)->orderBy('id')->get([
            'id', 'actual_name', 'short_name', 'base_unit_id', 'base_unit_multiplier', 'created_at', 'updated_at',
        ]);
        if ($unidades->isEmpty()) {
            return [];
        }

        $ids = $unidades->pluck('id')->all();
        $simbolos = Unit::withTrashed()->where('business_id', $businessId)->pluck('short_name', 'id');
        $logs = $this->logsPorUnidade($ids);
        $usoComoSub = $this->usoComoSubunidade($businessId);

        $irmas = [];
        foreach ($unidades as $u) {
            if ($u->base_unit_id !== null && $u->base_unit_multiplier !== null) {
                $irmas[$u->base_unit_id][] = round((float) $u->base_unit_multiplier, 4);
            }
        }

        $linhas = [];
        foreach ($unidades as $u) {
            $achado = $this->diagnosticar($u, $logs[$u->id] ?? [], $irmas, $usoComoSub[$u->id] ?? 0);
            if ($achado === null) {
                continue;
            }
            $achado['unit_id'] = (int) $u->id;
            $achado['unidade'] = (string) $u->short_name;
            $achado['base_atual'] = $u->base_unit_id !== null ? (string) ($simbolos[$u->base_unit_id] ?? '#'.$u->base_unit_id) : null;
            $achado['base_proposta'] = isset($achado['base_proposta_id']) ? (string) ($simbolos[$achado['base_proposta_id']] ?? '#'.$achado['base_proposta_id']) : $achado['base_atual'];
            $achado['produtos_com_subunidade'] = $usoComoSub[$u->id] ?? 0;
            $achado += $this->linhasAfetadas($businessId, (int) $u->id, $achado['desde'] ?? null);
            $linhas[] = $achado;
        }

        return $linhas;
    }

    /**
     * @param  list<array<string,mixed>>  $logs  entradas `updated`/`created` ascendentes
     * @param  array<int,list<float>>  $irmas
     */
    private function diagnosticar(Unit $u, array $logs, array $irmas, int $produtosComSub): ?array
    {
        $atualMult = $u->base_unit_multiplier !== null ? round((float) $u->base_unit_multiplier, 4) : null;
        $atualBase = $u->base_unit_id !== null ? (int) $u->base_unit_id : null;

        $relevantes = [];
        $criacao = null;
        foreach ($logs as $e) {
            if ($e['evento'] === 'created') {
                $criacao = $e['novo_mult'];

                continue;
            }
            $tocouMult = $e['tem_mult'] && ! $this->igual($e['velho_mult'], $e['novo_mult']);
            $tocouBase = $e['tem_base'] && $e['velho_base'] !== $e['novo_base'];
            if (! $tocouMult && ! $tocouBase) {
                continue;
            }
            $e['tipo'] = match (true) {
                $e['velho_base'] !== null && $e['tem_base'] && $e['novo_base'] === null => 'base_removida',
                $tocouMult && $e['velho_mult'] !== null && $e['novo_mult'] !== null
                    && $this->contem($this->corromper($e['velho_mult']), $e['novo_mult']) => 'arredondado',
                default => 'edicao',
            };
            $relevantes[] = $e;
        }

        // Caminha de trás pra frente: a cadeia de assinaturas tem de terminar no estado ATUAL.
        $mult = $atualMult;
        $base = $atualBase;
        $baseProposta = $atualBase;
        $passos = 0;
        $desde = null;
        $removida = false;
        $alvoProva = $atualMult;
        for ($i = count($relevantes) - 1; $i >= 0; $i--) {
            $e = $relevantes[$i];
            if ($e['tipo'] === 'base_removida' && ! $removida && $passos === 0 && $base === null && $mult === null) {
                $removida = true;
                $baseProposta = $e['velho_base'];
                $mult = $e['velho_mult'];
                $alvoProva = $e['velho_mult'];
                $base = $e['velho_base'];
                $desde = $e['quando'];

                continue;
            }
            if ($e['tipo'] === 'arredondado' && $this->igual($e['novo_mult'], $mult)) {
                $mult = $e['velho_mult'];
                $passos++;
                $desde = $e['quando'];

                continue;
            }
            break;
        }

        $temUpdate = in_array('updated', array_column($logs, 'evento'), true);
        $sinais = $this->heuristicas($u, $atualMult, $atualBase, $irmas, $produtosComSub, $temUpdate);
        $prova2 = $passos > 0 ? $this->reproduz($mult, $passos, $alvoProva) : null;

        if ($passos > 0 || $removida) {
            return [
                'classe' => $removida ? self::BASE_REMOVIDA : self::RECUPERAVEL,
                'mult_atual' => $atualMult,
                'mult_proposto' => $mult,
                'base_proposta_id' => $baseProposta,
                'passos_corrompidos' => $passos,
                'desde' => $desde,
                'prova_valor_log' => true,
                'prova_mecanismo' => $removida && $passos === 0 ? null : $prova2,
                'valor_na_criacao' => $criacao,
                'sinais' => $sinais,
            ];
        }

        // Houve corrupção, mas alguém editou depois. Se a edição já devolveu o valor de antes da
        // cadeia (correção manual ou por `units:corrigir-multiplicador`), não há dano a reportar.
        $tipos = array_column($relevantes, 'tipo');
        $ultima = array_search('arredondado', array_reverse($tipos, true), true);
        if ($ultima !== false) {
            $origem = $relevantes[$ultima]['velho_mult'];
            for ($i = $ultima - 1; $i >= 0 && $relevantes[$i]['tipo'] === 'arredondado' && $this->igual($relevantes[$i]['novo_mult'], $origem); $i--) {
                $origem = $relevantes[$i]['velho_mult'];
            }
            if (! $this->igual($origem, $atualMult)) {
                return [
                    'classe' => self::CORROMPIDA_DEPOIS_EDITADA,
                    'mult_atual' => $atualMult,
                    'mult_proposto' => null,
                    'mult_antes_da_corrupcao' => $origem,
                    'desde' => $relevantes[$ultima]['quando'],
                    'valor_na_criacao' => $criacao,
                    'sinais' => $sinais,
                ];
            }
        }

        // `editada_antes_do_log` sozinho não acusa: toda unidade editada antes de 2026-05-16 o teria.
        if (array_diff($sinais, ['editada_antes_do_log']) !== []) {
            return [
                'classe' => self::SUSPEITA_SEM_PROVA,
                'mult_atual' => $atualMult,
                'mult_proposto' => null,
                'valor_na_criacao' => $criacao,
                'sinais' => $sinais,
            ];
        }

        return null;
    }

    /** Prova 2: aplica o bug `$passos` vezes ao proposto e confere que chega ao atual. */
    private function reproduz(?float $proposto, int $passos, ?float $alvo): bool
    {
        if ($proposto === null || $alvo === null) {
            return false;
        }
        $frente = [$proposto];
        for ($p = 0; $p < $passos; $p++) {
            $prox = [];
            foreach ($frente as $v) {
                foreach ($this->corromper($v) as $c) {
                    $prox[] = $c;
                }
            }
            $frente = $prox;
        }

        return $this->contem($frente, $alvo);
    }

    /** @param list<float> $valores */
    private function contem(array $valores, ?float $alvo): bool
    {
        foreach ($valores as $v) {
            if ($this->igual($v, $alvo)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function heuristicas(Unit $u, ?float $mult, ?int $base, array $irmas, int $produtosComSub, bool $temUpdate): array
    {
        $s = [];
        if ($base !== null && $mult !== null) {
            if ($this->igual($mult, 1.0)) {
                $s[] = 'multiplicador_igual_a_1';
            }
            $milesimos = $mult * 1000;
            if ($mult >= 1 && floor($mult) != $mult && abs($milesimos - round($milesimos)) < 0.00005 && abs($mult * 100 - round($mult * 100)) >= 0.00005) {
                $s[] = 'milhar_lido_como_decimal';
            }
            $mesmas = array_filter($irmas[$base] ?? [], fn (float $m) => $this->igual($m, $mult));
            if (count($mesmas) > 1) {
                $s[] = 'irma_com_mesma_conversao';
            }
            if (! $temUpdate && $u->updated_at && $u->created_at && $u->updated_at->gt($u->created_at)) {
                $s[] = 'editada_antes_do_log';
            }
        }
        if ($base === null && $produtosComSub > 0) {
            $s[] = 'sem_base_mas_usada_como_subunidade';
        }

        return $s;
    }

    /** @param list<int> $ids  @return array<int,list<array<string,mixed>>> */
    private function logsPorUnidade(array $ids): array
    {
        $porUnidade = [];
        foreach (array_chunk($ids, 500) as $lote) {
            $rows = DB::table('activity_log')
                ->whereIn('subject_type', [Unit::class, (new Unit)->getMorphClass()])
                ->whereIn('subject_id', $lote)
                ->where('log_name', 'unit')
                ->whereIn('event', ['created', 'updated'])
                ->orderBy('id')
                ->get(['subject_id', 'event', 'properties', 'created_at']);
            foreach ($rows as $r) {
                $p = json_decode((string) $r->properties, true) ?: [];
                $novo = (array) ($p['attributes'] ?? []);
                $velho = (array) ($p['old'] ?? []);
                $porUnidade[(int) $r->subject_id][] = [
                    'evento' => $r->event,
                    'quando' => (string) $r->created_at,
                    'tem_mult' => array_key_exists('base_unit_multiplier', $novo),
                    'tem_base' => array_key_exists('base_unit_id', $novo),
                    'velho_mult' => $this->num($velho['base_unit_multiplier'] ?? null),
                    'novo_mult' => $this->num($novo['base_unit_multiplier'] ?? null),
                    'velho_base' => isset($velho['base_unit_id']) ? (int) $velho['base_unit_id'] : null,
                    'novo_base' => isset($novo['base_unit_id']) ? (int) $novo['base_unit_id'] : null,
                ];
            }
        }

        return $porUnidade;
    }

    private function num(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : round((float) $v, 4);
    }

    /** @return array<int,int> unit_id => nº de produtos do business que a listam em `sub_unit_ids` */
    private function usoComoSubunidade(int $businessId): array
    {
        $contagem = [];
        $rows = DB::table('products')->where('business_id', $businessId)->whereNotNull('sub_unit_ids')->pluck('sub_unit_ids');
        foreach ($rows as $txt) {
            $lista = json_decode((string) $txt, true);
            foreach (is_array($lista) ? array_unique(array_map('intval', $lista)) : [] as $id) {
                $contagem[$id] = ($contagem[$id] ?? 0) + 1;
            }
        }

        return $contagem;
    }

    /** Linhas de venda/compra lançadas nesta sub-unidade desde a 1ª corrupção (ou no total). */
    private function linhasAfetadas(int $businessId, int $unitId, ?string $desde): array
    {
        $conta = function (string $tabela) use ($businessId, $unitId, $desde): int {
            $q = DB::table($tabela.' as l')
                ->join('transactions as t', 't.id', '=', 'l.transaction_id')
                ->where('t.business_id', $businessId)
                ->where('l.sub_unit_id', $unitId);
            if ($desde !== null) {
                $q->where('l.created_at', '>=', $desde);
            }

            return (int) $q->count();
        };

        return [
            'linhas_venda' => $conta('transaction_sell_lines'),
            'linhas_compra' => $conta('purchase_lines'),
        ];
    }
}
