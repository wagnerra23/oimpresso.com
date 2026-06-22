<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Porta de saída do loop (ADR 0294 ext) — linha de SAÚDE DO SHIPPED-LOG no Daily Brief.
 * Fecha o resíduo "linha proativa no Brief": uma linha persistente com a contagem de
 * cycles registrados + quantos estão desatualizados (cron parou), lida 6x/dia sem esforço.
 *
 * Transporte (o brief é PHP, a sentinela é Node): shell-out de
 * `scripts/governance/shipped-log-generate.mjs --json` via Process + json_decode.
 * Fonte-única: NÃO reimplementa o check de freshness em PHP — quem conta é a sentinela.
 * Espelha PlanHealthBriefLineService (ADR 0294 Onda 1).
 *
 * Determinística (pós-LLM): `inject()` é chamado por GenerateBriefCommand DEPOIS do
 * Brain B gerar o markdown — o modelo nunca inventa esse número. Injeta um bullet na
 * seção `## FLAGS`.
 *
 * Degrada graciosamente (brief NUNCA quebra por causa dela):
 *  - `node` ausente / script não-deployado / timeout → null (sem linha)
 *  - JSON inválido → null
 *  - sentinela em no-op (`skipped`: diretório ausente) → null
 *  - 0 cycles registrados → null
 * Kill-switch: `governance.shipped_log_brief_line` false → no-op (default ON).
 *
 * @see scripts/governance/shipped-log-generate.mjs (sentinela --json · shape {ok,cycles,stale,findings})
 * @see Modules\Governance\Services\PlanHealthBriefLineService (pattern irmão ADR 0294 Onda 1)
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */
final class ShippedLogBriefLineService
{
    /** Teto de tempo do shell-out Node (s) — sentinela é local, sem rede; 20s folga. */
    private const TIMEOUT_SECONDS = 20;

    /**
     * Injeta a linha de shipped-log como 1º bullet da seção `## FLAGS`. Best-effort:
     * qualquer falha (ou linha null) devolve o conteúdo intacto.
     * Kill-switch: `governance.shipped_log_brief_line` false → no-op (default ON).
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.shipped_log_brief_line', true)) {
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
     * Linha de saúde do shipped-log, ou null quando não há nada confiável a reportar
     * (sentinela indisponível / no-op / 0 cycles — ver docblock da classe).
     *
     * Formato: `<emoji> Shipped-log: N cycle(s) ...`, onde
     *  - N        = arquivos de registro versionados (`cycles`)
     *  - stale>0  = 🔴 + "X desatualizado(s) (cron parou?)" (cron deixou o registro envelhecer)
     *  - stale==0 = 🟢 + "fresco"
     */
    public function line(): ?string
    {
        $data = $this->fetchHealth();

        if ($data === null || ($data['skipped'] ?? false) === true) {
            return null;
        }

        $cycles = (int) ($data['cycles'] ?? 0);
        if ($cycles === 0) {
            return null;
        }

        $stale = (int) ($data['stale'] ?? 0);

        return $stale > 0
            ? sprintf('🔴 Shipped-log: %d cycle(s) · %d desatualizado(s) (cron parou?)', $cycles, $stale)
            : sprintf('🟢 Shipped-log: %d cycle(s) registrado(s) · fresco', $cycles);
    }

    /**
     * Roda a sentinela Node e devolve o JSON decodificado, ou null se o shell-out
     * falhar/não produzir JSON. `--json` sai 1 quando há stale, mas ainda imprime o
     * JSON no stdout — por isso parseamos o output independente do exit code.
     *
     * @return array<string, mixed>|null
     */
    private function fetchHealth(): ?array
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run(['node', 'scripts/governance/shipped-log-generate.mjs', '--json']);
        } catch (Throwable $e) {
            // `node` ausente no host (ex.: shared hosting sem Node) → sem linha.
            Log::debug('[shipped-log brief line] shell-out falhou: '.$e->getMessage());

            return null;
        }

        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
