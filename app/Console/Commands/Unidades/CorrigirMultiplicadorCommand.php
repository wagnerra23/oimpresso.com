<?php

declare(strict_types=1);

namespace App\Console\Commands\Unidades;

use App\Services\Unidades\AuditoriaMultiplicadorUnidade;
use App\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restaura `base_unit_multiplier` (e, só com flag, `base_unit_id`) das unidades que a auditoria
 * classificou como recuperáveis. DRY-RUN por padrão: imprime o plano antes→depois e um código de
 * plano. Gravar exige `--aplicar --confirmar=<código>` do MESMO plano — se qualquer unidade mudou
 * desde o dry-run, o código muda e nada é gravado.
 *
 * REGRA MESTRE de estoque (memory/proibicoes.md): só entra no plano unidade com as DUAS provas
 * (valor no activity_log + mecanismo do bug reproduzindo o valor atual). Aplicar em produção é
 * decisão [W]; este comando não decide isso.
 *
 * NÃO recalcula estoque nem linhas de venda/compra já lançadas com a conversão errada — elas
 * aparecem contadas na auditoria e são decisão separada.
 *
 *   php artisan units:corrigir-multiplicador --business=1
 *   php artisan units:corrigir-multiplicador --business=1 --aplicar --confirmar=<código>
 */
class CorrigirMultiplicadorCommand extends Command
{
    protected $signature = 'units:corrigir-multiplicador
        {--business= : business_id (OBRIGATÓRIO — ADR 0093)}
        {--incluir-base-removida : Inclui BASE_REMOVIDA (pode ter sido desmarcada de propósito)}
        {--aplicar : Grava. Sem isto é dry-run.}
        {--confirmar= : Código do plano impresso pelo dry-run}';

    protected $description = 'Restaura a conversão de estoque corrompida de unidades (dry-run por padrão).';

    public function handle(AuditoriaMultiplicadorUnidade $auditoria): int
    {
        $biz = (int) $this->option('business');
        if ($biz <= 0) {
            $this->error('--business obrigatório (ADR 0093 Tier 0).');

            return self::FAILURE;
        }

        $plano = $this->plano($auditoria, $biz);
        $codigo = $this->codigo($biz, $plano);

        $this->info(($this->option('aplicar') ? 'APLICAR' : 'DRY-RUN')." · business_id={$biz} · ".count($plano).' unidade(s) no plano · código '.$codigo);
        if ($plano !== []) {
            $this->table(['unit', 'unidade', 'antes', 'depois', 'base antes', 'base depois'], array_map(fn (array $p) => [
                $p['unit_id'], $p['unidade'], $this->fmt($p['mult_antes']), $this->fmt($p['mult_depois']),
                $p['base_antes'] ?? '—', $p['base_depois'] ?? '—',
            ], $plano));
        }

        if (! $this->option('aplicar')) {
            $this->line('Nada gravado. Para aplicar (decisão [W]): --aplicar --confirmar='.$codigo);

            return self::SUCCESS;
        }

        if ((string) $this->option('confirmar') !== $codigo) {
            $this->error('Código de confirmação não confere com o plano atual — rode o dry-run de novo. Nada gravado.');

            return self::FAILURE;
        }

        $gravadas = 0;
        DB::transaction(function () use ($plano, $biz, &$gravadas) {
            foreach ($plano as $p) {
                $u = Unit::where('business_id', $biz)->lockForUpdate()->find($p['unit_id']);
                $baseAtual = $u?->base_unit_id !== null ? (int) $u->base_unit_id : null;
                $multAtual = $u?->base_unit_multiplier !== null ? round((float) $u->base_unit_multiplier, 4) : null;
                if ($u === null || $baseAtual !== $p['base_antes_id'] || $multAtual !== $p['mult_antes']) {
                    throw new \RuntimeException("unit {$p['unit_id']} mudou desde o plano — abortado sem gravar nada.");
                }
                $u->base_unit_id = $p['base_depois_id'];
                $u->base_unit_multiplier = $p['mult_depois'];
                $u->save();
                $gravadas++;
            }
        });

        $this->info("{$gravadas} unidade(s) restaurada(s). Cada uma ficou registrada no activity_log (log_name=unit).");

        return self::SUCCESS;
    }

    /** @return list<array<string,mixed>> */
    private function plano(AuditoriaMultiplicadorUnidade $auditoria, int $biz): array
    {
        $classes = [AuditoriaMultiplicadorUnidade::RECUPERAVEL];
        if ($this->option('incluir-base-removida')) {
            $classes[] = AuditoriaMultiplicadorUnidade::BASE_REMOVIDA;
        }

        $plano = [];
        foreach ($auditoria->auditar($biz) as $l) {
            if (! in_array($l['classe'], $classes, true) || ($l['mult_proposto'] ?? null) === null) {
                continue;
            }
            // As duas provas: o valor veio do log e (havendo cadeia) o bug reproduz o atual.
            if (($l['prova_valor_log'] ?? false) !== true || ($l['prova_mecanismo'] ?? null) === false) {
                continue;
            }
            $baseAntes = Unit::where('business_id', $biz)->whereKey($l['unit_id'])->value('base_unit_id');
            $plano[] = [
                'unit_id' => $l['unit_id'],
                'unidade' => $l['unidade'],
                'mult_antes' => $l['mult_atual'],
                'mult_depois' => $l['mult_proposto'],
                'base_antes_id' => $baseAntes !== null ? (int) $baseAntes : null,
                'base_depois_id' => $l['base_proposta_id'] ?? null,
                'base_antes' => $l['base_atual'],
                'base_depois' => $l['base_proposta'],
            ];
        }

        return $plano;
    }

    private function codigo(int $biz, array $plano): string
    {
        return substr(hash('sha256', $biz.'|'.json_encode(array_map(fn ($p) => [
            $p['unit_id'], $p['mult_antes'], $p['mult_depois'], $p['base_antes_id'], $p['base_depois_id'],
        ], $plano))), 0, 12);
    }

    private function fmt(?float $v): string
    {
        return $v === null ? '—' : rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    }
}
