<?php
/**
 * GUARDA anti-drift — verifica se controllers em Modules/<X>/ estão declarados
 * em Modules/<X>/SCOPE.md (frontmatter `contains[]` ou `drift_alerts[]`).
 *
 * Constituição Art. 7 — Module Charter: controller fora de scope = drift bloqueado.
 *
 * Uso:
 *   php bin/check-scope.php                          # checa todos módulos
 *   php bin/check-scope.php --strict                 # exit 1 em qualquer warning
 *   php bin/check-scope.php --staged                 # só arquivos staged em git
 *   php bin/check-scope.php Modules/Copiloto         # checa módulo específico
 *
 * Exit codes:
 *   0 = OK (nenhum drift detectado)
 *   1 = drift detectado (em --strict ou no GitHub Action)
 *   2 = erro de execução (módulo sem SCOPE.md, frontmatter inválido)
 */

declare(strict_types=1);

$args = array_slice($argv, 1);
$strict = in_array('--strict', $args, true);
$stagedOnly = in_array('--staged', $args, true);
$args = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));
$specificModule = $args[0] ?? null;

$baseDir = realpath(__DIR__ . '/..');
chdir($baseDir);

// Helpers
function color(string $text, string $color): string {
    $colors = ['red' => 31, 'green' => 32, 'yellow' => 33, 'blue' => 34, 'gray' => 90];
    if (PHP_OS_FAMILY === 'Windows' && getenv('TERM') === false) {
        return $text; // sem cor em CMD plain
    }
    return "\033[{$colors[$color]}m{$text}\033[0m";
}

function parseFrontmatter(string $path): ?array {
    $content = @file_get_contents($path);
    if ($content === false) return null;
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) return null;

    $fm = [];
    $currentKey = null;
    foreach (preg_split('/\R/', $m[1]) as $line) {
        // top-level "key: value" ou "key:" (linha não-indentada)
        if (preg_match('/^([a-z_]+):\s*(.*)$/i', $line, $kv)) {
            $key = $kv[1];
            $val = trim($kv[2]);
            if ($val === '') {
                $fm[$key] = [];
                $currentKey = $key;
            } else {
                $fm[$key] = trim($val, '"\'');
                $currentKey = null;
            }
            continue;
        }
        // item de lista "  - value"
        if ($currentKey !== null && preg_match('/^\s+-\s*"?(.+?)"?\s*$/', $line, $lm)) {
            if (!isset($fm[$currentKey]) || !is_array($fm[$currentKey])) {
                $fm[$currentKey] = [];
            }
            $fm[$currentKey][] = $lm[1];
        }
    }
    return $fm;
}

function listControllers(string $moduleDir): array {
    $ctrlDir = $moduleDir . '/Http/Controllers';
    if (!is_dir($ctrlDir)) return [];
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ctrlDir, FilesystemIterator::SKIP_DOTS));
    $controllers = [];
    foreach ($iter as $file) {
        if ($file->getExtension() === 'php' && str_ends_with($file->getFilename(), 'Controller.php')) {
            $controllers[] = str_replace('\\', '/', substr($file->getPathname(), strlen($moduleDir) + 1));
        }
    }
    sort($controllers);
    return $controllers;
}

function getStagedControllers(): array {
    exec('git diff --cached --name-only --diff-filter=ACMR 2>nul', $out, $code);
    if ($code !== 0) {
        exec('git diff --cached --name-only --diff-filter=ACMR 2>/dev/null', $out, $code);
    }
    return array_filter($out, fn($f) => preg_match('#^Modules/[^/]+/Http/Controllers/.+Controller\.php$#', $f));
}

// Boilerplate ignorado (todo módulo tem ou pode ter — base classes/scaffolding)
$boilerplate = [
    'Http/Controllers/DataController.php',
    'Http/Controllers/InstallController.php',
    'Http/Controllers/SuperadminController.php',
    'Http/Controllers/Controller.php',  // base class
];

// Pega módulos a verificar
$modules = [];
if ($specificModule !== null) {
    $modulePath = rtrim($specificModule, '/');
    if (!str_starts_with($modulePath, 'Modules/')) {
        $modulePath = 'Modules/' . basename($modulePath);
    }
    if (is_dir($modulePath)) {
        $modules[] = $modulePath;
    } else {
        echo color("✗ Módulo não encontrado: $modulePath\n", 'red');
        exit(2);
    }
} else {
    foreach (glob('Modules/*', GLOB_ONLYDIR) as $dir) {
        $modules[] = $dir;
    }
}

if ($stagedOnly) {
    $stagedCtrls = getStagedControllers();
    if (empty($stagedCtrls)) {
        echo color("✓ Nenhum controller staged.\n", 'green');
        exit(0);
    }
    // Limita modules aos que têm staged controllers
    $modulesWithStaged = [];
    foreach ($stagedCtrls as $sc) {
        if (preg_match('#^(Modules/[^/]+)/#', $sc, $m)) {
            $modulesWithStaged[$m[1]] = true;
        }
    }
    $modules = array_intersect($modules, array_keys($modulesWithStaged));
}

