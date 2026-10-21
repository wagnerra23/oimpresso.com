<?php

declare(strict_types=1);

namespace Modules\Fisio\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Fisio\Entities\Exercise;

/**
 * Seed da biblioteca GLOBAL do Fisio (US-FISIO-004, T-04 do HANDOFF-F1).
 *
 * Os 38 exercícios do lote L0 moram em `biblioteca-l0.php`, ao lado. Aprovados sem ressalvas
 * pela responsável técnica, Dra. Djenane Detroz (CREFITO 18.341-F), em 2026-10-19. Mudança de
 * conteúdo clínico passa pela revisão dela anotada no PR (ADR 0437).
 *
 * Roda sozinho no deploy pela migration `2026_10_19_000090_seed_biblioteca_global_fisio`.
 *
 * IDEMPOTENTE: chave = (business_id nulo, name). Rodar de novo atualiza o conteúdo do global,
 * nunca duplica e nunca toca em exercício de clínica. Não apaga nada.
 *
 *   php artisan db:seed --class="Modules\Fisio\Database\Seeders\FisioBibliotecaSeeder"
 */
class FisioBibliotecaSeeder extends Seeder
{
    /**
     * Formato de cada item (campos de `fisio_exercises`):
     *   name, region, type, pose?, steps (list<string>), synonyms?, default_sets?, default_reps?,
     *   default_hold_s?, times_per_day, safety_notes?, contraindication_flags?, needs_support
     *
     * @return list<array<string, mixed>>
     */
    public function exercicios(): array
    {
        return require __DIR__.'/biblioteca-l0.php';
    }

    public function run(): void
    {
        foreach ($this->exercicios() as $item) {
            $nome = (string) $item['name'];
            unset($item['name']);

            // SUPERADMIN: seed da biblioteca global (business_id nulo) roda sem sessão; não lê nem grava dado de clínica.
            Exercise::withoutGlobalScopes()->updateOrCreate(
                ['business_id' => null, 'name' => $nome],
                [...$item, 'is_custom' => false],
            );
        }
    }
}
