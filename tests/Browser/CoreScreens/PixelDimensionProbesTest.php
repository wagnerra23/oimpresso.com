<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — PROVA QUE A(S) MÁQUINA(S) "MORDEM" NAS 15 DIMENSÕES (o que der).
 *
 * Espelha o CONTROLE-NEGATIVO EMBUTIDO do ConformanceProbesTest (L-31 / "todo ✅ tem
 * que ter sido visto falhar"): perturba DE PROPÓSITO cada dimensão testável e assere
 * que a máquina certa acusa; sem perturbação, LIBERA (não dá falso-positivo).
 *
 * ── MATRIZ DAS 15 DIMENSÕES — máquina certa por dimensão (honesta, mata cobertura falsa) ──
 *   PIXEL (probe de pixel aqui, teste 1):
 *     1 Layout · 2 Hierarquia · 3 Densidade · 4 Iconografia · 9 Tipografia · 10 Espaço ·
 *     11 Cor · 12 Microinteração (parte ESTÁTICA: sombra/blur; animação NÃO é pixel)
 *   COMPORTAMENTAL (interação, teste 2 — PIXEL É CEGO):
 *     6 Atalhos teclado → dispara '/' e assere foco (a Unificada: window keydown → #fin-search-input)
 *   COBERTO EM OUTRO GATE (documentado, não re-testado aqui):
 *     5 Estados visuais → tests/Browser/CoreScreens/IsolatedStatesBaselineTest (default/empty/loading/dark)
 *     7 Persistência    → localStorage: NÃO EXISTE na Unificado/Index.tsx (grep vazio 2026-07-15);
 *                         onde existir (outra tela) = assert localStorage.getItem. N/A aqui.
 *   NÃO-TESTÁVEL POR MÁQUINA (meta/decisão — humano/PR UI Judge, por construção):
 *     13 Ref. aprovada · 14 Benchmarks · 15 Persona — são DECISÃO/DOC, não propriedade
 *        da tela renderizada. Marcar "auto-coberto" seria a cobertura falsa que o §5 proíbe.
 *
 * ⚠️ HONESTIDADE (ADR 0108): NÃO rodado local (Pest Browser = CI/CT 100 only). Validado por
 * php -l + espelhamento do padrão A11yAxe/ConformanceProbes (verde no visual-regression.yml).
 * NUNCA biz=4 (ADR 0101).
 *
 * @see tests/Browser/CoreScreens/ConformanceProbesTest.php (padrão negativo-embutido)
 * @see tests/Browser/Support/VisregThreshold.php (captureBlob + ratioBetween + τ)
 */

use App\Business;
use App\User;
use Tests\Browser\Support\VisregThreshold;

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

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

function pixdimResolveTenant(): array
{
    $business = Business::query()->orderBy('id')->first(); // ratchet-safe
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return [$business, $admin];
}

// ─────────────────────────────────────────────────────────────────────────────
// TESTE 1 — máquina de PIXEL morde as dimensões visuais e libera no limpo.
// ─────────────────────────────────────────────────────────────────────────────
it('Financeiro/Unificado — PIXEL morde dims 1/2/3/4/9/10/11/12 e LIBERA no limpo', function () {
    [, $admin] = pixdimResolveTenant();

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/financeiro/unificado'));
    $page->assertSee('Financeiro');
    $page->script(NORMALIZE_JS);
    $page->wait(1.5);

    $tauLow = VisregThreshold::tauLow();
    $tauHigh = VisregThreshold::tauHigh();

    $clean = VisregThreshold::captureBlob($page, 'pixdim_clean');

    // LIBERA — clean-vs-clean < τ_baixo (sem falso-positivo).
    $cleanAgain = VisregThreshold::captureBlob($page, 'pixdim_clean_again');
    $ratioStable = VisregThreshold::ratioBetween($clean, $cleanAgain);
    expect($ratioStable)->toBeLessThan(
        $tauLow,
        sprintf('LIBERA falhou: clean-vs-clean=%.4f%% deveria ser < τ_baixo=%.4f%%', $ratioStable * 100, $tauLow * 100)
    );

    // MORDE — uma perturbação por dimensão, sempre revertida (alvo: KPIs .fin-stat, no viewport).
    $probes = [
        'dim1-layout'        => ['__pd_layout', '.fin-stat { transform: translateY(40px) !important; }'],
        'dim2-hierarquia'    => ['__pd_hier',   '.fin-stat-hero { transform: scale(0.6) !important; }'],
        'dim3-densidade'     => ['__pd_dens',   '.fin-stat, .fin-stat * { letter-spacing: 4px !important; }'],
        'dim4-iconografia'   => ['__pd_icon',   '.fin-stat svg, header svg { visibility: hidden !important; }'],
        'dim9-tipografia'    => ['__pd_type',   '.fin-stat, .fin-stat * { font-size: 30px !important; line-height: 1.1 !important; }'],
        'dim10-espacamento'  => ['__pd_space',  '.fin-stat { padding: 40px !important; }'],
        'dim11-cor'          => ['__pd_cor',    '.fin-stat { background: #fde68a !important; border-color: #f59e0b !important; }'],
        'dim12-microinteracao' => ['__pd_shadow', '.fin-stat { box-shadow: 0 0 0 8px #ff0000 !important; }'],
    ];

    foreach ($probes as $label => [$id, $css]) {
        $page->script(pixdimInject($id, $css));
        $page->wait(0.6);
        $dirty = VisregThreshold::captureBlob($page, 'pixdim_' . $id);
        $ratio = VisregThreshold::ratioBetween($clean, $dirty);
        $page->script(pixdimRemove($id)); // reverte SEMPRE

        // "Enxerga" = ratio ACIMA de τ_baixo (a máquina NÃO classifica como idêntico/aprovado).
        // > τ_alto seria "regressão gritante" — rigoroso demais pra provar percepção. A dupla
        // LIBERA(<τ_baixo) / MORDE(>τ_baixo) prova que o limiar de identidade separa certo.
        expect($ratio)->toBeGreaterThan(
            $tauLow,
            sprintf('MORDE falhou (%s): ratio=%.4f%% NÃO passou de τ_baixo=%.4f%% → a máquina NÃO viu diferença nenhuma (cega)', $label, $ratio * 100, $tauLow * 100)
        );
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// TESTE 2 — máquina COMPORTAMENTAL vê o atalho de teclado (dim 6). PIXEL é CEGO nisso.
// ─────────────────────────────────────────────────────────────────────────────
it('Financeiro/Unificado — atalho "/" foca a busca (dim 6, comportamental) + controle-negativo', function () {
    [, $admin] = pixdimResolveTenant();

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/financeiro/unificado'));
    $page->assertSee('Financeiro');
    $page->wait(2.5); // hidratação: o handler global só existe após o useEffect (window.addEventListener)

    // CONTROLE-NEGATIVO: sem apertar nada, a busca NÃO está focada.
    $before = $page->script("return (document.activeElement && document.activeElement.id) || '';");
    expect($before)->not->toBe('fin-search-input', 'A busca já estava focada sem apertar / — controle-negativo inválido');

    // MORDE: dispara '/' direto no window (onde vive o addEventListener, linha ~1503 da tela).
    $page->script("window.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true })); return true;");
    $page->wait(0.6);

    $after = $page->script("return (document.activeElement && document.activeElement.id) || '';");
    expect($after)->toBe(
        'fin-search-input',
        'Atalho "/" NÃO focou #fin-search-input → dim 6 quebrada OU handler não capturou (a máquina comportamental estaria cega)'
    );
});
