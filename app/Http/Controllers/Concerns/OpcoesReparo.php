<?php

namespace App\Http\Controllers\Concerns;

/**
 * Opções da seção "Reparo" das telas de venda React (UC-S05 no Sells/Create, UC-SEDIT-12 no
 * Sells/Edit). Formata o `view_data` que o `Modules\Repair\...\DataController::get_pos_screen_view`
 * devolve pro POS Blade: só vem quando o sub_type é `repair` e o módulo está na assinatura, e
 * vem escopado por business_id.
 */
trait OpcoesReparo
{
    /** @param array<string, mixed> $d o `view_data` do Repair */
    protected function opcoesReparo(array $d): array
    {
        $sugeridos = explode(',', (string) ($d['repair_settings']['problem_reported_by_customer'] ?? ''));
        $semVazios = fn (array $l) => array_values(array_filter(array_map('trim', $l), fn ($v) => $v !== ''));

        // UC-S06: modelos com marca/aparelho (o Blade filtra a lista ao trocar marca/aparelho,
        // via /repair/get-device-models) e o checklist de cada um (o Blade busca por AJAX em
        // /repair/models-repair-checklist). Uma query, escopada por business — sem endpoint novo.
        $modelos = \Modules\Repair\Entities\DeviceModel::where('business_id', (int) session('user.business_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'brand_id', 'device_id', 'repair_checklist'])
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'name' => (string) $m->name,
                'brand_id' => $m->brand_id !== null ? (int) $m->brand_id : null,
                'device_id' => $m->device_id !== null ? (int) $m->device_id : null,
                'checklist' => $semVazios(explode('|', (string) $m->repair_checklist)),
            ])->values()->all();

        return [
            'statuses' => collect($d['repair_statuses'] ?? [])->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'color' => $s->color,
            ])->values()->all(),
            'defaultStatusId' => ! empty($d['default_status']) ? (int) $d['default_status'] : null,
            'brands' => $d['brands'] ?? [],
            'devices' => $d['devices'] ?? [],
            'modelos' => $modelos,
            'warranties' => $d['warranties'] ?? [],
            'defeitosSugeridos' => $semVazios($sugeridos),
            'checklistPadrao' => $semVazios(explode('|', (string) ($d['repair_settings']['default_repair_checklist'] ?? ''))),
        ];
    }
}
