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
 * - não consulta tabela de negócio: a cena é estática, então não há query a
 *   escopar por business_id (ADR 0093 satisfeito por ausência, não por sorte).
 *
 * A CENA ESPELHA O HANDOFF `design_handoff_cadastro_venda/design/sells-data.js`.
 * Os valores monetários vão como STRING já em pt-BR de propósito: quem formata
 * é quem tem locale, e mandar float locale-ambíguo pro front foi exatamente o
 * vetor do incidente `num_uf` de 2026-06-05 (final_total inflado ~×100.000 em
 * 16 vendas do biz=4). Ligar dados reais é decisão separada — e aí volta a
 * valer a REGRA MESTRE de valor/estoque.
 *
 * Refs: ADR 0093 (multi-tenant) · ADR 0143 (FSM) · ADR 0104 (MWART).
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
     * @return array<string, mixed>
     */
    private function dadosDeCena(): array
    {
        return [
            'cliente' => [
                'cod' => '0001',
                'nome' => 'Consumidor final',
                'padrao' => true,
                'doc' => '—',
                'ie' => 'ISENTO',
                'contrib' => 'nao',
                'regime' => 'Simples Nacional',
                'fone' => '—',
                'email' => '—',
                'emailNfe' => '—',
                'contato' => '—',
                'endereco' => 'Venda no balcão — sem endereço de entrega',
                'cidade' => 'Termas do Gravatal',
                'uf' => 'SC',
                'grupo' => 'Varejo',
                'prazo' => 'À vista',
                'tabela' => null, // sem tabela no cadastro → vale o padrão do balcão
            ],

            'itens' => [
                [
                    'k' => 1,
                    'sku' => 'LON-440-BR',
                    'nome' => 'Lona 440g branca fosca',
                    'un' => 'm²',
                    'medidas' => '5× 0,50×5,00m',
                    'qtd' => '12,50',
                    'preco' => '68,90',
                    'desc' => '0',
                    'acr' => '0',
                    'estoque' => 320.0,
                ],
                [
                    'k' => 2,
                    'sku' => 'BAN-ACAB-IL',
                    'nome' => 'Acabamento com ilhós',
                    'un' => 'un',
                    'medidas' => null,
                    'qtd' => '24',
                    'preco' => '3,50',
                    'desc' => '0',
                    'acr' => '0',
                    'estoque' => 1500.0,
                ],
            ],

            /*
             * Catálogo do Passo 2. `tipo` é EXPLÍCITO e não inferido de
             * `estoque: null` — serviço é uma característica do cadastro, e o
             * handoff usa `tipo === 'servico'` pra decidir o que a tela mostra
             * (funcionário vinculado) e o que ela NÃO diz (movimento de estoque).
             * Deixar isso implícito num null faria a UI mentir no dia em que
             * existir produto sem controle de estoque.
             */
            'catalogo' => [
                ['sku' => 'LON-440-BR', 'nome' => 'Lona 440g branca fosca', 'un' => 'm²', 'preco' => 68.90, 'estoque' => 320.0, 'tipo' => 'produto', 'ean' => '7891234000017', 'fabrica' => 'LN440BR', 'categoria' => 'Impressão digital'],
                ['sku' => 'ADE-VIN-BR', 'nome' => 'Adesivo vinílico branco brilho', 'un' => 'm²', 'preco' => 82.00, 'estoque' => 46.5, 'tipo' => 'produto', 'ean' => '7891234000024', 'fabrica' => 'AD-VNL-BB', 'categoria' => 'Impressão digital'],
                ['sku' => 'BAN-ACAB-IL', 'nome' => 'Acabamento com ilhós', 'un' => 'un', 'preco' => 3.50, 'estoque' => 1500.0, 'tipo' => 'produto', 'ean' => null, 'fabrica' => 'ILH-10', 'categoria' => 'Acabamento'],
                ['sku' => 'PLA-PS-2MM', 'nome' => 'Placa PS 2mm branca', 'un' => 'm²', 'preco' => 119.00, 'estoque' => 88.0, 'tipo' => 'produto', 'ean' => '7891234000048', 'fabrica' => 'PS2-BR', 'categoria' => 'Rígidos'],
                ['sku' => 'SRV-INST', 'nome' => 'Instalação em fachada', 'un' => 'h', 'preco' => 145.00, 'estoque' => null, 'tipo' => 'servico', 'ean' => null, 'fabrica' => null, 'categoria' => 'Serviço'],
                ['sku' => 'SRV-PROJ', 'nome' => 'Projeto e arte-final', 'un' => 'h', 'preco' => 180.00, 'estoque' => null, 'tipo' => 'servico', 'ean' => null, 'fabrica' => null, 'categoria' => 'Serviço'],
            ],

            /*
             * Quem pode EXECUTAR o serviço — funcionário ou técnico do cadastro
             * único. O campo só aparece quando `tipo === 'servico'`, porque é
             * ele que entra na apuração de comissão e na ordem de produção.
             */
            'executantes' => [
                ['id' => 'fun-01', 'nome' => 'Marcos Vinícius', 'papel' => 'funcionario'],
                ['id' => 'fun-02', 'nome' => 'Patrícia Lemos', 'papel' => 'funcionario'],
                ['id' => 'tec-01', 'nome' => 'Rogério Alves', 'papel' => 'tecnico'],
            ],

            /*
             * Alçada de PREÇO por perfil. Vem do servidor de propósito: quem
             * decide se este usuário altera valor é o backend, não o front —
             * `readOnly` no input é conforto de UI, nunca a trava.
             */
            'permissoes' => [
                'editarPrecoItem' => true,
            ],

            'tabelas' => [
                'Balcão — preço padrão',
                'Atacado — a partir de 50m²',
                'Governo 2026 — pregão 041/2026',
                'Parceiro / agência — 15% off',
            ],

            /*
             * Pipeline do handoff §6 — Rascunho → Orçamento → Aprovada →
             * Em produção → Faturada → Entregue, com Cancelada terminal.
             *
             * `acao` é o TEXTO DO BOTÃO do finalizador: não existe select de
             * estágio, o estágio só muda por ação nomeada. `efeitos` é o que a
             * transição dispararia — declarado ANTES, nunca depois.
             * `role` é o papel exigido: faltando, a tela NEGA e diz qual é
             * (fail-secure, handoff §6 regra 2).
             */
            'fsm' => [
                ['key' => 'rascunho', 'label' => 'Rascunho', 'acao' => 'Salvar como orçamento', 'role' => 'sell.create', 'efeitos' => []],
                ['key' => 'orcamento', 'label' => 'Orçamento', 'acao' => 'Aprovar orçamento', 'role' => 'sell.approve', 'efeitos' => []],
                ['key' => 'aprovada', 'label' => 'Aprovada', 'acao' => 'Iniciar produção', 'role' => 'sell.produce', 'efeitos' => ['ReservarEstoque']],
                ['key' => 'producao', 'label' => 'Em produção', 'acao' => 'Faturar venda', 'role' => 'sell.invoice', 'efeitos' => ['ConsumirEstoque', 'BaixarFinanceiro']],
                ['key' => 'faturada', 'label' => 'Faturada', 'acao' => 'Registrar entrega', 'role' => 'sell.deliver', 'efeitos' => []],
                ['key' => 'entregue', 'label' => 'Entregue', 'acao' => null, 'role' => null, 'efeitos' => []],
                ['key' => 'cancelada', 'label' => 'Cancelada', 'acao' => null, 'role' => null, 'efeitos' => ['CancelarVendaCascade', 'LiberarReserva']],
            ],

            /*
             * Papéis do usuário na CENA. Vem propositalmente incompleto: sem
             * `sell.approve`, a tela exercita o caminho fail-secure (botão
             * negado + papel faltante dito na tela) já no 2º estágio. Preview
             * que só mostra o caminho feliz esconde metade do desenho.
             */
            'papeisDoUsuario' => ['sell.create', 'sell.produce', 'sell.invoice', 'sell.deliver'],
        ];
    }
}
