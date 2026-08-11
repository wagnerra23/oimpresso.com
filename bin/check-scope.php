<?php
/**
 * GUARDA anti-drift — verifica se controllers em Modules/<X>/ estão declarados
 * em memory/requisitos/<X>/SCOPE.md (frontmatter `contains[]` ou `drift_alerts[]`).
 *
 * Constituição Art. 7 — Module Charter: controller fora de scope = drift bloqueado.
 *
 * DUAS DIREÇÕES, donos separados (a segunda nasceu em 2026-08-10):
 *   árvore → contains  (default/--strict) : controller REAL não declarado
 *   contains → árvore  (--declared)       : declarado mas AUSENTE — advisory
 *
 * Uso:
 *   php bin/check-scope.php                          # checa todos módulos
 *   php bin/check-scope.php --strict                 # exit 1 em qualquer warning
 *   php bin/check-scope.php --staged                 # só arquivos staged em git
 *   php bin/check-scope.php Modules/Copiloto         # checa módulo específico
 *   php bin/check-scope.php --declared               # contains[] sem lastro (advisory)
 *   php bin/check-scope.php --selftest               # prova que --declared morde/solta
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
$declaredOnly = in_array('--declared', $args, true);
$selftest = in_array('--selftest', $args, true);
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

// ─────────────────────────────────────────────────────────────────────────────
// DIREÇÃO INVERSA (`--declared`) — o `contains[]` declara algo que NÃO existe?
//
// O check acima (e o `--strict` que o scope-guard.yml roda) mede ÁRVORE → contains:
// "controller real não declarado". A direção oposta — contains → ÁRVORE, "declarado
// mas ausente" — não tinha dono, e é por onde o SCOPE apodrece calado. Caso-âncora
// confessado pelo próprio memory/requisitos/Jana/SCOPE.md sobre o `BriefController`:
//   "Ficou listado aqui por ~7 semanas depois de deixar de existir — nenhuma máquina
//    compara `contains` com a árvore, então o SCOPE apodreceu calado."
//
// ADVISORY POR CONSTRUÇÃO: roda só sob `--declared`, com exit próprio, e NÃO toca o
// fluxo do `--strict`. Gate novo nasce fora dos required (ADR 0275/0314/0336).
//
// EXCLUSÕES ESTRUTURAIS (nunca textuais — critério sintático sobre prosa é a família
// já reprovada 5× em proibicoes.md §5). Um item só é avaliado se o token inicial for
// identificador; fora isso o item é `prosa` ou `glob` e NÃO é acusado:
//   · glob   → token com `*` (`Services/Memoria/*`): não resolve 1:1 por construção
//   · prosa  → não casa /^[A-Za-z][\w\/.*-]*$/ (frase livre, tabela, nota)
//   · dir    → resolve a diretório real
//
// DOIS VEREDITOS, porque o custo de agir é diferente:
//   · FANTASMA  — não existe em lugar NENHUM do repo. É o BriefController. Acionável.
//   · FORA      — existe, mas sob outro módulo/app. Informativo: "está no módulo certo?"
//     é a pergunta do `--strict` (direção oposta), não desta. Reportar sem acusar
//     evita fabricar conflito entre os dois sentidos do mesmo gate.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Roots do índice de símbolos — FONTE ÚNICA (o `--declared` e o `--selftest` leem daqui).
 * Estavam duplicados; divergir fazia o selftest testar uma cobertura e o gate usar outra,
 * que é exatamente como o FP do FsmProcessoComunicacaoVisualSeeder passou verde.
 */
function scopeIndexRoots(): array {
    return ['Modules', 'app', 'database', 'routes', 'config'];
}

/** Índice basename→paths de todo .php sob os roots (sem vendor/node_modules). */
function indexPhpSymbols(array $roots): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $idx = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                fn($cur) => !in_array($cur->getFilename(), ['vendor', 'node_modules', '.git'], true)
            )
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $idx[$f->getBasename('.php')][] = str_replace('\\', '/', $f->getPathname());
            }
        }
    }
    return $cache = $idx;
}

