<?php

namespace Modules\DocVault\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Instala os git hooks do DocVault em `.git/hooks/`.
 *
 * Hooks copiados:
 *   - pre-commit: valida documentação modificada, bloqueia se critical
 *
 * Uso:
 *   php artisan docvault:install-hooks
 *   php artisan docvault:install-hooks --force  (sobrescreve existente)
 */
class InstallHooksCommand extends Command
{
    protected $signature = 'docvault:install-hooks {--force : Sobrescreve hooks existentes}';

    protected $description = 'Instala git hooks do DocVault em .git/hooks/';

    public function handle(): int
    {
        $src = base_path('bin/git-hooks');
        $dst = base_path('.git/hooks');

        if (! is_dir($dst)) {
            $this->error('.git/hooks/ não existe. Rode este comando dentro de um repo git.');
            return 1;
        }

        $installed = 0;
        $skipped = 0;

        foreach (File::files($src) as $f) {
            $name = $f->getFilename();
            $target = $dst . DIRECTORY_SEPARATOR . $name;

            if (File::exists($target) && ! $this->option('force')) {
                $this->warn("  skip  {$name} (já existe — use --force pra sobrescrever)");
                $skipped++;
                continue;
            }

            File::copy($f->getPathname(), $target);

            // Linux/Mac: chmod +x. Windows: ignora (git bash executa igual).
            if (PHP_OS_FAMILY !== 'Windows') {
                chmod($target, 0755);
            }

            $this->info("  ok    {$name}");
            $installed++;
        }

        $this->line('');
        $this->info("Hooks instalados: {$installed}");
        if ($skipped > 0) $this->line("Pulados: {$skipped}");

        if (PHP_OS_FAMILY === 'Windows') {
            $this->line('');
            $this->warn('Windows detectado: git executa os hooks via bash do Git for Windows.');
            $this->warn('Se hook não rodar, verifique que bash.exe está no PATH.');
        }

        return 0;
    }
}
