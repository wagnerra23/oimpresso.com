<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Inspeciona profundamente um módulo nwidart/laravel-modules e devolve uma
 * spec estruturada (array + markdown).
 *
 * Objetivo: reconhecer TUDO que existe (rotas, controllers, views, migrations,
 * permissões, hooks) pra gerar um roadmap seguro de migração/refactor.
 *
 * NÃO executa código — só lê arquivos. Zero side-effects.
 */
class ModuleSpecGenerator
{
    public function __construct(protected ModuleManagerService $manager)
    {
    }

    public function inspect(string $name): array
    {
        $path = base_path("Modules/{$name}");
        $existsInCurrentBranch = File::isDirectory($path);

        $spec = [
            'name'        => $name,
            'path'        => $path,
            'exists_in_current' => $existsInCurrentBranch,
            'module_json' => $existsInCurrentBranch ? $this->readModuleJson($path) : [],
            'composer'    => $existsInCurrentBranch ? $this->readComposerJson($path) : [],
            'routes'      => $existsInCurrentBranch ? $this->scanRoutes($path) : ['all' => [], 'files' => []],
            'controllers' => $existsInCurrentBranch ? $this->scanControllers($path) : [],
            'entities'    => $existsInCurrentBranch ? $this->scanEntities($path) : [],
            'migrations'  => $existsInCurrentBranch ? $this->scanMigrations($path) : [],
            'views'       => $existsInCurrentBranch ? $this->scanViews($path) : ['count' => 0, 'top_dirs' => []],
            'services'    => $existsInCurrentBranch ? $this->scanServices($path) : [],
            'requests'    => $existsInCurrentBranch ? $this->scanRequests($path) : [],
            'middleware'  => $existsInCurrentBranch ? $this->scanMiddleware($path) : [],
            'providers'   => $existsInCurrentBranch ? $this->scanProviders($path) : [],
            'data_controller_hooks' => $existsInCurrentBranch ? $this->scanDataControllerHooks($path) : [],
            'upos_hooks'  => $existsInCurrentBranch ? $this->scanUposHooks($path) : [],
            'permissions' => $existsInCurrentBranch ? $this->scanPermissions($path) : ['registered' => [], 'used_in_blade' => []],
            'jobs'        => $existsInCurrentBranch ? $this->scanJobs($path) : [],
            'commands'    => $existsInCurrentBranch ? $this->scanCommands($path) : [],
            'events'      => $existsInCurrentBranch ? $this->scanEvents($path) : [],
            'listeners'   => $existsInCurrentBranch ? $this->scanListeners($path) : [],
            'observers'   => $existsInCurrentBranch ? $this->scanObservers($path) : [],
            'policies'    => $existsInCurrentBranch ? $this->scanPolicies($path) : [],
            'notifications' => $existsInCurrentBranch ? $this->scanNotifications($path) : [],
            'factories'   => $existsInCurrentBranch ? $this->scanFactories($path) : [],
            'seeders'     => $existsInCurrentBranch ? $this->scanSeeders($path) : [],
            'config'      => $existsInCurrentBranch ? $this->scanConfig($path) : [],
            'cross_deps'  => $existsInCurrentBranch ? $this->scanCrossDeps($path, $name) : [],
            'db_integrity'=> $existsInCurrentBranch ? $this->scanDatabaseIntegrity($path) : ['foreign_keys' => [], 'triggers' => [], 'unique_indexes' => 0],
            'lang_files'  => $existsInCurrentBranch ? $this->scanLang($path) : [],
            'assets'      => $existsInCurrentBranch ? $this->scanAssets($path) : [
                'js' => 0, 'ts' => 0, 'vue' => 0, 'css' => 0, 'img' => 0,
                'js_files' => [], 'css_files' => [], 'frameworks' => [],
                'has_mix' => false, 'has_vite' => false, 'has_package' => false,
                'package_deps' => [],
            ],
            'tests'       => $existsInCurrentBranch ? $this->scanTests($path) : [],
            'branch_presence' => $this->checkBranchPresence($name),
            'git_changes' => $this->scanGitChanges($name),
            'signals'     => [],
        ];

        $spec['signals'] = $this->extractSignals($spec);

        return $spec;
    }

    /**
     * Verifica em quais branches do git o módulo existe.
     * Branches de interesse: current (working dir), main-wip-2026-04-22, origin/3.7-com-nfe.
     */
    protected function checkBranchPresence(string $name): array
    {
        $branches = ['main-wip-2026-04-22', 'origin/3.7-com-nfe', 'origin/6.7-bootstrap'];
        $out = [
            'current' => File::isDirectory(base_path("Modules/{$name}")),
        ];
        foreach ($branches as $br) {
            $out[$br] = $this->runGit(['ls-tree', '-d', '--name-only', $br, "Modules/{$name}"]) !== '';
        }
        return $out;
    }

    /**
     * Diff do módulo entre branch atual e branches relevantes.
     * Retorna contagem de arquivos alterados + primeiros N nomes.
     */
    protected function scanGitChanges(string $name): array
    {
        $result = [];
        $path = "Modules/{$name}";

        // vs 3.7 (origem)
        $diff37 = $this->runGit(['diff', '--stat', 'origin/3.7-com-nfe...HEAD', '--', $path]);
        $result['vs_3.7'] = [
            'summary' => $this->parseDiffStat($diff37),
            'files'   => $this->listChangedFiles('origin/3.7-com-nfe', 'HEAD', $path),
        ];

        // vs main-wip (backup customizações Wagner)
        $diffWip = $this->runGit(['diff', '--stat', 'main-wip-2026-04-22...HEAD', '--', $path]);
        $result['vs_main_wip'] = [
            'summary' => $this->parseDiffStat($diffWip),
            'files'   => $this->listChangedFiles('main-wip-2026-04-22', 'HEAD', $path),
        ];

        return $result;
    }

