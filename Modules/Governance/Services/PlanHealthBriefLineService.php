<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * ADR 0294 Onda 1 — linha de SAÚDE DOS PLANOS no Daily Brief (catraca da membrana
 * dual-track). Fecha o "meus planos se perdem": uma linha persistente no brief com
 * a contagem de planos vivos + os que estão órfãos/a-revisar, lida 6x/dia sem
 * esforço (PLANS-INDEX.md §"Como manter vivo" item 2).
 *
 * Transporte (o brief é PHP, a sentinela é Node): shell-out de
 * `scripts/governance/plan-health.mjs --json` via Process (mesmo idioma de
 * NpmAuditChecker/ComposerAuditChecker) + json_decode. Fonte-única: NÃO reimplementa
 * o parser do `## Status vivo` em PHP — quem conta é a sentinela, aqui só se formata.
 *
 * Determinística (pós-LLM): `inject()` é chamado por GenerateBriefCommand DEPOIS do
 * Brain B gerar o markdown — o modelo nunca inventa esse número. Espelha o pattern
 * de SddBriefLineService (GT-G8): injeta um bullet na seção `## FLAGS`.
 *
 * Degrada graciosamente (brief NUNCA quebra por causa dela):
 *  - `node` ausente / script não-deployado / timeout → null (sem linha)
 *  - JSON inválido → null
 *  - sentinela em no-op (`skipped`: PLANS-INDEX ausente) → null
 *  - índice vazio (0 planos) → null
 * Kill-switch: `governance.plan_health_brief_line` false → no-op (default ON).
 *
 * @see scripts/governance/plan-health.mjs (sentinela · shape {ok,planos,fail,warn,findings})
 * @see Modules\Governance\Services\SddBriefLineService (pattern irmão GT-G8)
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 * @see memory/requisitos/_processo/PLANS-INDEX.md §"Como manter vivo" item 2
 */
final class PlanHealthBriefLineService
{
    /** Teto de tempo do shell-out Node (s) — sentinela é local, sem rede; 20s folga. */
    private const TIMEOUT_SECONDS = 20;

    /**
     * Injeta a linha de planos como 1º bullet da seção `## FLAGS`. Best-effort:
     * qualquer falha (ou linha null) devolve o conteúdo intacto.
     * Kill-switch: `governance.plan_health_brief_line` false → no-op (default ON).
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.plan_health_brief_line', true)) {
            return $content;
        }

        try {
            $line = $this->line();
        } catch (Throwable) {
            return $content;
        }

        if ($line === null) {
            return $content;
        }

        $injected = preg_replace('/^## FLAGS$/m', "## FLAGS\n- {$line}", $content, 1, $count);

        return ($count === 1 && is_string($injected)) ? $injected : $content;
    }

    /**
     * Linha de saúde dos planos, ou null quando não há nada confiável a reportar
     * (sentinela indisponível / no-op / índice vazio — ver docblock da classe).
     *
     * Formato: `<emoji> Planos: N vivos · X órfãos · Y a revisar`, onde
     *  - N        = planos no Índice de Planos Vivos (`planos`)
     *  - X órfãos = planos DISTINTOS com achado 🔴 (índice dangling / órfão em-execução)
     *  - Y revisar = planos DISTINTOS com achado 🟡 (sem Status vivo / frescor / etc)
     *  - emoji    = 🔴 (há órfão) / 🟡 (só a-revisar) / 🟢 (todos saudáveis), convenção FLAGS.
     */
    public function line(): ?string
    {
        $data = $this->fetchHealth();

        if ($data === null || ($data['skipped'] ?? false) === true) {
            return null;
        }

        $planos = (int) ($data['planos'] ?? 0);
        if ($planos === 0) {
            return null;
        }

        $findings = array_values((array) ($data['findings'] ?? []));
        $orfaos = $this->distinctPlans($findings, 'fail');
        $revisar = $this->distinctPlans($findings, 'warn');

        $emoji = $orfaos > 0 ? '🔴' : ($revisar > 0 ? '🟡' : '🟢');

        return sprintf(
            '%s Planos: %d vivos · %d órfãos · %d a revisar',
            $emoji,
            $planos,
            $orfaos,
            $revisar,
        );
    }

    /**
     * Roda a sentinela Node e devolve o JSON decodificado, ou null se o shell-out
     * falhar/não produzir JSON. `--json` sai 1 quando há 🔴, mas ainda imprime o
     * JSON no stdout — por isso parseamos o output independente do exit code (só
     * o binário ausente/quebrado, que não produz JSON, vira null).
     *
     * @return array<string, mixed>|null
     */
    private function fetchHealth(): ?array
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run(['node', 'scripts/governance/plan-health.mjs', '--json']);
        } catch (Throwable $e) {
            // `node` ausente no host (ex.: shared hosting sem Node) → sem linha.
            Log::debug('[plan-health brief line] shell-out falhou: '.$e->getMessage());

            return null;
        }

        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Conta planos DISTINTOS com pelo menos um achado do nível dado (um plano pode
     * ter vários achados do mesmo nível — aqui interessa "quantos planos", não
     * "quantos achados").
     *
     * @param  array<int, mixed>  $findings
     */
    private function distinctPlans(array $findings, string $level): int
    {
        $plans = [];
        foreach ($findings as $f) {
            if (is_array($f) && ($f['level'] ?? null) === $level && isset($f['plan'])) {
                $plans[(string) $f['plan']] = true;
            }
        }

        return count($plans);
    }
}