// Banner
echo color("┌─────────────────────────────────────────────────────────────┐\n", 'blue');
echo color("│  GUARDA Anti-Drift — Constituição Art. 7 Module Charter    │\n", 'blue');
echo color("└─────────────────────────────────────────────────────────────┘\n", 'blue');
echo "\n";

$totalErrors = 0;
$totalWarnings = 0;
$modulesChecked = 0;
$modulesWithoutScope = [];

foreach ($modules as $moduleDir) {
    $moduleName = basename($moduleDir);
    $scopePath = $moduleDir . '/SCOPE.md';

    if (!file_exists($scopePath)) {
        $modulesWithoutScope[] = $moduleName;
        continue;
    }

    $fm = parseFrontmatter($scopePath);
    if ($fm === null) {
        echo color("✗ $moduleName — frontmatter inválido em SCOPE.md\n", 'red');
        $totalErrors++;
        continue;
    }

    $contains = $fm['contains'] ?? [];

    // Extrai filenames declarados em contains (procura por padrão "Controller" no item)
    $declaredControllers = [];
    foreach ($contains as $item) {
        if (preg_match('#((?:[A-Z][a-zA-Z0-9]*/)*[A-Z][a-zA-Z0-9]*Controller)#', $item, $m)) {
            $declaredControllers[] = $m[1];
        }
    }

    // Também aceita controllers em drift_alerts[] (transitório — outros módulos migrando PRA cá)
    // drift_alerts no YAML é lista de hash items; meu parser simplificado não decodifica,
    // então fallback: lê o arquivo e grep por linhas "controller: ..." dentro de drift_alerts
    $scopeContent = @file_get_contents($scopePath) ?: '';
    if (preg_match('/^drift_alerts:(.+?)(?=^[a-z_]+:|\z)/sm', $scopeContent, $dm)) {
        if (preg_match_all('/controller:\s*"([^"]+Controller)"/i', $dm[1], $dms)) {
            foreach ($dms[1] as $driftCtrl) {
                $declaredControllers[] = $driftCtrl;
            }
        }
    }

    // Lista controllers reais no filesystem
    $actualControllers = listControllers($moduleDir);

    // Filtra boilerplate
    $actualForCheck = array_filter($actualControllers, fn($c) => !in_array($c, $boilerplate));

    $undeclared = [];
    foreach ($actualForCheck as $ctrl) {
        // ctrl format: "Http/Controllers/Foo/BarController.php"
        // Convert to "Foo/BarController" or "BarController" pra match
        $shortName = preg_replace('#^Http/Controllers/#', '', $ctrl);
        $shortName = preg_replace('#Controller\.php$#', 'Controller', $shortName);

        $matches = false;
        foreach ($declaredControllers as $declared) {
            if ($declared === $shortName || str_ends_with($shortName, '/' . $declared)) {
                $matches = true;
                break;
            }
        }
        if (!$matches) {
            $undeclared[] = $shortName;
        }
    }

    $modulesChecked++;
    if (empty($undeclared)) {
        echo color("✓ $moduleName", 'green') . " — " . count($actualForCheck) . " controllers, todos em contains[]\n";
    } else {
        echo color("⚠ $moduleName", 'yellow') . " — " . count($undeclared) . " controller(s) não declarados:\n";
        foreach ($undeclared as $ctrl) {
            echo "    " . color("→ $ctrl", 'yellow') . "\n";
        }
        echo "    " . color("Adicione em SCOPE.md.contains[] OU mova pro módulo correto OU declare em drift_alerts[]", 'gray') . "\n";
        $totalWarnings += count($undeclared);
    }
}

echo "\n";
echo color("─────────────────────────────────────────────────────────────\n", 'blue');
echo "Módulos checados: $modulesChecked\n";

if (!empty($modulesWithoutScope)) {
    echo color("\nMódulos sem SCOPE.md (Fase 3.4 pendente):\n", 'gray');
    foreach (array_slice($modulesWithoutScope, 0, 10) as $m) {
        echo "  · $m\n";
    }
    if (count($modulesWithoutScope) > 10) {
        echo "  · ... +" . (count($modulesWithoutScope) - 10) . " outros\n";
    }
}

if ($totalErrors > 0) {
    echo color("\n✗ $totalErrors erros\n", 'red');
    exit(2);
}

if ($totalWarnings > 0) {
    echo color("\n⚠ $totalWarnings warnings\n", 'yellow');
    if ($strict) {
        echo color("Modo --strict: bloqueando.\n", 'red');
        exit(1);
    }
    echo color("Modo dev: passando (use --strict pra bloquear).\n", 'gray');
    exit(0);
}

echo color("\n✓ Tudo OK — nenhum drift detectado\n", 'green');
exit(0);
