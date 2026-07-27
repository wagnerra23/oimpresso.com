<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Linha de EXPOSIÇÃO TIER-0 × cobertura de comportamento no Daily Brief.
 *
 * =====================================================================================
 * POR QUE EXISTE
 * =====================================================================================
 * A sentinela `scripts/qa/exposicao-tier0.mjs` (Onda 0c · pilar CADÊNCIA da ADR 0256)
 * já cruzava as 3 camadas e RANQUEAVA o débito — tela quente (dinheiro/estoque/PII/
 * fiscal) sem teste de comportamento, peso valor/estoque no topo (REGRA MESTRE). Mas o
 * canal dela era só a **issue do GitHub semanal** (cron segunda 06:17 UTC do workflow
 * `exposicao-tier0-sentinel.yml`): o dado existia, ranqueado, e não chegava a quem abre
 * a sessão. Sintoma medido em 2026-07-27: pra saber "qual tela quente atacar primeiro" a
 * pergunta era refeita à mão (com proxy pior — grep de `num_uf` no Controller, que mede
 * o Controller e não a exposição da TELA em 4 categorias, e produziu ranking errado).
 *
 * Esta classe é só o TRANSPORTE pro brief. Não é régua nova: quem mede, pesa e ranqueia
 * segue sendo a sentinela (fonte única). Aqui só se formata uma linha.
 *
 * Transporte (o brief é PHP, a sentinela é Node): shell-out de
 * `scripts/qa/exposicao-tier0.mjs --stdout` via Process + json_decode — mesmo idioma de
 * PlanHealthBriefLineService. O modo `--stdout` existe pra isto: o default imprime
 * relatório de texto e `--json` GRAVA o baseline, nenhum dos dois é consumível 6x/dia.
 * O delta (`trend`) também vem da sentinela — NÃO se recalcula piso/débito em PHP.
 *
 * Determinística (pós-LLM): `inject()` é chamado por GenerateBriefCommand DEPOIS do
 * Brain B gerar o markdown — o modelo nunca inventa esse número. Espelha o pattern de
 * SddBriefLineService (GT-G8): injeta um bullet na seção `## FLAGS`.
 *
 * Degrada graciosamente (brief NUNCA quebra por causa dela):
 *  - `node` ausente / script não-deployado / timeout → null (sem linha)
 *  - JSON inválido → null
 *  - zero telas quentes (universo vazio / varredura sem match) → null
 * Kill-switch: `governance.exposicao_tier0_brief_line` false → no-op (default ON).
 *
 * NÃO é presence-gate (proibicoes §gate-de-presença): o número reportado é o `hot_debt`
 * da sentinela, que só credita cobertura por E2E de path exato OU UC citado por >=1 teste
 * (G-2, ADR 0264) — nunca por charter/casos existirem. Todos os valores são DERIVADOS da
 * árvore a cada execução; nada aqui é auto-declarado.
 *
 * @see scripts/qa/exposicao-tier0.mjs (sentinela · shape {aggregates,debt_ranked,trend})
 * @see Modules\Governance\Services\PlanHealthBriefLineService (pattern irmão)
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 * @see .github/workflows/exposicao-tier0-sentinel.yml (o outro canal — issue semanal)
 */
final class ExposicaoTier0BriefLineService
{
    /** Teto de tempo do shell-out Node (s). Medido 2026-07-27: ~1,4s — 20s é folga. */
    private const TIMEOUT_SECONDS = 20;

    /**
     * Injeta a linha de exposição Tier-0 como 1º bullet da seção `## FLAGS`.
     * Best-effort: qualquer falha (ou linha null) devolve o conteúdo intacto.
     * Kill-switch: `governance.exposicao_tier0_brief_line` false → no-op (default ON).
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.exposicao_tier0_brief_line', true)) {
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
     * Linha de débito Tier-0, ou null quando não há nada confiável a reportar
     * (sentinela indisponível / zero telas quentes — ver docblock da classe).
     *
     * Formato: `<emoji> Exposição Tier-0: D/H quentes sem teste[ (Δ±N)] · topo: <tela>`
     *  - D/H   = hot_debt / tier0_hot (telas expostas a dinheiro/estoque/PII/fiscal)
     *  - Δ±N   = delta do débito vs baseline da catraca (omitido quando 0)
     *  - topo  = 1ª tela do ranking por peso (dinheiro/estoque=4 · fiscal=3 · pii=2).
     *            Teto de vazão 1: o brief é curto; a cauda sai na issue semanal.
     *  - emoji = 🔴 piso Tier-0 regrediu OU débito cresceu · 🟡 débito estável ·
     *            🟢 débito zero (convenção FLAGS).
     */
    public function line(): ?string
    {
        $data = $this->fetchSnapshot();

        if ($data === null) {
            return null;
        }

        $agg = (array) ($data['aggregates'] ?? []);
        $hot = (int) ($agg['tier0_hot'] ?? 0);

        // Zero telas quentes = varredura sem match (universo vazio / script noutro cwd).
        // Reportar "0/0" seria afirmar saúde a partir de ausência de medição.
        if ($hot === 0) {
            return null;
        }

        $debt = (int) ($agg['hot_debt'] ?? 0);
        $trend = is_array($data['trend'] ?? null) ? $data['trend'] : null;

        $debtDelta = (int) ($trend['hot_debt_delta'] ?? 0);
        $pisoRegrediu = (bool) ($trend['piso_regrediu'] ?? false);

        if ($debt === 0) {
            $emoji = '🟢';
        } elseif ($pisoRegrediu || $debtDelta > 0) {
            $emoji = '🔴';
        } else {
            $emoji = '🟡';
        }

        $linha = sprintf('%s Exposição Tier-0: %d/%d quentes sem teste', $emoji, $debt, $hot);

        if ($debtDelta !== 0) {
            $linha .= sprintf(' (Δ%+d)', $debtDelta);
        }

        $topo = $this->topScreen($data);
        if ($topo !== null) {
            $linha .= ' · topo: '.$topo;
        }

        return $linha;
    }

    /**
     * 1ª tela do ranking de débito, ou null se a lista vier vazia/malformada.
     * A sentinela já entrega `debt_ranked` ordenado por peso — aqui não se reordena.
     */
    private function topScreen(array $data): ?string
    {
        $ranked = array_values((array) ($data['debt_ranked'] ?? []));

        if ($ranked === []) {
            return null;
        }

        $first = $ranked[0];
        $screen = is_array($first) ? ($first['screen'] ?? null) : null;

        return is_string($screen) && $screen !== '' ? $screen : null;
    }

    /**
     * Roda a sentinela Node e devolve o JSON decodificado, ou null se o shell-out
     * falhar/não produzir JSON. `--stdout` não grava baseline e não altera exit code,
     * mas parseamos o output independente do exit code por robustez (só o binário
     * ausente/quebrado, que não produz JSON, vira null).
     *
     * @return array<string, mixed>|null
     */
    private function fetchSnapshot(): ?array
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run(['node', 'scripts/qa/exposicao-tier0.mjs', '--stdout']);
        } catch (Throwable $e) {
            // `node` ausente no host (ex.: shared hosting sem Node) → sem linha.
            Log::debug('[exposicao-tier0 brief line] shell-out falhou: '.$e->getMessage());

            return null;
        }

        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
