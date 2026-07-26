<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * FLAG de OBRA PARADA no Daily Brief — "o cron roda" ≠ "o cron entrega".
 *
 * Origem (2026-07-26, [W]): *"meu principal problema não é resolver isso, é ter
 * estrutura para que isso se resolva sem mim"*. O caso concreto: o `Governance v4 /
 * Scoped Scorecards` (ADR 0160) tinha ADR aceita, cron diário 07:00
 * (`governance:scorecard-snapshot --alert`), tela e ~10 testes — e os 5 scorecards
 * que ele deveria atualizar estavam com `last_grade_at: 2026-05-16`, 71 dias
 * parados. Descoberto POR ACASO numa conversa, não por aviso.
 *
 * Por que nenhuma máquina pegou: os 34 gates required só rodam sobre DIFF, e coisa
 * parada não tem diff. O `cron-watchdog` mais próximo media liveness de workflow do
 * GitHub — e o cron em questão ESTAVA VIVO. Liveness dava verde.
 *
 * Este serviço fecha o elo de VISIBILIDADE: a sentinela (eixo 2 do cron-watchdog)
 * já detecta, mas só quem roda o script na mão via. Aqui a pendência chega ao [W]
 * no brief, junto das outras flags: `🟠 Obra parada: N artefato(s) — o mais velho
 * há Xd (arquivo)`.
 *
 * Transporte (o brief é PHP, a sentinela é Node): shell-out de
 * `scripts/governance/cron-watchdog.mjs --json` via Process + json_decode.
 * Fonte-única: NÃO reimplementa a regra em PHP — quem classifica é a sentinela
 * (núcleo puro `paradosEntre()` com selftest). Espelha AdrPendenteBriefLineService.
 *
 * Determinística (pós-LLM): `inject()` roda DEPOIS do Brain B gerar o markdown —
 * o modelo nunca inventa esses números.
 *
 * Degrada graciosamente (brief NUNCA quebra por causa dela):
 *  - `node` ausente / script não-deployado / timeout → null (sem linha)
 *  - JSON inválido ou de outro gate → null
 *  - 0 parados → null (flag só existe quando há o que reportar)
 * Kill-switch: `governance.obra_parada_brief_line` false → no-op (default ON).
 *
 * @see scripts/governance/cron-watchdog.mjs (sentinela · eixo 2 · shape {gate,limite_dias,parados[]})
 * @see Modules\Governance\Services\AdrPendenteBriefLineService (pattern irmão)
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */
final class ObraParadaBriefLineService
{
    /** Teto do shell-out Node (s) — a sentinela é local (varre o repo), sem rede. */
    private const TIMEOUT_SECONDS = 20;

    /**
     * Injeta a flag de obra parada como bullet da seção `## FLAGS`.
     * Best-effort: qualquer falha (ou linha null) devolve o conteúdo intacto.
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.obra_parada_brief_line', true)) {
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
     * Flag de obra parada, ou null quando não há nada a reportar.
     *
     * Formato: `🟠 Obra parada: 5 artefato(s) sem atualizar — pior: scorecards/admin.yaml (71d)`.
     * Cita o PIOR caso com nome e idade: número solto ("5 parados") não diz onde
     * olhar, e flag que não aponta o alvo vira ruído que se aprende a ignorar.
     */
    public function line(): ?string
    {
        $data = $this->fetchParados();

        if ($data === null || ($data['gate'] ?? null) !== 'obra-parada') {
            return null;
        }

        $parados = (array) ($data['parados'] ?? []);
        $n = count($parados);

        if ($n === 0) {
            return null;
        }

        // A sentinela já ordena do mais parado pro menos — o 1º é o pior.
        $pior = (array) $parados[0];
        $arquivo = (string) ($pior['arquivo'] ?? '?');
        $dias = (int) ($pior['dias'] ?? 0);

        return sprintf(
            '🟠 Obra parada: %d artefato(s) sem atualizar — pior: %s (%dd)',
            $n,
            $this->encurtar($arquivo),
            $dias
        );
    }

    /**
     * Encurta o path pro brief (que é lido no terminal e tem teto de tokens):
     * mantém os 2 últimos segmentos, que é o que identifica o arquivo.
     */
    private function encurtar(string $path): string
    {
        $partes = explode('/', $path);

        return count($partes) <= 2 ? $path : implode('/', array_slice($partes, -2));
    }

    /**
     * Roda a sentinela Node e devolve o JSON decodificado, ou null se o shell-out
     * falhar/não produzir JSON. O modo `--json` sai sempre com 0 (reporter), mas
     * parseamos o output independente do exit code, como os irmãos.
     *
     * @return array<string, mixed>|null
     */
    private function fetchParados(): ?array
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run(['node', 'scripts/governance/cron-watchdog.mjs', '--json']);
        } catch (Throwable $e) {
            Log::debug('[obra-parada brief line] shell-out falhou: '.$e->getMessage());

            return null;
        }

        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
