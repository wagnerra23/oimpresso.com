<?php

namespace Modules\Repair\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;

/**
 * Produção · Oficina — kanban da oficina (read-mostly).
 *
 * F3 inicial usa mock data. US-REPAIR-PROD-2 substitui por query real
 * em JobSheet scopada por business_id.
 *
 * Charter: resources/js/Pages/Repair/ProducaoOficina/Index.charter.md
 * Protótipo F1: prototipo-ui/prototipos/producao-oficina/F1.html
 */
class ProducaoOficinaController extends Controller
{
    public function index()
    {
        return Inertia::render('Repair/ProducaoOficina/Index', [
            'columns' => $this->mockColumns(),
            'totals' => [
                'os' => 17,
                'aguardando_aprovacao' => 3,
            ],
        ]);
    }

    private function mockColumns(): array
    {
        return [
            [
                'id' => 'recepcao',
                'label' => 'Recepção',
                'tone' => 'slate',
                'cards' => [
                    ['plate' => 'RUI-2A45', 'vehicle' => 'Civic 2019', 'brand' => 'Honda', 'km' => 78420, 'mecanico' => 'Carlos R.', 'mecanico_initials' => 'CR', 'wait' => 'há 12min', 'box' => null, 'aprovacao_pendente' => false],
                    ['plate' => 'QPF-7B12', 'vehicle' => 'Onix 2022', 'brand' => 'Chevrolet', 'km' => 42100, 'mecanico' => 'Diego M.', 'mecanico_initials' => 'DM', 'wait' => 'há 38min', 'box' => null, 'aprovacao_pendente' => false],
                    ['plate' => 'SVE-9C03', 'vehicle' => 'HB20 2020', 'brand' => 'Hyundai', 'km' => 105880, 'mecanico' => 'Carlos R.', 'mecanico_initials' => 'CR', 'wait' => 'há 1h', 'box' => null, 'aprovacao_pendente' => false],
                ],
            ],
            [
                'id' => 'diagnostico',
                'label' => 'Diagnóstico',
                'tone' => 'blue',
                'cards' => [
                    ['plate' => 'TPL-3D88', 'vehicle' => 'Corolla 2018', 'brand' => 'Toyota', 'km' => 156300, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'box' => 'B1', 'aprovacao_pendente' => false],
                    ['plate' => 'UNK-5E27', 'vehicle' => 'Polo 2021', 'brand' => 'Volkswagen', 'km' => 31770, 'mecanico' => 'Diego M.', 'mecanico_initials' => 'DM', 'box' => 'B2', 'aprovacao_pendente' => false],
                    ['plate' => 'VWP-1F94', 'vehicle' => 'Sandero 2017', 'brand' => 'Renault', 'km' => 198450, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'box' => 'E1', 'aprovacao_pendente' => false],
                    ['plate' => 'WXR-8G56', 'vehicle' => 'Yaris 2020', 'brand' => 'Toyota', 'km' => 67220, 'mecanico' => 'Carlos R.', 'mecanico_initials' => 'CR', 'box' => 'B3', 'aprovacao_pendente' => false],
                ],
            ],
            [
                'id' => 'aguardando-pecas',
                'label' => 'Aguardando peças',
                'tone' => 'amber',
                'cards' => [
                    ['plate' => 'YTQ-4H73', 'vehicle' => 'Strada 2019', 'brand' => 'Fiat', 'km' => 88350, 'mecanico' => null, 'mecanico_initials' => null, 'box' => null, 'aprovacao_pendente' => true, 'orcamento_total' => 2480, 'orcamento_pecas' => 4, 'orcamento_status' => 'Cliente não respondeu'],
                    ['plate' => 'ZAB-6I20', 'vehicle' => 'Compass 2021', 'brand' => 'Jeep', 'km' => 54110, 'mecanico' => 'Diego M.', 'mecanico_initials' => 'DM', 'wait' => '3 dias', 'eta' => 'sex.', 'aprovacao_pendente' => false],
                    ['plate' => 'ACE-2J15', 'vehicle' => 'Tracker 2018', 'brand' => 'Chevrolet', 'km' => 119880, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'wait' => '5 dias', 'eta' => 'seg.', 'aprovacao_pendente' => false],
                ],
            ],
            [
                'id' => 'em-execucao',
                'label' => 'Em execução',
                'tone' => 'violet',
                'cards' => [
                    ['plate' => 'BDF-9K61', 'vehicle' => 'Kicks 2022', 'brand' => 'Nissan', 'km' => 28500, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'box' => 'B1', 'aprovacao_pendente' => false],
                    ['plate' => 'CGE-3L08', 'vehicle' => 'Hilux 2019', 'brand' => 'Toyota', 'km' => 142700, 'mecanico' => 'Carlos R.', 'mecanico_initials' => 'CR', 'box' => 'E1', 'aprovacao_pendente' => false],
                    ['plate' => 'DHJ-7M52', 'vehicle' => 'Argo 2021', 'brand' => 'Fiat', 'km' => 49330, 'mecanico' => 'Diego M.', 'mecanico_initials' => 'DM', 'box' => 'B2', 'aprovacao_pendente' => false],
                    ['plate' => 'EIK-1N99', 'vehicle' => 'Renegade 2020', 'brand' => 'Jeep', 'km' => 71060, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'box' => 'E2', 'aprovacao_pendente' => false],
                ],
            ],
            [
                'id' => 'pronto',
                'label' => 'Pronto',
                'tone' => 'emerald',
                'cards' => [
                    ['plate' => 'FJL-5O44', 'vehicle' => 'Cronos 2020', 'brand' => 'Fiat', 'km' => 88100, 'mecanico' => null, 'mecanico_initials' => null, 'status_label' => 'Aguardando retirada', 'aprovado' => true],
                    ['plate' => 'GKM-8P77', 'vehicle' => 'Mobi 2018', 'brand' => 'Fiat', 'km' => 64220, 'mecanico' => null, 'mecanico_initials' => null, 'status_label' => 'Retirado às 14:30', 'aprovado' => true],
                    ['plate' => 'HLN-2Q31', 'vehicle' => 'Saveiro 2019', 'brand' => 'Volkswagen', 'km' => 121450, 'mecanico' => null, 'mecanico_initials' => null, 'status_label' => 'Aguardando retirada', 'aprovado' => true],
                ],
            ],
        ];
    }
}
