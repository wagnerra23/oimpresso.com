<?php

declare(strict_types=1);

namespace Modules\Fisio\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Database\Seeders\FisioBibliotecaSeeder;

/**
 * Semeia/atualiza a biblioteca GLOBAL do Fisio (US-FISIO-004). Idempotente por `code`; nunca
 * sobrescreve exercício já revisado (`publicado`/`retirado`). Roda também no Install 1-clique.
 * O conteúdo nasce `em_revisao`, pendente da revisão clínica da Dra. Dejeane (ADR 0437 §12).
 */
class SemearBibliotecaCommand extends Command
{
    protected $signature = 'fisio:biblioteca:semear';

    protected $description = 'Fisio: semeia os 38 exercícios globais da biblioteca L0 (pendentes de revisão clínica)';

    public function handle(): int
    {
        if (! Schema::hasColumn('fisio_exercises', 'code')) {
            $this->error('fisio_exercises sem a coluna code: rode as migrations do Fisio antes.');

            return self::FAILURE;
        }

        $r = (new FisioBibliotecaSeeder())->semear();
        $this->info("Biblioteca Fisio: {$r['criados']} criado(s), {$r['atualizados']} atualizado(s), {$r['preservados']} já revisado(s) preservado(s).");

        return self::SUCCESS;
    }
}