    protected function runGit(array $args): string
    {
        $cmd = escapeshellarg(base_path()) . ' --git-dir=' . escapeshellarg(base_path() . '/.git');
        $safe = array_map('escapeshellarg', $args);
        $nullDev = stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null';
        $full = 'git -C ' . escapeshellarg(base_path()) . ' ' . implode(' ', $safe) . ' 2>' . $nullDev;
        $out = shell_exec($full);
        return trim((string) $out);
    }

    protected function parseDiffStat(string $diffStat): array
    {
        if ($diffStat === '') {
            return ['files' => 0, 'insertions' => 0, 'deletions' => 0];
        }
        // última linha geralmente: " X files changed, Y insertions(+), Z deletions(-)"
        $lines = explode("\n", $diffStat);
        $summary = trim(end($lines));
        $files = 0; $ins = 0; $del = 0;
        if (preg_match('/(\d+)\s+files?\s+changed/', $summary, $m)) $files = (int) $m[1];
        if (preg_match('/(\d+)\s+insertions?/', $summary, $m)) $ins = (int) $m[1];
        if (preg_match('/(\d+)\s+deletions?/', $summary, $m)) $del = (int) $m[1];
        return ['files' => $files, 'insertions' => $ins, 'deletions' => $del];
    }

    protected function listChangedFiles(string $from, string $to, string $path): array
    {
        $out = $this->runGit(['diff', '--name-only', "{$from}...{$to}", '--', $path]);
        if ($out === '') return [];
        return array_slice(array_filter(explode("\n", $out)), 0, 30);
    }