/**
 * Classifica UM item de `contains[]`. PURO — o `--selftest` exercita isto, nunca o console.
 * @return array{status:string,token:string,achado?:string}
 *   status ∈ ok | dir | glob | prosa | fora | fantasma
 */
function classifyContainsItem(string $item, string $moduleDir, array $symbolIndex): array {
    // token = até o primeiro separador de prosa (travessão, parêntese, vírgula)
    $parts = preg_split('/\s+[—–]\s+|\s+\(|,\s/u', $item);
    $token = trim(str_replace('`', '', $parts[0] ?? ''));

    if ($token === '' || !preg_match('#^[A-Za-z][A-Za-z0-9_/.*-]*$#', $token)) {
        return ['status' => 'prosa', 'token' => $token];
    }
    if (str_contains($token, '*')) {
        return ['status' => 'glob', 'token' => $token];
    }

    $base = rtrim($token, '/');
    // 1) resolve dentro do próprio módulo (arquivo ou diretório)?
    if (is_dir($moduleDir . '/' . $base))            return ['status' => 'dir',  'token' => $token];
    if (is_file($moduleDir . '/' . $base . '.php'))  return ['status' => 'ok',   'token' => $token];

    // Token com EXTENSÃO explícita (`LICOES-OPERACAO.md`, `foo.json`) resolve como
    // arquivo literal — nunca pelo índice, que só contém .php. Sem isto, TODO item
    // não-php declarado vira fantasma mesmo existindo: falso-positivo pego pelo CI
    // em 2026-08-10 (Jana/LICOES-OPERACAO.md existe e foi acusado).
    if (preg_match('/\.[A-Za-z0-9]+$/', $base) && !str_ends_with($base, '.php')) {
        if (is_file($moduleDir . '/' . $base)) return ['status' => 'ok', 'token' => $token];
        // ADR 0374: doc do modulo mora em memory/requisitos/<X>/ desde 2026-08-10.
        // Sem este ramo, todo item .md declarado vira fantasma — o SCOPE saiu junto.
        if (is_file('memory/requisitos/' . basename($moduleDir) . '/' . $base)) {
            return ['status' => 'ok', 'token' => $token];
        }
        foreach (glob($moduleDir . '/*/' . $base) ?: [] as $_) return ['status' => 'ok', 'token' => $token];
        return ['status' => 'fantasma', 'token' => $token];
    }

    $leaf = basename($base);
    foreach ($symbolIndex[$leaf] ?? [] as $p) {
        if (str_starts_with($p, str_replace('\\', '/', $moduleDir) . '/')) {
            return ['status' => 'ok', 'token' => $token];
        }
    }
    // 2) existe em outro lugar do repo? informativo, não acusação
    if (!empty($symbolIndex[$leaf])) {
        return ['status' => 'fora', 'token' => $token, 'achado' => $symbolIndex[$leaf][0]];
    }
    // 3) não existe em lugar nenhum → fantasma
    return ['status' => 'fantasma', 'token' => $token];
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

if ($selftest) {
    $fails = 0;
    $ok = function (string $name, bool $cond) use (&$fails) {
        echo '  ' . ($cond ? '[OK]' : '[FAIL]') . " $name\n";
        if (!$cond) $fails++;
    };
    // Índice INJETADO — o contrato é a função pura, não a árvore do dia.
    $idx = [
        'SkillsService'  => ['Modules/Jana/Services/SkillsService.php'],
        'CobrancaController' => ['Modules/Financeiro/Http/Controllers/CobrancaController.php'],
    ];
    $M = 'Modules/Jana';

    // BITE — declarado e ausente em TODO o repo => fantasma (é o caso BriefController).
    $ok('BITE: classe declarada que não existe em lugar nenhum → fantasma',
        classifyContainsItem('BriefController — stub do brief', $M, $idx)['status'] === 'fantasma');
    $ok('BITE: fantasma vale mesmo com prosa explicativa ao lado (prosa não desarma)',
        classifyContainsItem('BriefController — removido, mantido por compat', $M, $idx)['status'] === 'fantasma');

    // CONTROLE NEGATIVO — o que é legítimo NÃO pode ser acusado.
    $ok('CN: classe real do próprio módulo → ok',
        classifyContainsItem('Services/SkillsService — lê catálogo', $M, $idx)['status'] === 'ok');
    $ok('CN: glob (`Services/Memoria/*`) → glob, nunca fantasma',
        classifyContainsItem('Services/Memoria/* — recall hybrid', $M, $idx)['status'] === 'glob');
    $ok('CN: prosa livre (não-identificador) → prosa, nunca fantasma',
        classifyContainsItem('⚠️ MOVIDO, NÃO FUNDIDO: as abas sobrepõem', $M, $idx)['status'] === 'prosa');
    $ok('CN: item que resolve a DIRETÓRIO real → dir, nunca fantasma',
        classifyContainsItem('Database/Migrations — tabela x', 'Modules/Jana', $idx)['status'] === 'dir');
    $ok('CN: classe que existe sob OUTRO módulo → fora (informativo), nunca fantasma',
        classifyContainsItem('CobrancaController', 'Modules/PaymentGateway', $idx)['status'] === 'fora');

    // CN + BITE do vetor não-php (FP real, pego pelo CI em 2026-08-10): o índice só
    // tem .php, então sem tratamento próprio TODO item .md/.json vira fantasma.
    $ok('CN: arquivo .md que EXISTE no módulo → ok (não fantasma)',
        classifyContainsItem('LICOES-OPERACAO.md — ledger append-only', 'memory/requisitos/Jana', $idx)['status'] === 'ok');
    $ok('BITE: arquivo .md que NÃO existe segue fantasma (o fix não cega o detector)',
        classifyContainsItem('NAO-EXISTE-XYZ.md — ledger', 'memory/requisitos/Jana', $idx)['status'] === 'fantasma');

    // ── COBERTURA DO ÍNDICE — o eixo que o FP real explorou ────────────────────────
    // Os asserts de classificação INJETAM o índice, então nenhum deles exercita
    // indexPhpSymbols(). Mutação medida: cegar o índice (tirar `app`, ou tirar tudo
    // menos `app`) passava 12/12 VERDE. É por esse buraco que o gate acusou o
    // FsmProcessoComunicacaoVisualSeeder, que existe em database/seeders/.
    $real = indexPhpSymbols(scopeIndexRoots());
    $ok('COBERTURA: índice alcança database/ (âncora: FsmProcessoComunicacaoVisualSeeder existe lá)',
        !empty($real['FsmProcessoComunicacaoVisualSeeder']));
    $ok('COBERTURA: índice alcança Modules/ (mutação que cega Modules não passa)',
        !empty($real['SkillsService']));
    $ok('COBERTURA: índice alcança app/ (mutação que tira app não passa)',
        !empty($real['ServiceOrderFsmActionController']));

    // ÂNCORAS DE CONTRATO — as premissas têm que ser verdade no repo AGORA.
    $ok('contrato: Modules/Jana/Services/SkillsService.php existe (âncora do CN "ok")',
        is_file('Modules/Jana/Services/SkillsService.php'));
    $ok('contrato: nenhum ConversationsController no repo (âncora do achado real Whatsapp)',
        empty($real['ConversationsController']));
    $ok('contrato: Modules/Jana/LICOES-OPERACAO.md existe (âncora do CN não-php)',
        is_file('memory/requisitos/Jana/LICOES-OPERACAO.md'));

    echo $fails
        ? "\n  $fails FALHA(S) — a direção contains→árvore não está honesta.\n"
        : "\n  SELFTEST OK — morde (fantasma) e solta (ok/dir/glob/prosa/fora).\n";
    exit($fails ? 1 : 0);
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

if ($declaredOnly) {
    echo color("┌─────────────────────────────────────────────────────────────┐\n", 'blue');
    echo color("│  contains[] → ÁRVORE — declarado mas ausente (advisory)     │\n", 'blue');
    echo color("└─────────────────────────────────────────────────────────────┘\n", 'blue');
    echo "\n";

    // ROOTS: `Modules` + `app` NÃO bastam. Ativo legítimo de módulo mora fora deles —
    // seeder de FSM canon vive em `database/seeders/` por convenção (4 lá hoje). Com o
    // índice cego, ele era classificado `fantasma` em vez de `fora`, e o gate acusava
    // código REAL: aconteceu com FsmProcessoComunicacaoVisualSeeder (294 linhas, criado
    // em #676, exercitado por 6 pontos do Tier0GuardTest) e um PR chegou a apagar a
    // declaração correta por obedecer ao veredito. Medido ao ampliar: flipa exatamente
    // 1 item, `fantasma → fora`, zero FP novo.
    $symbolIndex = indexPhpSymbols(scopeIndexRoots());
    $fantasmas = [];
    $fora = [];
    $tally = ['ok' => 0, 'dir' => 0, 'glob' => 0, 'prosa' => 0, 'fora' => 0, 'fantasma' => 0];
    $itens = 0;
    $mods = 0;

    foreach ($modules as $moduleDir) {
        $scopePath = 'memory/requisitos/' . basename($moduleDir) . '/SCOPE.md';
        if (!file_exists($scopePath)) continue;
        $fm = parseFrontmatter($scopePath);
        if ($fm === null) continue;
        $mods++;
        foreach (($fm['contains'] ?? []) as $item) {
            $itens++;
            $r = classifyContainsItem($item, $moduleDir, $symbolIndex);
            $tally[$r['status']]++;
            if ($r['status'] === 'fantasma') $fantasmas[] = [basename($moduleDir), $r['token'], $item];
            if ($r['status'] === 'fora')     $fora[]      = [basename($moduleDir), $r['token'], $r['achado']];
        }
    }

    foreach ($fantasmas as [$mod, $tok, $item]) {
        echo color("  👻 $mod", 'yellow') . " — declara \"$tok\" que NÃO existe em Modules/ nem app/\n";
        echo "       " . color(substr($item, 0, 96), 'gray') . "\n";
    }
    if (!empty($fora)) {
        echo "\n";
        foreach ($fora as [$mod, $tok, $achado]) {
            echo color("  ↗ $mod", 'gray') . " — declara \"$tok\", que existe em $achado\n";
        }
        echo "    " . color("(informativo: \"está no módulo certo?\" é a direção do --strict, não desta)", 'gray') . "\n";
    }

    echo "\n";
    echo color("─────────────────────────────────────────────────────────────\n", 'blue');
    echo "Módulos: $mods · itens de contains[]: $itens\n";
    echo "  resolvem: {$tally['ok']} arquivo · {$tally['dir']} diretório\n";
    echo "  não avaliados (estrutural): {$tally['glob']} glob · {$tally['prosa']} prosa livre\n";
    echo "  " . color("FANTASMA: {$tally['fantasma']}", $tally['fantasma'] ? 'yellow' : 'green')
        . " · fora do módulo: {$tally['fora']}\n";

    if ($tally['fantasma'] > 0) {
        echo color("\n⚠ {$tally['fantasma']} item(ns) declarado(s) sem lastro na árvore.\n", 'yellow');
        echo color("  Conserte o SCOPE (remova ou aponte pro nome real). Se for capacidade PLANEJADA,\n", 'gray');
        echo color("  ela não pertence a contains[] (que afirma o presente) — leve pro roadmap do módulo.\n", 'gray');
        echo color("  ADVISORY: não bloqueia merge (ADR 0275/0314 — gate novo nasce fora dos required).\n", 'gray');
        exit(1);
    }
    echo color("\n✓ Nenhum item de contains[] sem lastro na árvore.\n", 'green');
    exit(0);
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
    $scopePath = 'memory/requisitos/' . basename($moduleDir) . '/SCOPE.md';

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
