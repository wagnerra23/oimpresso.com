<?php

namespace App\Http\Controllers;

use App\Business;
use App\Domain\Fsm\Models\SaleProcess;
use App\Scopes\ScopeByBusiness;
use App\Services\Sells\IniciarPipelineVendaV3;
use App\BusinessLocation;
use App\SellingPriceGroup;
use App\Services\FeatureFlagService;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
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
 * - não grava por conta própria: não há store() aqui. Com a flag `useV3SellsGravar`
 *   (desligada por padrão) a tela posta em `/pos` — SellPosController@store, o mesmo
 *   caminho do Create.tsx — e este controller só entrega o local e o cliente padrão
 *   (UC-V380/V382). Total, linhas e estoque saem do store que já está em produção;
 * - com a flag desligada não consulta tabela de negócio: a cena é estática. Com ela
 *   ligada, as duas consultas (local e cliente padrão) filtram por business_id.
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

        $props = ['businessId' => $businessId, 'cena' => $this->dadosDeCena()];

        $gravacao = app(FeatureFlagService::class)->isOn(self::FLAG_GRAVAR, ['business_id' => $businessId])
            ? $this->dadosDeGravacao((int) $businessId)
            : null;
        if ($gravacao !== null) {
            $cliente = $gravacao['cliente'];
            unset($gravacao['cliente']);
            $props['gravacao'] = $gravacao;
            // Gravando, nada da cena vai para a tela (UC-V404). O cliente é o padrão DESTA empresa,
            // lido do cadastro; itens, catálogo, transportadoras, executantes, tabelas e o pipeline
            // de 7 etapas da demonstração vão vazios. A busca passa a ser /products/list (a mesma do
            // Create), as tabelas vêm de `gravacao.tabelas` e a venda entra no pipeline real ao gravar.
            $props['cena'] = [
                'cliente' => $cliente,
                'clientes' => [$cliente],
                'itens' => [],
                'catalogo' => [],
                'tabelas' => [],
                'fsm' => [],
                'papeisDoUsuario' => [],
                'executantes' => [],
                // A permissão de preço da cena (sempre true) dá lugar à do usuário (UC-V384).
                'permissoes' => ['editarPrecoItem' => $gravacao['permissoes']['editarPreco']],
                'transportadoras' => [],
            ];
        }

        return Inertia::render('Sells/CreateV3', $props);
    }

    /** Desligada por padrão (fora do fallbackDefaults): ligar é decisão [W] no GrowthBook. */
    public const FLAG_GRAVAR = 'useV3SellsGravar';

    /**
     * Local e cliente padrão para gravar, os dois DESTA empresa: o local vem de
     * `forDropdown` (que respeita os locais permitidos ao usuário) e o cliente de
     * `getWalkInCustomer`; ambos filtram por business_id (ADR 0093).
     *
     * As permissões são as da venda direta no Blade (`SellController`, tela de venda):
     * `edit_product_*_from_sale_screen` e o teto `users.max_sales_discount_percent`, o
     * mesmo que o Create.tsx lê. A trava é na tela — o store de /pos não as confere,
     * nem para o Create (medido em 2026-10-30).
     *
     * @return array{locationId:int, contactId:int, clienteNome:string, cliente:array<string, mixed>, permissoes:array{editarPreco:bool, editarDesconto:bool, descontoMaximo:float|null, criarCliente:bool, registrarPagamento:bool}, prazo:array{numero:mixed, tipo:mixed}, config:array<string, mixed>, comissionados:list<array{id:int, nome:string}>, tabelas:list<array{id:int, nome:string}>, tabelaPadraoId:int|null}|null
     */
    /**
     * Etapas do processo em que a venda da V3 entra ao gravar (UC-V414).
     *
     * O processo é o de `IniciarPipelineVendaV3` (`venda_com_producao`), não o modelo de fluxo
     * escolhido em /fluxos: é ali que a venda gravada pela V3 de fato entra. Sem o processo ativo,
     * a lista vem vazia e a tela mantém o texto (a empresa sem o processo ganha o processo ao
     * gravar, e quem o desativou grava sem pipeline).
     *
     * "Em espera" (`on_hold`) é desvio da produção e "Cancelada" (`cancelled`) é saída de qualquer
     * etapa: o protótipo (`CAMINHO` em venda-v3.jsx) não as mostra como passos do caminho.
     *
     * Tier 0: o escopo é declarado na query (`business_id`), porque o global scope do modelo lê a
     * sessão; o mesmo padrão do `IniciarPipelineVendaV3::processo`.
     *
     * @return list<array{key: string, label: string}>
     */
    private static function etapasDaVenda(int $businessId): array
    {
        $processo = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', IniciarPipelineVendaV3::PROCESSO)
            ->where('active', true)
            ->first();
        if (! $processo) {
            return [];
        }

        // `toBase()`: só duas colunas, sem hidratar o modelo. A relação já filtra pelo processo,
        // que acima foi achado com o business_id desta empresa.
        return $processo->stages()->toBase()
            ->whereNotIn('key', ['on_hold', 'cancelled'])
            ->orderBy('sort_order')
            ->get(['key', 'name'])
            ->map(fn (object $e) => ['key' => (string) $e->key, 'label' => (string) $e->name])
            ->values()
            ->all();
    }

    private function dadosDeGravacao(int $businessId): ?array
    {
        $user = auth()->user();
        $locationId = collect(BusinessLocation::forDropdown($businessId))->keys()->first();
        $cliente = app(ContactUtil::class)->getWalkInCustomer($businessId);
        if (empty($locationId) || empty($cliente['id'])) {
            return null;
        }

        return [
            'locationId' => (int) $locationId,
            'contactId' => (int) $cliente['id'],
            'clienteNome' => (string) ($cliente['name'] ?? 'Consumidor final'),
            'cliente' => self::clientePadrao($cliente),
            'permissoes' => [
                'editarPreco' => (bool) $user->can('edit_product_price_from_sale_screen'),
                'editarDesconto' => (bool) $user->can('edit_product_discount_from_sale_screen'),
                'descontoMaximo' => $user->max_sales_discount_percent !== null ? (float) $user->max_sales_discount_percent : null,
                // "Novo cliente" na consulta: o Blade mostra o + só com customer.create (SellPosController:1358).
                'criarCliente' => (bool) $user->can('customer.create'),
                // Seção de pagamento: o Blade só a mostra com sell.payments (sell/create.blade.php:775).
                'registrarPagamento' => (bool) $user->can('sell.payments'),
            ],
            // Prazo do cliente padrão: o Blade preenche pay_term com o do cliente (sell/create.blade.php:181).
            'prazo' => [
                'numero' => $cliente['pay_term_number'] ?? null,
                'tipo' => $cliente['pay_term_type'] ?? null,
            ],
            'config' => self::configDaVenda($business = Business::findOrFail($businessId)),
            'comissionados' => self::comissionados($businessId, $business->sales_cmsn_agnt),
            ...self::tabelasDePreco($businessId, (int) $locationId),
            // Situação (UC-V414): as etapas do processo em que a venda vai entrar ao gravar.
            'etapas' => self::etapasDaVenda($businessId),
        ];
    }

    /**
     * O cliente padrão no cartão do cliente (UC-V404), com a MESMA regra de `clienteDaBusca`
     * (`_components/v3/cliente-gravacao-dominio.ts`), que traduz qualquer outro cliente da busca:
     * o que o cadastro tem vai como está, o que a tela não lê dele vai como `—`, nunca inventado.
     * Até 2026-11-23 só o nome vinha do cadastro e o resto era da cena: o endereço e a cidade de
     * demonstração (Termas do Gravatal/SC) apareciam para o consumidor final de qualquer empresa.
     *
     * @param  array<string, mixed>  $contato  a linha de `ContactUtil::getWalkInCustomer`, já desta empresa
     * @return array<string, mixed>
     */
    public static function clientePadrao(array $contato): array
    {
        $texto = fn ($v): ?string => $v === null || trim((string) $v) === '' ? null : trim((string) $v);
        $nome = $texto($contato['name'] ?? null) ?? 'Consumidor final';
        $tipo = in_array($contato['pay_term_type'] ?? null, ['days', 'months'], true) ? $contato['pay_term_type'] : null;
        $prazo = $texto($contato['pay_term_number'] ?? null);
        $endereco = implode(' · ', array_filter([$texto($contato['address_line_1'] ?? null), $texto($contato['zip_code'] ?? null)]));

        return [
            'cod' => $texto($contato['contact_id'] ?? null) ?? (string) ($contato['id'] ?? ''),
            'nome' => $nome,
            'padrao' => true,
            'doc' => '—',
            'ie' => '—',
            'contrib' => 'nao',
            'regime' => '—',
            'fone' => $texto($contato['mobile'] ?? null) ?? '—',
            'email' => '—',
            'emailNfe' => '—',
            'contato' => $texto($contato['supplier_business_name'] ?? null) ?? $nome,
            'endereco' => $endereco !== '' ? $endereco : '—',
            'cidade' => $texto($contato['city'] ?? null) ?? '—',
            'uf' => $texto($contato['state'] ?? null) ?? '—',
            'grupo' => '—',
            'prazo' => $prazo === null || $tipo === null ? 'À vista' : $prazo.' '.($tipo === 'days' ? 'dias' : 'meses'),
            'tabela' => null,
        ];
    }

    /**
     * Tabelas de preço da venda (UC-V397), como o `SellPosController@create`: a lista é
     * `SellingPriceGroup::forDropdown`, que só traz as permitidas (`selling_price_group.{id}`) e o
     * preço padrão (id 0) só com `access_default_selling_price`; a tabela inicial é a do local, se
     * estiver na lista. As duas consultas filtram por business_id (ADR 0093).
     *
     * @return array{tabelas:list<array{id:int, nome:string}>, tabelaPadraoId:int|null}
     */
    public static function tabelasDePreco(int $businessId, int $locationId): array
    {
        $lista = SellingPriceGroup::forDropdown($businessId);
        $doLocal = BusinessLocation::where('business_id', $businessId)->where('id', $locationId)->value('selling_price_group_id');

        return [
            'tabelas' => collect($lista)->map(fn ($nome, $id) => ['id' => (int) $id, 'nome' => (string) $nome])->values()->all(),
            'tabelaPadraoId' => ! empty($doLocal) && array_key_exists($doLocal, $lista) ? (int) $doLocal : null,
        ];
    }

    /**
     * Quem pode ser o comissionado da venda (UC-V395), a mesma lista do `SellPosController@create`:
     * usuários com `sales_cmsn_agnt` = user, agentes com `cmsn_agnt`; nos demais não há select.
     * As duas consultas filtram por business_id (ADR 0093).
     *
     * @return list<array{id:int, nome:string}>
     */
    public static function comissionados(int $businessId, ?string $tipo): array
    {
        $lista = match ($tipo) {
            'user' => User::forDropdown($businessId, false),
            'cmsn_agnt' => User::saleCommissionAgentsDropdown($businessId, false),
            default => [],
        };

        return collect($lista)->map(fn ($nome, $id) => ['id' => (int) $id, 'nome' => (string) $nome])->values()->all();
    }

    /**
     * Configurações do business que a tela de venda obedece (UC-V386), lidas como o
     * `SellPosController@create` e o Blade leem: `pos_settings` (JSON, com o default de
     * `BusinessUtil::defaultPosSettings`) e colunas de `business`. Só dados, nenhuma regra:
     * a regra mora em `config-business-dominio.ts`.
     *
     * @return array<string, mixed>
     */
    public static function configDaVenda(Business $business): array
    {
        $pos = empty($business->pos_settings)
            ? app(BusinessUtil::class)->defaultPosSettings()
            : (json_decode((string) $business->pos_settings, true) ?: []);
        $liga = fn (string $k): bool => ! empty($pos[$k]);

        return [
            'dataEditavel' => $liga('enable_transaction_date'),
            'descontoDaVenda' => ! $liga('disable_discount'),
            'descontoPadrao' => (float) ($business->default_sales_discount ?? 0),
            'impostoDoPedido' => ! $liga('disable_order_tax'),
            'precoMinimo' => $liga('enable_msp'),
            'venderSemEstoque' => $liga('allow_overselling'),
            'comissionado' => [
                'tipo' => $business->sales_cmsn_agnt ?: null,
                'obrigatorio' => $liga('is_commission_agent_required'),
            ],
            'prazoObrigatorio' => $liga('is_pay_term_required'),
            'rascunho' => ! $liga('disable_draft'),
            'aPrazo' => ! $liga('disable_credit_sale_button'),
            'recorrente' => ! $liga('disable_recurring_invoice'),
            'arredondamento' => (float) ($pos['amount_rounding_method'] ?? 0),
            // 1 = lançar o mesmo produto soma na linha; 0 = linha nova (pos.js, pos_product_row).
            'somaNaLinha' => (int) $business->item_addition_method === 1,
        ];
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

            /*
             * Clientes da CENA — a consulta de clientes (modal 880px do handoff).
             *
             * Mora aqui, e não no .tsx, pela mesma regra das transportadoras: "nenhuma
             * lista de domínio nasce em arquivo de UI" (README do bundle `venda-v3`).
             * A primeira linha é o MESMO cadastro de `cliente` acima — é o padrão do
             * balcão, e a consulta precisa poder voltar pra ele depois de trocar.
             *
             * ⚠️ NÃO é consulta ao banco: o preview não lê `contacts`. Por isso não há
             * query a escopar por `business_id` (ADR 0093 satisfeito por ausência).
             * A copy do rodapé diz "cadastros ativos no business atual" porque é o que
             * a tela real fará — aqui a lista é fixa.
             *
             * ⚠️ DOCUMENTOS: todos SINTÉTICOS, com dígito verificador **medido inválido**
             * (validador com controle positivo e negativo, 2026-08-11). O CNPJ que o
             * protótipo usa no cliente Governo foi MEDIDO VÁLIDO — isto é, é documento
             * real — e por isso NÃO entrou aqui, nem em comentário: a allowlist do
             * `pii-scan` é explícita que "CPF/CNPJ real JAMAIS entra". Pelo mesmo motivo
             * o nome do órgão não é o de uma prefeitura existente, e "Rota Livre" do
             * protótipo saiu daqui — é o cliente-piloto de verdade (biz=4), não
             * personagem de cena.
             */
            'clientes' => [
                [
                    'cod' => '0001', 'nome' => 'Consumidor final', 'padrao' => true,
                    'doc' => '—', 'ie' => 'ISENTO', 'contrib' => 'nao', 'regime' => 'Simples Nacional',
                    'fone' => '—', 'email' => '—', 'emailNfe' => '—', 'contato' => '—',
                    'endereco' => 'Venda no balcão — sem endereço de entrega',
                    'cidade' => 'Termas do Gravatal', 'uf' => 'SC',
                    'grupo' => 'Varejo', 'prazo' => 'À vista', 'tabela' => null,
                ],
                [
                    'cod' => '0142', 'nome' => 'Autarquia de Saneamento Serra Verde', 'padrao' => false,
                    'doc' => '29.417.508/0001-62', // pii-allowlist — CNPJ de CENA, DV inválido (medido 2026-08-11)
                    'ie' => '255.618.240', 'contrib' => 'isento', 'regime' => 'Órgão público',
                    'fone' => '(47) 3431-3200', 'email' => 'compras@serraverde.exemplo.br',
                    'emailNfe' => 'nfe@serraverde.exemplo.br', 'contato' => 'Sandra Küster · compras',
                    'endereco' => 'Rua XV de Novembro, 1400 — Centro · 89201-601',
                    'cidade' => 'Joinville', 'uf' => 'SC',
                    'grupo' => 'Governo', 'prazo' => '30 dias', 'tabela' => 'Governo 2026 — pregão 041/2026',
                ],
                [
                    'cod' => '0288', 'nome' => 'Atacado Vale do Itajaí Ltda', 'padrao' => false,
                    'doc' => '41.882.507/0001-44', // pii-allowlist — CNPJ de CENA, DV inválido (medido 2026-08-11)
                    'ie' => '254.099.771', 'contrib' => 'sim', 'regime' => 'Simples Nacional',
                    'fone' => '(47) 99812-4470', 'email' => 'financeiro@valedoitajai.exemplo.br',
                    'emailNfe' => 'fiscal@valedoitajai.exemplo.br', 'contato' => 'Rodrigo Bastos · sócio',
                    'endereco' => 'Rod. BR-470, km 62 — Itoupava · 89066-000',
                    'cidade' => 'Blumenau', 'uf' => 'SC',
                    'grupo' => 'Atacado', 'prazo' => '28 dias', 'tabela' => 'Atacado — a partir de 50m²',
                ],
                [
                    'cod' => '0391', 'nome' => 'Marina Bordignon', 'padrao' => false,
                    'doc' => '045.882.119-30', // pii-allowlist — CPF de CENA, DV inválido (medido 2026-08-11)
                    'ie' => '—', 'contrib' => 'nao', 'regime' => 'Pessoa física',
                    'fone' => '(47) 98844-1207', 'email' => 'marina.bordignon@exemplo.br',
                    'emailNfe' => 'marina.bordignon@exemplo.br', 'contato' => 'Marina Bordignon',
                    'endereco' => 'Rua Blumenau, 890 — América · 89204-250',
                    'cidade' => 'Joinville', 'uf' => 'SC',
                    'grupo' => 'Varejo', 'prazo' => 'À vista', 'tabela' => null,
                ],
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

            /*
             * Transportadoras da CENA (onda 2 · CU-SELL-11).
             *
             * Mora aqui, e não no .tsx, porque a fonte de design é explícita: "nenhuma
             * lista de domínio nasce em arquivo de UI" (README do bundle `venda-v3`).
             * O protótipo contradiz a própria regra e declara a lista dentro do
             * `sells-entrega.jsx` — aqui ela volta pro lugar certo.
             *
             * São dados de cena, NÃO consulta ao banco: o preview não lê `contacts`
             * nem `contact_addresses`. Essa distinção é o que separa esta onda do
             * PR #2104, revertido em 2026-06-02 justamente porque passou a fazer
             * `->with(['addresses'])` num endpoint compartilhado com o Blade.
             */
            'transportadoras' => [
                ['cod' => '014', 'nome' => 'Transportadora Sul Ltda', 'doc' => '84.512.330/0001-07', 'uf' => 'SC', 'cidade' => 'Joinville', 'placa' => 'QHA5F21', 'antt' => '58412330', 'modal' => 'Rodoviário'], // pii-allowlist — CNPJ de CENA, DV inválido (medido): não é documento real
                ['cod' => '027', 'nome' => 'Rodoviário Bordignon Transportes ME', 'doc' => '11.204.877/0001-55', 'uf' => 'SC', 'cidade' => 'Blumenau', 'placa' => 'MKL2B88', 'antt' => '41120487', 'modal' => 'Rodoviário'], // pii-allowlist — CNPJ de CENA, DV inválido (medido): não é documento real
                ['cod' => '031', 'nome' => 'Expresso Norte Catarinense S/A', 'doc' => '02.998.140/0001-92', 'uf' => 'PR', 'cidade' => 'Curitiba', 'placa' => 'BEE7J45', 'antt' => '30299814', 'modal' => 'Rodoviário'], // pii-allowlist — CNPJ de CENA, DV inválido (medido): não é documento real
                ['cod' => '045', 'nome' => 'Frota própria — Office Impresso', 'doc' => '—', 'uf' => 'SC', 'cidade' => 'Joinville', 'placa' => 'RJP1A09', 'antt' => '—', 'modal' => 'Frota própria'], // pii-allowlist — CNPJ de CENA, DV inválido (medido): não é documento real
                ['cod' => '052', 'nome' => 'Log Fácil Entregas Rápidas Eireli', 'doc' => '38.771.905/0001-13', 'uf' => 'SC', 'cidade' => 'Joinville', 'placa' => 'SDA9C77', 'antt' => '73877190', 'modal' => 'Motoboy'], // pii-allowlist — CNPJ de CENA, DV inválido (medido): não é documento real
            ],
        ];
    }
}
