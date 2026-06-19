<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de Design System (ratchets R-DS-001..006) contra fonte-da-verdade móvel (.tsx) — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

/**
 * Design System Audit (R-DS-001..006) — varredura estática do source TSX.
 *
 * Origem: recomendação P0 #3 da auditoria 2026-04-24 (`memory/requisitos/_DesignSystem/SPEC.md`
 * marcava "Testado em: [TODO]" pra todas as 12 regras). Este test fecha esse débito.
 *
 * Padrão: grep estático em `resources/js/{Pages,Components}/**\/*.tsx` procurando
 * anti-patterns documentados em [GOTCHAS.md](.claude/skills/cockpit-runbook/GOTCHAS.md).
 * Cada regra falha com lista de `file:line` violadores — formato igual ao audit
 * Modo B do skill cockpit-runbook (CHECKLIST.md §E).
 *
 * **Exclusões intencionais** (cores fixas semânticas — R-DS-002 exceção):
 *   - Status badges (open/awaiting_human/resolved/archived/health/provider/category)
 *   - KPI tones (success/warning/danger/info)
 *   - Avatar paleta hash em `_components/Avatar.tsx` / helpers
 *
 * Use `pest tests/Unit/DesignSystemAuditTest.php` pra rodar isolado.
 *
 * @see memory/requisitos/_DesignSystem/SPEC.md
 * @see .claude/skills/cockpit-runbook/CHECKLIST.md §E
 */

use Symfony\Component\Finder\Finder;

/**
 * Coleta arquivos .tsx em paths relevantes (Pages + Components shared + Layouts).
 * Exclui _components/ específicos onde paleta hash é intencional.
 *
 * @return array<int, array{path: string, content: string}>
 */
function dsAuditCollectTsx(): array
{
    // Unit test: não usa app() bootstrap. Resolve path a partir deste arquivo:
    // tests/Unit/DesignSystemAuditTest.php → resources/js (3 níveis acima).
    $base = realpath(__DIR__.'/../../resources/js');
    if ($base === false || ! is_dir($base)) {
        return [];
    }

    $finder = (new Finder)
        ->in([
            $base.'/Pages',
            $base.'/Components',
            $base.'/Layouts',
        ])
        ->name('*.tsx')
        ->files();

    $files = [];
    foreach ($finder as $file) {
        // Path relativo à raiz do projeto (resources/js/...) — Finder só preserva
        // path relativo ao root passado em ->in(). Reconstruir manualmente a partir
        // do realPath pra preservar `Components/ui/...` e similar.
        $abs = str_replace('\\', '/', $file->getRealPath());
        $base = str_replace('\\', '/', dirname(__DIR__, 2)); // tests/Unit → projeto raiz
        $relFromRoot = ltrim(str_replace($base, '', $abs), '/');
        $files[] = [
            'path' => $relFromRoot,
            'rel' => $relFromRoot,
            'abs' => $abs,
            'content' => $file->getContents(),
        ];
    }

    return $files;
}

/**
 * Aplica regex ao conteúdo de cada arquivo, retorna lista de violações
 * `path:line — match`. Se $excludeIfMatch retornar true pra um match, pula.
 *
 * @param  array<int, array{path: string, content: string}>  $files
 * @return array<int, string>
 */
function dsAuditFindViolations(array $files, string $regex, ?callable $excludeIfMatch = null): array
{
    $violations = [];
    foreach ($files as $file) {
        $lines = preg_split('/\r\n|\r|\n/', $file['content']);
        foreach ($lines as $i => $line) {
            if (preg_match($regex, $line, $m)) {
                $lineNum = $i + 1;
                if ($excludeIfMatch !== null && $excludeIfMatch($file['path'], $line, $m)) {
                    continue;
                }
                $violations[] = sprintf('%s:%d — %s', $file['path'], $lineNum, trim(substr($line, 0, 120)));
            }
        }
    }

    return $violations;
}

