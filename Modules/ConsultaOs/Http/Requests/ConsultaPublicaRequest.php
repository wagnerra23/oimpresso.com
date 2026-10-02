<?php

namespace Modules\ConsultaOs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\ConsultaOs\Repositories\RepairConsultaOsRepository;

/**
 * Busca pública de OS — D8.c Security (US-CONSULTA-001, dados reais do Repair).
 *
 * Nunca aceita menos que o portal do Repair (#8527): `tipo` obrigatório numa lista fechada
 * (job_sheet_no | invoice_no | mobile_num — celular só se a config do Repair liga) e
 * `numero` obrigatório e não vazio. Sem isso a consulta sairia sem filtro (ADR 0093).
 *
 * Formato do número: letras, dígitos e `/ . -`, começando por letra ou dígito, até 20
 * caracteres. O alpha_num de antes recusava o próprio nº de OS do Repair (`JS2026/0001`)
 * e nº de venda com hífen; espaço, aspas e demais símbolos continuam recusados.
 * Throttle 30/min por IP na rota (defesa em profundidade).
 */
class ConsultaPublicaRequest extends FormRequest
{
    private const FORMATO = '/^[A-Za-z0-9][A-Za-z0-9\/.\-]*$/';

    /**
     * Endpoint público — autorização pelo throttle middleware na rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', Rule::in(RepairConsultaOsRepository::tiposHabilitados())],
            'numero' => ['required', 'string', 'max:20', 'regex:'.self::FORMATO],
            'serie' => ['nullable', 'string', 'max:50', 'regex:'.self::FORMATO],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Escolha como quer buscar a OS.',
            'tipo.in' => 'Tipo de busca inválido.',
            'numero.required' => 'Informe o número para buscar.',
            'numero.max' => 'O número não pode ter mais de 20 caracteres.',
            'numero.regex' => 'Use só letras, números e os sinais / . -',
            'serie.max' => 'O número de série não pode ter mais de 50 caracteres.',
            'serie.regex' => 'Use só letras, números e os sinais / . -',
        ];
    }
}
