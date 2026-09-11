<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * FLAG de PARIDADE BLADE→React no Daily Brief — a fila que ninguém via sem perguntar.
 *
 * Origem (2026-09-07, [W], textual): *"chato eu ter que pedir para deixar a paridade do
 * módulo"* e, na sequência, *"automatize isso"*. O caso concreto: o módulo Jana ainda
 * servia 6 endpoints em Blade dentro de um app Inertia, e isso só apareceu porque [W]
 * abriu uma tela e viu que estava feia. A informação existia — o censo
 * (`blade-migration-census.mjs`) mede isso desde sempre e a catraca `--ratchet` já roda
 * em `governance-gate` — mas ninguém era COBRADO por ela: a catraca só impede SUBIR, e
 * quem quisesse saber o tamanho da fila tinha que rodar o script na mão.
 *
 * O que esta flag acrescenta, e é só isto: a fila chega ao [W] todo dia, junto das outras.
 * Ela não julga, não prioriza e não cria régua nova — o dono do número continua sendo o
 * censo (fonte única; NÃO reimplementamos a classificação em PHP).
 *
 * DUAS COISAS NA MESMA LINHA, e a segunda é a que estava invisível:
 *  1. quantos endpoints ainda servem Blade (o tamanho da dívida);
 *  2. quantos escopos têm **ganho não travado** — mediram menos que o baseline e ninguém
 *     re-baselineou. Medido em 2026-09-07: 6 escopos, 16 endpoints de folga. Enquanto a
 *     folga existe, a catraca aceita reintroduzir Blade até o teto antigo sem ficar
 *     vermelha — ou seja, o progresso não estava travado.
 *
 * Determinística (pós-LLM): `inject()` roda DEPOIS do Brain B gerar o markdown — o modelo
 * nunca inventa estes números.
 *
 * Degrada graciosamente (o brief NUNCA quebra por causa dela):
 *  - `node` ausente / script não-deployado / timeout → null (sem linha)
 *  - JSON inválido → null
 *  - 0 endpoints Blade → null (flag só existe quando há o que reportar)
 * Kill-switch: `governance.paridade_blade_brief_line` false → no-op (default ON).
 *
 * @see scripts/governance/blade-migration-census.mjs (dono do número · `--resumo-json`)
 * @see governance/blade-migration-baseline.json (a catraca, que só impede SUBIR)
 * @see Modules\Governance\Services\ObraParadaBriefLineService (pattern irmão)
 * @see Modules/Forja/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */
final class ParidadeBladeBriefLineService
{
    /** Teto do shell-out Node (s) — o censo varre rotas do repo, sem rede. */
    private const TIMEOUT_SECONDS = 60;

    /**
     * Injeta a flag de paridade como bullet da seção `## FLAGS`.
     * Best-effort: qualquer falha (ou linha null) devolve o conteúdo intacto.
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.paridade_blade_brief_line', true)) {
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
     * A flag, ou null quando não há Blade a migrar.
     *
     * Formato: `🟠 Blade por migrar: 454 endpoints — pior módulo: Crm (47) · 16 de ganho não travado`.
     *
     * Cita o PIOR MÓDULO com nome e número porque total solto não diz onde olhar, e flag
     * que não aponta o alvo vira ruído que se aprende a ignorar. O núcleo (`core`) fica
     * FORA do "pior módulo" de propósito: ele é a maior fatia por construção (250 de 454
     * em 2026-09-07) e apareceria sempre, escondendo o módulo que de fato dá pra atacar —
     * o total, esse sim, continua contando o núcleo inteiro.
     */
    public function line(): ?string
    {
        $data = $this->fetchCenso();

        if ($data === null) {
            return null;
        }

        $total = (int) ($data['total_blade'] ?? 0);

        if ($total === 0) {
            return null;
        }

        $porEscopo = (array) ($data['por_escopo'] ?? []);

        $piorNome = null;
        $piorN = 0;
        foreach ($porEscopo as $escopo => $dados) {
            if ($escopo === 'core') {
                continue;
            }
            $n = (int) (((array) $dados)['blade'] ?? 0);
            if ($n > $piorN) {
                $piorN = $n;
                $piorNome = (string) $escopo;
            }
        }

        $folga = $this->folgaDaCatraca($porEscopo);

        $linha = sprintf('🟠 Blade por migrar: %d endpoints', $total);

        if ($piorNome !== null) {
            $linha .= sprintf(' — pior módulo: %s (%d)', $piorNome, $piorN);
        }

        if ($folga > 0) {
            $linha .= sprintf(' · %d de ganho não travado (re-baseline)', $folga);
        }

        return $linha;
    }

    /**
     * Quantos endpoints de progresso NÃO estão travados no baseline da catraca.
     *
     * A catraca (`--ratchet`) só impede SUBIR: descer passa, e o baseline continua com o
     * número antigo até alguém re-baselinear. Enquanto isso, dá pra reintroduzir Blade até
     * o teto velho sem gate nenhum reclamar. Este número é o tamanho dessa brecha.
     *
     * Baseline ausente ou ilegível → 0 (a linha simplesmente não fala de folga).
     *
     * @param  array<string, mixed>  $porEscopo
     */
    private function folgaDaCatraca(array $porEscopo): int
    {
        $arquivo = base_path('governance/blade-migration-baseline.json');

        if (! is_file($arquivo)) {
            return 0;
        }

        $baseline = json_decode((string) file_get_contents($arquivo), true);

        if (! is_array($baseline)) {
            return 0;
        }

        $base = (array) ($baseline['por_escopo'] ?? []);
        $folga = 0;

        foreach ($porEscopo as $escopo => $dados) {
            $antes = (int) (((array) ($base[$escopo] ?? []))['blade'] ?? 0);
            $agora = (int) (((array) $dados)['blade'] ?? 0);

            if ($agora < $antes) {
                $folga += $antes - $agora;
            }
        }

        return $folga;
    }

    /**
     * Roda o censo e devolve o JSON decodificado, ou null se o shell-out falhar.
     *
     * @return array<string, mixed>|null
     */
    private function fetchCenso(): ?array
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run(['node', 'scripts/governance/blade-migration-census.mjs', '--resumo-json']);
        } catch (Throwable $e) {
            Log::debug('[paridade-blade brief line] shell-out falhou: '.$e->getMessage());

            return null;
        }

        $saida = trim($result->output());

        if ($saida === '') {
            return null;
        }

        $data = json_decode($saida, true);

        return is_array($data) ? $data : null;
    }
}
