<?php

namespace Modules\ConsultaOs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ConsultaOs\Http\Requests\ConsultaPublicaRequest;

class ConsultaOsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ConsultaOs/Index');
    }

    public function buscar(ConsultaPublicaRequest $request): JsonResponse
    {
        $numero  = $request->input('numero');
        $estagio = $request->input('estagio', 'todos');

        // TODO(query-real): trocar mock por busca em transactions multi-tenant.
        // Decisao pendente Wagner: identificacao por invoice_no + ultimos 4 do
        // telefone (padrao Repair) e mapeamento dos estagios (transactions.status
        // + shipping_status vs tabela bridge consulta_os_estagio).
        $os = $this->mockData()[$numero] ?? null;

        if (! $os) {
            return response()->json(['found' => false], 404);
        }

        if ($estagio && $estagio !== 'todos' && $os['stage'] !== $estagio) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found' => true,
            'os'    => $os,
        ]);
    }

    private function mockData(): array
    {
        return [
            '4821' => [
                'id'       => '4821',
                'client'   => 'Acme Comércio Ltda',
                'contact'  => 'Camila Diniz',
                'vendedor' => 'Bruna Vendas',
                'designer' => 'Joana Lima',
                'created'  => '21/04/2025',
                'updated'  => 'hoje, 13:55',
                'stage'    => 'aprovacao',
                'items'    => [
                    ['desc' => 'Banner Lona 440g — 3×2m', 'qty' => 1, 'unit' => 'un', 'stage' => 'aprovacao'],
                ],
            ],
            '4819' => [
                'id'       => '4819',
                'client'   => 'Padaria Estrela',
                'contact'  => 'Renato Lopes',
                'vendedor' => 'Bruna Vendas',
                'designer' => 'Joana Lima',
                'created'  => '18/04/2025',
                'updated'  => 'ontem, 16:20',
                'stage'    => 'expedicao',
                'items'    => [
                    ['desc' => 'Cardápios A4 frente e verso', 'qty' => 50, 'unit' => 'un', 'stage' => 'expedicao'],
                ],
            ],
            '4817' => [
                'id'       => '4817',
                'client'   => 'Clínica Vida',
                'contact'  => 'Marcos Saraiva',
                'vendedor' => 'Bruna Vendas',
                'designer' => 'Carla Souza',
                'created'  => '16/04/2025',
                'updated'  => 'hoje, 08:42',
                'stage'    => 'producao',
                'items'    => [
                    ['desc' => 'Placa de sinalização PVC — 60×40cm', 'qty' => 8,  'unit' => 'un', 'stage' => 'producao'],
                    ['desc' => 'Placa de sinalização PVC — 30×20cm', 'qty' => 4,  'unit' => 'un', 'stage' => 'acabamento'],
                    ['desc' => 'Suporte metálico fixação parede',     'qty' => 12, 'unit' => 'un', 'stage' => 'producao'],
                ],
            ],
            '4815' => [
                'id'       => '4815',
                'client'   => 'Escola Aurora',
                'contact'  => 'Pedagógico',
                'vendedor' => 'Mateus PCP',
                'designer' => '—',
                'created'  => '10/04/2025',
                'updated'  => 'ontem, 17:50',
                'stage'    => 'entregue',
                'items'    => [
                    ['desc' => 'Banners faixas 1×3m', 'qty' => 6, 'unit' => 'un', 'stage' => 'entregue'],
                ],
            ],
        ];
    }
}
