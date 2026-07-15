<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — PROVA QUE A MÁQUINA DE PIXEL "MORDE" NAS DIMENSÕES VISUAIS.
 *
 * Espelha o padrão de CONTROLE-NEGATIVO EMBUTIDO do ConformanceProbesTest (L-31 /
 * "todo ✅ tem que ter sido visto falhar"): cada dimensão pixel-visível é perturbada
 * DE PROPÓSITO no DOM (style descartável), capturada, e o teste assere que o ratio
 * pixelmatch (o MESMO do gate visual-regression) ULTRAPASSA o τ_alto = a máquina
 * ENXERGA (morde). O clean-vs-clean fica ABAIXO do τ_baixo = não dá falso-positivo
 * (libera). Self-contained: compara duas capturas in-test, sem depender da baseline
 * commitada (elimina flakiness de seed/baseline).
 *
 * ── MATRIZ DE COBERTURA DAS 15 DIMENSÕES (honesta — mata cobertura falsa) ──────────
 *   PIXEL pega (probe aqui):   1 Layout · 2 Hierarquia · 3 Densidade · 4 Iconografia ·
 *                              9 Tipografia · 10 Espaçamento · 11 Cor
 *   PIXEL parcial:             5 Estados (só o estado capturado — ver IsolatedStates) ·
 *                              12 Microinterações (sombra/blur estático sim; animação não)
 *   PIXEL CEGO (comportamento → outro teste):
 *                              6 Atalhos teclado    → teste de interação (keypress)
 *                              7 Persistência        → assert localStorage
 *   PIXEL CEGO (meta/humano → PR UI Judge / [W]):
 *                              13 Ref. aprovada · 14 Benchmarks · 15 Persona
 *
 * Este arquivo prova as 3 mais visíveis (9/10/11) na Unificada; as parciais/cegas
 * ficam MARCADAS acima (documentado, não escondido). Estender para as demais = seguir
 * o mesmo molde ou apontar o teste comportamental.
 *
 * ⚠️ HONESTIDADE (ADR 0108): NÃO rodado local (Pest Browser = CI/CT 100 only). Validado
 * por php -l + espelhamento do padrão A11yAxe/ConformanceProbes que já roda verde no
 * visual-regression.yml. NUNCA biz=4 (ADR 0101).
 *
 * @see tests/Browser/CoreScreens/ConformanceProbesTest.php (padrão negativo-embutido espelhado)
 * @see tests/Browser/CoreScreens/PixelBaselineTest.php (a máquina de pixel de produção)
 * @see tests/Browser/Support/VisregThreshold.php (captureBlob + ratioBetween + τ)
 * @see .github/workflows/visual-regression.yml (gate que invoca)
 */

use App\Business;
use App\User;
use Tests\Browser\Support\VisregThreshold;

beforeEach(function () {
    // CROSS-PROCESS DB (igual A11yAxe/ConformanceProbes): browser usa MySQL (.env);
    // o processo de teste realinha pro MESMO MySQL pra enxergar o tenant seedado.
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

// Normalização visual IDÊNTICA à do PixelBaselineTest (transitions off + Arial +
// antialiasing) pra o ratio bater com o do engine nativo.
const NORMALIZE_JS = <<<'JS'
    (() => {
      const s = document.createElement('style');
      s.id = '__pixdim_normalize';
      s.textContent = `
        * { transition: none !important; animation: none !important; font-family: Arial, sans-serif !important; }
        body { -webkit-font-smoothing: antialiased !important; -moz-osx-font-smoothing: grayscale !important; }
        select, input[type=date], input[type=datetime-local], input[type=time] { visibility: hidden !important; }
      `;
      document.head.appendChild(s);
      return true;
    })()
JS;

// Injeta um <style> de perturbação por id; retorna true.
function pixdimInject(string $id, string $css): string
{
    return sprintf(
        <<<'JS'
        (() => {
          const s = document.createElement('style');
          s.id = %s;
          s.textContent = %s;
          document.head.appendChild(s);
          return true;
        })()
        JS,
        json_encode($id),
        json_encode($css),
    );
}

function pixdimRemove(string $id): string
{
    return sprintf('(() => { const e = document.getElementById(%s); if (e) e.remove(); return true; })()', json_encode($id));
}

it('Financeiro/Unificado — a máquina de pixel MORDE dims 9/10/11 e LIBERA no limpo', function () {
    $business = Business::query()->orderBy('id')->first(); // ratchet-safe (sem literal Business::first)
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/financeiro/unificado'));
    $page->assertSee('Financeiro');

    $page->script(NORMALIZE_JS);
    $page->wait(1.5);

    $tauLow = VisregThreshold::tauLow();
    $tauHigh = VisregThreshold::tauHigh();

    // Captura de referência (limpo).
    $clean = VisregThreshold::captureBlob($page, 'pixdim_clean');

    // LIBERA: capturar de novo sem mudar nada → ratio ~0 (< τ_baixo). Prova que não há
    // falso-positivo (a máquina não "vê" mudança onde não há).
    $cleanAgain = VisregThreshold::captureBlob($page, 'pixdim_clean_again');
    $ratioStable = VisregThreshold::ratioBetween($clean, $cleanAgain);
    expect($ratioStable)->toBeLessThan(
        $tauLow,
        sprintf('LIBERA falhou: clean-vs-clean=%.4f%% deveria ser < τ_baixo=%.4f%% (máquina instável/falso-positivo)', $ratioStable * 100, $tauLow * 100)
    );

    // MORDE — cada dimensão perturbada isoladamente, revertida em seguida.
    $probes = [
        // dim => [id, css alvo nos KPIs .fin-stat]
        'dim11-cor'         => ['__pixdim_cor', '.fin-stat { background: #fde68a !important; border-color: #f59e0b !important; }'],
        'dim10-espacamento' => ['__pixdim_space', '.fin-stat { padding: 40px !important; }'],
        'dim9-tipografia'   => ['__pixdim_type', '.fin-stat, .fin-stat * { font-size: 30px !important; line-height: 1.1 !important; }'],
    ];

    foreach ($probes as $label => [$id, $css]) {
        $page->script(pixdimInject($id, $css));
        $page->wait(0.6);
        $dirty = VisregThreshold::captureBlob($page, 'pixdim_' . $id);
        $ratio = VisregThreshold::ratioBetween($clean, $dirty);
        $page->script(pixdimRemove($id)); // reverte SEMPRE (mesmo se a asserção falhar depois)

        expect($ratio)->toBeGreaterThan(
            $tauHigh,
            sprintf('MORDE falhou (%s): perturbei a dimensão e o ratio=%.4f%% NÃO passou de τ_alto=%.4f%% → máquina CEGA nessa dimensão', $label, $ratio * 100, $tauHigh * 100)
        );
    }
});
