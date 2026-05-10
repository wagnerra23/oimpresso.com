<?php

declare(strict_types=1);

/**
 * withoutGlobalScopes Comment Guard — varredura estática garantindo que toda
 * chamada `withoutGlobalScopes(...)` em código de produção tem comentário
 * `// SUPERADMIN: <razão>` na mesma linha ou nas 3 linhas anteriores.
 *
 * **Por quê:** ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL. `business_id` global
 * scope é obrigatório em toda Eloquent Model que toca dados de negócio.
 * Bypass via `withoutGlobalScopes` é legítimo APENAS em superadmin/CLI/cross-
 * tenant explícito — e nesses casos exige justificativa textual auditável.
 *
 * **Skill:** commit-discipline Tier A always-on já exige; este guard é o
 * enforce automático em CI.
 *
 * **Regra:** toda chamada `withoutGlobalScopes` em `app/` ou
 * `Modules/<X>/{Services,Jobs,Listeners,Http,Console,Repositories,Observers}/`
 * deve ter `SUPERADMIN` em comentário na mesma linha OU 1-3 linhas acima.
 *
 * **Auditoria 2026-05-10:** ~30 violações detectadas, corrigidas em PRs
 * subsequentes. Guard previne regressão futura.
 *
 * **Modelo canônico:** Modules/Vestuario/Services/VestuarioSettingsResolver.php:130
 */

use Symfony\Component\Finder\Finder;

/**
 * Coleta arquivos .php de produção (app/ + Modules/<X>/<dirs operacionais>).
 * Exclui Tests, Database/migrations, Config, e o próprio guard.
 *
 * @return array<int, array{path: string, relpath: string, content: string}>
 */
function withoutScopesGuardCollectFiles(): array
{
    $base = realpath(__DIR__.'/../../..');
    if ($base === false) {
        return [];
    }

    $paths = [];

    if (is_dir($base.'/app')) {
        $paths[] = $base.'/app';
    }

    if (is_dir($base.'/Modules')) {
        $paths[] = $base.'/Modules';
    }

    if (empty($paths)) {
        return [];
    }

    $finder = (new Finder)
        ->in($paths)
        ->name('*.php')
        ->files()
        // Exclui pastas de test e migration (Tests/, Database/, database/)
        ->notPath('/(^|\/)Tests(\/|$)/')
        ->notPath('/(^|\/)tests(\/|$)/')
        ->notPath('/(^|\/)Database(\/|$)/')
        ->notPath('/(^|\/)database(\/|$)/')
        ->notPath('/(^|\/)Config(\/|$)/')
        ->notPath('/(^|\/)config(\/|$)/');

    $files = [];
    foreach ($finder as $file) {
        $relpath = str_replace('\\', '/', substr($file->getRealPath(), strlen($base) + 1));

        // Não auditar este próprio guard (auto-referência)
        if (str_ends_with($relpath, 'WithoutGlobalScopesCommentGuardTest.php')) {
            continue;
        }

        // Skip arquivo de test que possa ter escapado ao filtro Finder
        if (str_ends_with($relpath, 'Test.php')) {
            continue;
        }

        $files[] = [
            'path'    => $file->getRealPath(),
            'relpath' => $relpath,
            'content' => $file->getContents(),
        ];
    }

    return $files;
}

/**
 * Procura chamadas `withoutGlobalScopes(...)` sem comentário SUPERADMIN.
 *
 * @param  array<int, array{path: string, relpath: string, content: string}>  $files
 * @return array<int, string>
 */
function withoutScopesGuardScan(array $files): array
{
    $violations = [];
    $callPattern = '/withoutGlobalScopes\s*\(/';

    foreach ($files as $file) {
        $lines = preg_split('/\R/', $file['content']) ?: [];

        foreach ($lines as $idx => $line) {
            // Skip linha de comentário puro (declaração ou doc-block sobre o método)
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }

            if (! preg_match($callPattern, $line)) {
                continue;
            }

            // Skip definição de método (function withoutGlobalScopes() em trait/macro)
            if (preg_match('/function\s+withoutGlobalScopes\s*\(/', $line)) {
                continue;
            }

            // Procura SUPERADMIN nas 3 linhas anteriores OU na mesma linha
            $hasComment = false;
            $start = max(0, $idx - 3);
            for ($j = $start; $j <= $idx; $j++) {
                if (! isset($lines[$j])) {
                    continue;
                }
                if (preg_match('/SUPERADMIN/i', $lines[$j])) {
                    $hasComment = true;
                    break;
                }
            }

            if (! $hasComment) {
                $violations[] = sprintf(
                    '%s:%d: %s',
                    $file['relpath'],
                    $idx + 1,
                    trim($line),
                );
            }
        }
    }

    return $violations;
}

it('guard: toda chamada withoutGlobalScopes em produção tem // SUPERADMIN comment', function () {
    $files = withoutScopesGuardCollectFiles();
    expect($files)->not->toBeEmpty('Nenhum arquivo de produção encontrado — guard test inerte');

    $violations = withoutScopesGuardScan($files);

    if (! empty($violations)) {
        $count = count($violations);
        $msg = "VIOLAÇÃO: withoutGlobalScopes() sem // SUPERADMIN comment.\n\n"
            ."Skill commit-discipline Tier A exige razão explícita pra cada uso.\n"
            ."ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL.\n\n"
            ."Modelo canônico: Modules/Vestuario/Services/VestuarioSettingsResolver.php:130\n"
            ."Auditoria 2026-05-10 detectou ~30 violações (corrigidas em PRs).\n\n"
            ."Violações ({$count}):\n  - "
            .implode("\n  - ", $violations);

        expect($violations)->toBeEmpty($msg);
    }

    expect($violations)->toBeEmpty();
})->group('guard');