    public function renderMarkdown(array $spec): string
    {
        $name = $spec['name'];
        $m    = $spec['module_json'] ?? [];
        $sig  = $spec['signals'];
        $v    = $m['version'] ?? '?';
        $desc = $m['description'] ?? '—';
        $alias = $m['alias'] ?? '—';

        $md  = "# Módulo: {$name}\n\n";
        $md .= "> **" . ($desc) . "**\n\n";
        $md .= "- **Alias:** `{$alias}`\n";
        $md .= "- **Versão:** {$v}\n";
        $md .= "- **Path:** `{$spec['path']}`\n";
        $md .= "- **Status:** " . ($sig['active'] ? '🟢 ativo' : '⚪ inativo') . "\n";
        $md .= "- **Providers:** " . (empty($m['providers']) ? '—' : implode(', ', $m['providers'])) . "\n";
        $md .= "- **Requires (módulo.json):** " . (empty($m['requires']) ? 'nenhum' : implode(', ', $m['requires'])) . "\n\n";

        // Signals / riscos
        $md .= "## Sinais detectados\n\n";
        foreach ($sig['flags'] as $flag) {
            $md .= "- {$flag}\n";
        }
        $md .= "\n- **Prioridade sugerida de migração:** " . $sig['migration_priority'] . "\n";
        $md .= "- **Risco estimado:** " . $sig['risk'] . "\n\n";

        // Escopo
        $md .= "## Escopo\n\n";
        $md .= "| Peça | Qtde |\n|---|---:|\n";
        $md .= "| Rotas (web+api) | " . count($spec['routes']['all']) . " |\n";
        $md .= "| Controllers | " . count($spec['controllers']) . " |\n";
        $md .= "| Entities (Models) | " . count($spec['entities']) . " |\n";
        $md .= "| Services | " . count($spec['services']) . " |\n";
        $md .= "| FormRequests | " . count($spec['requests']) . " |\n";
        $md .= "| Middleware | " . count($spec['middleware']) . " |\n";
        $md .= "| Views Blade | " . $spec['views']['count'] . " |\n";
        $md .= "| Migrations | " . count($spec['migrations']) . " |\n";
        $md .= "| Arquivos de lang | " . count($spec['lang_files']) . " |\n";
        $md .= "| Testes | " . count($spec['tests']) . " |\n\n";

        // Rotas
        if (!empty($spec['routes']['all'])) {
            $md .= "## Rotas\n\n";
            foreach ($spec['routes']['files'] as $file => $routes) {
                $md .= "### `" . basename($file) . "`\n\n";
                if (empty($routes)) {
                    $md .= "_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_\n\n";
                    continue;
                }
                $md .= "| Método | URI | Controller |\n|---|---|---|\n";
                foreach (array_slice($routes, 0, 40) as $r) {
                    $md .= "| `" . $r['method'] . "` | `" . $r['uri'] . "` | `" . $r['action'] . "` |\n";
                }
                if (count($routes) > 40) {
                    $md .= "\n_... +" . (count($routes) - 40) . " rotas_\n";
                }
                $md .= "\n";
            }
        }

        // Controllers
        if (!empty($spec['controllers'])) {
            $md .= "## Controllers\n\n";
            foreach ($spec['controllers'] as $c) {
                $md .= "- **`{$c['class']}`** — " . count($c['actions']) . " ação(ões): " . implode(', ', array_slice($c['actions'], 0, 8));
                if (count($c['actions']) > 8) $md .= " +" . (count($c['actions']) - 8);
                $md .= "\n";
            }
            $md .= "\n";
        }

        // Entities
        if (!empty($spec['entities'])) {
            $md .= "## Entities (Models Eloquent)\n\n";
            foreach ($spec['entities'] as $e) {
                $table = $e['table'] ?? '—';
                $md .= "- **`{$e['class']}`** (tabela: `{$table}`)\n";
            }
            $md .= "\n";
        }

        // Migrations
        if (!empty($spec['migrations'])) {
            $md .= "## Migrations\n\n";
            foreach ($spec['migrations'] as $mig) {
                $md .= "- `" . basename($mig) . "`\n";
            }
            $md .= "\n";
        }

        // Views (top-level dirs)
        if ($spec['views']['count'] > 0) {
            $md .= "## Views (Blade)\n\n";
            $md .= "**Total:** " . $spec['views']['count'] . " arquivos\n\n";
            if (!empty($spec['views']['top_dirs'])) {
                $md .= "**Pastas principais:**\n\n";
                foreach ($spec['views']['top_dirs'] as $dir => $n) {
                    $md .= "- `{$dir}/` — {$n} arquivo(s)\n";
                }
                $md .= "\n";
            }
        }

        // Hooks DataController (reconhecidos do UltimatePOS)
        if (!empty($spec['upos_hooks'])) {
            $md .= "## Hooks UltimatePOS registrados\n\n";
            foreach ($spec['upos_hooks'] as $hook) {
                $md .= "- **`{$hook['method']}()`** — {$hook['description']}\n";
            }
            $md .= "\n";
        } elseif (!empty($spec['data_controller_hooks'])) {
            $md .= "## Hooks no DataController\n\n";
            foreach ($spec['data_controller_hooks'] as $hook) {
                $md .= "- `{$hook}()`\n";
            }
            $md .= "\n";
        }

        // Permissões
        $perms = $spec['permissions'] ?? [];
        if (!empty($perms['registered']) || !empty($perms['used_in_blade'])) {
            $md .= "## Permissões\n\n";
            if (!empty($perms['registered'])) {
                $md .= "**Registradas pelo módulo** (via `user_permissions()`):\n\n";
                foreach ($perms['registered'] as $p) $md .= "- `{$p}`\n";
                $md .= "\n";
            }
            if (!empty($perms['used_in_blade'])) {
                $md .= "**Usadas nas views** (`@can`/`@cannot`):\n\n";
                foreach (array_slice($perms['used_in_blade'], 0, 20) as $p) $md .= "- `{$p}`\n";
                if (count($perms['used_in_blade']) > 20) {
                    $md .= "- _... +" . (count($perms['used_in_blade']) - 20) . " permissões_\n";
                }
                $md .= "\n";
            }
        }

        // Processamento assíncrono
        $hasAsync = !empty($spec['jobs']) || !empty($spec['commands']) || !empty($spec['events']) || !empty($spec['listeners']) || !empty($spec['observers']);
        if ($hasAsync) {
            $md .= "## Processamento / eventos\n\n";
            if (!empty($spec['jobs'])) {
                $md .= "**Jobs (queue):** " . implode(', ', array_map(fn($j) => "`{$j}`", $spec['jobs'])) . "\n\n";
            }
            if (!empty($spec['commands'])) {
                $md .= "**Commands (artisan):** " . implode(', ', array_map(fn($c) => "`{$c}`", $spec['commands'])) . "\n\n";
            }
            if (!empty($spec['events'])) {
                $md .= "**Events:** " . implode(', ', array_map(fn($e) => "`{$e}`", $spec['events'])) . "\n\n";
            }
            if (!empty($spec['listeners'])) {
                $md .= "**Listeners:** " . implode(', ', array_map(fn($l) => "`{$l}`", $spec['listeners'])) . "\n\n";
            }
            if (!empty($spec['observers'])) {
                $md .= "**Observers:** " . implode(', ', array_map(fn($o) => "`{$o}`", $spec['observers'])) . "\n\n";
            }
        }

        // Outras peças
        $extras = [];
        if (!empty($spec['policies']))      $extras[] = "**Policies:** " . implode(', ', array_map(fn($p) => "`{$p}`", $spec['policies']));
        if (!empty($spec['notifications'])) $extras[] = "**Notifications:** " . implode(', ', array_map(fn($n) => "`{$n}`", $spec['notifications']));
        if (!empty($spec['factories']))     $extras[] = "**Factories:** " . count($spec['factories']) . " (" . implode(', ', array_map(fn($f) => "`{$f}`", array_slice($spec['factories'], 0, 5))) . ")";
        if (!empty($spec['seeders']))       $extras[] = "**Seeders:** " . implode(', ', array_map(fn($s) => "`{$s}`", $spec['seeders']));
        if (!empty($extras)) {
            $md .= "## Peças adicionais\n\n";
            foreach ($extras as $line) $md .= "- {$line}\n";
            $md .= "\n";
        }

        // Config settings
        $config = $spec['config'] ?? [];
        if (!empty($config)) {
            $md .= "## Configuração (`Config/config.php`)\n\n";
            $md .= "| Chave | Valor |\n|---|---|\n";
            foreach ($config as $k => $v) {
                $md .= "| `{$k}` | `" . str_replace('|', '\\|', (string) $v) . "` |\n";
            }
            $md .= "\n";
        }

        // Dependências cross-module
        $crossDeps = $spec['cross_deps'] ?? [];
        if (!empty($crossDeps)) {
            $md .= "## Dependências cross-module detectadas\n\n";
            $md .= "_Referências a outros módulos encontradas no código PHP._\n\n";
            $md .= "| Módulo referenciado | Ocorrências |\n|---|---:|\n";
            foreach ($crossDeps as $mod => $count) {
                $md .= "| `{$mod}` | {$count} |\n";
            }
            $md .= "\n";
        }

        // Integridade de BD
        $db = $spec['db_integrity'] ?? [];
        $hasDb = !empty($db['foreign_keys']) || !empty($db['triggers']) || ($db['unique_indexes'] ?? 0) > 0;
        if ($hasDb) {
            $md .= "## Integridade do banco\n\n";
            if (!empty($db['foreign_keys'])) {
                $md .= "**Foreign Keys** (" . count($db['foreign_keys']) . "):\n\n";
                foreach (array_slice($db['foreign_keys'], 0, 20) as $fk) {
                    $md .= "- `{$fk['column']}` → `{$fk['on']}.{$fk['references']}`\n";
                }
                if (count($db['foreign_keys']) > 20) {
                    $md .= "- _... +" . (count($db['foreign_keys']) - 20) . " FKs_\n";
                }
                $md .= "\n";
            }
            if (!empty($db['triggers'])) {
                $md .= "**Triggers MySQL** (" . count($db['triggers']) . "): " . implode(', ', array_map(fn($t) => "`{$t}`", $db['triggers'])) . "\n\n";
            }
            if (($db['unique_indexes'] ?? 0) > 0) {
                $md .= "**Unique indexes:** {$db['unique_indexes']}\n\n";
            }
        }

        // Assets / dependências composer
        if (!empty($spec['composer']['require'] ?? [])) {
            $md .= "## Dependências Composer\n\n";
            foreach ($spec['composer']['require'] as $pkg => $ver) {
                $md .= "- `{$pkg}` {$ver}\n";
            }
            $md .= "\n";
        }

        // Assets (JS, CSS, frameworks)
        $assets = $spec['assets'] ?? [];
        if (!empty($assets['js_files']) || !empty($assets['css_files']) || !empty($assets['frameworks']) || $assets['has_mix'] || $assets['has_vite']) {
            $md .= "## Assets (JS / CSS)\n\n";
            $md .= "| Tipo | Qtde |\n|---|---:|\n";
            $md .= "| JavaScript (.js/.mjs) | " . ($assets['js'] ?? 0) . " |\n";
            $md .= "| TypeScript (.ts) | " . ($assets['ts'] ?? 0) . " |\n";
            $md .= "| Vue SFC (.vue) | " . ($assets['vue'] ?? 0) . " |\n";
            $md .= "| CSS/SCSS | " . ($assets['css'] ?? 0) . " |\n";
            $md .= "| Imagens | " . ($assets['img'] ?? 0) . " |\n\n";

            if ($assets['has_mix']) $md .= "- Build: **Laravel Mix** (webpack.mix.js presente)\n";
            if ($assets['has_vite']) $md .= "- Build: **Vite** (vite.config.js/ts presente)\n";
            if ($assets['has_package']) $md .= "- `package.json` presente\n";
            if (!empty($assets['package_deps'])) {
                $md .= "- **Deps JS:** `" . implode('`, `', array_slice($assets['package_deps'], 0, 15)) . "`";
                if (count($assets['package_deps']) > 15) $md .= " +" . (count($assets['package_deps']) - 15);
                $md .= "\n";
            }
            $md .= "\n";

            if (!empty($assets['frameworks'])) {
                $md .= "**Frameworks/libs detectados no JS:** " . implode(', ', $assets['frameworks']) . "\n\n";
            }

            if (!empty($assets['js_files'])) {
                $md .= "**Arquivos JS** (primeiros " . count($assets['js_files']) . "):\n\n";
                foreach ($assets['js_files'] as $f) {
                    $md .= "- `{$f['path']}` (" . $this->formatSize($f['size']) . ")\n";
                }
                $md .= "\n";
            }
            if (!empty($assets['css_files'])) {
                $md .= "**Arquivos CSS/SCSS** (primeiros " . count($assets['css_files']) . "):\n\n";
                foreach ($assets['css_files'] as $f) {
                    $md .= "- `{$f['path']}` (" . $this->formatSize($f['size']) . ")\n";
                }
                $md .= "\n";
            }
        }

        // Presença em branches
        $pres = $spec['branch_presence'] ?? [];
        if (!empty($pres)) {
            $md .= "## Presença em branches\n\n";
            $md .= "| Branch | Presente |\n|---|:-:|\n";
            $md .= "| atual (6.7-react) | " . ($pres['current'] ?? false ? '✅' : '❌') . " |\n";
            $md .= "| `main-wip-2026-04-22` (backup Wagner) | " . ($pres['main-wip-2026-04-22'] ?? false ? '✅' : '❌') . " |\n";
            $md .= "| `origin/3.7-com-nfe` (versão antiga) | " . ($pres['origin/3.7-com-nfe'] ?? false ? '✅' : '❌') . " |\n";
            $md .= "| `origin/6.7-bootstrap` | " . ($pres['origin/6.7-bootstrap'] ?? false ? '✅' : '❌') . " |\n\n";
        }

        // Git changes (diffs)
        $git = $spec['git_changes'] ?? [];
        if (!empty($git)) {
            $md .= "## Diferenças vs versões anteriores\n\n";

            $s37 = $git['vs_3.7']['summary'] ?? null;
            if ($s37 && $s37['files'] > 0) {
                $md .= "### vs `origin/3.7-com-nfe`\n\n";
                $md .= "- **Arquivos alterados:** {$s37['files']}\n";
                $md .= "- **Linhas +:** {$s37['insertions']} **-:** {$s37['deletions']}\n";
                if (!empty($git['vs_3.7']['files'])) {
                    $md .= "- **Primeiros arquivos alterados:**\n";
                    foreach ($git['vs_3.7']['files'] as $f) {
                        $md .= "  - `" . str_replace('Modules/' . $spec['name'] . '/', '', $f) . "`\n";
                    }
                }
                $md .= "\n";
            }

            $sWip = $git['vs_main_wip']['summary'] ?? null;
            if ($sWip && $sWip['files'] > 0) {
                $md .= "### vs `main-wip-2026-04-22` (backup das customizações)\n\n";
                $md .= "- **Arquivos alterados:** {$sWip['files']}\n";
                $md .= "- **Linhas +:** {$sWip['insertions']} **-:** {$sWip['deletions']}\n";
                $md .= "- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**\n";
                foreach ($git['vs_main_wip']['files'] as $f) {
                    $md .= "  - `" . str_replace('Modules/' . $spec['name'] . '/', '', $f) . "`\n";
                }
                $md .= "\n";
            }
        }

        // Gaps / TODOs
        $md .= "## Gaps & próximos passos (preencher manualmente)\n\n";
        $md .= "- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)\n";
        $md .= "- [ ] Listar bugs conhecidos no módulo\n";
        $md .= "- [ ] Priorizar telas para migração React\n";
        $md .= "- [ ] Marcar rotas que devem virar Inertia\n";
        $md .= "\n";

        $md .= "---\n";
        $md .= "**Gerado automaticamente por `ModuleSpecGenerator` em " . now()->format('Y-m-d H:i') . ".**\n";
        $md .= "**Reaxecutar com:** `php artisan module:spec " . $name . "`\n";

        return $md;
    }

