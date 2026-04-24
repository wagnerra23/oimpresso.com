<?php

namespace Modules\MemCofre\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Sincroniza a memória persistente do Claude (perfil do usuário no SO) para
 * dentro do repositório (`memory/claude/`), tornando-a versionada no git.
 *
 * Isso dá:
 *   - Backup automático das memórias Claude (hoje vive só no C:\Users\...)
 *   - Visibilidade pra outros agentes (Cursor, ChatGPT) que leem o repo
 *   - Histórico de evolução das preferências/feedback via `git log memory/claude/`
 *
 * NÃO escreve no diretório do Claude — one-way sync (Claude → repo).
 *
 * Uso:
 *   php artisan memcofre:sync-memories
 *   php artisan memcofre:sync-memories --dry
 *   php artisan memcofre:sync-memories --commit  (faz git commit após sync)
 */
class SyncMemoriesCommand extends Command
{
    protected $signature = 'memcofre:sync-memories
                            {--dry : Mostra o que seria sincronizado, sem escrever}
                            {--commit : Cria git commit após sync se houve mudanças}';

    protected $description = 'Sincroniza memória Claude (home) para memory/claude/ (repo) — leitura unidirecional';

    public function handle(): int
    {
        $src = (string) config('memcofre.memory.claude_dir');
        $dst = base_path('memory/claude');

        if (! is_dir($src)) {
            $this->error("Fonte não existe: {$src}");
            $this->line('Configure CLAUDE_MEMORY_DIR no .env pra apontar pra pasta correta.');
            return 1;
        }

        if (! $this->option('dry')) {
            File::ensureDirectoryExists($dst);
        }

        $added = $removed = $changed = 0;
        $unchanged = 0;

        // 1. Copia arquivos do source pro destino (só .md/.txt/.json/.yaml/.yml)
        $srcFiles = $this->collectFiles($src);
        foreach ($srcFiles as $rel => $fullSrc) {
            $fullDst = $dst . DIRECTORY_SEPARATOR . $rel;

            if (! file_exists($fullDst)) {
                $added++;
                $this->line("  + {$rel}");
                if (! $this->option('dry')) {
                    File::ensureDirectoryExists(dirname($fullDst));
                    File::copy($fullSrc, $fullDst);
                    touch($fullDst, filemtime($fullSrc));
                }
                continue;
            }

            if (md5_file($fullSrc) !== md5_file($fullDst)) {
                $changed++;
                $this->line("  ~ {$rel}");
                if (! $this->option('dry')) {
                    File::copy($fullSrc, $fullDst);
                    touch($fullDst, filemtime($fullSrc));
                }
            } else {
                $unchanged++;
            }
        }

        // 2. Remove arquivos no destino que não existem mais na fonte
        if (is_dir($dst)) {
            $dstFiles = $this->collectFiles($dst);
            foreach (array_keys($dstFiles) as $rel) {
                if (! isset($srcFiles[$rel])) {
                    $removed++;
                    $this->line("  - {$rel}");
                    if (! $this->option('dry')) {
                        @unlink($dst . DIRECTORY_SEPARATOR . $rel);
                    }
                }
            }
        }

        // 3. README de orientação se ainda não existir
        $readme = $dst . DIRECTORY_SEPARATOR . 'README.md';
        if (! $this->option('dry') && ! File::exists($readme)) {
            File::put($readme, $this->readmeContent());
        }

        $this->line('');
        $this->info("Sync " . ($this->option('dry') ? '(dry)' : 'concluído') . ":");
        $this->line("  adicionados:   {$added}");
        $this->line("  modificados:   {$changed}");
        $this->line("  removidos:     {$removed}");
        $this->line("  inalterados:   {$unchanged}");

        // 4. Commit opcional
        $hasChanges = ($added + $changed + $removed) > 0;
        if ($this->option('commit') && $hasChanges && ! $this->option('dry')) {
            $this->line('');
            $this->info('Criando commit...');
            $date = now()->format('Y-m-d');
            chdir(base_path());
            shell_exec('git add memory/claude/');
            $msg = "chore(memcofre): sync Claude memory ({$date})\n\n+{$added} ~{$changed} -{$removed}";
            shell_exec('git commit -m ' . escapeshellarg($msg));
            $this->info('✓ Commit criado.');
        }

        return 0;
    }

    /**
     * @return array<string, string>  rel → full
     */
    protected function collectFiles(string $base): array
    {
        $out = [];
        if (! is_dir($base)) return $out;

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        $baseReal = realpath($base);
        foreach ($it as $f) {
            if (! $f->isFile()) continue;
            $ext = strtolower($f->getExtension());
            if (! in_array($ext, ['md', 'txt', 'json', 'yaml', 'yml'], true)) continue;

            $full = $f->getPathname();
            $rel = ltrim(str_replace($baseReal, '', realpath($full)), DIRECTORY_SEPARATOR);
            $out[str_replace('\\', '/', $rel)] = $full;
        }
        return $out;
    }

    protected function readmeContent(): string
    {
        return <<<MD
# Claude memory (mirror)

Este diretório é um **espelho read-only** da memória persistente do Claude Code
em `~/.claude/projects/D--oimpresso-com/memory/`.

Sincronizado automaticamente por:

```
php artisan memcofre:sync-memories
```

Agendado pra rodar todo dia às 23:00 via Laravel Scheduler (ver
`App\\Console\\Kernel::schedule`).

**NÃO edite arquivos aqui diretamente** — mudanças são sobrescritas na
próxima sync. Edite a fonte original em `~/.claude/projects/...` (ou
deixe o Claude escrever nela durante a conversa).

Por que commitar isso no git?
- Backup — memória do Claude só vive no `C:\\Users\\wagne` por padrão
- Outros agentes (ChatGPT, Cursor) ganham contexto pessoal ao ler o repo
- Histórico de evolução via `git log memory/claude/`

MD;
    }
}
