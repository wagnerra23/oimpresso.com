<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * ADR 0317 Onda 3 (M3) — linha de REVISÃO DE ADR no Daily Brief (surfacing da
 * máquina de revisão). Fecha o "fila detectada mas ninguém vê": os Checks O
 * (morta-mas-canon) e R (revisão vencida por TTL) do memory-health são 🟡
 * sentinela — não bloqueiam merge — então sem esta linha a fila só aparece pra
 * quem roda a sentinela na mão. Aqui ela chega ao Wagner 6x/dia, com TETO DE
 * VAZÃO (top-3 por severidade; resto silencioso — o flush trimestral
 * `governance:adr-review-flush` cobre a cauda). Evita o warn crônico que matou
 * o `next_review` manual do _INDEX-LIFECYCLE.
 *
 * Transporte (o brief é PHP, a sentinela é Node): shell-out de
 * `scripts/governance/memory-health.mjs --json` via Process + json_decode.
 * Fonte-única: NÃO reimplementa os checks em PHP — quem detecta é a sentinela,
 * aqui só se formata. Espelha PlanHealthBriefLineService (ADR 0294 Onda 1).
 *
 * Determinística (pós-LLM): `inject()` roda DEPOIS do Brain B gerar o markdown —
 * o modelo nunca inventa esses números. Injeta um bullet na seção `## FLAGS`.
 *
 * Degrada graciosamente (brief NUNCA quebra por causa dela):
 *  - `node` ausente / script não-deployado / timeout → null (sem linha)
 *  - JSON inválido → null
 *  - filas O+R vazias (ou só entries `*-error` da sentinela) → null
 * Kill-switch: `governance.adr_review_brief_line` false → no-op (default ON).
 *
 * @see scripts/governance/memory-health.mjs (sentinela --json · shape {fails,warns,ok})
 * @see Modules\Governance\Console\Commands\AdrReviewFlushCommand (flush trimestral, fila completa)
 * @see Modules\Governance\Services\PlanHealthBriefLineService (pattern irmão)
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */
final class AdrReviewBriefLineService
{
    /** Teto de tempo do shell-out Node (s) — sentinela é local, sem rede; a varredura de memory/ é maior que a dos irmãos. */
    private const TIMEOUT_SECONDS = 30;

    /** Teto de vazão (ADR 0317 §3): no máximo N slugs na linha; o resto fica pro flush trimestral. */
    private const TOP_N = 3;

    /** kind exato de cada check relevante — entries `*-error` da sentinela NÃO são fila. */
    private const KINDS = ['O' => 'morta-mas-canon', 'R' => 'revisao-vencida'];

    /**
     * Injeta a linha de revisão de ADR como 1º bullet da seção `## FLAGS`.
     * Best-effort: qualquer falha (ou linha null) devolve o conteúdo intacto.
     * Kill-switch: `governance.adr_review_brief_line` false → no-op (default ON).
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.adr_review_brief_line', true)) {
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
     * Linha da fila de revisão, ou null quando não há nada confiável a reportar
     * (sentinela indisponível / filas vazias — ver docblock da classe).
     *
     * Formato: `🟡 Revisão ADR: 5 morta-mas-canon (O) · 24 vencida(s) (R) — top: 0008, 0010, 0079 (+26)`
     *  - contadores por check, só quando > 0 (O antes de R: inconsistência > tempo, ADR 0317)
     *  - top-3 = números das ADRs, severidade O primeiro (teto de vazão; excedente vira `(+N)`)
     */
    public function line(): ?string
    {
        $data = $this->fetchHealth();

        if ($data === null) {
            return null;
        }

        $queues = $this->queues($data);
        $total = $queues['O']['count'] + $queues['R']['count'];

        if ($total === 0) {
            return null;
        }

        $parts = [];
        if ($queues['O']['count'] > 0) {
            $parts[] = sprintf('%d morta-mas-canon (O)', $queues['O']['count']);
        }
        if ($queues['R']['count'] > 0) {
            $parts[] = sprintf('%d vencida(s) (R)', $queues['R']['count']);
        }

        // Teto de vazão: O primeiro (inconsistência > tempo), depois R.
        $top = array_slice([...$queues['O']['nums'], ...$queues['R']['nums']], 0, self::TOP_N);
        $rest = $total - count($top);

        return sprintf(
            '🟡 Revisão ADR: %s — top: %s%s',
            implode(' · ', $parts),
            implode(', ', $top),
            $rest > 0 ? " (+{$rest})" : '',
        );
    }

    /**
     * Extrai as filas O/R do JSON da sentinela (fails + warns — hoje os dois
     * checks são 🟡, mas se um dia promoverem a 🔴 a linha continua correta).
     *
     * @param  array<string, mixed>  $data
     * @return array{O: array{count: int, nums: list<string>}, R: array{count: int, nums: list<string>}}
     */
    private function queues(array $data): array
    {
        $out = ['O' => ['count' => 0, 'nums' => []], 'R' => ['count' => 0, 'nums' => []]];

        $entries = [...array_values((array) ($data['fails'] ?? [])), ...array_values((array) ($data['warns'] ?? []))];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $check = (string) ($entry['check'] ?? '');
            if (! isset(self::KINDS[$check]) || ($entry['kind'] ?? null) !== self::KINDS[$check]) {
                continue; // check alheio, ou entry `*-error` (sentinela quebrou ≠ fila)
            }
            $out[$check]['count'] += (int) ($entry['count'] ?? 0);
            foreach ((array) ($entry['sample'] ?? []) as $slug) {
                // "0122-admin-center-ct100" → "0122" (linha curta; slug completo fica pro flush)
                if (is_string($slug) && preg_match('/^(\d{4})-/', $slug, $m)) {
                    $out[$check]['nums'][] = $m[1];
                }
            }
        }

        return $out;
    }

    /**
     * Roda a sentinela Node e devolve o JSON decodificado, ou null se o shell-out
     * falhar/não produzir JSON. `--json` sai 1 quando há 🔴, mas ainda imprime o
     * JSON no stdout — por isso parseamos o output independente do exit code.
     *
     * @return array<string, mixed>|null
     */
    private function fetchHealth(): ?array
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run(['node', 'scripts/governance/memory-health.mjs', '--json']);
        } catch (Throwable $e) {
            // `node` ausente no host (ex.: shared hosting sem Node) → sem linha.
            Log::debug('[adr-review brief line] shell-out falhou: '.$e->getMessage());

            return null;
        }

        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
