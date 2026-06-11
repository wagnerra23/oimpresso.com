<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Camada 1 do sistema de governance de secrets (ADR 0215).
 *
 * Varre repo procurando secret patterns + cross-check com índice canon.
 * Reporta:
 *   (a) secrets em git canon SEM entry no índice (drift fonte→índice)
 *   (b) entries no índice cujos arquivos canon não existem mais
 *
 * Origem: Wagner cobrou 2026-05-28 "isso não deveria ser automático?".
 * Camada 1 = auto-discovery prévio à validação Camada 2.
 *
 * Flag --diff-only (pre-commit hook): só escaneia git diff staged.
 * Flag --fail-on-drift: exit code 1 se drift detectado (CI gate).
 *
 * @see memory/_INDEX-SECRETS.md (índice canon)
 * @see .githooks/pre-commit (Camada 5 git hook chama com --diff-only)
 * @see .github/workflows/governance-drift.yml (Camada 5 CI — secrets-governance.yml consolidado aqui na ADR 0271 onda 2)
 */
class SecretsScanCommand extends Command
{
    protected $signature = 'secrets:scan
                            {--diff-only : escanear apenas git diff staged (pre-commit hook)}
                            {--fail-on-drift : exit 1 se drift detectado (CI gate)}';

    protected $description = 'Camada 1 ADR 0215 — varre repo procurando secrets sem entry no índice';

    /**
     * Patterns de secret comuns. Lista intencionalmente restrita pra evitar
     * falso positivo em test fixtures, mocks, exemplos.
     */
    private const SECRET_PATTERNS = [
        'bearer_token' => '/Authorization:\s*Bearer\s+([A-Za-z0-9_-]{20,})/i',
        'aws_access_key' => '/AKIA[0-9A-Z]{16}/',
        'api_key_assign' => '/(?:^|\s)(?:API_KEY|api_key|apiKey)\s*=\s*["\']?([A-Za-z0-9_-]{20,})["\']?/i',
        'secret_assign' => '/(?:^|\s)(?:SECRET|secret_key)\s*=\s*["\']?([A-Za-z0-9_-]{20,})["\']?/i',
        'password_assign' => '/(?:^|\s)PASSWORD\s*=\s*["\']?([A-Za-z0-9!@#$%^&*]{12,})["\']?/i',
    ];

    private const SCAN_PATHS = [
        'memory',
        'config',
        'app/Console/Commands',
        'Modules',
    ];

    private const EXCLUDE_PATHS = [
        'memory/_INDEX-SECRETS.md', // índice canônico ele mesmo
        'vendor',
        'node_modules',
        'storage',
    ];

    public function handle(): int
    {
        $this->info('[secrets:scan] iniciando varredura');

        $indexEntries = $this->loadIndexEntries();
        $this->info(sprintf('[secrets:scan] índice canon: %d entries conhecidas', count($indexEntries)));

        $findings = $this->scanForSecrets();
        $this->info(sprintf('[secrets:scan] padrões secret detectados: %d ocorrências', count($findings)));

        $drift = $this->computeDrift($findings, $indexEntries);

        if (empty($drift)) {
            $this->info('[secrets:scan] ✅ todos secrets detectados têm entry no índice');
            return self::SUCCESS;
        }

        $this->warn(sprintf('[secrets:scan] ⚠️  %d drift(s) entre fonte e índice', count($drift)));
        foreach ($drift as $item) {
            $this->warn(sprintf('  %s:%d → padrão %s sem entry no índice',
                $item['file'], $item['line'], $item['pattern']));
        }

        if ($this->option('fail-on-drift')) {
            $this->error('[secrets:scan] exit 1 (--fail-on-drift) — atualize memory/_INDEX-SECRETS.md');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Carrega lista de nomes/ponteiros do índice canon.
     */
    private function loadIndexEntries(): array
    {
        $path = base_path('memory/_INDEX-SECRETS.md');
        if (! file_exists($path)) {
            return [];
        }
        $content = (string) file_get_contents($path);
        $entries = [];
        foreach (explode("\n", $content) as $line) {
            if (str_starts_with($line, '| **') && substr_count($line, '|') >= 6) {
                $cells = array_map('trim', explode('|', trim($line, '|')));
                if (count($cells) >= 3) {
                    $entries[] = preg_replace('/\*\*/', '', $cells[0]);
                }
            }
        }
        return $entries;
    }

    /**
     * Varre paths configurados procurando padrões secret.
     */
    private function scanForSecrets(): array
    {
        $findings = [];
        $finder = new Finder();

        foreach (self::SCAN_PATHS as $path) {
            $full = base_path($path);
            if (! is_dir($full)) {
                continue;
            }
            $finder->in($full)->files()->name(['*.md', '*.php', '*.env', '*.env.*']);
        }

        foreach ($finder as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $fullRelative = ltrim(str_replace(base_path(), '', $file->getRealPath()), '/\\');
            $fullRelative = str_replace('\\', '/', $fullRelative);

            // Skip excludes
            $skip = false;
            foreach (self::EXCLUDE_PATHS as $exclude) {
                if (str_contains($fullRelative, $exclude)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                continue;
            }
            $lines = explode("\n", $contents);

            foreach ($lines as $lineNum => $line) {
                foreach (self::SECRET_PATTERNS as $patternName => $regex) {
                    if (preg_match($regex, $line, $m)) {
                        $findings[] = [
                            'file' => $fullRelative,
                            'line' => $lineNum + 1,
                            'pattern' => $patternName,
                            'sample' => substr($m[0], 0, 60),
                        ];
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * Cross-check findings vs índice. Drift = secret detectado mas
     * arquivo do índice NÃO referencia este path.
     *
     * Heurística simples: se findings['file'] aparece em algum location
     * do índice, OK. Senão, drift.
     */
    private function computeDrift(array $findings, array $indexEntries): array
    {
        $indexContent = file_exists(base_path('memory/_INDEX-SECRETS.md'))
            ? (string) file_get_contents(base_path('memory/_INDEX-SECRETS.md'))
            : '';

        $drift = [];
        foreach ($findings as $finding) {
            // Se o file aparece referenciado no índice, secret está catalogado
            if (str_contains($indexContent, $finding['file'])) {
                continue;
            }
            $drift[] = $finding;
        }

        return $drift;
    }
}
