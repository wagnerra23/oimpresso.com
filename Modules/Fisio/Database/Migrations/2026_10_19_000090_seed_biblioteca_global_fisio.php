<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Database\Seeders\FisioBibliotecaSeeder;

/**
 * US-FISIO-004 — semeia a biblioteca GLOBAL do Fisio (38 exercícios do lote L0, aprovados pela
 * responsável técnica em 2026-10-19) no deploy, sem passo manual.
 *
 * O seeder é idempotente pela chave (business_id nulo, name): rodar de novo só atualiza os
 * globais e nunca toca em exercício de clínica.
 *
 * down() não apaga: exercício global pode já estar em programa de paciente
 * (`fisio_program_items.exercise_id`), e prontuário é guardado por 20 anos (Lei 13.787/2018).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_exercises')) {
            return;
        }

        (new FisioBibliotecaSeeder())->run();
    }

    public function down(): void
    {
        // Intencionalmente vazio — ver o docblock.
    }
};
