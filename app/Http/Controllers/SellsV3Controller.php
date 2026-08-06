<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

/**
 * Tela de venda V3 — PREVIEW DE DESIGN, paralela e isolada.
 *
 * POR QUE EXISTE (Luiz [L], 2026-08-06)
 * A tela de cadastro de venda de produção (`Sells/Create.tsx`, servida por
 * SellPosController@create em /pos/create) NÃO pode ser alterada: ROTA LIVRE
 * (biz=4 — Larissa/Guilherme) opera nela e mudança quebra contrato comercial.
 * Então o redesenho nasce em ARQUIVO NOVO + ROTA NOVA, sem encostar em nada
 * que a tela viva consome.
 *
 * O QUE ESTE CONTROLLER DELIBERADAMENTE NÃO FAZ
 * - não monta as ~24 props reais de SellPosController@create (duplicar 200
 *   linhas de orquestração garantiria drift);
 * - não grava nada: não há store(), não há POST;
 * - não calcula valor: os números abaixo são DADOS DE CENA já formatados.
 *   Cálculo de total/desconto/estoque é território [V0] (REGRA MESTRE em
 *   memory/proibicoes.md) e não entra numa tela de preview.
 *
 * Ligar dados reais é decisão separada, depois que o desenho assentar.
 *
 * Refs: ADR 0093 (multi-tenant Tier 0) · ADR 0104 (MWART) · ADR 0062 (staging CT 100).
 */
class SellsV3Controller extends Controller
{
    public function create()
    {
        $businessId = request()->session()->get('user.business_id');

        // Mesma alçada da tela de venda real — preview não afrouxa permissão.
        if (! (auth()->user()->can('superadmin') || auth()->user()->can('sell.create'))) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('Sells/CreateV3', [
            'businessId' => $businessId,
            'cena' => $this->dadosDeCena(),
        ]);
    }

    /**
     * Dados de cena — espelham prototipo-ui/design-oimpresso/.../sells-data.js.
     * Valores já formatados em pt-BR de propósito: a tela NÃO faz conta.
     *
     * @return array<string, mixed>
     */
    private function dadosDeCena(): array
    {
        return [
            'cliente' => [
                'codigo' => '0001',
                'nome' => 'Consumidor final',
                'tipo' => 'pf',
                'grupo' => 'Varejo',
                'prazo' => 'À vista',
                'tabela' => 'Balcão — preço padrão',
                'endereco' => 'Venda no balcão — sem endereço de entrega',
            ],
            'itens' => [
                [
                    'sku' => 'LON-440-BR',
                    'nome' => 'Lona 440g branca fosca',
                    'un' => 'm²',
                    'medidas' => '5× 0,50×0,60m',
                    'qtd' => '12,50',
                    'valor' => '68,90',
                    'desc' => '0',
                    'acr' => '0',
                    'total' => '861,25',
                ],
                [
                    'sku' => 'BAN-ACAB-IL',
                    'nome' => 'Acabamento com ilhós',
                    'un' => 'un',
                    'medidas' => null,
                    'qtd' => '24',
                    'valor' => '3,50',
                    'desc' => '0',
                    'acr' => '0',
                    'total' => '84,00',
                ],
            ],
            'fechamento' => [
                'subtotal' => '945,25',
                'desconto' => '0,00',
                'imposto' => '170,15',
                'acrescimo' => '0,00',
                'frete' => '0,00',
                'total' => '1.115,40',
                'situacao' => 'A RECEBER',
                'falta' => '1.115,40',
            ],
        ];
    }
}
