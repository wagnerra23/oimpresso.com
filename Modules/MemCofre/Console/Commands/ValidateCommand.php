<?php

namespace Modules\MemCofre\Console\Commands;

use Illuminate\Console\Command;
use Modules\MemCofre\Services\DocValidator;

/**
 * Executa os 5 checks de integridade do MemCofre (ADR 0005).
 *
 * Uso:
 *   php artisan memcofre:validate                  (global)
 *   php artisan memcofre:validate --module=PontoWr2
 *   php artisan memcofre:validate --only=warning
 */
class ValidateCommand extends Command
{
    protected $signature = 'memcofre:validate
                            {--module= : Limita a um módulo}
                            {--only= : Mostra só um nível (critical|warning|info)}';

    protected $description = 'Valida rastreabilidade da documentação (stories órfãs, regras sem teste, ADRs dangling, etc).';

    public function handle(DocValidator $validator): int
    {
        $result = $validator->validate($this->option('module'));

        $issues = $result['issues'];
        if ($level = $this->option('only')) {
            $issues = array_filter($issues, fn ($i) => $i['level'] === $level);
        }

        $t = $result['totals'];
        $this->line('');
        $this->info("Health score: {$result['health_score']}/100");
        $this->line(sprintf(
            '  critical: %d  ·  warning: %d  ·  info: %d  ·  total: %d',
            $t['critical'], $t['warnings'], $t['infos'], $t['total']
        ));
        $this->line('');

        if (empty($issues)) {
            $this->info('✓ Sem issues.');
            return 0;
        }

        $rows = array_map(fn ($i) => [
            strtoupper($i['level']),
            $i['type'],
            $i['module'] ?? '-',
            $i['ref'],
            substr($i['message'], 0, 80),
        ], $issues);

        $this->table(['Nível', 'Tipo', 'Módulo', 'Ref', 'Mensagem'], $rows);

        return $t['critical'] > 0 ? 1 : 0;
    }
}