    // ========================================================================
    // Scanners
    // ========================================================================

    protected function readModuleJson(string $path): array
    {
        $file = $path . '/module.json';
        if (!File::exists($file)) return [];
        return json_decode(File::get($file), true) ?? [];
    }

    protected function readComposerJson(string $path): array
    {
        $file = $path . '/composer.json';
        if (!File::exists($file)) return [];
        return json_decode(File::get($file), true) ?? [];
    }

    protected function scanRoutes(string $path): array
    {
        $candidates = [
            'Http/routes.php',
            'Routes/web.php',
            'Routes/api.php',
            'Routes/install.php',
            'Http/routes/web.php',
        ];
        $files = [];
        $all = [];
        foreach ($candidates as $rel) {
            $abs = $path . '/' . $rel;
            if (!File::exists($abs)) continue;
            $content = File::get($abs);
            $parsed = $this->parseRoutesContent($content);
            $files[$rel] = $parsed;
            $all = array_merge($all, $parsed);
        }
        return ['all' => $all, 'files' => $files];
    }

    /**
     * Parse simples: busca Route::get/post/put/delete/patch/any/resource + URI + action.
     * Não é perfeito pra grupos complexos mas dá uma visão 80%.
     */
    protected function parseRoutesContent(string $src): array
    {
        $routes = [];

        // Route::METHOD('uri', ...)
        if (preg_match_all('/Route::(get|post|put|patch|delete|any|match)\s*\(\s*[\'"]([^\'"]*)[\'"]\s*,\s*(.+?)\)/ms', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $action = trim($hit[3]);
                $action = str_replace(["\n", "\r"], ' ', $action);
                $action = preg_replace('/\s+/', ' ', $action);
                $routes[] = [
                    'method' => strtoupper($hit[1]),
                    'uri'    => $hit[2],
                    'action' => mb_substr($action, 0, 120),
                ];
            }
        }

        // Route::resource('uri', Controller)
        if (preg_match_all('/Route::resource\s*\(\s*[\'"]([^\'"]*)[\'"]\s*,\s*([^,\)]+)/m', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $routes[] = [
                    'method' => 'RESOURCE',
                    'uri'    => $hit[1],
                    'action' => trim($hit[2]),
                ];
            }
        }

        return $routes;
    }

