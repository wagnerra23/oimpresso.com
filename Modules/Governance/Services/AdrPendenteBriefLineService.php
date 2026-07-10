<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * US-GOV-052 — FLAG de ADR PENDENTE no Daily Brief (elo do ciclo de ratificação).
 *
 * Fecha o "decisão parada que ninguém vê acaba não sendo feita" (Wagner,
 * 2026-07-09): a sentinela adr-proposto-parado detecta os 3 checks —
 *   A (🔴) decidido preso em proposals/ (lei invisível ao MCP) ·
 *   B (🟡) numerado em proposals/ ainda proposto ·
 *   C (🟡) proposto no top-level há >14d sem ratificação —
 * mas só aparecia pra quem roda o script na mão ou no ::warning de PR. Aqui a
 * pendência chega ao Wagner 6x/dia como flag compacta: `🟠 ADR pendente: A:N B:N C:N`.
 *
 * Transporte (o brief é PHP, a sentinela é Node): shell-out de
 * `scripts/governance/adr-proposto-parado.mjs --json` via Process + json_decode.
 * Fonte-única: NÃO reimplementa os checks em PHP — quem classifica é a sentinela
 * (núcleo puro com selftest), aqui só se formata. Espelha AdrReviewBriefLineService.
 *
 * Determinística (pós-LLM): `inject()` roda DEPOIS do Brain B gerar o markdown —
 * o modelo nunca inventa esses números. Injeta um bullet na seção `## FLAGS`.
 *
 * Degrada graciosamente (brief NUNCA quebra por causa dela):
 *  - `node` ausente / script não-deployado / timeout → null (sem linha)
 *  - JSON inválido → null
 *  - A+B+C = 0 (nenhuma pendência) → null (flag só existe quando N>0)
 * Kill-switch: `governance.adr_pendente_brief_line` false → no-op (default ON).
 *
 * @see scripts/governance/adr-proposto-parado.mjs (sentinela · shape {gate,dias,A,B,C,C_total})
 * @see Modules\Governance\Services\AdrReviewBriefLineService (pattern irmão — fila O/R ≠ ratificação A/B/C)
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */
final class AdrPendenteBriefLineService
{
    /** Teto de tempo do shell-out Node (s) — sentinela é local (varre memory/decisions), sem rede. */
    private const TIMEOUT_SECONDS = 20;

    /**
     * Injeta a flag de ADR pendente como 1º bullet da seção `## FLAGS`.
     * Best-effort: qualquer falha (ou linha null) devolve o conteúdo intacto.
     * Kill-switch: `governance.adr_pendente_brief_line` false → no-op (default ON).
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.adr_pendente_brief_line', true)) {
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
     * Flag de pendência de ratificação, ou null quando não há nada a reportar
     * (sentinela indisponível / A+B+C = 0 — ver docblock da classe).
     *
     * Formato: `🟠 ADR pendente: A:1 B:2 C:5` — contadores dos 3 checks da
     * sentinela (C usa `C_total`: o array `C` do JSON é capado em 15 itens).
     */
    public function line(): ?string
    {
        $data = $this->fetchPendencias();

        if ($data === null || ($data['gate'] ?? null) !== 'adr-proposto-parado') {
            return null;
        }

        $a = count((array) ($data['A'] ?? []));
        $b = count((array) ($data['B'] ?? []));
        $c = (int) ($data['C_total'] ?? count((array) ($data['C'] ?? [])));

        if ($a + $b + $c === 0) {
            return null;
        }

        return sprintf('🟠 ADR pendente: A:%d B:%d C:%d', $a, $b, $c);
    }

    /**
     * Roda a sentinela Node e devolve o JSON decodificado, ou null se o shell-out
     * falhar/não produzir JSON. Sem `--strict` o exit é sempre 0 (reporter) — mas
     * parseamos o output independente do exit code, como os irmãos.
     *
     * @return array<string, mixed>|null
     */
    private function fetchPendencias(): ?array
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run(['node', 'scripts/governance/adr-proposto-parado.mjs', '--json']);
        } catch (Throwable $e) {
            Log::debug('[adr-pendente brief line] shell-out falhou: '.$e->getMessage());

            return null;
        }

        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
