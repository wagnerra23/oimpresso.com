<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Montavel;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Roda um relatório montável (US-REL-001) a partir de uma DEFINIÇÃO:
 *   ['fonte' => 'vendas', 'campos' => [...], 'filtros' => [chave => valor], 'de' => 'Y-m-d', 'ate' => 'Y-m-d', 'grupo' => chave|null]
 *
 * Toda chave é conferida contra FontesDoRelatorio; chave desconhecida = InvalidArgumentException.
 * Campo de custo some para quem não vê preço de compra (`$veCusto = false`), inclusive nos totais.
 * `$locais` = locais permitidos do usuário ('all' ou lista), como os relatórios prontos fazem.
 * Os totais são somados no banco sobre o recorte inteiro, não sobre a página.
 */
final class RelatorioMontavel
{
    public const LIMITE_LINHAS = 1000;

    public function __construct(private readonly FontesDoRelatorio $fontes) {}

    /** Confere a definição e devolve a versão limpa (só chaves conhecidas). */
    public function validar(array $def, bool $veCusto): array
    {
        $fonte = (string) ($def['fonte'] ?? '');
        if (! in_array($fonte, $this->fontes->nomes(), true)) {
            throw new \InvalidArgumentException('Fonte inválida.');
        }
        $d = $this->fontes->definicao($fonte);
        $campos = array_values(array_unique((array) ($def['campos'] ?? [])));
        foreach ($campos as $c) {
            if (! isset($d['campos'][$c])) {
                throw new \InvalidArgumentException("Campo inválido: {$c}");
            }
        }
        $campos = array_values(array_filter($campos, fn ($c) => $veCusto || empty($d['campos'][$c][3])));
        if ($campos === []) {
            throw new \InvalidArgumentException('Escolha ao menos um campo.');
        }
        $filtros = array_filter((array) ($def['filtros'] ?? []), fn ($v) => $v !== null && $v !== '');
        foreach (array_keys($filtros) as $f) {
            if (! isset($d['filtros'][$f])) {
                throw new \InvalidArgumentException("Filtro inválido: {$f}");
            }
        }
        $grupo = $def['grupo'] ?? null;
        if ($grupo !== null && $grupo !== '' && ! isset($d['grupos'][$grupo])) {
            throw new \InvalidArgumentException("Agrupamento inválido: {$grupo}");
        }
        $data = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        return [
            'fonte' => $fonte, 'campos' => $campos, 'filtros' => $filtros,
            'de' => $data($def['de'] ?? null), 'ate' => $data($def['ate'] ?? null),
            'grupo' => $grupo ?: null, 'grafico' => in_array($def['grafico'] ?? null, ['barras', 'linha'], true) ? $def['grafico'] : null,
        ];
    }

    /** @return array{colunas: list<array{0:string,1:string,2:string}>, linhas: list<array>, totais: array<string, float>, truncado: bool} */
    public function executar(int $businessId, array $def, bool $veCusto, array|string $locais = 'all'): array
    {
        $def = $this->validar($def, $veCusto);
        $d = $this->fontes->definicao($def['fonte']);
        $q = $this->recorte($businessId, $def, $d, $locais);
        $somaveis = array_values(array_filter($def['campos'], fn ($c) => in_array($d['campos'][$c][2], ['brl', 'num'], true)));

        if ($def['grupo']) {
            $expr = $d['grupos'][$def['grupo']];
            $sel = [DB::raw("{$expr} as grupo"), DB::raw('COUNT(*) as linhas')];
            foreach ($somaveis as $c) {
                $sel[] = DB::raw("COALESCE(SUM({$d['campos'][$c][1]}),0) as `{$c}`");
            }
            $linhas = (clone $q)->select($sel)->groupBy(DB::raw($expr))->orderBy(DB::raw($expr))->limit(self::LIMITE_LINHAS + 1)->get();
            $colunas = array_merge([['grupo', $def['grupo'], 'txt'], ['linhas', 'Linhas', 'num']], array_map(fn ($c) => [$c, $d['campos'][$c][0], $d['campos'][$c][2]], $somaveis));
        } else {
            $sel = array_map(fn ($c) => DB::raw("{$d['campos'][$c][1]} as `{$c}`"), $def['campos']);
            $ordem = $d['data'] ?? array_values($d['campos'])[0][1];
            $linhas = (clone $q)->select($sel)->orderBy(DB::raw($ordem))->limit(self::LIMITE_LINHAS + 1)->get();
            $colunas = array_map(fn ($c) => [$c, $d['campos'][$c][0], $d['campos'][$c][2]], $def['campos']);
        }

        $totais = [];
        if ($somaveis !== []) {
            $soma = (clone $q)->select(array_map(fn ($c) => DB::raw("COALESCE(SUM({$d['campos'][$c][1]}),0) as `{$c}`"), $somaveis))->first();
            foreach ($somaveis as $c) {
                $totais[$c] = round((float) $soma->{$c}, 4);
            }
        }

        return [
            'colunas' => $colunas,
            'linhas' => $linhas->take(self::LIMITE_LINHAS)->map(fn ($l) => (array) $l)->values()->all(),
            'totais' => $totais,
            'truncado' => $linhas->count() > self::LIMITE_LINHAS,
        ];
    }

    private function recorte(int $businessId, array $def, array $d, array|string $locais): Builder
    {
        $q = $this->fontes->base($def['fonte'], $businessId);
        if ($d['local'] && $locais !== 'all') {
            $q->whereIn($d['local'], (array) $locais);
        }
        if ($d['data'] && $def['de']) {
            $q->whereDate($d['data'], '>=', $def['de']);
        }
        if ($d['data'] && $def['ate']) {
            $q->whereDate($d['data'], '<=', $def['ate']);
        }
        foreach ($def['filtros'] as $f => $v) {
            $q->where($d['filtros'][$f], $v);
        }

        return $q;
    }
}
