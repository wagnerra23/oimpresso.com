<?php

declare(strict_types=1);

namespace Modules\Fisio\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Fisio\Entities\Exercise;

/**
 * Seed da biblioteca GLOBAL do Fisio (US-FISIO-004, T-04 do HANDOFF-F1).
 *
 * ⚠️ CONTEÚDO CLÍNICO AGUARDA REVISÃO DA DRA. DEJEANE (responsável técnica, ADR 0437 §12).
 * Nenhum exercício entra aqui sem passos, dose e contraindicações escritos em documento canônico
 * e revisados por ela. Este seeder não inventa conteúdo: a lista `exercicios()` só recebe item que
 * tenha TODOS os campos obrigatórios documentados (nome, região, tipo, passos).
 *
 * ESTADO EM 2026-10-18: a lista está VAZIA, e isso é o resultado honesto da leitura dos docs.
 * `etapas/01-biblioteca.md` define a taxonomia e o lote L0 (38 exercícios), mas não traz o
 * conteúdo de nenhum exercício. O único documento com exercícios nomeados é
 * `protocolos/11-fibromialgia.md`, que dá nome e dose, mas não os passos nem a região, e marca
 * cada um como "a conferir na lista do seed". Ficaram fora, à espera de passos + revisão:
 *   - Respiração diafragmática (2×10, diário)
 *   - Gato-camelo (2×8)
 *   - Rotação de tronco deitado (2×8)
 *   - Alongamento de isquiotibiais sentado (2× 20 s)
 *   - Retração escapular (2×10)
 *   - Sentar e levantar (1–2×8–10)
 *   - Ponte de glúteo (1–2×8–10)
 *   - Elevação de panturrilha (1–2×10)
 *   - Mini agachamento na parede (1–2×8)
 *   - Apoio unipodal perto de apoio firme (3× 15–30 s)
 * (Caminhada leve, marcha estacionária, bicicleta ergométrica e remada com elástico o próprio
 * protocolo marca como "novo (gravar)".)
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
        return [];
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
