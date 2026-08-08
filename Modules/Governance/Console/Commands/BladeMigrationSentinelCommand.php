<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Services\TaskRegistry\HitlEscalationService;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

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
 * ── POR QUE EXISTE O ESTADO `cego` (medido em prod 2026-08-08) ──────────────
 * No cron do Hostinger o `node` NÃO é alcançável: o PATH herdado é
 * `/usr/local/bin:/usr/bin` e o node vive só em `~/.nvm/.../v24.15.0/bin`
 * (não há `/usr/bin/node`). Medido na própria prod, não suposto — é a MESMA
 * causa que fez o PR #5444 tirar o `governance:sdd-scorecard-snapshot` do Kernel
 * horas antes deste agendamento.
 *
 * Então o censo pode faltar. As duas saídas óbvias estão as duas erradas:
 *   - `mustRun()` + `onFailure(Log::error)`: cai num pile de falha que é
 *     PROVADAMENTE ignorado (medido em prod: ~10 schedules com falha repetida,
 *     `whatsapp:channels-reconcile` com 8996 linhas). Falha invisível.
 *   - engolir e devolver `ok`: seria afirmar verde sem ter medido — a lápide
 *     §5 2026-07-29 ("instrumento afirmar verde quando não conseguiu MEDIR").
 *
 * A saída certa vem de qual eixo depende de quê:
 *   - REGRESSÃO exige o censo (node) — e JÁ TEM DONO: a catraca
 *     `blade-migration-census.mjs --ratchet` roda no `governance-gate.yml` a cada
 *     PR, em Linux, contra o MESMO baseline único.
 *   - ESTAGNAÇÃO não precisa de node: sai de `gerado_em` + `total_blade` do
 *     baseline VERSIONADO. E é exatamente o eixo que a catraca do CI ignora DE
 *     PROPÓSITO (selftest: "catraca ignora tempo").
 *
 * Logo: sem censo, o sentinela ainda cobra estagnação (seu valor único) e, quando
 * não há o que cobrar, diz `cego` NOMEANDO o que não mediu — nunca "ok".
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

        // `cego` = não consegui medir o censo E não há estagnação a cobrar. Não é
        // verde (a mensagem diz o que ficou sem conferir), e não escala: o eixo que
        // ficou sem medida — regressão — tem dono no CI (--ratchet). Escalar aqui
        // seria nag semanal sobre um buraco que já é coberto por outra máquina.
        if (in_array($veredito['veredito'], ['ok', 'progresso', 'cego'], true)) {
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
                : sprintf('Migração Blade→React parada há %dd — %d endpoints ainda em Blade', $veredito['dias'], $veredito['total']),
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
     * `$atual === null` = o censo NÃO foi obtido (node ausente no cron). Aí a
     * regressão fica sem medida — e o veredito NÃO pode ser "ok" (§5 2026-07-29).
     * A estagnação segue medível, porque sai só do baseline versionado.
     *
     * @param  array{total_blade:int,por_escopo:array<string,array{blade:int}>}|null  $atual
     * @param  array{gerado_em?:string,total_blade?:int,por_escopo:array<string,array{blade:int}>}  $baseline
     * @return array{veredito:string,regressoes:array<string,array{de:int,para:int}>,delta:int,dias:int,total:int,resumo:string}
     */
    public static function avaliar(?array $atual, array $baseline, string $hoje, int $diasEstagnacao = 30): array
    {
        $mediu = $atual !== null;

        $regressoes = [];
        foreach (($atual['por_escopo'] ?? []) as $escopo => $dados) {
            $antes = (int) ($baseline['por_escopo'][$escopo]['blade'] ?? 0);
            $agora = (int) ($dados['blade'] ?? 0);
            if ($agora > $antes) {
                $regressoes[$escopo] = ['de' => $antes, 'para' => $agora];
            }
        }

        $totalBase = (int) ($baseline['total_blade'] ?? 0);
        // Sem censo, o total conhecido é o do baseline — e o delta é 0 por ignorância,
        // não por medida: por isso o ramo `progresso` abaixo exige `$mediu`.
        $totalAtual = $mediu ? (int) ($atual['total_blade'] ?? 0) : $totalBase;
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
                'total' => $totalAtual,
                'resumo' => "⛔ REGRESSÃO — rota Blade nova em: {$lista}",
            ];
        }

        // `$mediu` guarda o ramo: sem censo o delta é 0 por ignorância, então sem
        // esta perna um baseline com total 0 viraria "progresso" fabricado.
        if ($mediu && $delta < 0) {
            return [
                'veredito' => 'progresso',
                'regressoes' => [],
                'delta' => $delta,
                'dias' => $dias,
                'total' => $totalAtual,
                'resumo' => sprintf('✅ progresso: %d endpoint(s) saíram do Blade (%d → %d). Regrave o baseline.', -$delta, $totalBase, $totalAtual),
            ];
        }

        // Estagnação sai do baseline versionado — logo continua medível SEM node.
        if ($dias > $diasEstagnacao) {
            return [
                'veredito' => 'estagnado',
                'regressoes' => [],
                'delta' => $delta,
                'dias' => $dias,
                'total' => $totalAtual,
                'resumo' => sprintf(
                    '⏳ ESTAGNADA há %dd — %d endpoints ainda servem Blade e o número não desceu.%s',
                    $dias,
                    $totalAtual,
                    $mediu ? '' : ' (censo não medido: total vem do baseline; regressão NÃO conferida)',
                ),
            ];
        }

        if (! $mediu) {
            return [
                'veredito' => 'cego',
                'regressoes' => [],
                'delta' => 0,
                'dias' => $dias,
                'total' => $totalAtual,
                'resumo' => sprintf(
                    '⚠️ CEGO — não consegui rodar o censo (node ausente?), então NADA de regressão foi conferido aqui. '
                    . 'Sem estagnação a cobrar (baseline de %dd atrás, %d endpoints). '
                    . 'Esse eixo tem dono: `blade-migration-census.mjs --ratchet` no governance-gate, a cada PR.',
                    $dias,
                    $totalAtual,
                ),
            ];
        }

        return [
            'veredito' => 'ok',
            'regressoes' => [],
            'delta' => $delta,
            'dias' => $dias,
            'total' => $totalAtual,
            'resumo' => sprintf('ok — %d endpoints em Blade, sem regressão (baseline de %dd atrás).', $totalAtual, $dias),
        ];
    }

    /**
     * O censo, ou `null` quando não deu pra medir.
     *
     * `--input` explícito é contrato do chamador: se ele aponta pra um arquivo
     * ruim, isso é ERRO (lança). Já a ausência de `node` é uma condição de
     * AMBIENTE conhecida e medida (ver docblock da classe) — devolve `null`, e
     * quem decide o que fazer com a cegueira é `avaliar()`, não este método.
     *
     * @return array{total_blade:int,por_escopo:array<string,array{blade:int}>}|null
     */
    private function obterCenso(): ?array
    {
        if ($input = $this->option('input')) {
            $json = json_decode((string) file_get_contents((string) $input), true);
            if (! is_array($json) || ! isset($json['por_escopo'])) {
                throw new RuntimeException("--input não é JSON válido com chave `por_escopo`: {$input}");
            }

            return $json;
        }

        try {
            $process = new Process(['node', 'scripts/governance/blade-migration-census.mjs', '--resumo-json'], base_path(), null, null, 180);
            $process->run();
            if (! $process->isSuccessful()) {
                $this->warn('  censo não rodou: ' . trim($process->getErrorOutput() ?: 'exit ' . $process->getExitCode()));

                return null;
            }
            $json = json_decode($process->getOutput(), true);
        } catch (Throwable $e) {
            $this->warn("  censo não rodou: {$e->getMessage()}");

            return null;
        }

        if (! is_array($json) || ! isset($json['por_escopo'])) {
            $this->warn('  censo rodou mas não devolveu JSON com `por_escopo`.');

            return null;
        }

        return $json;
    }
}