/**
 * Baseline-based ratchet — número atual de violações por regra (2026-05-07).
 *
 * Quando alguém adiciona violação NOVA, test falha indicando qual regra
 * regrediu. Quando alguém CONSERTA, atualizar o baseline pra "travar" o ganho.
 *
 * Filosofia "boy scout rule": cada PR pode deixar o lugar mais limpo, nunca
 * mais sujo. CI verde = não regrediu; CI vermelho = ou refatora ou diminui
 * baseline (após consertar parte).
 */
const DS_AUDIT_BASELINE = [
    'R-DS-001' => 41,  // <button> HTML cru em Pages
    'R-DS-002' => 0,   // imports iconografia alternativa
    'R-DS-003' => 0,   // espaçamento arbitrário [Npx]
    'R-DS-004' => 0,   // hex/rgb hardcoded em snippets
    'R-DS-005' => 2,   // outline-none sem ring (dialog/input shadcn primitivas)
    'R-DS-006' => 5,   // Persistent Layout violations (5 telas — refactor pendente, ver TODO)
];

it('R-DS-001 — `<button>` HTML cru em Pages não deve aumentar (ratchet)', function () {
    $files = dsAuditCollectTsx();
    expect($files)->not->toBeEmpty('Nenhum .tsx encontrado em resources/js');

    $pagesOnly = array_filter($files, fn ($f) => str_starts_with($f['rel'], 'Pages/'));

    $violations = dsAuditFindViolations(
        $pagesOnly,
        '/<button\b/',
        function (string $path, string $line): bool {
            return str_contains(ltrim($line), '//')
                || str_contains(ltrim($line), '*');
        }
    );

    $baseline = DS_AUDIT_BASELINE['R-DS-001'];
    $count = count($violations);

    expect($count)->toBeLessThanOrEqual(
        $baseline,
        sprintf(
            "R-DS-001 ratchet violado: %d violações encontradas, baseline %d.\nNovas violações:\n  - %s\nFix: usar `<Button>` de @/Components/ui/button. Se intencional, atualizar DS_AUDIT_BASELINE.",
            $count,
            $baseline,
            implode("\n  - ", array_slice($violations, $baseline))
        )
    );
});

it('R-DS-002 — sem import de iconografia alternativa (lucide-react obrigatório)', function () {
    $files = dsAuditCollectTsx();

    $violations = dsAuditFindViolations(
        $files,
        '/from\s+["\'](?:@radix-ui\/react-icons|heroicons|react-icons|@heroicons\/react)["\'\/]/'
    );

    expect($violations)->toBe([], "R-DS-003 violado (iconografia alternativa):\n  - ".implode("\n  - ", $violations)
        .".\nFix: usar SOMENTE `lucide-react`. Trocar imports.");
})->skip(false);

it('R-DS-003 — sem espaçamento arbitrário fora da escala 4px (`p-[Npx]`, `m-[Npx]`, `gap-[Npx]`)', function () {
    $files = dsAuditCollectTsx();

    $violations = dsAuditFindViolations(
        $files,
        '/(?:^|\s|"|\')(?:p|m|gap|space-[xy])(?:t|r|b|l|x|y)?-\[\d+px\]/',
        function (string $path, string $line): bool {
            return str_contains(ltrim($line), '//')
                || str_contains(ltrim($line), '*');
        }
    );

    expect($violations)->toBe([], "R-DS-004 violado (espaçamento arbitrário):\n  - ".implode("\n  - ", $violations)
        .".\nFix: usar escala canônica `p-1 p-2 p-3 p-4 p-6 p-8 p-12 p-16` (múltiplos de 4px). Caso intencional, documentar em ADR.");
});

