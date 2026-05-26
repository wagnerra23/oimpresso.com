<?php

declare(strict_types=1);

namespace Modules\Compras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * US-COM-006 PR-0 Gap #4 (audit sênior 2026-05-25) — FormRequest pra validar
 * filtros de listagem do cockpit Compras.
 *
 * Pré-refactor: validação inline solta em ComprasController::index (D8.c=0/3).
 * Pós: FormRequest canônico + permission gate + whitelist allow-only.
 *
 * Whitelist (defesa anti-SQLi + anti-IDOR):
 *  - q: string opcional, max 100 chars
 *  - stage: enum ['all','received','ordered','pending','draft'] · default 'all'
 *  - sort: enum ['transaction_date','ref_no','final_total','contact_name'] · default 'transaction_date'
 *  - dir: enum ['asc','desc'] · default 'desc'
 *  - per_page: enum [10,25,50,100] · default 25
 *  - compra_id: integer >= 1 opcional (pra partial reload drawer)
 *
 * @see memory/requisitos/Compras/AUDIT-SENIOR-2026-05-25.md §Gap #4
 */
class ListarComprasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->can('compras.view');
    }

    public function rules(): array
    {
        return [
            'q'         => ['nullable', 'string', 'max:100'],
            'stage'     => ['nullable', 'string', 'in:all,received,ordered,pending,draft'],
            'sort'      => ['nullable', 'string', 'in:transaction_date,ref_no,final_total,contact_name'],
            'dir'       => ['nullable', 'string', 'in:asc,desc'],
            'per_page'  => ['nullable', 'integer', 'in:10,25,50,100'],
            'compra_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Defaults aplicados pós-validação — caller usa sem checar nullables.
     */
    public function filtros(): array
    {
        return [
            'q'        => (string) $this->input('q', ''),
            'stage'    => (string) $this->input('stage', 'all'),
            'sort'     => (string) $this->input('sort', 'transaction_date'),
            'dir'      => strtolower((string) $this->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
            'per_page' => (int) $this->input('per_page', 25),
        ];
    }

    public function compraId(): int
    {
        return (int) $this->input('compra_id', 0);
    }
}
