<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreMarcacaoRequest — registro de batida (marcação de ponto).
 *
 * APPEND-ONLY por força de lei:
 * - CLT Art. 74 §2º — registro de jornada obrigatório > 20 empregados
 * - Portaria MTP nº 671/2021 (Anexo I) — marcações eletrônicas são imutáveis,
 *   apenas anuláveis via fluxo de intercorrência/justificativa
 *
 * Esta request valida APENAS criação (POST /ponto/api/marcar). NÃO existe
 * UpdateMarcacaoRequest — marcação errada vira intercorrência (ver
 * StoreIntercorrenciaRequest).
 *
 * Tier 0 multi-tenant (ADR 0093): business_id NÃO vem do request — é injetado
 * via session() ou no Service (anti-tampering cross-tenant).
 *
 * @see memory/requisitos/Ponto/SPEC.md US-PONTO-001 (marcação REP-P mobile)
 * @see Modules\Ponto\Services\MarcacaoService
 */
class StoreMarcacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Identificação do colaborador (FK validada cross-tenant no Service)
            'colaborador_id' => ['required', 'integer', 'exists:ponto_colaboradores,id'],

            // Momento da batida — server-side authoritative.
            // Cliente envia, mas Service compara com now() e rejeita se delta > tolerância.
            // Portaria 671 Art. 80: REP-P aceita batida offline até 24h após gerada.
            'registrada_em' => ['required', 'date', 'before_or_equal:now', 'after:-24 hours'],

            // Origem (REP-P mobile, REP-A relógio, manual via gestor).
            // Manual exige permissão extra — validada no Service.
            'origem' => ['required', Rule::in(['REP_P_MOBILE', 'REP_A_RELOGIO', 'MANUAL_GESTOR', 'IMPORTACAO_AFD'])],

            // Geolocalização REP-P (Portaria 671 Anexo I §10 — obrigatório pra mobile)
            'latitude'  => ['nullable', 'required_if:origem,REP_P_MOBILE', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:origem,REP_P_MOBILE', 'numeric', 'between:-180,180'],

            // NSR (Número Sequencial de Registro) — pode vir do REP-A; gerado pelo Service se ausente
            'nsr' => ['nullable', 'integer', 'min:1'],

            // Hash de integridade — calculado/validado no Service (não confia em cliente)
            'hash_origem' => ['nullable', 'string', 'max:64'],

            // Dispositivo (User-Agent / serial REP-A) — auditoria
            'dispositivo_identificador' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'registrada_em.before_or_equal' => 'A batida não pode ser no futuro (Portaria 671/2021).',
            'registrada_em.after' => 'A batida não pode ter mais de 24h (limite REP-P offline).',
            'latitude.required_if' => 'REP-P mobile exige latitude (Portaria 671 Anexo I §10).',
            'longitude.required_if' => 'REP-P mobile exige longitude (Portaria 671 Anexo I §10).',
        ];
    }
}