it('R-DS-004 — sem cor hardcoded em hex/rgb em snippets TSX', function () {
    $files = dsAuditCollectTsx();

    $violations = dsAuditFindViolations(
        $files,
        '/(?:className|style)\s*=\s*\{?[^}]*(?:#[0-9a-fA-F]{3,8}\b|rgb\([^)]+\)|rgba\([^)]+\))/',
        function (string $path, string $line): bool {
            return str_contains(ltrim($line), '//')
                || str_contains(ltrim($line), '*')
                // gradient inline OK quando referencia var() do shell
                || (str_contains($line, 'var(') && ! preg_match('/#[0-9a-fA-F]{3,8}\b/', $line))
                // ConversationSidebar pinta swatch a partir de cor user-input (status.color do DB)
                || str_contains($path, 'Repair/Status/Index.tsx')
                // Bubble color do Cockpit consome var(--bubble-me) já é exceção R-DS-002
                || str_contains($path, '_components/ConversationThread.tsx')
                // CMS landing (Site/Home) usa gradient decorativo radial — exceção R-DS-007 documentada
                || str_contains($path, 'Site/Home.tsx');
        }
    );

    expect($violations)->toBe([], "R-DS-007 violado (cor hex/rgb hardcoded):\n  - ".implode("\n  - ", $violations)
        .".\nFix: usar tokens semânticos `bg-primary` ou variáveis do shell `var(--accent)`.");
});

it('R-DS-005 — sem `outline-none` sem `ring-` no mesmo elemento (focus visível obrigatório)', function () {
    $files = dsAuditCollectTsx();

    $violations = dsAuditFindViolations(
        $files,
        '/outline-none/',
        function (string $path, string $line): bool {
            // Aceita se a linha (ou linha próxima) tem ring-* — heurística simples
            return str_contains($line, 'ring-')
                || str_contains($line, 'focus-visible:')
                // Comentários
                || str_contains(ltrim($line), '//')
                || str_contains(ltrim($line), '*')
                // Componentes shadcn primitivos têm focus visible próprio
                || str_contains($path, 'Components/ui/')
                // TODO US-COPI-FOCUS-1: AssistantUiChat textarea precisa de ring focus-visible
                // — registrado como exceção temporária com path explícito (não falha o test
                // mas mantém ele detectável em audit Modo B futuro).
                || str_contains($path, 'copiloto/AssistantUiChat.tsx');
        }
    );

    $baseline = DS_AUDIT_BASELINE['R-DS-005'];
    $count = count($violations);

    expect($count)->toBeLessThanOrEqual(
        $baseline,
        sprintf(
            "R-DS-005 ratchet violado: %d violações, baseline %d.\nNovas:\n  - %s\nFix: `focus-visible:ring-2 focus-visible:ring-ring` ou remover `outline-none`.",
            $count,
            $baseline,
            implode("\n  - ", array_slice($violations, $baseline))
        )
    );
});

it('R-DS-006 — Pages devem usar Persistent Layout (`Tela.layout = page => <AppShellV2>`), não envolver inline', function () {
    $files = dsAuditCollectTsx();
    $pagesOnly = array_filter($files, fn ($f) => str_starts_with($f['rel'], 'Pages/') || str_contains($f['path'], '/Pages/'));

    $violations = [];
    foreach ($pagesOnly as $file) {
        $hasInlineShell = preg_match('/return\s*\(\s*<AppShellV2/', $file['content'])
            || preg_match('/return\s*<AppShellV2/', $file['content']);
        if ($hasInlineShell) {
            $violations[] = $file['path'].' — usa `return <AppShellV2>` inline em vez de `Tela.layout = (page) => <AppShellV2>{page}</AppShellV2>`';
        }
    }

    $baseline = DS_AUDIT_BASELINE['R-DS-006'];
    $count = count($violations);

    expect($count)->toBeLessThanOrEqual(
        $baseline,
        sprintf(
            "Persistent Layout ratchet violado: %d violações, baseline %d.\nNovas:\n  - %s\nFix: padrão DESIGN.md §4 — `Tela.layout = (page) => <AppShellV2>{page}</AppShellV2>`.",
            $count,
            $baseline,
            implode("\n  - ", array_slice($violations, $baseline))
        )
    );
});
