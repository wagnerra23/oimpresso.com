<?php

namespace Modules\Vestuario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Esqueleto FormRequest pra atualizar Grade Avancada Vestuario.
 *
 * Wave S (D8.c Security): Modules/Vestuario nao tem VestuarioController
 * implementado ainda (apenas InstallController + VestuarioSettingsResolver +
 * VestuarioSetting entity). Sprint 2+ vai concretizar quando endpoints
 * Update reais existirem (ADR 0105 — sinal qualificado primeiro).
 *
 * Grade Avancada = matriz cor × tamanho × quantidade (vestuario_grades
 * table — ver GradeAvancadaMultiTenantTest). Wagner palavras 2026-05-10:
 * "vestuario sem grade avancada nao serve, e o coracao".
 *
 * Tier 0 IRREVOGAVEL — Cliente piloto ROTA LIVRE biz=4 em prod desde
 * 2024-Q1 (CNAE 4781-4/00, Larissa Termas do Gravatal/SC, 99% volume
 * vendas). NAO criar fixtures com biz=4 (memory/proibicoes.md §"Sempre
 * fazer" + ADR 0101 tests biz=1). Refactor conservador.
 *
 * Pattern canonico igual StoreVestuarioRequest (skeleton irmao).
 *
 * @see Modules\Vestuario\Http\Requests\StoreVestuarioRequest
 * @see Modules\Vestuario\Tests\Feature\GradeAvancadaMultiTenantTest
 * @see memory/requisitos/Vestuario/SPEC.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */
class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras placeholder — Sprint 2+ vai concretizar.
     *
     * Notas pro Sprint futuro:
     *   - `business_id` JAMAIS aceito do request (vem da session — Tier 0).
     *   - `cor_id` / `tamanho_id` precisam exists scoped por business_id.
     *   - `quantidade` integer >= 0 (estoque negativo ja eh tratado no Service).
     *   - Pest test em biz=1 (NUNCA biz=4 ROTA LIVRE prod).
     */
    public function rules(): array
    {
        return [
            // Sprint 2+ — adicionar regras quando endpoints Update reais existirem.
        ];
    }
}
