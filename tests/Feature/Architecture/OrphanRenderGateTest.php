<?php

declare(strict_types=1);

/**
 * Gate de TELA ÓRFÃ/MORTA — todo `Inertia::render('X')` tem uma page viva
 * (resources/js/Pages/X.tsx|.jsx).
 *
 * Origem: sessão 2026-06-17/18 (auditoria adversarial). Furo provado: NÃO havia
 * defesa contra render órfão / tela morta. O único teste capaz (AppShellUsageGateTest)
 * dá `continue` quando a page não existe (linha ~115) — cego POR DESIGN pro caso. Dois
 * renders apontam pra page inexistente em prod: `Atendimento/Inbox/Index` e
 * `Financeiro/Boletos/Index` (telas substituídas no cutover, controllers nunca limpos).
 * Render sem page = 500 em runtime se a rota for atingida, OU dead code silencioso.
 *
 * Plano: "Catraca Viva", Fase 1. Fecha o ponto cego do AppShellUsageGateTest sem
 * mexer nele (responsabilidade única: aquele checa AppShellV2, este checa existência).
 *
 * Regra dos 3:
 *   - MORDE: roda no ui-architecture-gate.yml (required, ADR 0263); falha em render órfão.
 *   - AUTO-TESTA: caso negativo in-band (target sintético inexistente) prova o dente.
 *   - VIGIA: allowlist explícita versionada (não skip silencioso).
 *
 * Filesystem-puro (sem DB/browser). Funções com nomes próprios (orphan*) pra não
 * colidir com AppShellUsageGateTest no mesmo suite.
 *
 * @see tests/Feature/Architecture/AppShellUsageGateTest.php (o `continue` que este cobre)
 */

// Render targets ÓRFÃOS conhecidos (controller renderiza page que não existe mais), com
// motivo + ação. NÃO é skip: target NOVO órfão falha mesmo assim. Limpeza do dead code
// rastreada fora (1 PR = 1 intent). Allowlist só ENCOLHE.
const ORPHAN_RENDER_ALLOWLIST = [
    'Atendimento/Inbox/Index'
        => 'InboxController::index órfão pós-cutover Caixa Unificada V4 (ADR 0135); /inbox é 301 → caixa-unificada. Limpar dead code: task spawn_37d7a9e6',
    'Financeiro/Boletos/Index'
        => 'BoletoController::index órfão; /financeiro/boletos é 301 → /financeiro/cobranca (hotfix 2026-05-19). Limpar dead code: task separada',
];

function orphanGateRepoRoot(): string
{
    // tests/Feature/Architecture -> repo root (3 níveis acima).
    return dirname(__DIR__, 3);
}

/**
 * Alvos de `Inertia::render('X/Y')` / `inertia('X/Y')` em app/ e Modules/.
 *
 * @return list<string>
 */
function orphanRenderTargets(): array
{
    $roots = [orphanGateRepoRoot().'/app', orphanGateRepoRoot().'/Modules'];
    $targets = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if ($code === false || ! str_contains($code, 'nertia')) {
                continue;
            }
            if (preg_match_all("/(?:Inertia::render|inertia)\(\s*'([A-Za-z0-9_\/]+)'/", $code, $m)) {
                foreach ($m[1] as $t) {
                    $targets[$t] = true;
                }
            }
        }
    }

    return array_keys($targets);
}

/**
 * Um render target é órfão quando NÃO existe page (.tsx nem .jsx) correspondente.
 * Inertia::render('X') exige resources/js/Pages/X.{tsx,jsx} — sem ela, 500 em runtime.
 */
function orphanRenderViolation(string $target, string $repoRoot): bool
{
    $base = $repoRoot.'/resources/js/Pages/'.$target;

    return ! is_file($base.'.tsx') && ! is_file($base.'.jsx');
}

it('todo Inertia::render aponta pra uma page viva (sem tela órfã/morta, exceto allowlist)', function () {
    $root = orphanGateRepoRoot();
    $targets = orphanRenderTargets();

    // Sanidade: o crawler tem que achar um volume realista de telas.
    expect(count($targets))->toBeGreaterThan(100);

    $violations = [];
    foreach ($targets as $target) {
        if (array_key_exists($target, ORPHAN_RENDER_ALLOWLIST)) {
            continue;
        }
        if (orphanRenderViolation($target, $root)) {
            $violations[] = $target;
        }
    }

    sort($violations);

    expect($violations)->toBe([], sprintf(
        "Inertia::render apontando pra page inexistente (%d) — tela órfã/morta. "
        ."Crie a page, remova o render morto, OU (se transição conhecida) adicione à "
        ."allowlist com motivo em %s:\n  - %s",
        count($violations),
        'tests/Feature/Architecture/OrphanRenderGateTest.php',
        implode("\n  - ", $violations)
    ));
});

it('MORDE: acusa um render sem page (target sintético — anti-teatro)', function () {
    // Self-test in-band: a MESMA lógica, apontada pra um target que comprovadamente
    // não tem page. Se NÃO acusar, o gate virou teatro.
    $synthetic = 'Fixture/__RenderSemPageInexistente__';

    expect(orphanRenderViolation($synthetic, orphanGateRepoRoot()))
        ->toBeTrue('o gate NÃO acusou um render sem page — parou de morder');

    // E um target real e vivo NÃO pode ser falso-positivo.
    expect(orphanRenderViolation('Home/Index', orphanGateRepoRoot()))
        ->toBeFalse('falso-positivo: Home/Index tem page viva e foi acusado');
});