    protected function scanControllers(string $path): array
    {
        $dir = $path . '/Http/Controllers';
        if (!File::isDirectory($dir)) return [];

        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') continue;
            try {
                $content = $file->getContents();
            } catch (Throwable $e) {
                continue;
            }
            if (!preg_match('/class\s+(\w+)/', $content, $m)) continue;
            $className = $m[1];
            // actions públicos
            preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $am);
            $actions = array_filter($am[1] ?? [], fn ($a) => !in_array($a, ['__construct', '__invoke', 'middleware']));
            $out[] = ['class' => $className, 'actions' => array_values($actions)];
        }
        return $out;
    }

    protected function scanEntities(string $path): array
    {
        $dir = $path . '/Entities';
        if (!File::isDirectory($dir)) return [];
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') continue;
            try {
                $content = $file->getContents();
            } catch (Throwable $e) {
                continue;
            }
            if (!preg_match('/class\s+(\w+)/', $content, $m)) continue;
            $className = $m[1];
            $table = null;
            if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $tm)) {
                $table = $tm[1];
            }
            $out[] = ['class' => $className, 'table' => $table];
        }
        return $out;
    }

    protected function scanMigrations(string $path): array
    {
        $dir = $path . '/Database/Migrations';
        if (!File::isDirectory($dir)) return [];
        $files = File::glob($dir . '/*.php');
        sort($files);
        return $files;
    }

    protected function scanViews(string $path): array
    {
        $dir = $path . '/Resources/views';
        if (!File::isDirectory($dir)) return ['count' => 0, 'top_dirs' => []];
        $count = 0;
        $topDirs = [];
        foreach (File::allFiles($dir) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $count++;
                $rel = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPath());
                $top = explode(DIRECTORY_SEPARATOR, $rel)[0] ?? '(root)';
                if ($top === '' || $top === $dir) $top = '(root)';
                $topDirs[$top] = ($topDirs[$top] ?? 0) + 1;
            }
        }
        arsort($topDirs);
        return ['count' => $count, 'top_dirs' => $topDirs];
    }

    protected function scanServices(string $path): array
    {
        $dir = $path . '/Services';
        if (!File::isDirectory($dir)) return [];
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') continue;
            $out[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }
        return $out;
    }

    protected function scanRequests(string $path): array
    {
        $dir = $path . '/Http/Requests';
        if (!File::isDirectory($dir)) return [];
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') continue;
            $out[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }
        return $out;
    }

    protected function scanMiddleware(string $path): array
    {
        $dir = $path . '/Http/Middleware';
        if (!File::isDirectory($dir)) return [];
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') continue;
            $out[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }
        return $out;
    }

    protected function scanProviders(string $path): array
    {
        $dir = $path . '/Providers';
        if (!File::isDirectory($dir)) return [];
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') continue;
            $out[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }
        return $out;
    }

    protected function scanDataControllerHooks(string $path): array
    {
        $file = $path . '/Http/Controllers/DataController.php';
        if (!File::exists($file)) return [];
        try {
            $content = File::get($file);
        } catch (Throwable $e) {
            return [];
        }
        preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $m);
        return array_values(array_filter(
            $m[1] ?? [],
            fn ($a) => !in_array($a, ['__construct', '__invoke'])
        ));
    }

    protected function scanLang(string $path): array
    {
        $dir = $path . '/Resources/lang';
        if (!File::isDirectory($dir)) return [];
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() === 'php' || $file->getExtension() === 'json') {
                $out[] = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
        return $out;
    }

    protected function scanAssets(string $path): array
    {
        $dir = $path . '/Resources/assets';
        $out = [
            'js'           => 0,
            'css'          => 0,
            'img'          => 0,
            'vue'          => 0,
            'ts'           => 0,
            'js_files'     => [],  // nome + tamanho dos primeiros 20
            'css_files'    => [],
            'frameworks'   => [],  // frameworks/libs detectados no código
            'has_mix'      => File::exists($path . '/webpack.mix.js'),
            'has_vite'     => File::exists($path . '/vite.config.js') || File::exists($path . '/vite.config.ts'),
            'has_package'  => File::exists($path . '/package.json'),
            'package_deps' => [],
        ];

        if (File::exists($path . '/package.json')) {
            try {
                $pkg = json_decode(File::get($path . '/package.json'), true) ?? [];
                $out['package_deps'] = array_merge(
                    array_keys($pkg['dependencies']    ?? []),
                    array_keys($pkg['devDependencies'] ?? [])
                );
            } catch (Throwable $e) {}
        }

        if (!File::isDirectory($dir)) {
            return $out;
        }

        $jsSamples = [];
        $cssSamples = [];
        $frameworkHits = [];

        foreach (File::allFiles($dir) as $file) {
            $ext = strtolower($file->getExtension());
            $relPath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $size = $file->getSize();

            if (in_array($ext, ['js', 'mjs'])) {
                $out['js']++;
                if (count($jsSamples) < 20) {
                    $jsSamples[] = ['path' => $relPath, 'size' => $size];
                }
                $frameworkHits = array_merge($frameworkHits, $this->detectFrameworks($file->getPathname()));
            } elseif ($ext === 'ts') {
                $out['ts']++;
                if (count($jsSamples) < 20) {
                    $jsSamples[] = ['path' => $relPath, 'size' => $size];
                }
            } elseif ($ext === 'vue') {
                $out['vue']++;
                $frameworkHits[] = 'Vue';
            } elseif (in_array($ext, ['css', 'scss', 'sass', 'less'])) {
                $out['css']++;
                if (count($cssSamples) < 20) {
                    $cssSamples[] = ['path' => $relPath, 'size' => $size];
                }
            } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'])) {
                $out['img']++;
            }
        }

        $out['js_files']   = $jsSamples;
        $out['css_files']  = $cssSamples;
        $out['frameworks'] = array_values(array_unique($frameworkHits));

        return $out;
    }

    /**
     * Detecta frameworks/libs JS populares por grep simples do conteúdo.
     * Barato (só abre arquivos < 1MB) mas útil pra saber onde a complexidade mora.
     */
    protected function detectFrameworks(string $file): array
    {
        try {
            if (filesize($file) > 1024 * 1024) return []; // pula minified grande
            $content = file_get_contents($file, length: 64 * 1024); // primeiros 64KB
        } catch (Throwable $e) {
            return [];
        }

        $hits = [];
        $patterns = [
            'jQuery'   => '/\$\(function\s*\(\)|jQuery\s*\(|\$\.(ajax|get|post)/i',
            'Vue'      => '/\bnew\s+Vue\s*\(|import\s+Vue\b|from\s+[\'"]vue[\'"]/i',
            'Alpine'   => '/\bAlpine\.|x-data\s*=|alpinejs/i',
            'Bootstrap'=> '/data-toggle=|bootstrap\.min\.js|\.modal\(/i',
            'DataTables' => '/\$\.fn\.dataTable|DataTable\s*\(/i',
            'Select2'  => '/\.select2\s*\(|select2\.min/i',
            'Chart.js' => '/new\s+Chart\s*\(|chart\.js/i',
            'Moment'   => '/moment\s*\(|moment\.min/i',
            'SweetAlert' => '/swal\s*\(|sweetalert/i',
            'Toastr'   => '/\btoastr\.(success|error|info|warning)/i',
            'TinyMCE'  => '/tinymce\.(init|Editor)/i',
            'CKEditor' => '/CKEDITOR\.|ClassicEditor/i',
            'Pusher'   => '/new\s+Pusher\s*\(|pusher\.min/i',
            'Laravel Echo' => '/new\s+Echo\s*\(/i',
            'React'    => '/from\s+[\'"]react[\'"]|React\.createElement/i',
        ];
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $content)) {
                $hits[] = $name;
            }
        }
        return $hits;
    }

    protected function scanTests(string $path): array
    {
        $dir = $path . '/Tests';
        if (!File::isDirectory($dir)) return [];
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') continue;
            if (str_contains($file->getFilename(), 'Test')) {
                $out[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            }
        }
        return $out;
    }

    // ========================================================================
    // Scanners adicionais — permissões, jobs, events, config, deps, FK, hooks
    // ========================================================================

    /**
     * Hooks específicos do UltimatePOS que o DataController pode implementar.
     * Lista descoberta via grep em outros módulos (Repair, Essentials, Project).
     */
    protected function scanUposHooks(string $path): array
    {
        $file = $path . '/Http/Controllers/DataController.php';
        if (!File::exists($file)) return [];
        try {
            $content = File::get($file);
        } catch (Throwable $e) {
            return [];
        }

        $knownHooks = [
            'modifyAdminMenu'          => 'Injeta itens na sidebar admin',
            'modifyUserMenu'           => 'Injeta itens no menu do usuário',
            'superadmin_package'       => 'Registra pacote de licenciamento no Superadmin',
            'user_permissions'         => 'Registra permissões Spatie no cadastro de Roles',
            'get_pos_screen_view'      => 'Adiciona view extra na tela POS',
            'after_sale_saved'         => 'Callback após venda ser salva',
            'before_sale_saved'        => 'Callback antes de venda ser salva',
            'after_product_saved'      => 'Callback após produto ser salvo',
            'addTaxonomies'            => 'Registra taxonomias/categorias customizadas',
            'addTransactionType'       => 'Registra tipo de transação customizado',
            'afterInstall'             => 'Hook pós-instalação do módulo',
            'moduleViewPartials'       => 'Partials injetáveis em views do core',
            'business_settings_tabs'   => 'Aba extra em Configurações da Empresa',
            'profile_settings_tabs'    => 'Aba extra em Perfil do usuário',
            'additional_views'         => 'Views adicionais registradas',
        ];

        $hits = [];
        foreach ($knownHooks as $method => $desc) {
            if (preg_match('/public\s+function\s+' . preg_quote($method, '/') . '\s*\(/', $content)) {
                $hits[] = ['method' => $method, 'description' => $desc];
            }
        }
        return $hits;
    }

    /**
     * Permissões registradas pelo módulo + permissões usadas nas blades (@can).
     */
    protected function scanPermissions(string $path): array
    {
        $registered = [];
        $usedInBlade = [];

        // 1. Permissões registradas no DataController::user_permissions()
        $file = $path . '/Http/Controllers/DataController.php';
        if (File::exists($file)) {
            try {
                $content = File::get($file);
                if (preg_match('/function\s+user_permissions\s*\([^)]*\)\s*\{(.+?)^\s*\}/ms', $content, $m)) {
                    $block = $m[1];
                    if (preg_match_all('/[\'"]([\w\.\-]+\.[\w\.\-]+)[\'"]/', $block, $pm)) {
                        $registered = array_values(array_unique(
                            array_filter($pm[1], fn ($s) => str_contains($s, '.') && !str_contains($s, '::'))
                        ));
                    }
                }
            } catch (Throwable $e) {}
        }

        // 2. Permissões usadas em blades (@can e similares)
        $viewsDir = $path . '/Resources/views';
        if (File::isDirectory($viewsDir)) {
            foreach (File::allFiles($viewsDir) as $file) {
                if (!str_ends_with($file->getFilename(), '.blade.php')) continue;
                try {
                    $content = $file->getContents();
                } catch (Throwable $e) { continue; }
                if (preg_match_all('/@(?:can|cannot|cannotany|canany)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
                    foreach ($m[1] as $perm) {
                        $usedInBlade[$perm] = true;
                    }
                }
                if (preg_match_all('/->can\s*\(\s*[\'"]([\w\.\-]+)[\'"]/', $content, $m)) {
                    foreach ($m[1] as $perm) {
                        $usedInBlade[$perm] = true;
                    }
                }
            }
        }

        return [
            'registered' => $registered,
            'used_in_blade' => array_keys($usedInBlade),
        ];
    }

    protected function scanJobs(string $path): array
    {
        return $this->listPhpClasses($path . '/Jobs');
    }

    protected function scanCommands(string $path): array
    {
        return $this->listPhpClasses($path . '/Console/Commands', $path . '/Console');
    }

    protected function scanEvents(string $path): array
    {
        return $this->listPhpClasses($path . '/Events');
    }

    protected function scanListeners(string $path): array
    {
        return $this->listPhpClasses($path . '/Listeners');
    }

    protected function scanObservers(string $path): array
    {
        return $this->listPhpClasses($path . '/Observers');
    }

    protected function scanPolicies(string $path): array
    {
        return $this->listPhpClasses($path . '/Policies');
    }

    protected function scanNotifications(string $path): array
    {
        return $this->listPhpClasses($path . '/Notifications');
    }

    protected function scanFactories(string $path): array
    {
        return $this->listPhpClasses($path . '/Database/factories', $path . '/Database/Factories');
    }

    protected function scanSeeders(string $path): array
    {
        return $this->listPhpClasses($path . '/Database/Seeders');
    }

    /**
     * Scan genérico: lista nomes de arquivos .php em um ou mais diretórios candidatos.
     */
    protected function listPhpClasses(string ...$dirs): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            if (!File::isDirectory($dir)) continue;
            foreach (File::allFiles($dir) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $out[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Dump da configuração em Config/config.php — só os top-level keys pra não
     * explodir o markdown com valores enormes.
     */
    protected function scanConfig(string $path): array
    {
        $file = $path . '/Config/config.php';
        if (!File::exists($file)) return [];
        try {
            // Avaliação segura: só retorna o array sem side-effects
            $config = @include $file;
        } catch (Throwable $e) {
            return ['__error__' => $e->getMessage()];
        }
        if (!is_array($config)) return [];

        $out = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $out[$key] = '[array(' . count($value) . ' itens)]';
            } elseif (is_bool($value)) {
                $out[$key] = $value ? 'true' : 'false';
            } elseif (is_string($value) && strlen($value) > 120) {
                $out[$key] = mb_substr($value, 0, 120) . '…';
            } else {
                $out[$key] = (string) $value;
            }
        }
        return $out;
    }

    /**
     * Dependências cross-module: grep de `Modules\Xyz\` OU `modules/xyz/` em
     * arquivos PHP do módulo atual. Mostra acoplamentos escondidos.
     */
    protected function scanCrossDeps(string $path, string $selfName): array
    {
        $deps = [];
        $knownModules = [];
        $modulesRoot = base_path('Modules');
        if (File::isDirectory($modulesRoot)) {
            foreach (File::directories($modulesRoot) as $dir) {
                $knownModules[] = basename($dir);
            }
        }

        // Grep apenas em .php do módulo (ignora vendor/node_modules)
        $phpFiles = [];
        foreach (['/Http', '/Entities', '/Services', '/Jobs', '/Listeners', '/Observers', '/Policies'] as $sub) {
            if (File::isDirectory($path . $sub)) {
                foreach (File::allFiles($path . $sub) as $f) {
                    if ($f->getExtension() === 'php') $phpFiles[] = $f->getPathname();
                }
            }
        }

        foreach ($phpFiles as $file) {
            try { $content = File::get($file); } catch (Throwable $e) { continue; }
            foreach ($knownModules as $mod) {
                if ($mod === $selfName) continue;
                if (preg_match('/\\\\Modules\\\\' . preg_quote($mod, '/') . '\\\\/', $content) ||
                    preg_match('#Modules/' . preg_quote($mod, '/') . '/#', $content)) {
                    $deps[$mod] = ($deps[$mod] ?? 0) + 1;
                }
            }
        }

        arsort($deps);
        return $deps;
    }

    /**
     * Integridade no DB: grep nas migrations por foreign keys + triggers.
     * Custo muito baixo (grep textual) mas útil pra ver acoplamento em dados.
     */
    protected function scanDatabaseIntegrity(string $path): array
    {
        $dir = $path . '/Database/Migrations';
        if (!File::isDirectory($dir)) {
            return ['foreign_keys' => [], 'triggers' => [], 'unique_indexes' => 0];
        }

        $foreignKeys = [];
        $triggers = [];
        $uniqueIndexes = 0;

        foreach (File::glob($dir . '/*.php') as $file) {
            try { $content = File::get($file); } catch (Throwable $e) { continue; }

            // Foreign keys: ->foreign('x')->references('y')->on('z')
            if (preg_match_all("/->foreign\s*\(\s*['\"]([^'\"]+)['\"]\s*\)\s*->references\s*\(\s*['\"]([^'\"]+)['\"]\s*\)\s*->on\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $foreignKeys[] = ['column' => $hit[1], 'references' => $hit[2], 'on' => $hit[3]];
                }
            }

            // Triggers SQL (PontoWR2 tem)
            if (preg_match_all('/CREATE\s+TRIGGER\s+`?(\w+)`?/i', $content, $m)) {
                foreach ($m[1] as $trig) $triggers[] = $trig;
            }

            // Unique indexes (contagem simples)
            $uniqueIndexes += preg_match_all('/->unique\s*\(/', $content);
        }

        return [
            'foreign_keys'    => $foreignKeys,
            'triggers'        => $triggers,
            'unique_indexes'  => $uniqueIndexes,
        ];
    }

    protected function formatSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1024 / 1024, 2) . ' MB';
    }

    /**
     * Extrai sinais de risco / prioridade para guiar migração.
     */
    protected function extractSignals(array $spec): array
    {
        $statuses = [];
        $statusesFile = base_path('modules_statuses.json');
        if (File::exists($statusesFile)) {
            $statuses = json_decode(File::get($statusesFile), true) ?? [];
        }
        $active = $statuses[$spec['name']] ?? false;

        $flags = [];

        if (!($spec['exists_in_current'] ?? true)) $flags[] = '❌ NÃO EXISTE na branch atual (só em branches antigas — migração perdida?)';
        if (empty($spec['module_json']) && ($spec['exists_in_current'] ?? true)) $flags[] = '⚠️ Sem `module.json` (inconsistente com padrão nwidart)';
        if (empty($spec['controllers']) && ($spec['exists_in_current'] ?? true)) $flags[] = '⚠️ Nenhum controller encontrado';
        if (($spec['views']['count'] ?? 0) === 0 && ($spec['exists_in_current'] ?? true)) $flags[] = 'ℹ️ Módulo sem views (provável API-only ou service)';
        if (empty($spec['migrations']) && ($spec['exists_in_current'] ?? true)) $flags[] = 'ℹ️ Sem migrations próprias (pode depender de tabelas de outros módulos)';

        $hooksCount = count($spec['upos_hooks'] ?? []);
        if ($hooksCount > 0) {
            $hookNames = implode(', ', array_map(fn($h) => $h['method'], $spec['upos_hooks']));
            $flags[] = "🔗 Registra {$hooksCount} hook(s) UltimatePOS: {$hookNames}";
        }

        if (count($spec['routes']['all']) > 50) $flags[] = '🔴 +50 rotas — módulo grande, migrar em fases';
        elseif (count($spec['routes']['all']) > 20) $flags[] = '🟡 ' . count($spec['routes']['all']) . ' rotas — escopo médio';
        if (($spec['views']['count'] ?? 0) > 50) $flags[] = '🔴 +50 views — trabalho pesado';
        if (!empty($spec['tests'])) $flags[] = '✅ Tem testes (' . count($spec['tests']) . ')';
        if (!$active) $flags[] = '⚪ Inativo em `modules_statuses.json`';

        // Novos sinais (dos scanners 6)
        $perms = $spec['permissions'] ?? [];
        $registered = count($perms['registered'] ?? []);
        if ($registered > 0) $flags[] = "🔐 Registra {$registered} permissão(ões) Spatie";

        $async = count($spec['jobs'] ?? []) + count($spec['events'] ?? []) + count($spec['listeners'] ?? []);
        if ($async > 0) $flags[] = "⚙️ Processamento assíncrono: {$async} peça(s) (jobs/events/listeners)";

        $deps = count($spec['cross_deps'] ?? []);
        if ($deps > 0) $flags[] = "🔗 Acoplamento: depende de {$deps} outro(s) módulo(s)";

        $fks = count($spec['db_integrity']['foreign_keys'] ?? []);
        if ($fks > 10) $flags[] = "🗃️ {$fks} foreign keys — alto acoplamento em dados";

        if (!empty($spec['db_integrity']['triggers'] ?? [])) {
            $flags[] = "🗄️ Tem triggers MySQL (" . count($spec['db_integrity']['triggers']) . ") — append-only / imutabilidade";
        }

        // Priorização simples
        $routeCount = count($spec['routes']['all']);
        $viewCount = $spec['views']['count'];
        $complexity = $routeCount + $viewCount;

        $priority = 'média';
        $risk = 'médio';

        if (!$active) {
            $priority = 'baixa (desativado)';
            $risk = 'baixo';
        } elseif ($complexity < 15) {
            $priority = 'alta (pequeno, ganho rápido)';
            $risk = 'baixo';
        } elseif ($complexity < 40) {
            $priority = 'média';
            $risk = 'médio';
        } else {
            $priority = 'baixa (grande, fazer por último ou dividir)';
            $risk = 'alto';
        }

        return [
            'active' => $active,
            'flags' => $flags,
            'migration_priority' => $priority,
            'risk' => $risk,
            'complexity_score' => $complexity,
        ];
    }
}
