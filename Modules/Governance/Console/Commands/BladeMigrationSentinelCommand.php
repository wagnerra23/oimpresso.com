<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Services\TaskRegistry\HitlEscalationService;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * governance:blade-migration-sentinel — a COBRANÇA da rota de migração Blade→React.
 *
 * ── O PROBLEMA (medido 2026-08-08, não suposto) ─────────────────────────────
 * A ADR 0277 decidiu a rota em 2026-06-13 (10 ondas, contrato "migrado = route
 * Blade morto ou 302") e previu na Onda 10 um contador de routes Blade vivos.
 * Esse contador nunca existiu, o censo A–L foi digitado à mão e nunca recalculado,
 * e a rota parou: o único toque desde junho foi lote de metadados.
 *
 * O medidor nasceu no PR #5424 (`scripts/governance/blade-migration-census.mjs`).
 * Mas medidor sem cobrança é número que alguém precisa LEMBRAR de olhar — o mesmo
 * defeito que matou a rota. Este comando é o elo que faltava.
 *
 * ── O QUE ESTE COMANDO É (e o que NÃO é) ────────────────────────────────────
 * É TRANSPORTE: compara o censo com o baseline versionado e, quando a rota
 * REGRIDE (rota Blade nova) ou ESTAGNA (não desce há N dias), materializa UMA task
 * `blocked`/`wagner` no canal que o brief já lê. Não decide onda, não migra tela,
 * não bloqueia merge, não tem exit code de gate.
 *
 * NÃO É catraca de CI nem presence-gate (proibicoes §5): não mede presença de
 * campo, não roda em PR, não derruba build. E não é canal novo — `mcp_tasks` é o
 * dono do tema (ADR 0070) e o `HitlEscalationService` é o transporte canônico,
 * já provado pelo `handoff:stale-alert` (Modules/Forja).
 *
 * ── POR QUE O ESTADO HUMANO VENCE ───────────────────────────────────────────
 * O `escalar()` usa `task_id` determinístico, então re-escalar ATUALIZA a mesma
 * task em vez de criar a enésima; e se um humano fechar a task (done/cancelled),
 * o sentinela NÃO reabre. Nag perpétuo é ruído com cara de vigilância.
 *
 * @see memory/decisions/0277-rota-migracao-blade-ondas-completude.md
 * @see memory/decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md
 */
class BladeMigrationSentinelCommand extends Command
{
    protected $signature = 'governance:blade-migration-sentinel
                            {--input= : JSON pré-gerado do censo (testes/CI sem node)}
                            {--baseline= : path do baseline (default governance/blade-migration-baseline.json)}
                            {--dias-estagnacao=30 : dias sem progresso que disparam alarme}
                            {--dry-run : mede e reporta, mas NÃO escala}';

    protected $description = 'Cobra a rota de migração Blade→React (ADR 0277): escala pro brief quando regride ou estagna';

    public function handle(HitlEscalationService $escalador): int
    {
        $baselinePath = (string) ($this->option('baseline') ?: base_path('governance/blade-migration-baseline.json'));
        if (! is_file($baselinePath)) {
            $this->error("baseline não encontrado: {$baselinePath}");

            return self::FAILURE;
        }
        $baseline = json_decode((string) file_get_contents($baselinePath), true);
        if (! is_array($baseline) || ! isset($baseline['por_escopo'])) {
            throw new RuntimeException('baseline inválido: falta `por_escopo`');
        }

        $atual = $this->obterCenso();
        $veredito = self::avaliar($atual, $baseline, date('Y-m-d'), (int) $this->option('dias-estagnacao'));

        $this->line($veredito['resumo']);

        if ($veredito['veredito'] === 'ok' || $veredito['veredito'] === 'progresso') {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->comment('  (--dry-run: não escalei)');

            return self::SUCCESS;
        }

        $escalador->escalar(
            chave: 'BLADE-MIGRACAO',
            titulo: $veredito['veredito'] === 'regressao'
                ? sprintf('Rota Blade NOVA em %d escopo(s) — a migração regrediu', count($veredito['regressoes']))
                : sprintf('Migração Blade→React parada há %dd — %d endpoints ainda em Blade', $veredito['dias'], $atual['total_blade']),
            descricao: $veredito['resumo']
                . "\n\nRota: memory/requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md (10 ondas, ADR 0277)."
                . "\nMedir agora: `node scripts/governance/blade-migration-census.mjs --report`."
                . "\nSe a subida foi consciente, regrave o baseline no MESMO PR (governance/blade-migration-baseline.json), com o motivo."
                . "\n\nFechar esta task (done/cancelled) SILENCIA o escalonamento — o sentinela não reabre.",
            modulo: 'Governance',
            prioridade: 'p2',
            origem: 'governance:blade-migration-sentinel',
        );

        return self::SUCCESS;
    }

    /**
     * A DECISÃO — pura e sem I/O, para ser testável sem DB nem node.
     *
     * Precedência: regressão > progresso > estagnação > ok. Regressão vence sempre,
     * porque rota Blade nova é dívida NOVA mesmo que o total tenha caído por outra
     * frente (senão um escopo pioraria escondido atrás do progresso alheio — é o
     * mesmo whack-a-mole que a ADR 0277 §1 proíbe na contagem).
     *
     * @param  array{total_blade:int,por_escopo:array<string,array{blade:int}>}  $atual
     * @param  array{gerado_em?:string,total_blade?:int,por_escopo:array<string,array{blade:int}>}  $baseline
     * @return array{veredito:string,regressoes:array<string,array{de:int,para:int}>,delta:int,dias:int,resumo:string}
     */
    public static function avaliar(array $atual, array $baseline, string $hoje, int $diasEstagnacao = 30): array
    {
        $regressoes = [];
        foreach ($atual['por_escopo'] as $escopo => $dados) {
            $antes = (int) ($baseline['por_escopo'][$escopo]['blade'] ?? 0);
            $agora = (int) ($dados['blade'] ?? 0);
            if ($agora > $antes) {
                $regressoes[$escopo] = ['de' => $antes, 'para' => $agora];
            }
        }

        $totalAtual = (int) ($atual['total_blade'] ?? 0);
        $totalBase = (int) ($baseline['total_blade'] ?? 0);
        $delta = $totalAtual - $totalBase;

        $dias = 0;
        if (! empty($baseline['gerado_em'])) {
            $dias = (int) floor((strtotime($hoje) - strtotime((string) $baseline['gerado_em'])) / 86400);
        }

        if ($regressoes !== []) {
            $lista = implode(' · ', array_map(
                fn ($e, $d) => sprintf('%s %d→%d', $e, $d['de'], $d['para']),
                array_keys($regressoes),
                $regressoes,
            ));

            return [
                'veredito' => 'regressao',
                'regressoes' => $regressoes,
                'delta' => $delta,
                'dias' => $dias,
                'resumo' => "⛔ REGRESSÃO — rota Blade nova em: {$lista}",
            ];
        }

        if ($delta < 0) {
            return [
                'veredito' => 'progresso',
                'regressoes' => [],
                'delta' => $delta,
                'dias' => $dias,
                'resumo' => sprintf('✅ progresso: %d endpoint(s) saíram do Blade (%d → %d). Regrave o baseline.', -$delta, $totalBase, $totalAtual),
            ];
        }

        if ($dias > $diasEstagnacao) {
            return [
                'veredito' => 'estagnado',
                'regressoes' => [],
                'delta' => $delta,
                'dias' => $dias,
                'resumo' => sprintf('⏳ ESTAGNADA há %dd — %d endpoints ainda servem Blade e o número não desceu.', $dias, $totalAtual),
            ];
        }

        return [
            'veredito' => 'ok',
            'regressoes' => [],
            'delta' => $delta,
            'dias' => $dias,
            'resumo' => sprintf('ok — %d endpoints em Blade, sem regressão (baseline de %dd atrás).', $totalAtual, $dias),
        ];
    }

    /** @return array{total_blade:int,por_escopo:array<string,array{blade:int}>} */
    private function obterCenso(): array
    {
        if ($input = $this->option('input')) {
            $raw = (string) file_get_contents((string) $input);
        } else {
            $process = new Process(['node', 'scripts/governance/blade-migration-census.mjs', '--resumo-json'], base_path(), null, null, 180);
            $process->mustRun();
            $raw = $process->getOutput();
        }

        $json = json_decode($raw, true);
        if (! is_array($json) || ! isset($json['por_escopo'])) {
            throw new RuntimeException('output do blade-migration-census.mjs não é JSON válido com chave `por_escopo`');
        }

        return $json;
    }
}
