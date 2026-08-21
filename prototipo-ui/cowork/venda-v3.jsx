// venda-v3.jsx — VENDA V3 DE PRODUÇÃO (git SSOT · resources/js/Pages/Sells/CreateV3.tsx + _components/v3/*)
// Build espelhado de prototipo-ui/cowork/venda-v3/ no main. Concatenado num único IIFE:
// os arquivos originais eram scripts irmãos que conversavam por globais — aqui o escopo é
// compartilhado sem vazar nome nenhum pro host (evita colidir com Button/Sec/Grid/brl/Icon do shell).
// Só `window.VendaV3Create` sai. Nada de .html novo (CLAUDE.md · app único).
(() => {
const CuDrawer = () => null; /* sells-roteiro.jsx não veio no handoff do git — modo produção não usa CU */
/* ── sells-data.js ── */
/* Dados do guia de produção · Família de telas de Venda — derivados do SDD v1.0
   (uploads/SDD-tela-venda-v1.0.md · domínio Sells). Estado (ok|parcial|falta|nv) é CURADO:
   sai do veredito da lane, nunca de leitura de código. */
window.SD = (() => {
  const telas = [
    { key: 'create', rota: '/sells/create', page: 'Create.tsx · 2004 L', contrato: 'Create.casos.md', pt: 'Cockpit V2 · form (PT-02)', papel: 'Tela-âncora: adicionar venda completa. Substitui sell/create.blade.php (998 L) — a Blade é o baseline de paridade VIVO enquanto a flag useV2SellsCreate existir.' },
    { key: 'index', rota: '/sells', page: 'Index.tsx · 1811 L', contrato: 'Index.casos.md', pt: 'PT-01 Lista + drawer (list-detail)', papel: 'Lista e cobrança — "quem está devendo?". Payload por /sells-list-json; drawer por /sells/{id}/sheet-data. É também a porta do pipeline FSM e da devolução.' },
    { key: 'edit', rota: '/sells/{id}/edit', page: 'Edit.tsx · 1100 L', contrato: null, pt: 'Cockpit V2 · form (PT-02)', papel: 'Editar venda já emitida. SEM casos.md — nenhum CU a defende hoje; o que ela pode mexer é valor/estoque em produção. Prioridade 2 do §10.' },
    { key: 'show', rota: '/sells/{id}', page: 'Show.tsx · 772 L', contrato: null, pt: 'PT-03 Detalhe', papel: 'Ficha da venda (read-only) + timeline do FSM. SEM casos.md.' },
    { key: 'caixa', rota: '/vendas/caixa', page: 'Caixa/Index.tsx · 364 L', contrato: null, pt: 'PT-05 Dashboard', papel: 'Caixa do dia (inertiaCaixa). Números de dinheiro agregados na cara do operador — e sem contrato. SEM casos.md.' },
    { key: 'subs', rota: '/sells/subscriptions', page: 'Subscriptions.tsx · 326 L', contrato: null, pt: 'PT-01 Lista', papel: 'Assinatura/recorrência. NG-01: não entra no Create. SEM casos.md — e o módulo recorrente nunca foi auditado.' },
    { key: 'quotations', rota: '/sells/quotation/create', page: 'Quotations.tsx', contrato: null, pt: 'PT-01 Lista', papel: 'Cotações (FSM quote_draft). NG-04: fluxo próprio, fora do Create. SEM casos.md.' },
    { key: 'drafts', rota: '/sells/drafts', page: 'Drafts.tsx', contrato: null, pt: 'PT-01 Lista', papel: 'Rascunhos — a outra ponta do auto-save por {biz}.{user} (CU-SELL-13). SEM casos.md.' },
  ];

  const cus = [
    /* 6.1 · Núcleo da venda — mapeia CASOS-USO-CREATE-VENDA.md */
    { id: 'CU-SELL-01', t: 'Vender pra cliente cadastrado', peso: 'must', e: 'ok', legado: 'CU-01', uc: 'UC-SCRE-*', telas: ['create', 'edit'],
      dado: 'Dado um cliente cadastrado; quando selecionado na venda; então grupo de preço, prazo de pagamento e endereço são auto-aplicados sem digitação.',
      itens: [['must V0', 'Grupo de preço do cliente reprecifica as linhas já lançadas', 'ok'], ['must', 'Prazo de pagamento e endereço vêm do cadastro', 'ok'], ['T0', 'CustomerSearchAutocomplete escopado por business_id', 'ok'], ['', 'Resíduo declarado: o autocomplete NÃO migra pro canon Command (decisão [W] 2026-07-15 · §5 proibicoes.md)', 'ok']] },
    { id: 'CU-SELL-02', t: 'Vender pra walk-in ("Cliente padrão")', peso: 'must', e: 'ok', legado: 'CU-02', telas: ['create'],
      itens: [['must', 'Cliente padrão do business pré-selecionado ao abrir', 'ok'], ['', 'Venda fecha sem nenhum dado de cliente digitado', 'ok']] },
    { id: 'CU-SELL-03', t: 'Cadastrar cliente inline sem sair da venda', peso: 'must', e: 'ok', legado: 'CU-03', telas: ['create'],
      itens: [['must', 'Modal de cadastro mínimo dentro da venda', 'ok'], ['', 'Cliente criado volta selecionado no seletor de origem', 'ok'], ['T0', 'Carimba o business atual', 'ok']] },
    { id: 'CU-SELL-04', t: 'Buscar produto (nome/SKU/lote/código) + variação', peso: 'must', e: 'ok', legado: 'CU-04', telas: ['create'],
      itens: [['must', 'Busca por nome · SKU · lote · código de barras', 'ok'], ['must', 'Escolha de variação antes de entrar na tabela', 'ok'], ['', 'Resíduo: ProductSearchAutocomplete fica fora do canon Command — carrega o hotfix do sufixo', 'ok'], ['T0', 'Busca só alcança produto do business', 'ok']] },
    { id: 'CU-SELL-05', t: 'Editar linha pt-BR — anti-inflação de decimal', peso: 'must', e: 'ok', legado: 'CU-05', uc: 'UC-S02', telas: ['create', 'edit'],
      dado: 'Dado um produto na tabela; quando o operador digita valor com vírgula decimal e aplica desconto percentual; então o final_total calculado é o total real e NUNCA um valor inflado por leitura do ponto decimal como separador de milhar.',
      nota: 'Este CU é o contrato do incidente 2026-06-05 (biz=4 ROTA LIVRE): Util::num_uf leu o ponto decimal como separador de milhar → final_total inflado ~×100.000 em 16 vendas + pagamentos. Fix #2279. Lição perene: separador de milhar tem SEMPRE 3 dígitos; o frontend arredonda a 2 casas no submit.',
      itens: [['must V0', 'Round-trip num_uf(num_f(x)) == x na precisão de moeda', 'ok'], ['must V0', 'Invariante final_total ≤ total_before_tax vale sempre', 'ok'], ['V0', 'Divergência CARACTERIZADA, não unificada: getTotalPaid é líquido (SUM(IF(is_return=0, amount, amount*-1))) e é a fonte do payment_status; getTotalAmountPaid é bruto. Unificar = mudança de valor em prod → US separada sob REGRA MESTRE, nunca carona', 'ok'], ['', 'Teste: tests/Feature/Calculo/CalculoValorSellsTest.php', 'ok'], ['reg', 'Guard de isolamento: tests/Unit/Utils/IncidentValorInfladoNumUfTest.php', 'ok']] },
    { id: 'CU-SELL-06', t: 'Pagamento split + cartão + saldo (falta/troco/exato)', peso: 'must', e: 'ok', legado: 'CU-06', uc: 'UC-S01', telas: ['create', 'show'],
      dado: 'Dado cliente e produto no carrinho; quando salva SEM informar pagamento; então a tela acusa o saldo devedor ANTES do submit e o backend grava payment_status=\'due\' — não bloqueia.',
      nota: 'Decisão [W] 2026-05-27: paridade com o POS Blade, que sempre permitiu finalizar sem pagamento. Venda a prazo (fiado) é caminho normal do balcão, não erro.',
      itens: [['must V0', 'N linhas de pagamento (split) somando ≤ final_total', 'ok'], ['must V0', 'Saldo declarado antes do submit: falta · troco · exato', 'ok'], ['must', 'Fechar sem pagamento grava payment_status=\'due\' e NÃO bloqueia', 'ok'], ['V0', 'Cartão/parcelas não alteram final_total', 'ok']] },
    { id: 'CU-SELL-07', t: 'Desconto do pedido (fixo/%) respeitando permissão', peso: 'must', e: 'ok', legado: 'CU-07', telas: ['create', 'edit'],
      itens: [['must V0', 'Desconto fixo e percentual entram em calculateInvoiceTotal (server authoritative)', 'ok'], ['must', 'Alçada: acima do limite exige permissão', 'ok'], ['V0', 'Desconto % é o vetor do incidente num_uf — arredonda 2 casas no submit', 'ok']] },
    { id: 'CU-SELL-08', t: 'Status da venda (final/rascunho/cotação/proforma)', peso: 'must', e: 'ok', legado: 'CU-08', telas: ['create', 'drafts', 'quotations'],
      itens: [['must', 'Os 4 status na mesma tela, sem rota separada', 'ok'], ['', 'Só status=final entra na lista de cobrança e move estoque', 'ok']] },
    { id: 'CU-SELL-09', t: 'Prazo de pagamento + comissionista', peso: 'should', e: 'ok', legado: 'CU-09', telas: ['create'],
      itens: [['', 'Prazo (dias/vencimento) grava e alimenta o financeiro', 'ok'], ['', 'Comissionista selecionável na venda', 'ok'], ['', 'Política de comissão (recebimento vs faturamento) é decisão [W] em aberto', 'nv']] },
    { id: 'CU-SELL-10', t: 'Esquema/nº fatura + imposto do pedido', peso: 'should', e: 'ok', legado: 'CU-10', telas: ['create'],
      itens: [['', 'Esquema de numeração + nº de fatura manual', 'ok'], ['V0', 'Imposto do pedido entra no total pelo tax_rate_id (server-side)', 'ok']] },
    { id: 'CU-SELL-11', t: 'Frete/entrega (endereço + custo + status remessa)', peso: 'must', e: 'parcial', legado: 'CU-11', gap: 'D-6', telas: ['create', 'edit'],
      nota: 'PARCIAL: hoje é free-text, não estruturado. O frete de 1ª classe era o PR2 #2104, REVERTIDO (incidente 2026-06-02). Re-fazer exige smoke biz=4 antes de religar.',
      itens: [['must', 'Endereço de entrega + custo de frete no total', 'parcial'], ['must V0', 'Custo de frete entra no final_total sem inflar decimal', 'parcial'], ['', 'Status de remessa estruturado (enum), não free-text', 'falta'], ['reg', 'Smoke biz=4 antes de religar (o PR anterior foi revertido)', 'falta']] },
    { id: 'CU-SELL-12', t: 'Notas + despesas adicionais', peso: 'should', e: 'ok', legado: 'CU-12', telas: ['create'],
      itens: [['', 'Nota da venda + nota do balcão (staff note)', 'ok'], ['V0', 'Despesa adicional soma ao total pelo caminho server-side', 'ok']] },
    { id: 'CU-SELL-13', t: 'Salvar + auto-save draft por {biz}.{user}', peso: 'must', e: 'ok', legado: 'CU-13', telas: ['create', 'drafts'],
      dado: 'Dado uma venda em digitação; quando a operadora atende o telefone e a aba morre; então ao reabrir a venda está lá — restaurada do rascunho da própria dupla business+usuário.',
      nota: 'Nasceu da persona P1 (Larissa, ROTA LIVRE biz=4): balcão, monitor 1280px, atende telefone no meio da venda.',
      itens: [['must T0', 'Chave do rascunho é {business_id}.{user_id} — nunca só user', 'ok'], ['must', 'Restaura ao reabrir; descarta ao salvar', 'ok'], ['T0', 'localStorage prefixado oimpresso.*', 'ok'], ['', 'Dívida do protótipo: portar o auto-save do Create.tsx real (D2 do Red Team)', 'nv']] },
    { id: 'CU-SELL-14', t: 'Criar OS a partir da venda (comvis/oficina)', peso: 'should', e: 'ok', legado: 'CU-14', telas: ['create', 'show'],
      itens: [['', 'Venda com item de produção abre OS pelo processo "Venda Com Produção"', 'ok'], ['', 'Ida e volta: a OS aponta a venda de origem', 'ok']] },
    { id: 'CU-SELL-15', t: 'Isolamento multi-tenant nos dropdowns/buscas', peso: 'must', e: 'ok', legado: 'CU-15', telas: ['create', 'index'],
      nota: 'App\\Transaction NÃO tem global scope (padrão UltimatePOS) — o escopo é MANUAL por query. Cada subquery crua é um vetor. É daí que nascem CU-SELL-32 e 33.',
      itens: [['must T0', 'Cliente · produto · localização · comissionista só do business', 'ok'], ['T0', 'Cross-tenant por ID → 404, nunca 500', 'ok'], ['T0 reg', 'ADR 0093 · o escopo é manual: toda query nova declara business_id', 'ok']] },

    /* 6.2 · Pipeline FSM — mapeia CASOS-USO-PIPELINE-VENDAS.md · TODOS "verde impossível" (D-3) */
    { id: 'CU-SELL-20', t: 'Cancelar NF-e não pula sequencial', peso: 'must', e: 'nv', legado: 'CU-01 (G1+G2)', us: 'US-SELL-029/030', telas: ['index', 'show'],
      nota: 'Teste existe em tests/Feature/Domain/Fsm/*, mas NENHUMA lane de CI o executa no PR — "verde impossível" (D-3). A lane criada pelo SDD cobre tests/Feature/Sells, não Domain/Fsm.',
      itens: [['must', 'Cancelamento registra o evento sem consumir o próximo número', 'nv'], ['must', 'Sequencial da série continua contíguo após cancelar', 'nv'], ['reg', 'Precisa de lane pra Domain/Fsm (item 4 do §10)', 'falta']] },
    { id: 'CU-SELL-21', t: 'Action FSM crítica exige role obrigatória (fail-secure)', peso: 'must', e: 'nv', legado: 'CU-02 (G3)', us: 'US-SELL-031', telas: ['index', 'show'],
      itens: [['must T0', 'is_critical sem role → nega (fail-secure), nunca permite por omissão', 'nv'], ['must', 'RBAC lido de sale_stage_action_roles', 'nv'], ['', 'Motivo do bloqueio visível ao operador, não erro genérico', 'nv']] },
    { id: 'CU-SELL-22', t: 'UPDATE direto em current_stage_id é bloqueado', peso: 'must', e: 'nv', legado: 'CU-03 (G4)', us: 'US-SELL-032', telas: ['index'],
      itens: [['must', 'Trait GuardsFsmTransitions barra UPDATE fora do serviço', 'nv'], ['must', 'Único caminho de mudança de estágio: ExecuteStageActionService::execute', 'nv']] },
    { id: 'CU-SELL-23', t: 'Processo "Venda Com Produção" canônico', peso: 'must', e: 'nv', legado: 'CU-04 (G5)', us: 'US-SELL-033', telas: ['index', 'show'],
      itens: [['must', 'Um único caminho de processo (ADR 0104 MWART)', 'nv'], ['', 'Side-effects nomeados: ReservarEstoque · ConsumirEstoque · LiberarReserva', 'nv']] },
    { id: 'CU-SELL-24', t: 'Cancelamento em cascata (CancelarVendaCascade)', peso: 'must', e: 'nv', legado: 'CU-05 (G6)', us: 'US-SELL-034', telas: ['index', 'show'],
      itens: [['must V0', 'Cancelar a venda reverte estoque reservado/consumido', 'nv'], ['must V0', 'Financeiro baixado é revertido — assert antes→depois', 'nv'], ['', 'A cascata é atômica: nada fica meio cancelado', 'nv']] },
    { id: 'CU-SELL-25', t: 'Voltar de estágio exige autorização explícita', peso: 'must', e: 'nv', legado: 'CU-06', us: 'US-031+033', telas: ['index'],
      itens: [['must', 'Retrocesso é ação nomeada com role, não botão livre', 'nv'], ['', 'Motivo obrigatório no retrocesso (fica na history)', 'nv']] },
    { id: 'CU-SELL-26', t: 'Timeline auditável visível ao operador', peso: 'should', e: 'nv', legado: 'CU-07 (G7)', us: 'US-SELL-035', telas: ['show', 'index'],
      itens: [['should', 'sale_stage_history append-only exibida na ficha', 'nv'], ['', 'Quem · quando · de onde pra onde · efeitos colaterais', 'nv']] },

    /* 6.3 · Lista e cobrança */
    { id: 'CU-SELL-30', t: 'Enxergar a cobrança num relance', peso: 'must', e: 'ok', uc: 'UC-S10', telas: ['index'],
      dado: 'Dado a lista carregada; então o título Vendas renderiza e as pílulas de status por pagamento aparecem (default Todas; pagas/a-receber derivadas de payment_status).',
      itens: [['must ux', 'Pílulas por payment_status · default Todas', 'ok'], ['ux', 'Cabe em 1280px sem scroll horizontal (persona P1)', 'ok'], ['V0', 'Rodapé com sum_final_total / sum_total_paid', 'ok']] },
    { id: 'CU-SELL-31', t: 'Reconhecer, na lista, o que já teve devolução', peso: 'must', e: 'ok', uc: 'UC-S11 + UC-S12', telas: ['index'],
      dado: 'Dado uma transação sell_return com return_parent_id apontando pra venda; quando a lista carrega; então o payload traz has_return: true e a linha ganha o indicador de retorno.',
      nota: 'REGRESSÃO QUE DEFENDE: a "setinha de retorno" existia no Blade (SellController@index → return_exists, fa-undo) e SUMIU no rewrite Cowork #1032. Incidente 2026-07-03, reportado por Guilherme @ biz=4. Voltou como contrato.',
      itens: [['must', 'Indicador de retorno na linha quando has_return', 'ok'], ['must reg', 'Ponto de entrada da devolução no menu ⋮ → /sell-return/add/{id}', 'ok'], ['', 'NG-02: a devolução NÃO acontece no Create — fluxo separado', 'ok']] },
    { id: 'CU-SELL-32', t: 'O indicador de devolução só conta devoluções do mesmo business', peso: 'must', e: 'nv', uc: 'UC-SIDX-01', gap: 'D-1', telas: ['index'],
      dado: 'Dado uma venda do business A; quando existe uma transação sell_return de OUTRO business cujo return_parent_id aponta pra ela; então o payload da lista de A traz has_return: false.',
      nota: 'Dois sites medidos sem escopo: SellController@inertiaList (subquery DB::raw) e TransactionUtil::getSellsCurrentFy (leftjoin transactions as SR). Limite honesto de severidade: return_parent_id não é controlado pelo usuário no fluxo normal → NÃO há vazamento provado; é gap de defesa em profundidade. Corrigir a query é decisão [W] (Tier 0).',
      itens: [['must T0', 'Derivação de has_return escopada por business_id (ADR 0093)', 'falta'], ['T0', 'Vale nos DOIS sites: inertiaList e getSellsCurrentFy', 'falta'], ['', 'Por que não é automático: App\\Transaction não tem global scope — nada supre a subquery crua', 'falta'], ['reg', 'Estado ⬜ não verificado: o contrato nasce no PR do SDD; o veredito é da lane', 'nv']] },
    { id: 'CU-SELL-33', t: 'O totalizador do rodapé não soma venda de outro business', peso: 'must', e: 'nv', uc: 'UC-SIDX-02', gap: 'D-1', telas: ['index', 'caixa'],
      dado: 'Dado uma venda final de valor conhecido no business B; quando o operador do business A carrega a lista; então sum_final_total / sum_total_paid de A NÃO variam por causa dela.',
      nota: 'O que este CU NÃO afirma: que o agregado seja igual à soma das linhas retornadas. Medido: $totalsQuery é query separada, SEM o limit da listagem — cobre o conjunto filtrado inteiro, não a página. Afirmar "soma == linhas visíveis" geraria falso-vermelho contra comportamento correto. O contrato é o ESCOPO, não a paginação.',
      itens: [['must V0', 'Número de dinheiro exibido → REGRA MESTRE: assert antes→depois', 'falta'], ['must T0', 'O agregado herda o escopo business_id da query base', 'falta'], ['', 'NÃO é contrato: agregado == soma da página visível', 'ok'], ['reg', 'Estado ⬜ não verificado — contrato novo', 'nv']] },
  ];

  /* Non-goals · §6.4 — decisão de produto, o teste não cobra */
  const nonGoals = [
    ['NG-01', 'Assinatura/recorrência no Create', 'Non-Goal', 'vive na tela Sells/Subscriptions'],
    ['NG-02', 'Devolução no Create', 'Non-Goal', 'fluxo separado (/sell-return/add/{id})'],
    ['NG-03', 'POS rápido', 'Non-Goal', 'vai pra /sale-pos/create'],
    ['NG-04', 'Cotação', 'Non-Goal', '/sells/quotation/create (FSM quote_draft)'],
    ['NG-05', 'Print direto', 'Non-Goal', 'rota Blade separada /sells/{id}/print'],
    ['NG-06', 'Resgate de pontos (reward)', 'gap', 'UPOS legado; sem sinal de cliente (ADR 0105)'],
    ['NG-07', 'Anexar documento à venda', 'gap', 'idem'],
    ['NG-08', 'Tipos de serviço (multi-select)', 'a avaliar', 'prop typesOfService existe, sem UI na V2 — decisão [W] PENDENTE'],
  ];

  /* Dívidas · §9 — exatamente como estão no SDD */
  const dividas = [
    { id: 'D-1', t: 'has_return deriva de subquery/JOIN não escopado por business_id', sev: 'alta', cu: ['CU-SELL-32', 'CU-SELL-33'],
      med: 'Dois sites: SellController@inertiaList (subquery DB::raw) e TransactionUtil::getSellsCurrentFy (leftjoin SR). grep -n "addGlobalScope" app/Transaction.php = 0.',
      lim: 'Limite honesto: return_parent_id não é controlado pelo usuário no fluxo normal → não há vazamento provado. É defesa em profundidade.',
      dest: 'Decisão [W] — mudar query de produção é Tier 0. O contrato (CU-SELL-32) é que denuncia. Item 3 do §10.' },
    { id: 'D-2', t: 'POST /sells é um endpoint vivo que não faz nada', sev: 'média', cu: [],
      med: 'SellController@store tem corpo //; Route::resource(\'sells\', …)->except([\'show\']) registra POST /sells de qualquer forma. 200 com corpo vazio.',
      lim: 'O writer real é POST /pos (SellPosController@store).',
      dest: 'Reportado, não corrigido — mexer em rota de venda é Tier 0. Risco: integração futura aponta pro endpoint errado e "salva" nada.' },
    { id: 'D-3', t: '12 US com teste fora de lane ("verde impossível")', sev: 'alta', cu: ['CU-SELL-20', 'CU-SELL-21', 'CU-SELL-22', 'CU-SELL-23', 'CU-SELL-24', 'CU-SELL-25', 'CU-SELL-26'],
      med: 'anchor-lint acusa US-SELL-011..014 e 029..036 — todas apontando tests/Feature/Domain/Fsm/*, que não está em lane nenhuma.',
      lim: 'A lane criada pelo SDD cobre tests/Feature/Sells, NÃO Domain/Fsm (escopo compartilhado).',
      dest: 'Lane pra Domain/Fsm — item 4 do §10.' },
    { id: 'D-4', t: 'A porta viva requisitos-status.mjs não enxerga os UC de Sells', sev: 'baixa', cu: [],
      med: 'O extrator exige 3 segmentos (UC-[A-Z0-9]{2,10}-\\d{2,3}). Sells é o único módulo com 2 segmentos (UC-S01) → seus 5 UC reais são reportados como inexistentes.',
      lim: 'scripts/ está fora da área do chip.',
      dest: 'Mitigação aplicada: todo UC novo nasce com 3 segmentos (UC-SCRE-*, UC-SIDX-*); os antigos ficam — renomear quebraria a citação dos testes que já os defendem.' },
    { id: 'D-5', t: 'Colisão de namespace entre os dois docs canon de CU', sev: 'baixa', cu: [],
      med: 'CASOS-USO-CREATE-VENDA.md e CASOS-USO-PIPELINE-VENDAS.md usam ambos CU-01..07 para casos DIFERENTES.',
      lim: 'Resolvido forward-only pelo namespace CU-SELL-NN do §6.',
      dest: 'Os docs antigos seguem como referência histórica; ids antigos não são reusados.' },
    { id: 'D-6', t: 'CU-SELL-11 (frete estruturado) segue parcial', sev: 'média', cu: ['CU-SELL-11'],
      med: 'Era o PR2 #2104, revertido (incidente 2026-06-02). Hoje o frete é free-text.',
      lim: '—',
      dest: 'Re-fazer exige smoke biz=4 antes de religar. Item 5 do §10.' },
  ];

  /* Ondas — eixo escolhido: RISCO TIER 0 primeiro, ancorado no gate de cutover do §8.2.
     Ordem: instrumentar (a lane) → blindar valor [V0] → blindar tenant [T0] → paridade da lista →
     destravar FSM → fechar contrato das telas órfãs e virar a flag. */
  const ondas = [
    { id: 1, nome: 'Instrumentar antes de tocar', obj: 'Nada de valor/estoque se mexe às cegas. Primeiro a lane vê o teste.', telas: ['create', 'index'],
      gate: 'Lane sells-pest verde e estável em 3 execuções seguidas (advisory, fora do required-checks-baseline). Reprova visível, não bloqueia merge.',
      itens: [
        { id: 'O1-1', t: 'Lane sells-pest.yml no ar: MySQL real + seed biz=1/biz=2, espelhando compras-pest.yml', ent: '.github/workflows/sells-pest.yml + allowlist explícita', cus: [] },
        { id: 'O1-2', t: 'Tirar os 72 arquivos de tests/Feature/Sells do escuro do nightly', ent: 'Relatório JUnit da lane com os 72 arquivos enumerados', cus: [] },
        { id: 'O1-3', t: 'Baseline medido das 3 portas (roda em algum lugar? roda no PR? bloqueia merge?)', ent: 'Tabela §8.1 re-rodada e colada no PR', cus: [] },
        { id: 'O1-4', t: 'Smoke biz=1 do caminho F1 (Create → POST /pos) antes de qualquer refactor', ent: 'Roteiro de smoke em biz=1 (nunca biz=4 · ADR 0101)', v0: true, cus: ['CU-SELL-06'] },
      ] },
    { id: 2, nome: 'Blindar o valor [V0]', obj: 'O incidente 2026-06-05 não pode voltar. Todo caminho de dinheiro sob dupla-confirmação.', telas: ['create', 'edit'],
      gate: 'CU-SELL-05/06/07 verdes na lane + assert antes→depois em cada item [V0] + OK explícito [W]. Nenhum float locale-ambíguo sai do frontend.',
      itens: [
        { id: 'O2-1', t: 'Guard de parse pt-BR no submit: arredonda 2 casas, nunca manda float ambíguo', ent: 'CalculoValorSellsTest cobrindo round-trip num_uf(num_f(x)) == x', v0: true, cus: ['CU-SELL-05'] },
        { id: 'O2-2', t: 'Invariante final_total ≤ total_before_tax como assert de suíte, não comentário', ent: 'Teste de invariante + guard IncidentValorInfladoNumUfTest', v0: true, cus: ['CU-SELL-05'] },
        { id: 'O2-3', t: 'Saldo devedor declarado na tela ANTES do submit (falta/troco/exato)', ent: 'UC-S01 verde: split + cartão + fechar sem pagamento → payment_status=due', v0: true, cus: ['CU-SELL-06'] },
        { id: 'O2-4', t: 'Alçada de desconto: acima do limite exige permissão, e o % passa pelo guard de decimal', ent: 'Teste de alçada + teste de desconto % no caminho server-side', v0: true, cus: ['CU-SELL-07'] },
        { id: 'O2-5', t: 'Caracterizar (não unificar) getTotalPaid líquido × getTotalAmountPaid bruto', ent: 'Teste de caracterização + US separada aberta pra eventual unificação', v0: true, cus: ['CU-SELL-05'] },
      ] },
    { id: 3, nome: 'Blindar o tenant [T0]', obj: 'App\\Transaction não tem global scope — cada query crua é um vetor. Fechar por contrato.', telas: ['index', 'create', 'caixa'],
      gate: 'CU-SELL-32 e 33 saem de ⬜ com veredito da lane. Zero vazamento cross-tenant no smoke de dois businesses (biz=1 × biz=2).',
      itens: [
        { id: 'O3-1', t: 'Contrato UC-SIDX-01: has_return só conta devolução do mesmo business', ent: 'Teste cross-tenant nos dois sites (inertiaList + getSellsCurrentFy)', cus: ['CU-SELL-32'] },
        { id: 'O3-2', t: 'Contrato UC-SIDX-02: sum_final_total / sum_total_paid não variam por venda de outro business', ent: 'Teste antes→depois do agregado, sem afirmar soma == página', v0: true, cus: ['CU-SELL-33'] },
        { id: 'O3-3', t: 'Decisão [W] sobre escopar a query de has_return (D-1)', ent: 'ADR ou registro de "aceito como defesa em profundidade pendente"', cus: ['CU-SELL-32'] },
        { id: 'O3-4', t: 'Varredura dos dropdowns/buscas do Create por escopo declarado', ent: 'CU-SELL-15 verde na lane', cus: ['CU-SELL-15'] },
        { id: 'O3-5', t: 'Reportar POST /sells vazio ao consumidor (D-2) — não corrigir sem decisão', ent: 'Nota no charter + aviso na doc de integração', cus: [] },
      ] },
    { id: 4, nome: 'Paridade da lista e da cobrança', obj: 'A Blade é o baseline vivo (MWART). Feature não some sem Non-Goal explícito.', telas: ['index'],
      gate: 'CU-SELL-30/31 verdes + a "setinha de retorno" presente na linha + smoke de cobrança com Guilherme/Kamila @ biz=4.',
      itens: [
        { id: 'O4-1', t: 'Pílulas de payment_status com default Todas + rodapé de totais', ent: 'UC-S10 verde', cus: ['CU-SELL-30'] },
        { id: 'O4-2', t: 'Indicador de retorno na linha + entrada da devolução no menu ⋮', ent: 'UC-S11 + UC-S12 verdes (a regressão do rewrite #1032 fechada)', cus: ['CU-SELL-31'] },
        { id: 'O4-3', t: 'Diff de paridade contra sell/create.blade.php (998 L) — a homônima certa', ent: 'Checklist de paridade; o que falta vira CU ou Non-Goal, nunca silêncio', cus: ['CU-SELL-11'] },
        { id: 'O4-4', t: 'Cabe em 1280px sem scroll horizontal (persona P1 Larissa)', ent: 'Gate visual do PRE-MERGE-UI em 1280×1024', cus: ['CU-SELL-30'] },
      ] },
    { id: 5, nome: 'Destravar o pipeline FSM', obj: 'Os 7 CU de FSM têm teste e não têm lane. Sair do "verde impossível".', telas: ['index', 'show'],
      gate: 'Lane pra tests/Feature/Domain/Fsm no ar e os 7 CU com veredito real (não "nv"). Nenhuma mudança de estágio fora do ExecuteStageActionService.',
      itens: [
        { id: 'O5-1', t: 'Lane pra tests/Feature/Domain/Fsm (D-3 · escopo compartilhado, exige acordo)', ent: 'Workflow novo + 12 US saindo do anchor-lint', cus: ['CU-SELL-20', 'CU-SELL-22'] },
        { id: 'O5-2', t: 'RBAC fail-secure: action is_critical sem role NEGA', ent: 'CU-SELL-21 verde + motivo do bloqueio visível na UI', cus: ['CU-SELL-21'] },
        { id: 'O5-3', t: 'Cascata de cancelamento reverte estoque e financeiro, atômica', ent: 'CU-SELL-24 verde com assert antes→depois', v0: true, cus: ['CU-SELL-24'] },
        { id: 'O5-4', t: 'Retrocesso de estágio com autorização + motivo na history', ent: 'CU-SELL-25 verde', cus: ['CU-SELL-25'] },
        { id: 'O5-5', t: 'Timeline append-only visível ao operador na ficha', ent: 'CU-SELL-26 verde (should)', cus: ['CU-SELL-26', 'CU-SELL-23'] },
      ] },
    { id: 6, nome: 'Fechar o trio e virar a flag', obj: 'As 6 telas sem casos.md ganham contrato; só então o cutover.', telas: ['edit', 'show', 'caixa', 'subs', 'quotations', 'drafts'],
      gate: 'REGRA DE CUTOVER (decisão A do §8.2): religar a V2 só quando TODO CU must estiver verde na lane + smoke biz=4 (PRE-MERGE-UI Camada 4).',
      itens: [
        { id: 'O6-1', t: 'casos.md para Edit e Show — as duas que mexem/exibem valor de venda emitida', ent: 'Edit.casos.md + Show.casos.md com UC de 3 segmentos', v0: true, cus: [] },
        { id: 'O6-2', t: 'casos.md para Caixa (números agregados na cara do operador)', ent: 'Caixa.casos.md · UC de agregado sob [V0]+[T0]', v0: true, cus: ['CU-SELL-33'] },
        { id: 'O6-3', t: 'casos.md para Subscriptions · Quotations · Drafts', ent: '3 contratos + auditoria KB-9.75 do módulo recorrente (blind spot)', cus: ['CU-SELL-08', 'CU-SELL-13'] },
        { id: 'O6-4', t: 'Retomar CU-SELL-11 (frete de 1ª classe) com smoke biz=4', ent: 'PR novo do PR2 #2104 revertido + status de remessa estruturado', v0: true, cus: ['CU-SELL-11'] },
        { id: 'O6-5', t: 'Decidir NG-08 (tipos de serviço) — prop existe, UI não', ent: 'Decisão [W]: vira CU ou vira Non-Goal fechado', cus: [] },
        { id: 'O6-6', t: 'Destilar ANTI-REGRESSAO-venda-legacy.md (a 4ª perna que não existe)', ent: 'Doc de paridade do Office Comercial — hoje GAP DECLARADO', cus: [] },
        { id: 'O6-7', t: 'Cutover: flag useV2SellsCreate por business → smoke biz=1 → canary → ON', ent: 'Registro de rollout + rollback ensaiado', cus: [] },
      ] },
  ];

  const tier0 = [
    ['REGRA MESTRE valor/estoque', 'Todo CU que toca preço · total · desconto · final_total · num_uf · estoque é [V0]: exige dupla-confirmação por 2 caminhos + tabela antes→depois + OK do [W]. Fonte: proibicoes.md.'],
    ['Multi-tenant business_id (ADR 0093)', 'App\\Transaction NÃO tem global scope (padrão UltimatePOS) — o escopo é MANUAL por query. Cada subquery crua é um vetor (§9 D-1).'],
    ['FSM canônica (ADR 0143)', 'Mudança de estágio só via ExecuteStageActionService. UPDATE direto em current_stage_id é bloqueado pelo trait GuardsFsmTransitions.'],
    ['MWART (ADR 0104)', 'A Blade sell/create.blade.php (998 L) é o baseline de paridade VIVO. Feature não some sem Non-Goal explícito.'],
  ];

  const nfr = [
    ['First-paint p95', '< 1200 ms (Create.charter.md)'],
    ['Save click → response', '< 800 ms (Create.charter.md)'],
    ['Viewport', '1280px sem scroll horizontal (persona P1 Larissa)'],
    ['Erros JS no console', '0 (Create.charter.md)'],
    ['Props caras', 'Inertia::defer por default (RUNBOOK-inertia-defer-pattern)'],
    ['Isolamento', '0 vazamento cross-tenant (ADR 0093)'],
  ];

  const rollout = [
    'Flag useV2SellsCreate no GrowthBook self-hosted, avaliada POR BUSINESS.',
    'Smoke biz=1 (WR2 SC) — nunca biz=4 no teste (ADR 0101).',
    'Canary com um business real acompanhado.',
    'Cutover (decisão A): só religar a V2 quando TODO CU must estiver verde na lane + smoke biz=4 (PRE-MERGE-UI Camada 4).',
    'Enquanto a flag existir, sell/create.blade.php é código VIVO — paridade é obrigação, não arqueologia.',
  ];

  const portas = [
    ['roda em algum lugar?', 'phpunit.xml + shards-plan.mjs', 'sim — enumerado como shard, roda no full-suite nightly do CT 100', 'ok'],
    ['roda no PR?', 'allowlist dos workflows + .github/ci-sqlite-pest.list', 'não — grep -rn "Feature/Sells" .github/ = 0. A lane nova resolve (advisory)', 'parcial'],
    ['bloqueia merge?', 'governance/required-checks-baseline.json', 'não — nenhuma entrada Sells. A lane nasce FORA do baseline, de propósito', 'parcial'],
  ];

  const suites = ['CalculoValorSellsTest [V0]', 'IncidentValorInfladoNumUfTest [reg]', 'sells-pest.yml (lane nova · advisory)', 'tests/Feature/Sells (72 arquivos)', 'tests/Feature/Domain/Fsm (sem lane · D-3)', 'anchor-lint.mjs', 'requisitos-status.mjs', 'PRE-MERGE-UI Camada 4'];

  const personas = [
    ['P1 · Larissa — ROTA LIVRE (biz=4)', '99% do volume de vendas do oimpresso novo. Balcão, monitor 1280px, atende telefone no meio da venda (origem do auto-save draft, CU-SELL-13). É quem sofre primeiro qualquer erro de valor.'],
    ['P2 · Wagner — WR2 SC (biz=1)', 'Operador-dono e cobaia segura. Todo smoke/Pest usa biz=1, nunca biz=4 (ADR 0101).'],
    ['P3 · Guilherme / Kamila @ biz=4', 'Operadores de retaguarda. Reportaram o incidente da "setinha de retorno" (2026-07-03) que virou UC-S12.'],
  ];

  /* Dados de cena — venda de balcão realista (biz=1 WR2 SC) */
  const clientes = [
    { id: 1, cod: '0001', nome: 'Consumidor final', im: '—', regime: 'Consumidor', contato: '—', nascimento: '', creditoIcms: false, issRetido: false, rural: false, emailNfe: '—', doc: '—', tipo: 'pf', grupo: 'Varejo', prazo: 'À vista', padrao: true, tabela: null,
      ie: 'ISENTO', contrib: 'nao', fone: '—', email: '—', cidade: 'Joinville', uf: 'SC', endereco: 'Venda no balcão — sem endereço de entrega' },
    { id: 2, cod: '0142', nome: 'Prefeitura de Joinville', im: '00.412.775', regime: 'Órgão público', contato: 'Sandra Küster · compras', nascimento: '', creditoIcms: false, issRetido: true, rural: false, emailNfe: 'nfe@joinville.sc.gov.br', doc: '83.169.623/0001-10', tipo: 'pj', grupo: 'Governo', prazo: '30 dias', tabela: 'Governo 2026 — pregão 041/2026',
      ie: '255.618.240', contrib: 'isento', fone: '(47) 3431-3200', email: 'compras@joinville.sc.gov.br', cidade: 'Joinville', uf: 'SC', endereco: 'Rua XV de Novembro, 1400 — Centro · 89201-601' },
    { id: 3, cod: '0288', nome: 'Rota Livre Comércio', im: '55.109.220', regime: 'Simples Nacional', contato: 'Rodrigo Bastos · sócio', nascimento: '', creditoIcms: true, issRetido: false, rural: false, emailNfe: 'fiscal@rotalivre.com.br', doc: '41.882.507/0001-44', tipo: 'pj', grupo: 'Atacado', prazo: '28 dias', tabela: 'Atacado — a partir de 50m²',
      ie: '254.099.771', contrib: 'sim', fone: '(47) 99812-4470', email: 'financeiro@rotalivre.com.br', cidade: 'Blumenau', uf: 'SC', endereco: 'Rod. BR-470, km 62 — Itoupava · 89066-000' },
    { id: 4, cod: '0391', nome: 'Marina Bordignon', im: '—', regime: 'Pessoa física', contato: 'Marina Bordignon', nascimento: '1988-03-14', creditoIcms: false, issRetido: false, rural: false, emailNfe: 'marina.bordignon@gmail.com', doc: '045.882.119-30', tipo: 'pf', grupo: 'Varejo', prazo: 'À vista', tabela: null,
      ie: '—', contrib: 'nao', fone: '(47) 98844-1207', email: 'marina.bordignon@gmail.com', cidade: 'Joinville', uf: 'SC', endereco: 'Rua Blumenau, 890 — América · 89204-250' },
  ];

  const permissoes = {
    editarPrecoItem: true,
    descontoAcimaDaAlcada: false,
    usuario: 'wagner',
    roles: ['vendas.criar', 'vendas.aprovar', 'producao.iniciar', 'expedicao.entregar', 'vendas.encerrar'],
  };

  const catalogo = [
    { sku: 'LON-440-BR', nome: 'Lona 440g branca fosca', un: 'm²', preco: 68.9, tipo: 'm2', estoque: 412.5, ncm: '3921.90.19', localEstoque: 'Depósito · bobinas', peso: 0.55, ean: '7899123400015', fabrica: 'LN440-BR-FO', categoria: 'Lonas', obs: 'largura útil 3,20 m' },
    { sku: 'ADE-VIN-BR', nome: 'Adesivo vinil branco brilho', un: 'm²', preco: 74.5, tipo: 'm2', estoque: 188.0, ncm: '3919.10.00', localEstoque: 'Depósito · bobinas', peso: 0.18, ean: '7899123400022', fabrica: 'VN-BR-BRI', categoria: 'Adesivos', obs: 'vinil monomérico 80µ' },
    { sku: 'BAN-ACAB-IL', nome: 'Acabamento com ilhós', un: 'un', preco: 3.5, tipo: 'servico', estoque: null, ncm: '—', localEstoque: null, peso: 0.01, ean: '—', fabrica: '—', categoria: 'Acabamentos', obs: 'ilhós latão 12 mm' },
    { sku: 'PLA-ACM-3MM', nome: 'Placa ACM 3mm cinza', un: 'm²', preco: 189.0, tipo: 'm2', estoque: 42.0, ncm: '7606.12.90', localEstoque: 'Pátio · chapas', peso: 4.5, ean: '7899123400046', fabrica: 'ACM3-CZ', categoria: 'Chapas', obs: 'chapa 1,22 × 3,05 m' },
    { sku: 'INS-MO-HORA', nome: 'Instalação — hora técnica', un: 'h', preco: 120.0, tipo: 'servico', estoque: null, ncm: '—', localEstoque: null, peso: 0, ean: '—', fabrica: '—', categoria: 'Serviços', obs: 'hora técnica em obra' },
  ];

  const vendas = [
    { id: 4821, inv: 'VD-2026-4821', cliente: 'Prefeitura de Joinville', tipo: 'pj', data: '27/07/2026', total: 12480.0, pago: 0, pay: 'due', st: 'final', estagio: 'producao', nfe: 'autorizada', ret: false, venc: '26/08/2026', comiss: 'Kamila' },
    { id: 4820, inv: 'VD-2026-4820', cliente: 'Rota Livre Comércio', tipo: 'pj', data: '27/07/2026', total: 3890.5, pago: 1890.5, pay: 'partial', st: 'final', estagio: 'faturada', nfe: 'autorizada', ret: true, venc: '24/08/2026', comiss: 'Guilherme' },
    { id: 4819, inv: 'VD-2026-4819', cliente: 'Marina Bordignon', tipo: 'pf', data: '26/07/2026', total: 486.0, pago: 486.0, pay: 'paid', st: 'final', estagio: 'entregue', nfe: 'autorizada', ret: false, venc: '—', comiss: '—' },
    { id: 4818, inv: 'VD-2026-4818', cliente: 'Consumidor final', tipo: 'pf', data: '26/07/2026', total: 137.8, pago: 137.8, pay: 'paid', st: 'final', estagio: 'entregue', nfe: 'nao_emitida', ret: false, venc: '—', comiss: '—' },
    { id: 4817, inv: 'VD-2026-4817', cliente: 'Prefeitura de Joinville', tipo: 'pj', data: '22/07/2026', total: 8760.0, pago: 8760.0, pay: 'paid', st: 'final', estagio: 'entregue', nfe: 'autorizada', ret: true, venc: '—', comiss: 'Kamila' },
    { id: 4816, inv: 'VD-2026-4816', cliente: 'Rota Livre Comércio', tipo: 'pj', data: '18/07/2026', total: 2145.0, pago: 0, pay: 'due', st: 'final', estagio: 'aprovada', nfe: 'pendente', ret: false, venc: '15/07/2026', comiss: 'Guilherme', atraso: true },
    { id: 4815, inv: 'VD-2026-4815', cliente: 'Marina Bordignon', tipo: 'pf', data: '15/07/2026', total: 1290.0, pago: 645.0, pay: 'partial', st: 'final', estagio: 'producao', nfe: 'autorizada', ret: false, venc: '14/08/2026', comiss: '—' },
    { id: 4814, inv: 'VD-2026-4814', cliente: 'Consumidor final', tipo: 'pf', data: '14/07/2026', total: 320.0, pago: 320.0, pay: 'paid', st: 'final', estagio: 'cancelada', nfe: 'cancelada', ret: false, venc: '—', comiss: '—' },
  ];

  /* FSM — processo "Venda Com Produção" (ADR 0143) */
  const fsm = [
    { key: 'orcamento', l: 'Orçamento', acao: 'Aprovar orçamento', role: 'vendas.aprovar', efeitos: [] },
    { key: 'aprovada', l: 'Aprovada', acao: 'Iniciar produção', role: 'producao.iniciar', efeitos: ['ReservarEstoque'] },
    { key: 'producao', l: 'Em produção', acao: 'Faturar', role: 'financeiro.faturar', efeitos: ['ConsumirEstoque', 'BaixarFinanceiro'] },
    { key: 'faturada', l: 'Faturada', acao: 'Entregar', role: 'expedicao.entregar', efeitos: [] },
    { key: 'entregue', l: 'Entregue', acao: 'Encerrar', role: 'vendas.encerrar', efeitos: [] },
    { key: 'cancelada', l: 'Cancelada', acao: null, role: null, efeitos: ['CancelarVendaCascade', 'LiberarReserva'] },
  ];

  let simularFalha = false;

  /* Cadastro único de pessoas comissionáveis — papel em campo próprio, nunca sufixo no nome.
     Uma fonte só: o lançamento do item e o bloco de comissão leem daqui. */
  const pessoas = [
    { id: 1, nome: 'Kamila Reis', tipo: 'funcionario', papel: 'instalação' },
    { id: 2, nome: 'Guilherme Sato', tipo: 'funcionario', papel: 'impressão' },
    { id: 3, nome: 'Larissa Prado', tipo: 'funcionario', papel: 'balcão' },
    { id: 4, nome: 'Vanderlei Cruz', tipo: 'representante', papel: 'rep. Norte SC' },
    { id: 5, nome: 'Ana Beatriz Muller', tipo: 'representante', papel: 'rep. Vale' },
    { id: 6, nome: 'Estúdio Norte Comunicação', tipo: 'agencia', papel: 'agência parceira' },
    { id: 7, nome: 'Agência Pilar', tipo: 'agencia', papel: 'agência parceira' },
    { id: 8, nome: 'Bureau 47', tipo: 'agencia', papel: 'agência parceira' },
    { id: 9, nome: 'Equipe interna — box 2', tipo: 'tecnico', papel: 'acabamento' },
    { id: 10, nome: 'Equipe externa (terceiro)', tipo: 'tecnico', papel: 'terceirizado' },
  ];
  const pessoasDe = (tipo) => pessoas.filter((p) => !tipo || p.tipo === tipo);

  const equipamentos = [
    { cod: 'EQ-01', nome: 'Látex HP 3600', setor: 'Impressão', tipo: 'impressora' },
    { cod: 'EQ-02', nome: 'UV Flatbed 2513', setor: 'Impressão', tipo: 'impressora' },
    { cod: 'EQ-03', nome: 'Plotter de recorte CE7000', setor: 'Acabamento', tipo: 'recorte' },
    { cod: 'EQ-04', nome: 'Router CNC 1325', setor: 'Usinagem', tipo: 'fresa' },
    { cod: 'EQ-05', nome: 'Mesa de aplicação 2', setor: 'Acabamento', tipo: 'bancada' },
    { cod: 'EQ-06', nome: 'Solda de lona HF', setor: 'Acabamento', tipo: 'solda' },
  ];
  const setores = ['Criação', 'Impressão', 'Acabamento', 'Usinagem', 'Expedição', 'Balcão', 'Instalação'];
  const locaisEstoque = ['Depósito · bobinas', 'Pátio · chapas', 'Almoxarifado · insumos', 'Loja · balcão'];

  const cuById = (id) => cus.find((c) => c.id === id);
  const telaByKey = (k) => telas.find((t) => t.key === k);
  return { telas, cus, ondas, tier0, nfr, rollout, portas, suites, personas, nonGoals, dividas, clientes, catalogo, permissoes, pessoas, pessoasDe, equipamentos, setores, locaisEstoque, vendas, fsm, simularFalha, cuById, telaByKey };
})();

/* ── sells-ui.jsx ── */
/* Primitivos locais do guia de Venda — compõem sobre o DS, sem criar cor nova. */
const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0;
const { Button, Input, Select, Textarea, Switch, Checkbox, StatusBadge, KpiCard, PageHeader, TabBar, DataTable,
  Alert, Drawer, DrawerSection, EmptyState, Skeleton, Pagination, BulkBar, FilterChip, Dimension, Progress, Tooltip,
  AppSidebar, Breadcrumb, Modal, Chart, Avatar, TagChip, DropdownMenu, FsmStepper, Toast, DatePicker, PeriodBar } = DS;

/* Ícone do lucide — única fonte de iconografia (AP4). Nome em PascalCase, ex. "Search". */
function Icon({ name, size = 14, stroke = 1.8, style }) {
  if (!name) return null;
  const L = window.lucide;
  const node = L && (L.icons ? (L.icons[name] || L.icons[name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()]) : L[name]);
  if (!node) return null;
  const filhos = Array.isArray(node) ? (Array.isArray(node[2]) ? node[2] : node) : (node.children || []);
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={stroke}
      strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false" style={{ flex: 'none', display: 'block', ...style }}>
      {filhos.map(([tag, attrs], i) => React.createElement(tag, { key: i, ...attrs }))}
    </svg>
  );
}

function Trilho({ items }) {
  return (
    <nav aria-label="Você está em" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, font: '400 12.5px/1.3 var(--font-sans)', color: 'var(--text)', minWidth: 0 }}>
      {items.map((it, i) => (
        <React.Fragment key={i}>
          {i > 0 && <span aria-hidden="true" style={{ color: 'inherit', opacity: .55 }}>/</span>}
          {it.href && i < items.length - 1
            ? <a href={it.href} style={{ color: 'inherit', opacity: .78, textDecoration: 'none' }}>{it.label}</a>
            : <b style={{ color: 'inherit', fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{it.label}</b>}
        </React.Fragment>
      ))}
    </nav>
  );
}

/* tom legível nos DOIS temas: mistura com --text (escuro no claro, claro no escuro) */
const tomFg = (c) => 'color-mix(in oklch, ' + c + ' 62%, var(--text))';

/* viewport estreito — usado para trocar tabela por cartão (dimensão 9) */
function useEstreito(q = '(max-width: 640px)') {
  const [v, setV] = React.useState(() => window.matchMedia(q).matches);
  React.useEffect(() => { const m = window.matchMedia(q); const h = () => setV(m.matches); m.addEventListener('change', h); return () => m.removeEventListener('change', h); }, [q]);
  return v;
}

const EST = {
  ok: { l: 'atende', c: 'var(--pos)', s: 'color-mix(in oklch, var(--pos) 12%, var(--surface))' },
  parcial: { l: 'parcial', c: 'var(--warn)', s: 'color-mix(in oklch, var(--warn) 12%, var(--surface))' },
  falta: { l: 'a criar', c: 'var(--neg)', s: 'color-mix(in oklch, var(--neg) 12%, var(--surface))' },
  nv: { l: 'não verificado', c: 'var(--text-mute)', s: 'var(--bg-2)' },
};

function Pill({ children, c = 'var(--text-dim)', s, mono, title, dot }) {
  const neutro = c === 'var(--text-dim)' || c === 'var(--text-mute)' || c === 'transparent';
  const mostraDot = dot === undefined ? !neutro : dot;
  return (
    <span title={title} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, height: 20, color: tomFg(neutro ? 'var(--text-dim)' : c),
      font: (mono ? '600 10.5px/1 var(--font-mono)' : '600 10.5px/1 var(--font-sans)'), letterSpacing: '.04em', textTransform: mono ? 'none' : 'uppercase', whiteSpace: 'nowrap' }}>
      {mostraDot && <span aria-hidden="true" style={{ width: 6, height: 6, borderRadius: 999, background: c, flex: 'none' }}></span>}
      {children}
    </span>
  );
}

function EstPill({ e }) { const x = EST[e] || EST.nv; return <Pill c={x.c} s={x.s}>{x.l}</Pill>; }

function TagV0({ tag }) {
  if (!tag) return null;
  const map = { V0: ['var(--neg)', 'color-mix(in oklch, var(--neg) 12%, var(--surface))'], T0: ['var(--accent)', 'color-mix(in oklch, var(--accent) 12%, var(--surface))'], reg: ['var(--warn)', 'color-mix(in oklch, var(--warn) 12%, var(--surface))'], ux: ['var(--color-info)', 'color-mix(in oklch, var(--color-info) 12%, transparent)'], must: ['var(--text-dim)', 'var(--bg-2)'], should: ['var(--text-mute)', 'var(--bg-2)'] };
  return <>{String(tag).split(' ').filter(Boolean).map((p) => {
    const [c, s] = map[p] || ['var(--text-mute)', 'var(--bg-2)'];
    return <Pill key={p} c={c} s={s} mono>{'[' + p + ']'}</Pill>;
  })}</>;
}

/* Marcador de CU: clicável, abre o drawer do caso de uso */
function CuChip({ id, onOpen }) {
  const cu = window.SD.cuById(id);
  if (!cu) return null;
  const x = EST[cu.e] || EST.nv;
  return (
    <button type="button" onClick={() => onOpen(id)} title={cu.t}
      style={{ display: 'inline-flex', alignItems: 'center', gap: 4, height: 20, padding: '0 8px', borderRadius: 5, cursor: 'pointer', flex: 'none', whiteSpace: 'nowrap', background: x.s, color: x.c, border: '1px solid color-mix(in oklch, ' + x.c + ' 26%, transparent)', font: '600 10.5px/1 var(--font-mono)' }}>
      <span style={{ width: 5, height: 5, borderRadius: 999, background: x.c }}></span>{id.replace('CU-SELL-', 'CU-')}
    </button>
  );
}

function CuRow({ ids, onOpen }) {
  if (!ids || !ids.length) return null;
  return <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>{ids.map((i) => <CuChip key={i} id={i} onOpen={onOpen} />)}</div>;
}

/* Modo produção: desligado, a tela é só a tela — sem marcador de CU, sem faixa Tier 0 */
const MetaCtx = React.createContext(false);
const useMeta = () => React.useContext(MetaCtx);
function Meta({ children }) { return useMeta() ? <>{children}</> : null; }

/* Card de seção com header colorido por domínio — padrão do módulo Vendas. */
function Sec({ title, sub, hue = 'var(--accent)', ico, right, children, cus, onOpen, pad = 16, dobra, resumo, clip = true }) {
  const meta = useMeta();
  const [aberta, setAberta] = React.useState(dobra !== 'fechada');
  const dobravel = !!dobra;
  return (
    <section style={{ background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-soft)', overflow: clip ? 'hidden' : 'visible' }}>
      <header onClick={dobravel ? () => setAberta(!aberta) : undefined} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px', borderBottom: (dobravel && !aberta) ? 0 : '1px solid var(--border)', background: 'color-mix(in oklch, ' + hue + ' 5%, var(--surface))', cursor: dobravel ? 'pointer' : 'default' }}>
        {ico !== false && <span style={{ width: 30, height: 30, flex: 'none', borderRadius: 8, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', background: 'color-mix(in oklch, ' + hue + ' 14%, transparent)', color: tomFg(hue) }}>
          <Icon name={ico || 'List'} size={15} />
        </span>}
        <div style={{ minWidth: 0 }}>
          <h3 style={{ margin: 0, font: '600 15px/1.3 var(--font-sans)', color: 'var(--text)' }}>{title}</h3>
          {sub && <p style={{ margin: '2px 0 0', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{sub}</p>}
        </div>
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', justifyContent: 'flex-end', flexWrap: 'wrap', gap: 8 }}>
          {dobravel && !aberta && resumo && <span style={{ font: '11.5px/1 var(--font-sans)', color: 'var(--text-dim)' }}>{resumo}</span>}
          {meta && cus && <CuRow ids={cus} onOpen={onOpen} />}{right}
          {dobravel && <span style={{ color: 'var(--text-dim)', display: 'inline-flex', transform: aberta ? 'rotate(180deg)' : 'none' }}><Icon name="ChevronDown" size={15} /></span>}
        </div>
      </header>
      {(!dobravel || aberta) && (pad === 0
        ? (clip ? <div className="oi-scroll" style={{ overflowX: 'auto' }}>{children}</div> : <div>{children}</div>)
        : <div style={{ padding: pad }}>{children}</div>)}
    </section>
  );
}

const Grid = ({ cols = 4, gap = 12, children, style }) => <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, ' + (cols >= 4 ? 190 : cols === 3 ? 230 : 280) + 'px), 1fr))', gap, ...style }}>{children}</div>;

/* line-height 1.5 e margin 4 = a MESMA caixa de rótulo do Input/Select do DS (15,75 + 4).
   Com line-height 1 os campos locais subiam ~6px ao lado dos do DS na mesma linha. */
const Lbl = ({ children, c = 'var(--text-dim)' }) => {
  const tom = /--(accent|pos|neg|warn|color-info)\b/.test(c) && !c.includes('color-mix') ? tomFg(c) : c;
  return <span style={{ display: 'block', font: '600 10.5px/1.5 var(--font-sans)', letterSpacing: '.04em', textTransform: 'uppercase', color: tom, marginBottom: 4 }}>{children}</span>;
};

/* Campo monetário pt-BR — o guard do incidente num_uf vive aqui */
function Money({ label, value, onChange, onBlur, error, hue = 'var(--text-dim)', suffix, readOnly, help, prefix = 'R$', aria }) {
  const invalido = !!error;
  const base = { fontFamily: 'var(--font-mono)' };
  const est = readOnly ? { ...base, background: 'var(--bg-2)', cursor: 'default', fontWeight: 600 }
    : invalido ? { ...base, borderColor: 'var(--neg)', boxShadow: '0 0 0 3px color-mix(in oklch, var(--neg) 16%, transparent)' } : base;
  return (
    <div>
      {label && <Lbl c={invalido ? 'var(--neg)' : hue}>{label}</Lbl>}
      <div className={'dsfa pre' + (suffix ? ' suf' : '')}>
        <span className="afx l">{prefix}</span>
        <input value={value} readOnly={readOnly} inputMode="decimal" aria-label={aria || label} aria-invalid={invalido || undefined}
          onChange={(e) => onChange && onChange(e.target.value)} onBlur={onBlur} style={est} />
        {suffix && <span className="afx r">{suffix}</span>}
      </div>
      {invalido
        ? <span role="alert" style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 4, font: '600 11.5px/1.35 var(--font-sans)', color: tomFg('var(--neg)') }}>
            <Icon name="AlertTriangle" size={12} />{error}
          </span>
        : help && <span style={{ display: 'block', marginTop: 4, font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{help}</span>}
    </div>
  );
}

/* Campo de texto com validação — o Input do DS não repassa onBlur, então o fiscal usa este */
function Campo({ label, value, onChange, onBlur, error, help, placeholder, mono = true, readOnly }) {
  const invalido = !!error;
  return (
    <div>
      {label && <Lbl c={invalido ? 'var(--neg)' : 'var(--text-dim)'}>{label}</Lbl>}
      <div className="dsfa">
        <input value={value} placeholder={placeholder} readOnly={readOnly} aria-label={label} aria-invalid={invalido || undefined}
          onChange={(e) => onChange && onChange(e.target.value)} onBlur={onBlur}
          style={invalido ? { fontFamily: mono ? 'var(--font-mono)' : 'var(--font-sans)', borderColor: 'var(--neg)', boxShadow: '0 0 0 3px color-mix(in oklch, var(--neg) 16%, transparent)' } : { fontFamily: mono ? 'var(--font-mono)' : 'var(--font-sans)' }} />
      </div>
      {invalido
        ? <span role="alert" style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 4, font: '600 11.5px/1.35 var(--font-sans)', color: tomFg('var(--neg)') }}>
            <Icon name="AlertTriangle" size={12} />{error}
          </span>
        : help && <span style={{ display: 'block', marginTop: 4, font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{help}</span>}
    </div>
  );
}

const brl = (n) => (n || n === 0) ? 'R$ ' + Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
const num = (n, d = 2) => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d });
const fmtBR = (n) => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
/* parse pt-BR: tira separador de milhar (SEMPRE 3 dígitos) e troca vírgula decimal — guard CU-SELL-05 */
const parseBR = (s) => { if (typeof s === 'number') return s; const t = String(s || '').replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.'); const v = parseFloat(t); return isNaN(v) ? 0 : v; };
/* o que o frontend PODE mandar: 2 casas, sem ambiguidade de locale */
const submitSafe = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const PAY = { paid: ['Pago', 'var(--pos)', 'color-mix(in oklch, var(--pos) 12%, var(--surface))'], partial: ['Parcial', 'var(--warn)', 'color-mix(in oklch, var(--warn) 12%, var(--surface))'], due: ['A receber', 'var(--neg)', 'color-mix(in oklch, var(--neg) 12%, var(--surface))'] };
function PayPill({ p, atraso }) { const [l, c, s] = PAY[p] || PAY.due; return <Pill c={c} s={s}>{atraso && p !== 'paid' ? 'Vencido' : l}</Pill>; }

const FISCAL = { autorizada: ['NF-e autorizada', 'var(--pos)'], pendente: ['NF-e pendente', 'var(--warn)'], cancelada: ['NF-e cancelada', 'var(--neg)'], nao_emitida: ['sem NF', 'var(--text-mute)'] };
function FiscalPill({ f }) { const [l, c] = FISCAL[f] || FISCAL.nao_emitida; return <Pill c={c} s={'color-mix(in oklch, ' + c + ' 10%, transparent)'} mono>{l}</Pill>; }

const ESTAGIO_HUE = { orcamento: 'var(--text-mute)', aprovada: 'var(--color-info)', producao: 'var(--warn)', faturada: 'var(--accent)', entregue: 'var(--pos)', cancelada: 'var(--neg)' };
function EstagioPill({ k }) { const f = window.SD.fsm.find((x) => x.key === k) || {}; const c = ESTAGIO_HUE[k] || 'var(--text-mute)'; return <Pill c={c} s={'color-mix(in oklch, ' + c + ' 12%, transparent)'}>{f.l || k}</Pill>; }

/* DataCampo — campo de data com calendário em PORTAL.
   Por que não o DatePicker do DS: o popover dele é irmão absoluto dentro do campo, então
   QUALQUER ancestral com overflow:auto o corta — medido: 176px cortados no modal de
   lançamento e 202px no drawer, mais barra horizontal. Aqui o calendário vive no body,
   com posição fixa calculada do gatilho, e escapa de todo scroller. (Exceção AP2 nº3.) */
const DIAS_SEMANA = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const dParse = (v) => {
  if (!v) return null;
  if (v instanceof Date) return isNaN(v) ? null : v;
  const br = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(v));
  if (br) return new Date(+br[3], +br[2] - 1, +br[1]);
  const iso = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v));
  if (iso) return new Date(+iso[1], +iso[2] - 1, +iso[3]);
  const d = new Date(v);
  return isNaN(d) ? null : d;
};
const dTexto = (d) => d ? String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear() : '';
const mesmoDia = (a, b) => !!a && !!b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

function DataCampo({ label, value, onChange, help, disabled, placeholder = 'dd/mm/aaaa' }) {
  const sel = dParse(value);
  const [aberto, setAberto] = React.useState(false);
  const [pos, setPos] = React.useState(null);
  const [vista, setVista] = React.useState(() => { const d = sel || new Date(); return { a: d.getFullYear(), m: d.getMonth() }; });
  const gatilho = React.useRef(null);
  const painel = React.useRef(null);

  const medir = () => {
    const el = gatilho.current && gatilho.current.querySelector('input');
    if (!el) return;
    const r = el.getBoundingClientRect();
    const alt = 300, larg = 268;
    const abaixo = window.innerHeight - r.bottom > alt + 8;
    setPos({
      left: Math.max(8, Math.min(r.left, window.innerWidth - larg - 8)),
      top: abaixo ? r.bottom + 4 : Math.max(8, r.top - alt - 4),
    });
  };
  const abrir = () => { if (disabled) return; const d = sel || new Date(); setVista({ a: d.getFullYear(), m: d.getMonth() }); medir(); setAberto(true); };

  React.useEffect(() => {
    if (!aberto) return;
    const fora = (e) => { if (!painel.current || painel.current.contains(e.target)) return; if (gatilho.current && gatilho.current.contains(e.target)) return; setAberto(false); };
    const tecla = (e) => {
      if (e.key !== 'Escape') return;
      /* corta o evento em window/captura: o Modal e o Drawer do DS têm handler de Escape
         no document e, registrados antes, fechariam o overlay junto — stopPropagation
         não basta (mesmo nó exige stopImmediatePropagation). */
      e.preventDefault();
      e.stopImmediatePropagation();
      e.stopPropagation();
      setAberto(false);
    };
    const remede = () => medir();
    document.addEventListener('mousedown', fora, true);
    window.addEventListener('keydown', tecla, true);
    window.addEventListener('resize', remede);
    window.addEventListener('scroll', remede, true);
    return () => {
      document.removeEventListener('mousedown', fora, true);
      window.removeEventListener('keydown', tecla, true);
      window.removeEventListener('resize', remede);
      window.removeEventListener('scroll', remede, true);
    };
  }, [aberto]);

  const escolher = (d) => { onChange && onChange(d); setAberto(false); };
  const primeiro = new Date(vista.a, vista.m, 1);
  const inicio = primeiro.getDay();
  const dias = new Date(vista.a, vista.m + 1, 0).getDate();
  const hoje = new Date();
  const celulas = [];
  for (let i = 0; i < inicio; i++) celulas.push(null);
  for (let i = 1; i <= dias; i++) celulas.push(new Date(vista.a, vista.m, i));
  const passo = (n) => setVista((v) => { const d = new Date(v.a, v.m + n, 1); return { a: d.getFullYear(), m: d.getMonth() }; });

  const cal = (
    <div ref={painel} role="dialog" aria-label="Escolher data"
      style={{ position: 'fixed', left: (pos || {}).left || 0, top: (pos || {}).top || 0, zIndex: 90, width: 268, padding: 10, background: 'var(--surface, #fff)', backgroundColor: 'var(--surface, #fff)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: '0 20px 50px -10px color-mix(in oklch, var(--text) 45%, transparent)' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
        <button type="button" aria-label="Mês anterior" onClick={() => passo(-1)} style={{ width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="ChevronLeft" size={14} /></button>
        <b style={{ flex: 1, textAlign: 'center', font: '600 12.5px/1 var(--font-sans)', color: 'var(--text)' }}>{MESES[vista.m]} {vista.a}</b>
        <button type="button" aria-label="Próximo mês" onClick={() => passo(1)} style={{ width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="ChevronRight" size={14} /></button>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 2 }}>
        {DIAS_SEMANA.map((d, i) => <span key={i} aria-hidden="true" style={{ textAlign: 'center', font: '600 10px/20px var(--font-sans)', color: 'var(--text-dim)' }}>{d}</span>)}
        {celulas.map((d, i) => d === null ? <span key={'v' + i}></span> : (
          <button key={i} type="button" onClick={() => escolher(d)}
            aria-current={mesmoDia(d, sel) ? 'date' : undefined}
            style={{ height: 28, borderRadius: 6, cursor: 'pointer', font: (mesmoDia(d, sel) || mesmoDia(d, hoje) ? '600 ' : '') + '12px/1 var(--font-mono)',
              border: '1px solid ' + (mesmoDia(d, sel) ? 'var(--accent)' : mesmoDia(d, hoje) ? 'var(--border)' : 'transparent'),
              background: mesmoDia(d, sel) ? 'var(--accent)' : 'transparent',
              color: mesmoDia(d, sel) ? 'var(--accent-fg)' : 'var(--text)' }}>{d.getDate()}</button>
        ))}
      </div>
      <div style={{ display: 'flex', gap: 6, marginTop: 8, paddingTop: 8, borderTop: '1px solid var(--border)' }}>
        <Button size="sm" onClick={() => escolher(new Date())}>Hoje</Button>
        {sel && <Button size="sm" onClick={() => escolher(null)}>Limpar</Button>}
      </div>
    </div>
  );

  return (
    <div ref={gatilho} onClick={abrir}>
      <Input label={label} help={help} disabled={disabled} readOnly value={dTexto(sel)} placeholder={placeholder} onChange={() => {}} />
      {aberto && pos && ReactDOM.createPortal(cal, document.querySelector('.cockpit') || document.body)}
    </div>
  );
}

/* Faixa Tier 0 — só no modo produção */
function TierBar({ children, tone = 'neg' }) {
  if (!useMeta()) return null;
  const c = tone === 'accent' ? 'var(--accent)' : 'var(--neg)';
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 12px', borderRadius: 8, background: tone === 'accent' ? 'color-mix(in oklch, var(--accent) 12%, var(--surface))' : 'color-mix(in oklch, var(--neg) 12%, var(--surface))', border: '1px solid color-mix(in oklch, ' + c + ' 24%, transparent)', font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>
      <Pill c={c} s="transparent">{tone === 'accent' ? 'Tier 0 · multi-tenant' : 'Tier 0 · Regra Mestre'}</Pill>
      <span style={{ minWidth: 0 }}>{children}</span>
    </div>
  );
}

/* Tela sem contrato — as 6 do §1.1 sem casos.md */
function SemContrato({ tela, onda, children }) {
  const t = window.SD.telaByKey(tela);
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <Meta><Alert tone="warn" title={'Esta tela não tem ' + '\u0060casos.md\u0060' + ' — nenhum CU a defende'}>
        <p style={{ margin: 0 }}>{t.papel}</p>
        <p style={{ margin: '6px 0 0' }}>Escrever o contrato é o item <b>{onda}</b> do roteiro (onda 6 · "Fechar o trio e virar a flag"). Até lá, o que esta tela faz em produção não é coberto por teste nenhum — e ela mexe/exibe valor de venda emitida.</p>
      </Alert></Meta>
      {children}
    </div>
  );
}

/* ── sells-comissao.jsx ── */
/* Comissão da venda — modelo de ERP, não um campo "Comissionista".
   O que um ERP sério exige e o select único não dava:
   1. VÁRIOS beneficiários na mesma venda, de tipos diferentes (funcionário, representante,
      agência, técnico) — quem vendeu, quem trouxe o cliente e quem executou raramente
      são a mesma pessoa, e cada um tem regra própria;
   2. BASE declarada por beneficiário: bruto, líquido de desconto ou MARGEM. Comissão sobre
      bruto paga o vendedor para dar desconto; sobre margem, alinha o incentivo;
   3. REGRA: percentual, valor fixo, ou faixa progressiva (tiered) por volume;
   4. GATILHO de direito: emissão, faturamento ou RECEBIMENTO (accrual vs cash) — é o que
      decide se a empresa paga comissão de venda que o cliente não pagou;
   5. ESTORNO (clawback) na devolução e no cancelamento, proporcional ao devolvido;
   6. SNAPSHOT da regra no momento da venda: mudar a política não pode reescrever comissão
      de venda passada;
   7. Apuração em ciclo próprio (provisionada → aprovada → paga) e teto/piso por venda.
   Aqui a tela cobre 1–6 e mostra o estado da apuração (7), que é do módulo Financeiro/RH. */

const COM_TIPOS = [
  { k: 'funcionario', l: 'Funcionário (vendedor interno)', hue: 'var(--accent)', pgto: 'folha de pagamento', doc: 'CLT' },
  { k: 'representante', l: 'Representante (externo)', hue: 'var(--color-info)', pgto: 'título a pagar', doc: 'RPA / nota' },
  { k: 'agencia', l: 'Agência / parceiro', hue: 'var(--warn)', pgto: 'título a pagar', doc: 'nota de serviço' },
  { k: 'tecnico', l: 'Técnico / instalador', hue: 'var(--pos)', pgto: 'folha ou produção', doc: 'CLT / autônomo' },
];
/* fonte única: window.SD.pessoas (sells-data.js). Rótulo mostra o papel, valor é só o nome. */
const comOpcoes = (tipo) => window.SD.pessoasDe(tipo).map((p) => ({ value: p.nome, label: p.papel ? p.nome + ' · ' + p.papel : p.nome }));
const comPrimeira = (tipo) => (window.SD.pessoasDe(tipo)[0] || {}).nome || '';
const COM_BASES = [
  { value: 'liquido', label: 'Líquido de desconto' },
  { value: 'bruto', label: 'Bruto (antes do desconto)' },
  { value: 'margem', label: 'Margem (venda − custo)' },
];
const COM_GATILHOS = [
  { value: 'recebimento', label: 'A cada parcela recebida' },
  { value: 'faturamento', label: 'No faturamento da venda' },
  { value: 'emissao', label: 'Na emissão da venda' },
];
const COM_FAIXAS = [{ ate: 5000, p: 2 }, { ate: 20000, p: 3 }, { ate: null, p: 4 }];

const comTipo = (k) => COM_TIPOS.find((t) => t.k === k) || COM_TIPOS[0];
const comFaixa = (base) => (COM_FAIXAS.find((f) => f.ate === null || base <= f.ate) || COM_FAIXAS[0]).p;

/* base de cálculo de cada beneficiário, a partir dos totais da venda */
function comBase(b, tot) {
  if (b.base === 'bruto') return tot.bruto;
  if (b.base === 'margem') return tot.margem;
  return tot.liquido;
}
function comValor(b, tot) {
  const base = comBase(b, tot);
  if (b.regra === 'fixo') return submitSafe(parseBR(b.valor));
  const p = b.regra === 'faixa' ? comFaixa(base) : parseBR(b.pct);
  return submitSafe(base * p / 100);
}

/* Resumo na tela — abre o modal; nunca fica escondido atrás de "mais opções" */
function ComissaoResumo({ bens, tot, gatilho, onAbrir, parcelas = [], totalVenda = 0 }) {
  const soma = submitSafe(bens.reduce((s, b) => s + comValor(b, tot), 0));
  const pctVenda = tot.liquido > 0 ? soma / tot.liquido * 100 : 0;
  const g = COM_GATILHOS.find((x) => x.value === gatilho) || COM_GATILHOS[0];
  const recebidas = gatilho === 'recebimento' ? parcelas.filter((p) => p.lanc === 'RECEBIDA') : [];
  const liberada = gatilho === 'recebimento' && totalVenda > 0
    ? submitSafe(recebidas.reduce((s, p) => s + soma * parseBR(p.valor) / totalVenda, 0)) : 0;
  return (
    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
      <div>
        <Lbl>Comissão da venda</Lbl>
        <b style={{ font: '600 15px/1 var(--font-mono)' }}>{brl(soma)}</b>
        {soma > 0 && <span style={{ font: '11.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}> · {num(pctVenda, 2)}% da venda</span>}
      </div>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5, minWidth: 0 }}>
        {bens.length === 0
          ? <span style={{ font: '12px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Nenhum beneficiário — venda sem comissão</span>
          : bens.map((b) => (
            <Pill key={b.k} c={comTipo(b.tipo).hue} s={'color-mix(in oklch, ' + comTipo(b.tipo).hue + ' 12%, var(--surface))'}>
              {b.pessoa || comTipo(b.tipo).l} · {brl(comValor(b, tot))}
            </Pill>
          ))}
      </div>
      <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
        {gatilho === 'recebimento' && parcelas.length > 0 && <Pill mono c={liberada > 0 ? 'var(--pos)' : 'var(--text-dim)'} s={liberada > 0 ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'transparent'} title="Comissão já devida pelas parcelas baixadas">{brl(liberada)} devida · {recebidas.length}/{parcelas.length} parcelas</Pill>}
        <Pill mono title="Quando a comissão passa a ser devida">{g.label}</Pill>
        <Button size="sm" onClick={onAbrir}>Configurar comissão</Button>
      </span>
    </div>
  );
}

/* rateio da comissão pelas parcelas — só faz sentido no gatilho "a cada parcela recebida" */
function comPorParcela(parcelas, soma, totalVenda) {
  if (!parcelas.length || totalVenda <= 0) return [];
  return parcelas.map((p) => {
    const v = parseBR(p.valor);
    return { num: p.num, de: p.de, venc: p.venc, valor: v, recebida: p.lanc === 'RECEBIDA', pgto: p.pgto, com: submitSafe(soma * v / totalVenda) };
  });
}

function ComissaoModal({ open, onClose, bens, setBens, tot, gatilho, setGatilho, itensServico, parcelas = [], totalVenda = 0 }) {
  const add = (tipo) => setBens([...bens, { k: Date.now(), tipo, pessoa: comPrimeira(tipo), base: tipo === 'agencia' ? 'margem' : 'liquido', regra: 'pct', pct: tipo === 'agencia' ? '5,00' : '3,00', valor: '0,00' }]);
  const setB = (k, campo, v) => setBens(bens.map((b) => b.k === k ? { ...b, [campo]: v } : b));
  const soma = submitSafe(bens.reduce((s, b) => s + comValor(b, tot), 0));
  const sobreMargem = tot.margem > 0 ? soma / tot.margem * 100 : 0;
  const comeMargem = soma > tot.margem * 0.5;

  return (
    <Modal open={open} onClose={onClose} width={880} title="Comissão desta venda"
      footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
        <div>
          <Lbl>Total de comissão</Lbl>
          <b style={{ font: '600 17px/1 var(--font-mono)' }}>{brl(soma)}</b>
          {tot.margem > 0 && <span style={{ font: '11.5px/1 var(--font-sans)', color: comeMargem ? tomFg('var(--neg)') : 'var(--text-dim)' }}> · {num(sobreMargem, 1)}% da margem</span>}
        </div>
        <span style={{ marginLeft: 'auto' }}><Button variant="primary" onClick={onClose}>Fechar</Button></span>
      </div>}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
          {[['Bruto', tot.bruto], ['Líquido de desconto', tot.liquido], ['Margem estimada', tot.margem]].map(([l, v]) => (
            <div key={l}><Lbl>{l}</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(v)}</b></div>
          ))}
          <div style={{ minWidth: 220, marginLeft: 'auto' }}>
            <Select label="Quando a comissão é devida" value={gatilho} onChange={(e) => setGatilho(e.target.value)} options={COM_GATILHOS} />
          </div>
        </div>

        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', marginBottom: 8 }}>
            <Lbl>Beneficiários</Lbl>
            <span style={{ marginLeft: 'auto', display: 'flex', gap: 5, flexWrap: 'wrap' }}>
              {COM_TIPOS.map((t) => (
                <button key={t.k} type="button" onClick={() => add(t.k)}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: 5, height: 26, padding: '0 9px', borderRadius: 999, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text)', cursor: 'pointer', font: '12px/1 var(--font-sans)' }}>
                  <Icon name="Plus" size={12} />{t.l.split(' (')[0]}
                </button>
              ))}
            </span>
          </div>
          {bens.length === 0
            ? <p style={{ margin: 0, font: '12.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Nenhum beneficiário. Uma venda pode ter mais de um: o vendedor interno que digitou, o representante da região e a agência que trouxe o cliente — cada um com base e percentual próprios.</p>
            : <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {bens.map((b) => {
                const t = comTipo(b.tipo);
                const base = comBase(b, tot);
                return (
                  <div key={b.k} style={{ padding: 12, borderRadius: 12, background: 'var(--surface)', border: '1px solid color-mix(in oklch, ' + t.hue + ' 24%, var(--border))' }}>
                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8, marginBottom: 10 }}>
                      <Pill c={t.hue} s={'color-mix(in oklch, ' + t.hue + ' 12%, var(--surface))'}>{t.l}</Pill>
                      <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>paga por <b>{t.pgto}</b> · documento {t.doc}</span>
                      <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
                        <span><Lbl>Comissão</Lbl><b style={{ font: '600 14px/1 var(--font-mono)' }}>{brl(comValor(b, tot))}</b></span>
                        <Button size="sm" onClick={() => setBens(bens.filter((x) => x.k !== b.k))}>Remover</Button>
                      </span>
                    </div>
                    <Grid cols={4} gap={10}>
                      <div><Select label="Quem" value={b.pessoa} onChange={(e) => setB(b.k, 'pessoa', e.target.value)} options={comOpcoes(b.tipo)} /></div>
                      <div><Select label="Base de cálculo" value={b.base} onChange={(e) => setB(b.k, 'base', e.target.value)} options={COM_BASES} /></div>
                      <div><Select label="Regra" value={b.regra} onChange={(e) => setB(b.k, 'regra', e.target.value)} options={[{ value: 'pct', label: 'Percentual' }, { value: 'faixa', label: 'Faixa progressiva' }, { value: 'fixo', label: 'Valor fixo' }]} /></div>
                      {b.regra === 'fixo'
                        ? <Money label="Valor" value={b.valor} onChange={(v) => setB(b.k, 'valor', v)} />
                        : b.regra === 'faixa'
                          ? <div><Lbl>Faixa aplicada</Lbl><b style={{ font: '600 13.5px/1.4 var(--font-mono)' }}>{num(comFaixa(base), 2)}%</b><span style={{ display: 'block', font: '11px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>até 5 mil 2% · até 20 mil 3% · acima 4%</span></div>
                          : <Money label="Percentual" prefix="%" value={b.pct} onChange={(v) => setB(b.k, 'pct', v)} />}
                    </Grid>
                    <span style={{ display: 'block', marginTop: 8, font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>
                      Base de {brl(base)} ({(COM_BASES.find((x) => x.value === b.base) || {}).label.toLowerCase()})
                      {b.base === 'bruto' && <span style={{ color: tomFg('var(--warn)') }}> — comissão sobre bruto paga o mesmo com ou sem desconto; é o incentivo invertido.</span>}
                    </span>
                  </div>
                );
              })}
            </div>}
        </div>

        {gatilho === 'recebimento' && (() => {
          const rat = comPorParcela(parcelas, soma, totalVenda);
          const liberada = submitSafe(rat.filter((r) => r.recebida).reduce((s, r) => s + r.com, 0));
          return (
            <div>
              <Lbl>Liberação por parcela recebida</Lbl>
              {rat.length === 0
                ? <p style={{ margin: 0, font: '12.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Esta venda ainda não tem parcelas. Com o gatilho <b>a cada parcela recebida</b>, a comissão é rateada na proporção de cada parcela e só vira devida quando a parcela é baixada — venda em 3× libera a comissão em 3 vezes.</p>
                : <>
                  <div className="oi-scroll" style={{ overflowX: 'auto', border: '1px solid var(--border)', borderRadius: 10 }}>
                    <table style={{ width: '100%', minWidth: 460, borderCollapse: 'separate', borderSpacing: 0, font: '12.5px/1.4 var(--font-sans)' }}>
                      <thead><tr>{['Parcela', 'Vencimento', 'Valor', 'Comissão', 'Situação'].map((h, i) => (
                        <th key={h} style={{ background: 'var(--bg-2)', padding: '7px 10px', textAlign: i >= 2 && i <= 3 ? 'right' : 'left', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap' }}>{h}</th>
                      ))}</tr></thead>
                      <tbody>{rat.map((r) => (
                        <tr key={r.num}>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)', font: '12.5px/1 var(--font-mono)' }}>{r.num}/{r.de}</td>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)', font: '12.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{dTexto(dParse(r.venc))}</td>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)', textAlign: 'right', font: '12.5px/1 var(--font-mono)' }}>{fmtBR(r.valor)}</td>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)', textAlign: 'right', font: '600 12.5px/1 var(--font-mono)' }}>{fmtBR(r.com)}</td>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)' }}>
                            {r.recebida
                              ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">comissão liberada</Pill>
                              : <Pill>aguarda recebimento</Pill>}
                          </td>
                        </tr>
                      ))}</tbody>
                    </table>
                  </div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, marginTop: 10 }}>
                    <div><Lbl c="var(--pos)">Já devida</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(liberada)}</b></div>
                    <div><Lbl>Ainda provisionada</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(submitSafe(soma - liberada))}</b></div>
                    <span style={{ marginLeft: 'auto', maxWidth: 340, font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Rateio proporcional ao valor de cada parcela. Parcela em atraso não libera; parcela devolvida ou estornada gera lançamento negativo.</span>
                  </div>
                </>}
            </div>
          );
        })()}

        {comeMargem && <Alert tone="warn" title="A comissão come mais da metade da margem">Total de {brl(soma)} sobre margem estimada de {brl(tot.margem)} ({num(sobreMargem, 1)}%). Em ERP maduro isso dispara alçada de aprovação, não bloqueio.</Alert>}

        {itensServico > 0 && <Alert tone="info" title={itensServico === 1 ? '1 item de serviço tem comissão própria' : itensServico + ' itens de serviço têm comissão própria'}>
          A comissão por item (funcionário vinculado + % no lançamento) é <b>somada</b> à da venda e apurada para quem executou, não para quem vendeu. Ver na coluna <b>Funcionário</b> do grid ou no detalhe do item.
        </Alert>}

        <div>
          <Lbl>Ciclo de apuração</Lbl>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
            <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">provisionada</Pill>
            <Icon name="ChevronRight" size={13} />
            <Pill>aprovada</Pill>
            <Icon name="ChevronRight" size={13} />
            <Pill>paga</Pill>
            <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>a apuração e o pagamento acontecem no Financeiro/RH; aqui só nasce a provisão.</span>
          </div>
          <p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>
            <b>Estorno:</b> devolução e cancelamento estornam a comissão na proporção devolvida — a provisão não some, ganha um lançamento negativo, para a apuração do mês fechar.
            <b> Snapshot:</b> a regra vigente é copiada para a venda no momento em que ela é finalizada; mudar a política de comissão depois <b>não</b> reescreve venda passada.
          </p>
        </div>
      </div>
    </Modal>
  );
}

/* ── sells-item-detail.jsx ── */
/* Detalhe do Produto/Serviço na venda — equivalente ao "Detalhes do Produto / Serviço" do legado.
   Abas: Geral · Produção · Fluxo · Tributação (sub-abas por imposto) · Formação do preço · Anexos · Observação.
   Abre pela lupa da linha de item; edita uma CÓPIA e só grava no Confirmar. */

/* mesmas listas do lançamento (sells-lancamento.jsx), com a opção vazia do legado */
const LOCAIS_DET = ['', 'Fachada', 'Interno', 'Veículo', 'Painel', 'Vitrine', 'Totem', 'Obra'];
const IMPRESSOES_DET = ['', 'Digital — látex', 'Digital — UV', 'Offset', 'Recorte eletrônico', 'Sublimação', 'Sem impressão'];

const IMPOSTOS = [
  { k: 'icms', l: 'ICMS', aliq: 18 }, { k: 'ipi', l: 'IPI', aliq: 0 }, { k: 'pis', l: 'PIS', aliq: 1.65 },
  { k: 'cofins', l: 'COFINS', aliq: 7.6 }, { k: 'issqn', l: 'ISSQN', aliq: 0 }, { k: 'ii', l: 'II', aliq: 0 },
  { k: 'is', l: 'IS', aliq: 0 }, { k: 'ibs', l: 'IBS', aliq: 0.1 }, { k: 'cbs', l: 'CBS', aliq: 0.9 },
];
const CST_ICMS = ['00 — Tributada integralmente', '20 — Com redução de base', '40 — Isenta', '41 — Não tributada', '60 — ST cobrado anteriormente', '102 — Simples sem crédito'];
/* etapa: responsável é PESSOA, setor é ONDE — misturar os dois numa coluna foi o defeito apontado */
const FLUXO_PADRAO = [
  { e: 'Arte / pré-impressão', resp: 'Kamila Reis', setor: 'Criação', st: 'concluída', prev: '28/07' },
  { e: 'Impressão digital', resp: 'Guilherme Sato', setor: 'Impressão', st: 'em execução', prev: '29/07' },
  { e: 'Acabamento — ilhós', resp: 'Equipe interna — box 2', setor: 'Acabamento', st: 'pendente', prev: '30/07' },
  { e: 'Expedição', resp: 'Larissa Prado', setor: 'Balcão', st: 'pendente', prev: '31/07' },
];
const FLUXO_ST = { 'concluída': 'var(--pos)', 'em execução': 'var(--warn)', pendente: 'var(--text-mute)' };

/* Validação fiscal — formato legal + coerência CST × alíquota.
   Erro fiscal sai daqui direto pra NF-e; rejeição da SEFAZ é retrabalho da Kamila. */
const soDig = (v) => String(v == null ? '' : v).replace(/\D/g, '');
const VALIDA = {
  ncm: (v) => { const n = soDig(v); if (!n) return 'NCM é obrigatório na NF-e'; if (n.length !== 8) return 'NCM tem 8 dígitos — faltam ' + Math.abs(8 - n.length); return null; },
  cfop: (v) => { const n = soDig(v); if (!n) return 'CFOP é obrigatório'; if (n.length !== 4) return 'CFOP tem 4 dígitos'; if (!'123567'.includes(n[0])) return 'CFOP começa em 1,2,3,5,6 ou 7'; return null; },
  cest: (v) => { const n = soDig(v); if (!n) return null; if (n.length !== 7) return 'CEST tem 7 dígitos'; return null; },
  gtin: (v) => { const n = soDig(v); if (!n) return null; if (![8, 12, 13, 14].includes(n.length)) return 'GTIN tem 8, 12, 13 ou 14 dígitos'; return null; },
  cbenef: (v) => { const t = String(v || '').trim(); if (!t) return null; if (!/^[A-Za-z]{2}\d{6}$/.test(t)) return 'cBenef: 2 letras da UF + 6 dígitos'; return null; },
  aliq: (v) => { const n = parseBR(v); if (n < 0) return 'Alíquota não pode ser negativa'; if (n > 100) return 'Alíquota acima de 100%'; return null; },
  red: (v) => { const n = parseBR(v); if (n < 0 || n > 100) return 'Redução vai de 0 a 100%'; return null; },
};
/* CST 40/41/60 (isento, não tributado, ST anterior) com alíquota ≠ 0 é rejeição certa */
const CST_SEM_ALIQ = ['40', '41', '60', '04'];
function erroCoerencia(cst, aliq) {
  const cod = String(cst || '').trim().slice(0, 3).replace(/\D/g, '');
  const a = parseBR(aliq);
  if (CST_SEM_ALIQ.includes(cod.slice(0, 2)) && a > 0) return 'CST ' + cod.slice(0, 2) + ' não admite alíquota — zere ou troque o CST';
  if (cod === '00' && a <= 0) return 'CST 00 é tributado integralmente — alíquota não pode ser zero';
  return null;
}

const dv = (l, campo, padrao) => (l && l[campo] !== undefined && l[campo] !== null) ? l[campo] : padrao;

function ItemDetail({ linha, index, total, onClose, onSave, onNav, abaInicial = 'geral' }) {
  const [aba, setAba] = React.useState(abaInicial);
  const [trib, setTrib] = React.useState('resumo');
  const [etapas, setEtapas] = React.useState(FLUXO_PADRAO);
  const [novaEtapa, setNovaEtapa] = React.useState(null);
  const inicial = (l) => ({ ncm: '39199090', cfop: '5102', ...l });
  const [d, setD] = React.useState(() => inicial(linha));
  const [base, setBase] = React.useState(() => inicial(linha));
  const [tocado, setTocado] = React.useState({});
  const [carregando, setCarregando] = React.useState(false);
  const [confirmando, setConfirmando] = React.useState(false);
  const [pendente, setPendente] = React.useState(null); /* navegação com edição não confirmada */

  /* carrega o item: skeleton curto (estado 3) — o dado fiscal vem do cadastro do produto */
  React.useEffect(() => {
    if (!linha) return;
    setCarregando(true);
    const copia = inicial(linha);
    setD(copia); setBase(copia);
    setTocado({}); setAba(abaInicial); setTrib('resumo');
    const t = setTimeout(() => setCarregando(false), 380);
    return () => clearTimeout(t);
  }, [linha, abaInicial]);
  if (!linha) return null;

  /* alteracao pendente: compara com o snapshot de abertura — a linha da venda nao tem campo fiscal */
  const sujo = JSON.stringify(d) !== JSON.stringify(base);
  const marcar = (campo) => () => setTocado((s) => ({ ...s, [campo]: true }));
  /* set + marca: digitar já marca o campo, então o erro aparece sem depender de onBlur
     (o Input/Select do DS não repassa onBlur — foi o que escondeu a validação) */
  const setV = (campo) => (v) => { setD((st) => ({ ...st, [campo]: (v && v.target) ? v.target.value : v })); setTocado((st) => st[campo] ? st : { ...st, [campo]: true }); };
  const erroDe = (campo, fn, valor) => (tocado[campo] ? fn(valor) : null);

  const set = (campo) => (e) => setD((s) => ({ ...s, [campo]: (e && e.target) ? e.target.value : e }));
  /* o tipo de comissionado segue o cadastro de quem está no item — não um default fixo,
     porque comTipo é o que decide folha de pagamento vs título a pagar */
  const comTipoItem = dv(d, 'comTipo', ((window.SD.pessoas.find((p) => p.nome === d.func) || {}).tipo) || 'funcionario');
  const valorLinha = submitSafe(parseBR(d.qtd) * parseBR(d.preco) * (1 - parseBR(d.desc) / 100) * (1 + parseBR(dv(d, 'acr', '0')) / 100));
  const m2 = submitSafe(parseBR(dv(d, 'altura', '1,00')) * parseBR(dv(d, 'largura', '1,00')) * parseBR(dv(d, 'pecas', '1')));
  const impostoDe = (i) => submitSafe(valorLinha * (parseBR(dv(d, 'aliq_' + i.k, fmtBR(i.aliq))) / 100));
  const somaImpostos = submitSafe(IMPOSTOS.reduce((s, i) => s + impostoDe(i), 0));

  const ABAS = [
    { key: 'geral', label: 'Geral' }, { key: 'producao', label: 'Produção' }, { key: 'fluxo', label: 'Fluxo de produção', count: etapas.length },
    { key: 'trib', label: 'Tributação' }, { key: 'preco', label: 'Preço' },
    { key: 'anexos', label: 'Anexos', count: 2 }, { key: 'obs', label: 'Observação' },
  ];

  const campoTrib = (i) => {
    const opcoes = i.k === 'icms' ? CST_ICMS : ['01 — Tributado', '04 — Isento', '49 — Outros', '99 — Outras saídas'];
    const cst = dv(d, 'cst_' + i.k, opcoes[0]);
    const aliq = dv(d, 'aliq_' + i.k, fmtBR(i.aliq));
    const eFaixa = erroDe('aliq_' + i.k, VALIDA.aliq, aliq);
    const eCoer = tocado['aliq_' + i.k] || tocado['cst_' + i.k] ? erroCoerencia(cst, aliq) : null;
    return (
    <>
    <Grid cols={4} gap={10}>
      <div><Select label={'CST / situação — ' + i.l} value={cst} onChange={(e) => { set('cst_' + i.k)(e); setTocado((s) => ({ ...s, ['cst_' + i.k]: true })); }} options={opcoes} error={eCoer} /></div>
      <Money label="Base de cálculo" value={fmtBR(valorLinha)} onChange={() => {}} />
      <Money label="Alíquota" prefix="%" value={aliq} onChange={setV('aliq_' + i.k)} onBlur={marcar('aliq_' + i.k)} error={eFaixa || eCoer} />
      <Money label="Valor do imposto" value={fmtBR(impostoDe(i))} onChange={() => {}} readOnly />
      {i.k === 'icms' && <>
        <Money label="Redução de base" prefix="%" value={dv(d, 'red_icms', '0,00')} onChange={setV('red_icms')} onBlur={marcar('red_icms')} error={erroDe('red_icms', VALIDA.red, dv(d, 'red_icms', '0,00'))} />
        <Money label="MVA / margem ST" prefix="%" value={dv(d, 'mva', '0,00')} onChange={setV('mva')} onBlur={marcar('mva')} error={erroDe('mva', VALIDA.red, dv(d, 'mva', '0,00'))} />
        <Money label="Base ST" value={fmtBR(0)} onChange={() => {}} readOnly />
        <Money label="ICMS ST" value={fmtBR(0)} onChange={() => {}} readOnly />
      </>}
      {(i.k === 'ibs' || i.k === 'cbs') && <>
        <div><Select label="Classificação tributária (cClassTrib)" options={['000001 — Regra geral', '200001 — Alíquota reduzida', '400001 — Isenção']} help="código de 6 dígitos da reforma tributária" /></div>
        <Money label="Alíquota efetiva" prefix="%" value={dv(d, 'aliq_ef_' + i.k, fmtBR(i.aliq))} onChange={set('aliq_ef_' + i.k)} />
        <Money label="Crédito presumido" value={fmtBR(0)} onChange={() => {}} readOnly help="calculado pela classificação" />
        <div style={{ display: 'flex', alignItems: 'flex-end' }}><Switch label="Monofasia" sublabel="Reforma tributária — transição 2026" checked={false} onChange={() => {}} /></div>
      </>}
      {i.k === 'issqn' && <>
        <div><Input label="Código do serviço (LC 116)" placeholder="17.06" /></div>
        <div><Select label="Município de incidência" options={['Joinville/SC — 4209102', 'Outro município']} /></div>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}><Switch label="ISS retido na fonte" checked={false} onChange={() => {}} /></div>
        <Money label="Base reduzida" value={fmtBR(valorLinha)} onChange={() => {}} readOnly />
      </>}
    </Grid>
    {i.k === 'icms' && (() => {
      /* DIFAL — EC 87/2015: venda interestadual para NÃO contribuinte recolhe a diferença
         de alíquota para a UF de destino (partilha 100% destino desde 2019). */
      const difal = dv(d, 'difal', false);
      const bc = valorLinha;
      const aInter = parseBR(dv(d, 'difal_inter', '12,00'));
      const aDest = parseBR(dv(d, 'difal_dest', '18,00'));
      const pFcp = parseBR(dv(d, 'difal_fcp', '2,00'));
      const vRemet = submitSafe(bc * aInter / 100);
      const vDest = submitSafe(bc * aDest / 100 - vRemet);
      const vFcp = submitSafe(bc * pFcp / 100);
      const invertido = aDest < aInter;
      return (
        <div style={{ marginTop: 12, padding: 12, borderRadius: 12, background: difal ? 'color-mix(in oklch, var(--warn) 7%, var(--surface))' : 'var(--bg-2)', border: '1px solid ' + (difal ? 'color-mix(in oklch, var(--warn) 26%, var(--border))' : 'var(--border)') }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 10, marginBottom: difal ? 12 : 0 }}>
            <Switch label="DIFAL — diferencial de alíquota" sublabel="venda interestadual para não contribuinte (EC 87/2015)" checked={difal} onChange={(v) => setD((s) => ({ ...s, difal: v }))} />
            {difal && <span style={{ marginLeft: 'auto', display: 'flex', gap: 10, alignItems: 'baseline' }}>
              <span><Lbl>Total DIFAL + FCP</Lbl><b style={{ font: '600 14px/1 var(--font-mono)' }}>{brl(submitSafe(Math.max(0, vDest) + vFcp))}</b></span>
            </span>}
          </div>
          {difal && <>
            <Grid cols={4} gap={10}>
              <div><Select label="UF de destino" value={dv(d, 'difal_uf', 'PR')} onChange={set('difal_uf')} options={['PR', 'SP', 'RJ', 'MG', 'RS', 'BA', 'PE', 'GO', 'DF']} /></div>
              <Money label="Alíquota interestadual" prefix="%" value={dv(d, 'difal_inter', '12,00')} onChange={setV('difal_inter')} help="7% ou 12% conforme a origem" />
              <Money label="Alíquota interna do destino" prefix="%" value={dv(d, 'difal_dest', '18,00')} onChange={setV('difal_dest')} error={invertido ? 'menor que a interestadual — não há DIFAL a recolher' : null} />
              <Money label="FCP do destino" prefix="%" value={dv(d, 'difal_fcp', '2,00')} onChange={setV('difal_fcp')} help="fundo de combate à pobreza" />
            </Grid>
            <div style={{ marginTop: 10, display: 'flex', flexWrap: 'wrap', gap: 16, padding: 12, borderRadius: 10, background: 'var(--surface)', border: '1px solid var(--border)' }}>
              {[['Base do DIFAL', bc], ['ICMS UF remetente', vRemet], ['ICMS UF destino', Math.max(0, vDest)], ['FCP UF destino', vFcp]].map(([l, v]) => (
                <div key={l}><Lbl>{l}</Lbl><b style={{ font: '600 13px/1 var(--font-mono)' }}>{fmtBR(v)}</b></div>
              ))}
              <span style={{ marginLeft: 'auto', font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)', maxWidth: 300 }}>
                Partilha <b>100% para o destino</b> desde 2019. Sai na NF-e como <code>vICMSUFDest</code>, <code>vICMSUFRemet</code> e <code>vFCPUFDest</code>.
              </span>
            </div>
            <Meta><p style={{ margin: '8px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Quem liga o DIFAL não deveria ser o operador: o gatilho é <b>UF do destinatário ≠ UF da empresa</b> + destinatário <b>não contribuinte</b> — os dois dados já estão no cadastro do cliente. Aqui está manual porque essa derivação automática <b>não tem CU</b> nos 33 do SDD v1.0.</p></Meta>
          </>}
        </div>
      );
    })()}
    </>
  ); };

  /* pendências fiscais do item — o que barra o Confirmar */
  const pendencias = [
    ['NCM', VALIDA.ncm(dv(d, 'ncm', ''))], ['CFOP', VALIDA.cfop(dv(d, 'cfop', ''))],
    ['CEST', VALIDA.cest(dv(d, 'cest', ''))], ['GTIN', VALIDA.gtin(dv(d, 'gtin', ''))],
    ['cBenef', VALIDA.cbenef(dv(d, 'cbenef', ''))],
    ...IMPOSTOS.map((i) => {
      const cst = dv(d, 'cst_' + i.k, (i.k === 'icms' ? CST_ICMS : ['01 — Tributado'])[0]);
      const aliq = dv(d, 'aliq_' + i.k, fmtBR(i.aliq));
      return [i.l, VALIDA.aliq(aliq) || erroCoerencia(cst, aliq)];
    }),
  ].filter(([, e]) => e);

  const confirmar = () => {
    if (pendencias.length) { setTocado(Object.fromEntries([...Object.keys(d), ...IMPOSTOS.map((i) => 'aliq_' + i.k), 'ncm', 'cfop', 'cest', 'gtin', 'cbenef'].map((k) => [k, true]))); setAba('trib'); return; }
    setConfirmando(true);
    setTimeout(() => { setConfirmando(false); onSave(d); }, 420);
  };
  /* navegar com edição pendente pede confirmação (achado ALTA nº2) */
  const navegar = (i) => { if (sujo) { setPendente(i); return; } onNav(i); };

  return (
    <Drawer open={!!linha} onClose={onClose} width={880}
      title={'Item ' + (index + 1) + ' · ' + (linha.nome.length > 48 ? linha.nome.slice(0, 47) + '…' : linha.nome)}
      subtitle={linha.sku + ' · ' + linha.un + ' · ' + brl(valorLinha)}
      badge={<Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))" mono>{(index + 1) + '/' + total}</Pill>}
      footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
        <Button size="sm" disabled={index === 0} onClick={() => navegar(index - 1)}>‹ Anterior</Button>
        <Button size="sm" disabled={index === total - 1} onClick={() => navegar(index + 1)}>Próximo ›</Button>
        {sujo && <Pill mono c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">alteração não confirmada</Pill>}
        {pendencias.length > 0 && <Pill mono c="var(--neg)" s="color-mix(in oklch, var(--neg) 12%, var(--surface))">{pendencias.length === 1 ? '1 pendência fiscal' : pendencias.length + ' pendências fiscais'}</Pill>}
        <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <Button onClick={onClose}>Cancelar</Button>
          <Button variant="primary" disabled={confirmando} onClick={confirmar}>{confirmando ? 'Confirmando…' : 'Confirmar item'}</Button>
        </span>
      </div>}>
      <div style={{ padding: '0 0 4px' }}><TabBar active={aba} onChange={setAba} tabs={ABAS} /></div>

      {pendencias.length > 0 && Object.keys(tocado).length > 0 && <div style={{ padding: '12px 0 0' }}>
        <Alert tone="danger" title={pendencias.length === 1 ? 'Uma pendência fiscal impede confirmar' : pendencias.length + ' pendências fiscais impedem confirmar'}
          action={aba !== 'trib' ? <Button size="sm" onClick={() => setAba('trib')}>Ir para Tributação</Button> : null}>
          {pendencias.slice(0, 3).map(([campo, erro]) => <span key={campo} style={{ display: 'block' }}><b>{campo}:</b> {erro}</span>)}
          {pendencias.length > 3 && <span style={{ display: 'block', color: 'var(--text-dim)' }}>e mais {pendencias.length - 3}…</span>}
        </Alert>
      </div>}

      {carregando && <DrawerSection title="Carregando dados fiscais do produto">
        <Grid cols={4} gap={10}>{Array.from({ length: 8 }).map((_, i) => <div key={i}><Skeleton variant="caption" width="60%" /><Skeleton variant="row" /></div>)}</Grid>
      </DrawerSection>}

      <Modal open={pendente !== null} onClose={() => setPendente(null)} width={480} title="Há alteração não confirmada neste item"
        footer={<div style={{ display: 'flex', gap: 8, width: '100%' }}>
          <Button onClick={() => { const i = pendente; setPendente(null); onNav(i); }}>Descartar e continuar</Button>
          <span style={{ marginLeft: 'auto' }}><Button variant="primary" onClick={() => { const i = pendente; setPendente(null); if (!pendencias.length) { onSave(d); onNav(i); } else { setAba('trib'); } }}>Confirmar e continuar</Button></span>
        </div>}>
        <p style={{ margin: 0, font: '13.5px/1.5 var(--font-sans)' }}>Você alterou este item e ainda não confirmou. Ir para outro item agora <b>descarta a alteração</b>.</p>
      </Modal>

      {!carregando && aba === 'geral' && <>
        <DrawerSection title="Identificação e medidas">
          <Grid cols={4} gap={10}>
            <div><Input label="Código do produto" defaultValue={linha.sku} readOnly /></div>
            <div><Input label="Descrição na venda" value={d.nome} onChange={set('nome')} /></div>
            <div><Select label="Unidade" defaultValue={linha.un} options={['m²', 'un', 'h', 'm', 'kg']} /></div>
            <Money label="Peças" prefix="qt" value={dv(d, 'pecas', '1')} onChange={set('pecas')} />
            <Money label="Altura" prefix="m" value={dv(d, 'altura', '1,00')} onChange={set('altura')} />
            <Money label="Largura" prefix="m" value={dv(d, 'largura', '1,00')} onChange={set('largura')} />
            <Money label="Espessura" prefix="mm" value={dv(d, 'esp', '0,00')} onChange={set('esp')} />
            <Money label="Área calculada" prefix="m²" value={fmtBR(m2)} onChange={() => {}} readOnly help="peças × altura × largura" />
          </Grid>
        </DrawerSection>
        <DrawerSection title="Valores da linha">
          <Grid cols={4} gap={10}>
            <Money label="Quantidade" prefix="qt" value={d.qtd} onChange={set('qtd')} />
            <Money label="Valor unitário" value={d.preco} onChange={set('preco')} />
            <div><Select label="Tipo de preço" options={['Tabela do grupo', 'Manual', 'Por m²', 'Por milheiro']} /></div>
            <Money label="% desconto" prefix="%" value={d.desc} onChange={set('desc')} />
            <Money label="Desconto R$" value={fmtBR(submitSafe(parseBR(d.qtd) * parseBR(d.preco) * parseBR(d.desc) / 100))} onChange={() => {}} readOnly />
            <Money label="% acréscimo" prefix="%" value={dv(d, 'acr', '0')} onChange={set('acr')} />
            <Money label="Total deste item" value={fmtBR(valorLinha)} onChange={() => {}} readOnly help="quantidade × valor unitário, com desconto e acréscimo" />
          </Grid>
        </DrawerSection>
        <DrawerSection title="Comissão deste item">
          <div style={{ display: 'flex', alignItems: 'flex-end', gap: 10, marginBottom: dv(d, 'comiss', true) ? 10 : 0 }}>
            <Switch label="Comissiona este item" sublabel="desligado, o item sai da base de comissão da venda" checked={dv(d, 'comiss', true)} onChange={(v) => setD((s) => ({ ...s, comiss: v }))} />
          </div>
          {dv(d, 'comiss', true) && <>
            <Grid cols={4} gap={10}>
              <div><Select label="Tipo de comissionado" value={comTipoItem} onChange={set('comTipo')} options={COM_TIPOS.map((t) => ({ value: t.k, label: t.l }))} /></div>
              <div><Select label="Quem executa / vende" value={dv(d, 'func', '')} onChange={set('func')}
                options={(() => {
                  const base = comOpcoes(comTipoItem);
                  const atual = dv(d, 'func', '');
                  /* valor gravado que não pertence ao tipo escolhido não pode virar a opção 0 em silêncio */
                  return (!atual || base.some((o) => o.value === atual)) ? [{ value: '', label: '—' }, ...base] : [{ value: atual, label: atual + ' · fora deste tipo' }, ...base];
                })()} /></div>
              <div><Select label="Base de cálculo" value={dv(d, 'comBase', 'liquido')} onChange={set('comBase')} options={COM_BASES} /></div>
              <Money label="Percentual" prefix="%" value={dv(d, 'comPct', '3,00')} onChange={set('comPct')} />
            </Grid>
            <div style={{ marginTop: 10, display: 'flex', flexWrap: 'wrap', gap: 16, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
              <div><Lbl>Base do item</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(valorLinha)}</b></div>
              <div><Lbl>Comissão do item</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(submitSafe(valorLinha * parseBR(dv(d, 'comPct', '3,00')) / 100))}</b></div>
              <span style={{ marginLeft: 'auto', font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)', maxWidth: 360 }}>
                Somada à comissão da venda e apurada para <b>quem executou</b> — não para quem digitou. É por aqui que serviço com técnico próprio recebe percentual diferente do produto.
              </span>
            </div>
          </>}
        </DrawerSection>
      </>}

      {!carregando && aba === 'producao' && <>
        <DrawerSection title="Instruções de produção">
          <Grid cols={3} gap={10}>
            <div><Select label="Em produção" defaultValue="Não" options={['Não', 'Sim']} /></div>
            <div><Select label="Tipo de impressão" value={dv(d, 'impressao', IMPRESSOES_DET[0])} onChange={set('impressao')} options={IMPRESSOES_DET} /></div>
            <div><Select label="Acabamento" options={['Sem acabamento', 'Ilhós a cada 50cm', 'Bastão + corda', 'Solda perimetral', 'Laminação']} /></div>
            <div><Select label="Local de aplicação" value={dv(d, 'local', LOCAIS_DET[0])} onChange={set('local')} options={LOCAIS_DET} /></div>
            <div><Select label="Equipamento / setor" value={dv(d, 'equip', '')} onChange={set('equip')}
              options={[{ value: '', label: '—' }, ...window.SD.equipamentos.map((e) => ({ value: e.nome, label: e.nome + ' · ' + e.setor }))]} /></div>
            <div><Select label="Prioridade" options={['Normal', 'Urgente', 'Programada']} /></div>
            <div><Select label="Requisitar do estoque" value={dv(d, 'localEstoque', (window.SD.catalogo.find((p) => p.sku === linha.sku) || {}).localEstoque || '')} onChange={set('localEstoque')}
              options={[{ value: '', label: 'Não requisita (serviço)' }, ...window.SD.locaisEstoque.map((l) => ({ value: l, label: l }))]} help="de onde a produção retira o material" /></div>
            <div><DataCampo label="Prazo da equipe (produção)" value={dv(d, 'prazoEquipe', null)} onChange={set('prazoEquipe')} /></div>
            <div><DataCampo label="Prazo da etapa" value={dv(d, 'prazoEtapa', null)} onChange={set('prazoEtapa')} /></div>
            <div />
          </Grid>
          <div style={{ marginTop: 12 }}><Textarea label="Observação de produção (vai na OP, não sai no documento do cliente)" rows={3} value={dv(d, 'obsProd', '')} onChange={set('obsProd')} placeholder="Sangria de 5cm. Cliente aprovou arte por e-mail em 27/07." /></div>
        </DrawerSection>
        <DrawerSection title="Arquivo de arte">
          <div style={{ display: 'flex', gap: 12, alignItems: 'center', padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px dashed var(--border)' }}>
            <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Caminho do arquivo na rede</span>
            <div style={{ flex: 1, minWidth: 0 }}><Input placeholder="\\\\servidor\\arte\\2026\\07\\lona-prefeitura-v3.pdf" /></div>
            <Button size="sm">Anexar arquivo</Button>
          </div>
        </DrawerSection>
      </>}

      {!carregando && aba === 'fluxo' && <DrawerSection title="Fluxo de produção deste item">
        {etapas.length === 0 && <EmptyState variant="first" title="Nenhuma etapa neste item" description="Este produto não tem fluxo de produção configurado. Aplique o fluxo padrão do cadastro ou monte as etapas à mão." action={<Button size="sm" variant="primary">Aplicar fluxo padrão</Button>} />}
        {etapas.length > 0 && <DataTable
          columns={[{ key: 'e', label: 'Etapa' }, { key: 'r', label: 'Responsável' }, { key: 'se', label: 'Setor' }, { key: 'st', label: 'Situação' }, { key: 'p', label: 'Previsão', mono: true }, { key: 'x', label: '', align: 'center' }]}
          rows={etapas.map((et, i) => ({ id: i, cells: {
            e: et.e, r: et.resp, se: <Pill>{et.setor}</Pill>,
            st: <Pill c={FLUXO_ST[et.st]} s={et.st === 'pendente' ? 'var(--bg-2)' : 'color-mix(in oklch, ' + FLUXO_ST[et.st] + ' 12%, var(--surface))'}>{et.st}</Pill>,
            p: et.prev,
            x: <button type="button" aria-label={'Remover etapa ' + et.e} onClick={() => setEtapas(etapas.filter((_, j) => j !== i))}
              style={{ width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="X" size={13} /></button>,
          } }))} />}
        <div style={{ marginTop: 12, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <Button size="sm" onClick={() => setNovaEtapa({ e: '', resp: (window.SD.pessoas[0] || {}).nome, setor: window.SD.setores[0], st: 'pendente', prev: '' })}>Adicionar etapa</Button>
          <Button size="sm" onClick={() => setEtapas(FLUXO_PADRAO)}>Aplicar fluxo padrão do produto</Button>
        </div>
        <Modal open={!!novaEtapa} onClose={() => setNovaEtapa(null)} width={560} title="Nova etapa do fluxo"
          footer={<div style={{ display: 'flex', gap: 8, width: '100%' }}>
            <Button onClick={() => setNovaEtapa(null)}>Cancelar</Button>
            <span style={{ marginLeft: 'auto' }}><Button variant="primary" disabled={!novaEtapa || !novaEtapa.e.trim()}
              onClick={() => { setEtapas([...etapas, novaEtapa]); setNovaEtapa(null); }}>Adicionar</Button></span>
          </div>}>
          {novaEtapa && <Grid cols={2} gap={10}>
            <div><Input label="Etapa" value={novaEtapa.e} onChange={(e) => setNovaEtapa({ ...novaEtapa, e: e.target.value })} placeholder="Ex: aplicação no local" /></div>
            <div><Select label="Setor" value={novaEtapa.setor} onChange={(e) => setNovaEtapa({ ...novaEtapa, setor: e.target.value })} options={window.SD.setores} /></div>
            <div><Select label="Responsável" value={novaEtapa.resp} onChange={(e) => setNovaEtapa({ ...novaEtapa, resp: e.target.value })} options={window.SD.pessoas.map((p) => p.nome)} /></div>
            <div><Input label="Previsão" value={novaEtapa.prev} onChange={(e) => setNovaEtapa({ ...novaEtapa, prev: e.target.value })} placeholder="dd/mm" /></div>
          </Grid>}
        </Modal>
      </DrawerSection>}

      {!carregando && aba === 'trib' && <>
        <DrawerSection title="Classificação fiscal">
          <Grid cols={4} gap={10}>
            <div><Select label="Grupo do produto" options={['17 — VENDA', '18 — SERVIÇO', '21 — REVENDA']} /></div>
            <Campo label="NCM" value={dv(d, 'ncm', '')} onChange={setV('ncm')} onBlur={marcar('ncm')} error={erroDe('ncm', VALIDA.ncm, dv(d, 'ncm', ''))} help="8 dígitos — Mercosul" />
            <Campo label="CEST" value={dv(d, 'cest', '')} onChange={setV('cest')} onBlur={marcar('cest')} error={erroDe('cest', VALIDA.cest, dv(d, 'cest', ''))} placeholder="sem CEST" />
            <Campo label="CFOP" value={dv(d, 'cfop', '')} onChange={setV('cfop')} onBlur={marcar('cfop')} error={erroDe('cfop', VALIDA.cfop, dv(d, 'cfop', ''))} help="natureza da operação" />
            <div><Select label="Origem da mercadoria" options={['0 — Nacional', '1 — Importação direta', '2 — Adquirida no mercado interno']} /></div>
            <div><Input label="Cód. de fábrica" placeholder="não informado" /></div>
            <Campo label="Cód. EAN / GTIN" value={dv(d, 'gtin', '')} onChange={setV('gtin')} onBlur={marcar('gtin')} error={erroDe('gtin', VALIDA.gtin, dv(d, 'gtin', ''))} placeholder="sem GTIN" />
            <Campo label="cBenef" value={dv(d, 'cbenef', '')} onChange={setV('cbenef')} onBlur={marcar('cbenef')} error={erroDe('cbenef', VALIDA.cbenef, dv(d, 'cbenef', ''))} placeholder="ex: SC830001" help="2 letras da UF + 6 dígitos" />
          </Grid>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center' }}>
            <Switch label="Não recalcular impostos na impressão da nota" checked={dv(d, 'norecalc', false)} onChange={(v) => setD((s) => ({ ...s, norecalc: v }))} />
            <span style={{ marginLeft: 'auto' }}><Button size="sm">Recalcular impostos</Button></span>
          </div>
        </DrawerSection>
        <DrawerSection title="Impostos do item">
          <span style={{ display: 'block', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)', marginBottom: 8 }}>Um imposto por linha — abra a setinha para ver e editar os campos. O ponto verde marca o que tem valor nesta venda.</span>
          <div style={{ border: '1px solid var(--border)', borderRadius: 12, overflow: 'hidden' }}>
            <div className="imp-linha" style={{ padding: '8px 12px', background: 'var(--bg-2)', borderBottom: '1px solid var(--border)' }}>
              {['Imposto', 'Base de cálculo', 'Alíquota', 'Valor', ''].map((h, k) => (
                <span key={h + k} style={{ font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', textAlign: k === 0 ? 'left' : k === 4 ? 'center' : 'right' }}>{h}</span>
              ))}
            </div>
            {IMPOSTOS.map((i) => {
              const aberto = trib === i.k;
              const val = impostoDe(i);
              const temValor = val > 0.005;
              const cstAtual = dv(d, 'cst_' + i.k, (i.k === 'icms' ? CST_ICMS : ['01 — Tributado'])[0]);
              const aliqAtual = dv(d, 'aliq_' + i.k, fmtBR(i.aliq));
              const erro = !!(VALIDA.aliq(aliqAtual) || erroCoerencia(cstAtual, aliqAtual));
              return (
                <div key={i.k} style={{ borderBottom: '1px solid var(--border-2)', background: aberto ? 'var(--bg-2)' : 'transparent' }}>
                  <button type="button" className="imp-linha" onClick={() => setTrib(aberto ? 'resumo' : i.k)} aria-expanded={aberto}
                    style={{ width: '100%', padding: '9px 12px', border: 0, background: 'transparent', cursor: 'pointer', textAlign: 'left', font: '13.5px/1.4 var(--font-sans)', color: 'var(--text)' }}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 7, minWidth: 0 }}>
                      <b style={{ fontWeight: 600 }}>{i.l}</b>
                      {erro
                        ? <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, font: '600 11px/1 var(--font-sans)', color: tomFg('var(--neg)') }}><Icon name="AlertTriangle" size={11} />pendência</span>
                        : temValor && <span aria-label="tem valor nesta venda" style={{ width: 5, height: 5, borderRadius: 999, background: 'var(--pos)' }}></span>}
                      {i.k === 'icms' && (dv(d, 'difal', false)
                        ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">DIFAL ligado</Pill>
                        : <Pill>tem DIFAL</Pill>)}
                    </span>
                    <span style={{ textAlign: 'right', font: '13px/1 var(--font-mono)', color: 'var(--text-dim)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(valorLinha)}</span>
                    <span style={{ textAlign: 'right', font: '13px/1 var(--font-mono)', color: erro ? tomFg('var(--neg)') : temValor ? 'var(--text)' : 'var(--text-dim)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(parseBR(aliqAtual))}%</span>
                    <span style={{ textAlign: 'right', font: '600 13.5px/1 var(--font-mono)', color: temValor ? 'var(--text)' : 'var(--text-dim)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(val)}</span>
                    <span aria-hidden="true" style={{ display: 'inline-flex', justifyContent: 'center', color: 'var(--text-dim)', transform: aberto ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }}><Icon name="ChevronDown" size={15} /></span>
                  </button>
                  {aberto && <div style={{ padding: '2px 12px 14px' }}>{campoTrib(i)}</div>}
                </div>
              );
            })}
            <div className="imp-linha" style={{ padding: '10px 12px', background: 'var(--bg-2)' }}>
              <span style={{ gridColumn: 'span 3', textAlign: 'right', font: '600 11px/1 var(--font-sans)', letterSpacing: '.04em', textTransform: 'uppercase', color: 'var(--text-dim)' }}>Total de impostos do item</span>
              <span style={{ textAlign: 'right', font: '600 15px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(somaImpostos)}</span>
              <span></span>
            </div>
          </div>

          <div style={{ marginTop: 14 }}><Grid cols={4} gap={10}>
            <Money label="IBPT nacional" prefix="%" value="0,00" onChange={() => {}} />
            <Money label="IBPT importação" prefix="%" value="0,00" onChange={() => {}} />
            <Money label="IBPT estadual" prefix="%" value="0,00" onChange={() => {}} />
            <Money label="IBPT municipal" prefix="%" value="0,00" onChange={() => {}} />
            <Money label="Peso líquido" prefix="kg" value={dv(d, 'peso', '0,00')} onChange={set('peso')} help="só o produto" />
            <Money label="Peso bruto" prefix="kg" value={dv(d, 'peso_bruto', dv(d, 'peso', '0,00'))} onChange={set('peso_bruto')} help="com embalagem" />
            <Money label="Despesas acessórias" value={dv(d, 'desp', '0,00')} onChange={set('desp')} />
            <Money label="Frete do item" value={dv(d, 'frete_item', '0,00')} onChange={set('frete_item')} />
          </Grid></div>
        </DrawerSection>
        <DrawerSection title="Importação">
          <Grid cols={3} gap={10}>
            <div><Input label="Nº da DI / DUIMP" placeholder="produto nacional" /></div>
            <div><DataCampo label="Data do desembaraço" /></div>
            <div><Input label="Local do desembaraço" placeholder="não se aplica" /></div>
            <Money label="Valor aduaneiro" value="0,00" onChange={() => {}} />
            <Money label="AFRMM" value="0,00" onChange={() => {}} />
            <div><Select label="Via de transporte" options={['Marítima', 'Aérea', 'Rodoviária']} /></div>
          </Grid>
        </DrawerSection>
        <DrawerSection title="Descrição na NF-e">
          <Textarea label="Descrição do produto como sai na NF-e" rows={4} defaultValue={linha.nome} help="é este texto que o cliente lê na nota — não a descrição interna" />
        </DrawerSection>
      </>}

      {!carregando && aba === 'preco' && <DrawerSection title="Preço deste item">
        <Grid cols={3} gap={10}>
          <Money label="Preço de tabela" value="68,90" onChange={() => {}} readOnly help="o que a tabela do cliente indica" />
          <Money label="Menor preço permitido" value="58,40" onChange={() => {}} readOnly help="abaixo disto precisa liberação do supervisor" />
          <Money label="Preço nesta venda" value={d.preco} onChange={set('preco')} help="o valor que vai para o cliente" />
        </Grid>
        <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 14, alignItems: 'center', padding: 12, borderRadius: 12, background: parseBR(d.preco) >= 58.4 ? 'color-mix(in oklch, var(--pos) 8%, var(--surface))' : 'color-mix(in oklch, var(--neg) 8%, var(--surface))', border: '1px solid ' + (parseBR(d.preco) >= 58.4 ? 'color-mix(in oklch, var(--pos) 26%, transparent)' : 'color-mix(in oklch, var(--neg) 26%, transparent)') }}>
          {parseBR(d.preco) >= 58.4
            ? <><Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">preço liberado</Pill>
              <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Está {brl(submitSafe(parseBR(d.preco) - 58.4))} acima do menor preço permitido — pode fechar sem pedir nada.</span></>
            : <><Pill c="var(--neg)" s="color-mix(in oklch, var(--neg) 12%, var(--surface))">precisa liberação</Pill>
              <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Faltam {brl(submitSafe(58.4 - parseBR(d.preco)))} para chegar ao menor preço permitido. Chame o supervisor antes de fechar.</span></>}
          <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'baseline', gap: 6 }}>
            <Lbl>Desconto sobre a tabela</Lbl>
            <b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{num(Math.max(0, (68.9 - parseBR(d.preco)) / 68.9 * 100), 1)}%</b>
          </span>
        </div>
        <p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Custo, markup e margem <b>não aparecem para o vendedor</b> — o limite comercial já está no "menor preço permitido". Quem forma preço faz isso no cadastro do produto, com permissão própria.</p>
      </DrawerSection>}

      {!carregando && aba === 'anexos' && <DrawerSection title="Anexos do item">
        <div style={{ padding: 16, borderRadius: 12, border: '1px dashed var(--border)', background: 'var(--bg-2)', textAlign: 'center' }}>
          <p style={{ margin: '0 0 8px', font: '12.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Arraste o arquivo de arte, a foto do local ou o comprovante de aprovação.</p>
          <Button size="sm" variant="primary">Escolher arquivo</Button>
        </div>
        <div style={{ marginTop: 12 }}>
          <DataTable columns={[{ key: 'n', label: 'Arquivo' }, { key: 't', label: 'Tipo' }, { key: 'd', label: 'Enviado', mono: true }, { key: 'a', label: '', align: 'center' }]}
            rows={[['lona-prefeitura-v3.pdf', 'arte final', '27/07/2026'], ['aprovacao-email.png', 'aprovação', '27/07/2026']].map(([n, t, dt], i) => ({ id: i, cells: { n, t: <Pill>{t}</Pill>, d: dt, a: <Button size="sm">Baixar</Button> } }))} />
        </div>
      </DrawerSection>}

      {!carregando && aba === 'obs' && <DrawerSection title="Observações do produto">
        <Textarea label="Observação geral do produto (sai no documento do cliente)" rows={4} placeholder="Lona com 5cm de sangria em cada lado, ilhós a cada 50cm." />
        <div style={{ marginTop: 12 }}><Textarea label="Observação interna (não sai no documento)" rows={3} value={dv(d, 'obsItem', '')} onChange={set('obsItem')} placeholder="Cliente reclamou da cor na última compra — conferir perfil ICC." /></div>
        <Meta><p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-mute)' }}>Duas observações separadas de propósito: a do cliente vai pro PDF/NF-e, a interna fica na OP. Unificar as duas foi o que gerou a reclamação de vazamento de nota interna no documento (CU-SELL-12).</p></Meta>
      </DrawerSection>}
    </Drawer>
  );
}

/* ── sells-parcelas.jsx ── */
/* Financeiro da venda — condição de pagamento, geração e edição de múltiplas parcelas.
   Equivalente à aba "Financeiro" + diálogo "Parcela" do legado. */

const CONDICOES = [
  { id: '18', nome: 'PIX', parcelas: 1, intervalo: 0, tipo: 'PIX' },
  { id: '02', nome: 'Boleto 30/60', parcelas: 2, intervalo: 30, tipo: 'Boleto' },
  { id: '05', nome: 'Cartão 3x sem juros', parcelas: 3, intervalo: 30, tipo: 'Cartão de crédito' },
  { id: '09', nome: 'Entrada + 2x', parcelas: 3, intervalo: 28, tipo: 'Boleto' },
  { id: '12', nome: 'À vista — dinheiro', parcelas: 1, intervalo: 0, tipo: 'Dinheiro' },
];
const PLANOS = ['1.1.5 — Recebido em depósito', '1.1.1 — Caixa', '1.2.1 — Duplicatas a receber'];
const CONTAS = ['1 — Caixa financeiro', '2 — Banco Itaú c/c', '3 — Banco Sicredi'];
const LANC = ['A RECEBER', 'RECEBIDA'];

const hoje0 = () => { const d = new Date(); d.setHours(0, 0, 0, 0); return d; };
const dia0 = (v) => { const d = new Date(v); d.setHours(0, 0, 0, 0); return d; };
const vencida = (v) => dia0(v) < hoje0();
const addDias = (base, dias) => { const d = new Date(base.getTime()); d.setDate(d.getDate() + dias); return d; };
const dBR = (d) => d.toLocaleDateString('pt-BR');

/* Divide `total` em n parcelas com centavo de ajuste na PRIMEIRA (evita soma ≠ total) */
function ratear(total, n) {
  const cent = Math.round(submitSafe(total) * 100);
  const base = Math.floor(cent / n);
  const resto = cent - base * n;
  return Array.from({ length: n }, (_, i) => (base + (i < resto ? 1 : 0)) / 100);
}

function ParcelasDrawer({ open, onClose, total, parcelas, setParcelas, docBase, onOpen }) {
  const meta = useMeta();
  const [cond, setCond] = React.useState('18');
  const [n, setN] = React.useState('2');
  const [intervalo, setIntervalo] = React.useState('30');
  const [porMes, setPorMes] = React.useState(true);
  const [caixa, setCaixa] = React.useState(CONTAS[0]);
  const [primeiro, setPrimeiro] = React.useState(hoje0);
  const [edit, setEdit] = React.useState(null);
  const [undo, setUndo] = React.useState(null);
  React.useEffect(() => { if (!undo) return; const t = setTimeout(() => setUndo(null), 7000); return () => clearTimeout(t); }, [undo]);

  const c = CONDICOES.find((x) => x.id === cond) || CONDICOES[0];
  const soma = submitSafe(parcelas.reduce((s, p) => s + parseBR(p.valor), 0));
  const dif = submitSafe(total - soma);

  const aplicarCondicao = (id) => {
    const x = CONDICOES.find((y) => y.id === id) || CONDICOES[0];
    setCond(id); setN(String(x.parcelas)); setIntervalo(String(x.intervalo));
  };

  const gerar = () => {
    const qtd = Math.max(1, Math.min(48, Math.round(parseBR(n)) || 1));
    const passo = porMes ? 30 : (Math.round(parseBR(intervalo)) || 0);
    const valores = ratear(total, qtd);
    setParcelas(valores.map((v, i) => ({
      k: Date.now() + i, num: i + 1, de: qtd, valor: fmtBR(v),
      venc: addDias(dia0(primeiro), i * passo), pgto: null,
      tipo: c.tipo, lanc: 'A RECEBER', plano: PLANOS[0], conta: caixa,
      doc: docBase + ' ' + (i + 1) + '/' + qtd, resp: '', hist: '',
    })));
  };
  const setP = (k, campo, v) => setParcelas((s) => s.map((p) => p.k === k ? { ...p, [campo]: v } : p));
  const receber = (k) => setParcelas((s) => s.map((p) => p.k === k ? { ...p, lanc: 'RECEBIDA', pgto: new Date() } : p));
  const ajustarUltima = () => setParcelas((s) => s.map((p, i) => i === s.length - 1 ? { ...p, valor: fmtBR(submitSafe(parseBR(p.valor) + dif)) } : p));

  return (
    <>
      <Drawer open={open} onClose={onClose} width={860} title="Financeiro da venda — parcelas"
        subtitle={'Total a parcelar ' + brl(total)}
        badge={<Pill c={Math.abs(dif) < 0.005 ? 'var(--pos)' : 'var(--neg)'} s={Math.abs(dif) < 0.005 ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'color-mix(in oklch, var(--neg) 12%, var(--surface))'} mono>{parcelas.length ? parcelas.length + 'x' : 'sem parcelas'}</Pill>}
        footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
          <div>
            <Lbl>Soma das parcelas</Lbl>
            <b style={{ font: '600 15px/1 var(--font-mono)', color: Math.abs(dif) < 0.005 ? 'var(--pos)' : 'var(--neg)' }}>{brl(soma)}</b>
          </div>
          {Math.abs(dif) >= 0.005 && <Button size="sm" onClick={ajustarUltima}>Jogar {brl(Math.abs(dif))} na última</Button>}
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button onClick={onClose}>Fechar</Button>
            <Button variant="primary" disabled={Math.abs(dif) >= 0.005} onClick={onClose}>Confirmar parcelas</Button>
          </span>
        </div>}>
        <DrawerSection title="Condição de pagamento">
          <Grid cols={4} gap={10}>
            <div><Select label="Condição" value={cond} onChange={(e) => aplicarCondicao(e.target.value)} options={CONDICOES.map((x) => ({ value: x.id, label: x.id + ' — ' + x.nome }))} /></div>
            <Money label="Parcelas" prefix="qt" value={n} onChange={setN} />
            <Money label="Intervalo (dias)" prefix="d" value={porMes ? '30' : intervalo} onChange={setIntervalo} readOnly={porMes} />
            <div><DataCampo label="1º vencimento" value={primeiro} onChange={(d) => d && setPrimeiro(dia0(d))} /></div>
            <div><Select label="Caixa / conta de destino" value={caixa} onChange={(e) => setCaixa(e.target.value)} options={CONTAS} /></div>
            <div><Select label="Tipo de pagamento" defaultValue={c.tipo} options={['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Boleto', 'Cheque', 'Fiado (a prazo)']} /></div>
            <div style={{ display: 'flex', alignItems: 'flex-end' }}><Switch label="Mês fechado" sublabel="Vence no mesmo dia de cada mês" checked={porMes} onChange={setPorMes} /></div>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8 }}>
              <Button variant="primary" onClick={gerar}>Gerar parcelas</Button>
              {parcelas.length > 0 && <Button onClick={() => setParcelas([])}>Limpar</Button>}
            </div>
          </Grid>
        </DrawerSection>

        <DrawerSection title="Recebimento">
          {parcelas.length === 0
            ? <EmptyState variant="first" title="Nenhuma parcela gerada" description="Escolha a condição, o número de parcelas e o 1º vencimento e clique em Gerar parcelas. Depois você pode editar valor, data e conta de cada uma." />
            : <div className="oi-scroll" style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', minWidth: 760, borderCollapse: 'separate', borderSpacing: 0, font: '13.5px/1.4 var(--font-sans)' }}>
                <thead><tr>{['#', 'Valor', 'Vencimento', 'Tipo', 'Conta', 'Documento', 'Situação', ''].map((h, i) => (
                  <th key={h + i} style={{ background: 'var(--bg-2)', padding: '8px 12px', textAlign: i === 1 ? 'right' : i === 7 ? 'center' : 'left', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap' }}>{h}</th>
                ))}</tr></thead>
                <tbody>{parcelas.map((p) => (
                  <tr key={p.k}>
                    <td style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-2)', font: '600 12.5px/1 var(--font-mono)', whiteSpace: 'nowrap' }}>{p.num}/{p.de}</td>
                    <td style={{ padding: '4px 8px', borderBottom: '1px solid var(--border-2)', width: 118 }}>
                      <div className="dsfa pre"><span className="afx l">R$</span><input value={p.valor} inputMode="decimal" aria-label={'Valor da parcela ' + p.num + ' de ' + p.de} onChange={(e) => setP(p.k, 'valor', e.target.value)} style={{ textAlign: 'right', fontFamily: 'var(--font-mono)' }} /></div>
                    </td>
                    <td style={{ padding: '4px 8px', borderBottom: '1px solid var(--border-2)', width: 150 }}>
                      <DataCampo value={p.venc} onChange={(d) => d && setP(p.k, 'venc', dia0(d))} />
                    </td>
                    <td style={{ padding: '4px 8px', borderBottom: '1px solid var(--border-2)', width: 130 }}>
                      <Select value={p.tipo} onChange={(e) => setP(p.k, 'tipo', e.target.value)} options={['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Boleto', 'Cheque']} />
                    </td>
                    <td style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-2)', font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>{p.conta}</td>
                    <td style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-2)', font: '11.5px/1.3 var(--font-mono)', color: 'var(--text-mute)', whiteSpace: 'nowrap' }}>{p.doc}</td>
                    <td style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-2)' }}>
                      {p.lanc === 'RECEBIDA'
                        ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">recebida {p.pgto ? dBR(p.pgto) : ''}</Pill>
                        : <Pill c={vencida(p.venc) ? 'var(--neg)' : 'var(--warn)'} s={vencida(p.venc) ? 'color-mix(in oklch, var(--neg) 12%, var(--surface))' : 'color-mix(in oklch, var(--warn) 12%, var(--surface))'}>{vencida(p.venc) ? 'vencida' : 'a receber'}</Pill>}
                    </td>
                    <td style={{ padding: '4px 8px', borderBottom: '1px solid var(--border-2)', textAlign: 'center', whiteSpace: 'nowrap' }}>
                      <DropdownMenu align="end" trigger={<span title="Ações da parcela" style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)' }}>
                        <Icon name="EllipsisVertical" size={15} /></span>}
                        items={[
                          { id: 'ed', label: 'Editar parcela…', onSelect: () => setEdit(p) },
                          { id: 'rc', label: 'Marcar como recebida', disabled: p.lanc === 'RECEBIDA', onSelect: () => receber(p.k) },
                          { id: 'rec', label: 'Imprimir recibo' },
                          { id: 's1', separator: true },
                          { id: 'del', label: 'Excluir parcela', tone: 'danger', onSelect: () => { const pos = parcelas.indexOf(p); setParcelas((s) => s.filter((x) => x.k !== p.k)); setUndo({ msg: 'Parcela ' + p.num + '/' + p.de + ' excluída', undo: () => setParcelas((s) => { const c = [...s]; c.splice(pos, 0, p); return c; }) }); } },
                        ]} />
                    </td>
                  </tr>
                ))}</tbody>
              </table>
            </div>}
          {undo && <div role="status" style={{ display: 'flex', alignItems: 'center', gap: 16, marginTop: 12, padding: '10px 12px', borderRadius: 12, background: 'var(--text)', color: 'var(--bg)', font: '12.5px/1.3 var(--font-sans)' }}>
            <span>{undo.msg}</span>
            <button type="button" onClick={() => { undo.undo(); setUndo(null); }} style={{ marginLeft: 'auto', border: 0, background: 'transparent', color: 'var(--accent-2)', cursor: 'pointer', font: '600 12.5px/1 var(--font-sans)' }}>Desfazer</button>
          </div>}
          {parcelas.length > 0 && Math.abs(dif) >= 0.005 && <div style={{ marginTop: 12 }}>
            <Alert tone="danger" title="A soma das parcelas não fecha com o total da venda">Diferença de <b>{brl(Math.abs(dif))}</b> {dif > 0 ? 'faltando' : 'sobrando'}. Ajuste um valor ou jogue a diferença na última parcela — a venda não fecha com parcelas divergentes.</Alert>
          </div>}
          <Meta><div style={{ marginTop: 12 }}><TierBar>Rateio com centavo de ajuste: <code>ratear()</code> distribui em centavos inteiros e sobra o resto nas primeiras parcelas — <b>soma sempre igual ao <code>final_total</code></b>. Dividir por float e arredondar cada parcela é o que produz o clássico R$ 0,01 perdido (CU-SELL-09).</TierBar></div></Meta>
        </DrawerSection>

      </Drawer>

      <Modal open={!!edit} onClose={() => setEdit(null)} title={edit ? 'Parcela ' + edit.num + '/' + edit.de : 'Parcela'}
        footer={<div style={{ display: 'flex', gap: 8, width: '100%' }}>
          <Button onClick={() => setEdit(null)}>Recibo</Button>
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button onClick={() => setEdit(null)}>Cancelar</Button>
            <Button variant="primary" onClick={() => { setParcelas((s) => s.map((x) => x.k === edit.k ? edit : x)); setEdit(null); }}>Confirmar</Button>
          </span>
        </div>}>
        {edit && <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <Grid cols={2} gap={10}>
            <div><Input label="Responsável" value={edit.resp} onChange={(e) => setEdit({ ...edit, resp: e.target.value })} placeholder="Cliente da venda" /></div>
            <div><Select label="Lançamento" value={edit.lanc} onChange={(e) => setEdit({ ...edit, lanc: e.target.value })} options={LANC} /></div>
          </Grid>
          <Grid cols={3} gap={10}>
            <div><Select label="Tipo de pagamento" value={edit.tipo} onChange={(e) => setEdit({ ...edit, tipo: e.target.value })} options={['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Boleto', 'Cheque']} /></div>
            <div><Input label="Documento" value={edit.doc} onChange={(e) => setEdit({ ...edit, doc: e.target.value })} /></div>
            <Money label="Valor" value={edit.valor} onChange={(v) => setEdit({ ...edit, valor: v })} />
          </Grid>
          <Grid cols={2} gap={10}>
            <div><DataCampo label="Vencimento" value={edit.venc} onChange={(d) => d && setEdit({ ...edit, venc: dia0(d) })} /></div>
            <div><DataCampo label="Pagamento" value={edit.pgto} onChange={(d) => setEdit({ ...edit, pgto: d })} /></div>
          </Grid>
          <Grid cols={2} gap={10}>
            <div><Select label="Plano de contas" value={edit.plano} onChange={(e) => setEdit({ ...edit, plano: e.target.value })} options={PLANOS} /></div>
            <div><Select label="Conta" value={edit.conta} onChange={(e) => setEdit({ ...edit, conta: e.target.value })} options={CONTAS} /></div>
          </Grid>
          <Textarea label="Histórico" rows={2} value={edit.hist} onChange={(e) => setEdit({ ...edit, hist: e.target.value })} placeholder="Cliente pediu boleto por e-mail" />
        </div>}
      </Modal>
    </>
  );
}

/* ── sells-entrega.jsx ── */
/* Entrega e frete da venda — transporte (modelo da NF-e) + endereço de entrega alternativo.
   Peso bruto vem somado dos itens; o checkbox libera digitação manual.
   Endereço vazio = usa o do cadastro do cliente. */

const FRETE_CONTA = [
  '0 — Contratação por conta do Remetente (CIF)',
  '1 — Contratação por conta do Destinatário (FOB)',
  '2 — Contratação por conta de Terceiros',
  '3 — Transporte próprio por conta do Remetente',
  '4 — Transporte próprio por conta do Destinatário',
  '9 — Sem ocorrência de transporte',
];
const ESPECIES = ['Caixa', 'Palete', 'Pacote', 'Rolo', 'Fardo', 'Tubo', 'Bobina', 'Volume'];
const UFS = ['SC', 'PR', 'RS', 'SP', 'RJ', 'MG', 'BA', 'GO', 'DF'];
const TRANSPORTADORAS = [
  { cod: '014', nome: 'Transportadora Sul Ltda', doc: '84.512.330/0001-07', uf: 'SC', cidade: 'Joinville', placa: 'QHA5F21', antt: '58412330', modal: 'Rodoviário' },
  { cod: '027', nome: 'Rodoviário Bordignon Transportes ME', doc: '11.204.877/0001-55', uf: 'SC', cidade: 'Blumenau', placa: 'MKL2B88', antt: '41120487', modal: 'Rodoviário' },
  { cod: '031', nome: 'Expresso Norte Catarinense S/A', doc: '02.998.140/0001-92', uf: 'PR', cidade: 'Curitiba', placa: 'BEE7J45', antt: '30299814', modal: 'Rodoviário' },
  { cod: '045', nome: 'Frota própria — Office Impresso', doc: '—', uf: 'SC', cidade: 'Joinville', placa: 'RJP1A09', antt: '—', modal: 'Frota própria' },
  { cod: '052', nome: 'Log Fácil Entregas Rápidas Eireli', doc: '38.771.905/0001-13', uf: 'SC', cidade: 'Joinville', placa: 'SDA9C77', antt: '73877190', modal: 'Motoboy' },
];

function EntregaFiscal({ itens, cli, frete, setFrete, freteModo, setFreteModo, onOpen }) {
  const meta = useMeta();
  const [conta, setConta] = React.useState(FRETE_CONTA[0]);
  const [pesoManual, setPesoManual] = React.useState(false);
  const [pesoBrutoM, setPesoBrutoM] = React.useState('0,000');
  const [pesoLiqM, setPesoLiqM] = React.useState('0,000');
  const [entregaOutro, setEntregaOutro] = React.useState(false);
  const [saiEm, setSaiEm] = React.useState({ orc: false, vd: true, nf: false });
  const [transp, setTransp] = React.useState(null);
  const [buscaT, setBuscaT] = React.useState('');
  const [consulta, setConsulta] = React.useState(false);
  const [ufVeic, setUfVeic] = React.useState('SC');
  const [modal, setModal] = React.useState('Rodoviário');
  const trazer = (t) => { setTransp(t || null); if (t) { setUfVeic(t.uf); setModal(t.modal); } };

  /* peso somado dos itens (kg por unidade × quantidade) */
  const pesoUn = (l) => parseBR(l.peso !== undefined ? l.peso : (l.un === 'm²' ? '0,450' : l.un === 'un' ? '0,080' : '0,000'));
  const pesoCalc = submitSafe(itens.reduce((s, l) => s + pesoUn(l) * parseBR(l.qtd), 0));
  const pesoBruto = pesoManual ? parseBR(pesoBrutoM) : pesoCalc;
  const pesoLiq = pesoManual ? parseBR(pesoLiqM) : submitSafe(pesoCalc * 0.94);
  const semTransporte = conta.startsWith('9');

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      {/* ————— Frete / transporte ————— */}
      <div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
          <Lbl>Frete e transporte</Lbl>
          <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>o <b>valor</b> do frete fica no fechamento, à direita</span>
          {meta && <span style={{ marginLeft: 'auto' }}><CuRow ids={['CU-SELL-11']} onOpen={onOpen} /></span>}
        </div>
        <Grid cols={2} gap={12}>
          <div><Select label="Frete por conta" value={conta} onChange={(e) => setConta(e.target.value)} options={FRETE_CONTA} /></div>
          <Money label="Valor do frete (entra no total)" value={frete} onChange={setFrete} />
        </Grid>
        {!semTransporte && <>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', gap: 12 }}>
            <div style={{ width: 104, flex: 'none' }}><Input label="Código" value={transp ? transp.cod : ''} onChange={(e) => trazer(TRANSPORTADORAS.find((x) => x.cod === e.target.value))} placeholder="000" /></div>
            <div style={{ flex: '1 1 420px', minWidth: 260 }}><Input label="Nome / razão social da transportadora" value={transp ? transp.nome : ''} onChange={() => {}} placeholder="Digite o código, ou consulte o cadastro →" /></div>
            <div style={{ flex: 'none', paddingBottom: 1 }}><Button onClick={() => setConsulta(true)}>Consultar cadastro… F2</Button></div>
            {transp && <div style={{ flex: 'none', paddingBottom: 7, display: 'flex', gap: 8, alignItems: 'center' }}>
              <Pill mono>{transp.doc}</Pill>
              <button type="button" title="Limpar transportadora" onClick={() => setTransp(null)} style={{ width: 24, height: 24, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer' }}>×</button>
            </div>}
          </div>
          <div style={{ marginTop: 12 }}><Grid cols={4} gap={12}>
            <div><Input label="Placa do veículo" value={transp ? transp.placa : ''} onChange={() => {}} placeholder="ABC1D23" /></div>
            <div><Select label="UF do veículo" value={ufVeic} onChange={(e) => setUfVeic(e.target.value)} options={UFS} /></div>
            <div><Input label="Renavam" placeholder="00000000000" /></div>
            <div><Input label="ANTT / RNTRC" value={transp ? transp.antt : ''} onChange={() => {}} placeholder="sem registro" /></div>
            <div><Select label="Modalidade" value={modal} onChange={(e) => setModal(e.target.value)} options={['Rodoviário', 'Retirada no balcão', 'Frota própria', 'Motoboy', 'Correios / Sedex']} /></div>
            <div><Select label="UF de destino do transporte" options={UFS} /></div>
          </Grid></div>
          <div style={{ marginTop: 12, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12, flexWrap: 'wrap' }}>
              <Lbl>Volumes</Lbl>
              <span style={{ marginLeft: 'auto' }}><Switch label="Informar peso manualmente" sublabel={'Somado dos itens: ' + num(pesoCalc, 3) + ' kg'} checked={pesoManual} onChange={setPesoManual} /></span>
            </div>
            <Grid cols={4} gap={12}>
              <Money label="Quantidade de volumes" prefix="qt" value="1" onChange={() => {}} />
              <div><Select label="Espécie" options={ESPECIES} /></div>
              <div><Input label="Marca" placeholder="Sem marca" /></div>
              <div><Input label="Numeração dos volumes" defaultValue="1" /></div>
              <Money label="Peso bruto (kg)" prefix="kg" value={pesoManual ? pesoBrutoM : num(pesoBruto, 3)} onChange={setPesoBrutoM} readOnly={!pesoManual}
                help={pesoManual ? 'Digitado — ignora o peso dos itens' : 'Calculado pelo peso cadastrado de cada item'} />
              <Money label="Peso líquido (kg)" prefix="kg" value={pesoManual ? pesoLiqM : num(pesoLiq, 3)} onChange={setPesoLiqM} readOnly={!pesoManual} />
              <div><Input label="Código de coleta" placeholder="—" /></div>
              <div><DataCampo label="Data da coleta" /></div>
            </Grid>
          </div>
          <div style={{ marginTop: 12 }}><Grid cols={3} gap={12}>
            <div><Select label="Status da remessa" options={['Pendente', 'Em separação', 'Despachado', 'Entregue', 'Devolvido']} /></div>
            <div><DataCampo label="Previsão de entrega" /></div>
            <div><Input label="Rastreio / conhecimento" placeholder="CT-e ou código de rastreio" /></div>
          </Grid></div>
        </>}
        {semTransporte && <div style={{ marginTop: 12 }}><Alert tone="info" title="Sem ocorrência de transporte">O cliente retira no balcão — a NF-e sai sem grupo de transporte e nenhum campo de volume é exigido.</Alert></div>}
      </div>

      {/* ————— Endereço de entrega ————— */}
      <div style={{ paddingTop: 16, borderTop: '1px solid var(--border)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12, flexWrap: 'wrap' }}>
          <Lbl>Endereço de entrega</Lbl>
          <Switch label="Entregar em outro endereço" sublabel="Vazio = usa o endereço do cadastro do cliente" checked={entregaOutro} onChange={setEntregaOutro} />
        </div>
        {!entregaOutro ? (
          <div style={{ display: 'flex', gap: 12, alignItems: 'center', padding: 12, borderRadius: 12, background: 'color-mix(in oklch, var(--accent) 12%, var(--surface))', border: '1px solid color-mix(in oklch, var(--accent) 22%, transparent)' }}>
            <span style={{ flex: 'none', color: 'var(--accent)' }}>
              <Icon name="MapPin" size={18} />
            </span>
            <div style={{ minWidth: 0 }}>
              <b style={{ font: '600 13.5px/1.4 var(--font-sans)' }}>{cli.nome}</b>
              <span style={{ display: 'block', font: '12.5px/1.45 var(--font-sans)', color: 'var(--text-dim)' }}>
                {cli.tipo === 'pj' ? 'Rua XV de Novembro, 1400 — Centro · Joinville/SC · 89201-601' : 'Endereço do cadastro do cliente'}
              </span>
            </div>
            <span style={{ marginLeft: 'auto' }}><Pill c="var(--accent)" s="transparent">do cadastro</Pill></span>
          </div>
        ) : (
          <>
            <Grid cols={4} gap={12}>
              <div><Input label="Cód. cidade (IBGE)" placeholder="4209102" /></div>
              <div><Input label="Cidade" placeholder="Joinville" /></div>
              <div><Select label="UF" options={UFS} /></div>
              <div><Input label="CEP" placeholder="89201-601" /></div>
              <div><Input label="Logradouro" placeholder="Rua, avenida, rodovia…" /></div>
              <div><Input label="Número" placeholder="1400" /></div>
              <div><Input label="Bairro" placeholder="Centro" /></div>
              <div><Input label="Complemento" placeholder="Galpão 2, fundos" /></div>
              <div><Select label="País" options={['1058 — Brasil', 'Outro']} /></div>
              <div><Input label="Nome do recebedor" placeholder="Quem recebe na obra" /></div>
              <div><Input label="Telefone" placeholder="(47) 9…" /></div>
              <div><Input label="E-mail" placeholder="obra@cliente.com.br" /></div>
              <div><Input label="Inscrição estadual" placeholder="Isento" /></div>
            </Grid>
          </>
        )}
      </div>

      {/* ————— Fiscal do pedido ————— */}
      <div style={{ paddingTop: 16, borderTop: '1px solid var(--border)' }}>
        <Lbl>Fiscal do pedido</Lbl>
        <Grid cols={4} gap={12}>
          <div><Select label="Natureza da operação" options={['Venda de mercadoria', 'Venda de serviço', 'Remessa para conserto', 'Bonificação']} /></div>
          <div><Select label="Esquema de numeração" options={['VD-2026 (padrão)', 'Manual']} /></div>
          <div><Input label="Nº da fatura" placeholder="automático" /></div>
          <div><Select label="Imposto do pedido" options={['ICMS 18% — exclusivo', 'ICMS 12% — exclusivo', 'Isento']} /></div>
        </Grid>
        <div style={{ marginTop: 12 }}><Textarea label="Informações complementares da NF-e" rows={2} placeholder="Pedido de compra 4471. Entregar em horário comercial." /></div>
      </div>

      <Modal open={consulta} onClose={() => setConsulta(false)} width={880} title="Consulta de transportadoras"
        footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
          <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>{TRANSPORTADORAS.length} cadastros ativos</span>
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button onClick={() => setConsulta(false)}>Fechar</Button>
            <Button variant="primary" onClick={() => setConsulta(false)}>Novo cadastro</Button>
          </span>
        </div>}>
        <div style={{ marginBottom: 12 }}>
          <Input value={buscaT} onChange={(e) => setBuscaT(e.target.value)} placeholder="Buscar por razão social, CNPJ, cidade ou código…" />
        </div>
        <div className="oi-scroll" style={{ overflowX: 'auto' }}>
        <DataTable
          columns={[{ key: 'c', label: 'Código', mono: true }, { key: 'n', label: 'Razão social' }, { key: 'd', label: 'CNPJ', mono: true }, { key: 'l', label: 'Cidade / UF' }, { key: 'm', label: 'Modalidade' }]}
          rows={TRANSPORTADORAS.filter((t) => (t.cod + t.nome + t.doc + t.cidade).toLowerCase().includes(buscaT.toLowerCase())).map((t) => ({
            id: t.cod, state: transp && transp.cod === t.cod ? 'selected' : undefined,
            cells: { c: t.cod, n: { primary: t.nome, sub: 'placa ' + t.placa }, d: t.doc, l: t.cidade + '/' + t.uf, m: <Pill>{t.modal}</Pill> },
          }))}
          onRowClick={(r) => { trazer(TRANSPORTADORAS.find((t) => t.cod === r.id)); setConsulta(false); setBuscaT(''); }} />
        </div>
        <p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Clique na linha para trazer a transportadora — placa, ANTT, UF e modalidade vêm preenchidas do cadastro e podem ser ajustadas nesta venda.</p>
      </Modal>

      <Meta>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <Lbl>Estado do frete no código</Lbl>
            {[['hoje', 'Hoje (free-text · parcial)'], ['depois', 'Depois de O6-4 (estruturado)']].map(([k, l]) => (
              <button key={k} type="button" onClick={() => setFreteModo(k)} style={{ height: 24, padding: '0 12px', borderRadius: 6, cursor: 'pointer', border: '1px solid ' + (freteModo === k ? 'transparent' : 'var(--border)'), background: freteModo === k ? 'var(--accent)' : 'var(--surface)', color: freteModo === k ? 'var(--accent-fg)' : 'var(--text-dim)', font: '600 11.5px/1 var(--font-sans)' }}>{l}</button>
            ))}
          </div>
          <Alert tone="warn" title="CU-SELL-11 é parcial — e o PR anterior foi revertido">
            Em produção hoje só existe <b>um campo de texto</b> de entrega e um valor de frete. Todo este grupo (frete por conta, transportadora, volumes, peso, endereço alternativo) é o <b>PR2 #2104</b>, revertido no incidente <b>2026-06-02</b>: religar exige <b>smoke biz=4</b> antes (item O6-4 · dívida D-6). Peso somado dos itens depende do cadastro de peso — item sem peso entra como zero.
          </Alert>
        </div>
      </Meta>
    </div>
  );
}

/* ── sells-lancamento.jsx ── */
/* Lançamento do item — entre escolher o produto e ele entrar na venda.
   Medidas (peças × altura × largura × espessura) quando a unidade é dimensional,
   valor unitário sob permissão, e funcionário vinculado quando o item é serviço.
   Também mora aqui a consulta de produtos (mesmo padrão de cliente/transportadora). */

/* quem executa serviço: funcionário ou técnico do cadastro único (window.SD.pessoas) */
const execOpcoes = () => [...comOpcoes('funcionario'), ...comOpcoes('tecnico')];
const DIMENSIONAL = { 'm²': ['pecas', 'altura', 'largura'], 'm³': ['pecas', 'altura', 'largura', 'esp'], m: ['pecas', 'largura'] };
/* "Informações Adicionais do Produto / Serviço" do legado — o que a maioria dos clientes preenche em todo item */
const LOCAIS = ['Fachada', 'Interno', 'Veículo', 'Painel', 'Vitrine', 'Totem', 'Obra'];
const IMPRESSOES = ['Digital — látex', 'Digital — UV', 'Offset', 'Recorte eletrônico', 'Sublimação', 'Sem impressão'];
/* data guardada em ISO (o DataCampo aceita Date|ISO|dd/mm/aaaa e exibe dd/mm/aaaa) */
const dISO = (d) => (d instanceof Date && !isNaN(d)) ? d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') : '';

function LancarItem({ produto, onClose, onConfirm }) {
  const meta = useMeta();
  const podePreco = window.SD.permissoes.editarPrecoItem;
  const dims = produto ? DIMENSIONAL[produto.un] : null;
  const servico = produto && produto.tipo === 'servico';
  const [pecas, setPecas] = React.useState('1');
  const [altura, setAltura] = React.useState('0,50');
  const [largura, setLargura] = React.useState('0,10');
  const [esp, setEsp] = React.useState('0,00');
  const [qtdDireta, setQtdDireta] = React.useState('1');
  const [preco, setPreco] = React.useState('0,00');
  const [desc, setDesc] = React.useState('0');
  const [acr, setAcr] = React.useState('0');
  const [func, setFunc] = React.useState(() => comPrimeira('funcionario'));
  const [obs, setObs] = React.useState('');
  const [obsProd, setObsProd] = React.useState('');
  const [local, setLocal] = React.useState('');
  const [impressao, setImpressao] = React.useState('');
  const [prazoEquipe, setPrazoEquipe] = React.useState('');
  const [prazoEtapa, setPrazoEtapa] = React.useState('');
  const [adicAberto, setAdicAberto] = React.useState(false);

  React.useEffect(() => {
    if (!produto) return;
    setPreco(fmtBR(produto.preco)); setPecas('1'); setQtdDireta('1'); setDesc('0'); setAcr('0');
    setAltura(produto.un === 'm²' || produto.un === 'm³' ? '0,50' : '0,00');
    setLargura(produto.un === 'm²' || produto.un === 'm³' || produto.un === 'm' ? '0,10' : '0,00');
    setEsp('0,00'); setObs(''); setObsProd(''); setLocal(''); setImpressao(''); setPrazoEquipe(''); setPrazoEtapa('');
  }, [produto]);
  if (!produto) return null;

  const nPecas = Math.max(parseBR(pecas), 0);
  const areaUn = produto.un === 'm²' ? submitSafe(parseBR(altura) * parseBR(largura))
    : produto.un === 'm³' ? submitSafe(parseBR(altura) * parseBR(largura) * parseBR(esp))
    : produto.un === 'm' ? submitSafe(parseBR(largura)) : 1;
  const qtd = dims ? submitSafe(nPecas * areaUn) : submitSafe(parseBR(qtdDireta));
  const unitario = submitSafe(parseBR(preco) * (1 - parseBR(desc) / 100) * (1 + parseBR(acr) / 100));
  const total = submitSafe(qtd * unitario);
  const abaixoDoPiso = parseBR(preco) < produto.preco * 0.85;
  const preenchidos = [obs, obsProd, local, impressao, prazoEquipe, prazoEtapa].filter((v) => v && v.trim()).length;
  const semEstoque = produto.estoque !== null && qtd > produto.estoque;

  const confirmar = () => onConfirm({
    k: Date.now(), sku: produto.sku, nome: produto.nome, un: produto.un,
    qtd: fmtBR(qtd), preco: fmtBR(parseBR(preco)), desc: String(parseBR(desc)), acr: String(parseBR(acr)),
    pecas: fmtBR(nPecas), altura, largura, esp, func: servico ? func : null, obsItem: obs,
    obsProd, local, impressao, prazoEquipe, prazoEtapa,
  });

  return (
    <Modal open={!!produto} onClose={onClose} width={720}
      title={'Lançar ' + produto.nome}
      footer={<div style={{ display: 'flex', gap: 12, alignItems: 'center', width: '100%' }}>
        <div>
          <Lbl>Total do item</Lbl>
          <b style={{ font: '600 18px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums' }}>{brl(total)}</b>
        </div>
        <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <Button onClick={onClose}>Cancelar</Button>
          <Button variant="primary" disabled={qtd <= 0} onClick={confirmar}>Adicionar à venda</Button>
        </span>
      </div>}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
          <Pill mono>{produto.sku}</Pill>
          <Pill c={servico ? 'var(--color-info)' : 'var(--accent)'} s={servico ? 'color-mix(in oklch, var(--color-info) 12%, transparent)' : 'color-mix(in oklch, var(--accent) 12%, var(--surface))'}>{servico ? 'serviço' : 'produto'}</Pill>
          <Pill mono>unidade {produto.un}</Pill>
          {produto.estoque !== null
            ? <Pill c={semEstoque ? 'var(--neg)' : 'var(--pos)'} s={semEstoque ? 'color-mix(in oklch, var(--neg) 12%, var(--surface))' : 'color-mix(in oklch, var(--pos) 12%, var(--surface))'} mono>estoque {num(produto.estoque, 2)} {produto.un}</Pill>
            : <Pill mono>não controla estoque</Pill>}
        </div>

        {dims && <div>
          <Lbl>Medidas</Lbl>
          <Grid cols={4} gap={12}>
            <Money label="Peças" prefix="qt" value={pecas} onChange={setPecas} />
            <Money label="Altura" prefix="m" value={altura} onChange={setAltura} />
            <Money label="Largura" prefix="m" value={largura} onChange={setLargura} />
            {produto.un === 'm³'
              ? <Money label="Espessura" prefix="m" value={esp} onChange={setEsp} />
              : <div><Lbl>Medida da peça</Lbl><b style={{ font: '600 13.5px/1.4 var(--font-mono)' }}>{num(parseBR(altura), 2)} × {num(parseBR(largura), 2)} m</b><span style={{ display: 'block', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{num(areaUn, 3)} {produto.un} por peça</span></div>}
          </Grid>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center', padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
            <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>
              {num(nPecas, 0)} peça(s) de {num(parseBR(altura), 2)} × {num(parseBR(largura), 2)} m
            </span>
            <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'baseline', gap: 8 }}>
              <Lbl>Quantidade faturada</Lbl>
              <b style={{ font: '600 15px/1 var(--font-mono)' }}>{num(qtd, 2)} {produto.un}</b>
            </span>
          </div>
        </div>}

        {!dims && <div>
          <Grid cols={3} gap={12}>
            <Money label={produto.un === 'h' ? 'Horas' : 'Quantidade'} prefix={produto.un === 'h' ? 'h' : 'qt'} value={qtdDireta} onChange={setQtdDireta} />
            <div><Lbl>Unidade</Lbl><b style={{ font: '600 13.5px/1.4 var(--font-mono)' }}>{produto.un}</b></div>
            <div />
          </Grid>
        </div>}

        <div>
          <Lbl>Valores</Lbl>
          <Grid cols={4} gap={12}>
            <Money label="Valor de tabela" value={fmtBR(produto.preco)} onChange={() => {}} readOnly />
            <Money label="Valor unitário" value={preco} onChange={setPreco} readOnly={!podePreco}
              help={podePreco ? null : 'Seu perfil não pode alterar preço'} hue={abaixoDoPiso ? 'var(--neg)' : 'var(--text-dim)'} />
            <Money label="Desconto" prefix="%" value={desc} onChange={setDesc} readOnly={!podePreco} />
            <Money label="Acréscimo" prefix="%" value={acr} onChange={setAcr} readOnly={!podePreco} />
          </Grid>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 16, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
            <div><Lbl>Unitário líquido</Lbl><b style={{ font: '600 15px/1 var(--font-mono)' }}>{brl(unitario)}</b></div>
            <div><Lbl>Quantidade</Lbl><b style={{ font: '600 15px/1 var(--font-mono)' }}>{num(qtd, 2)} {produto.un}</b></div>
            <div style={{ marginLeft: 'auto' }}><Lbl c="var(--accent)">Total do item</Lbl><b style={{ font: '600 17px/1 var(--font-mono)' }}>{brl(total)}</b></div>
          </div>
          {!podePreco && <div style={{ marginTop: 8 }}><Alert tone="info" title="Preço travado pelo perfil">O valor vem da tabela aplicada na venda. Pedir liberação ao supervisor para alterar.</Alert></div>}
          {podePreco && abaixoDoPiso && <div style={{ marginTop: 8 }}><Alert tone="warn" title="Abaixo do piso de preço">{brl(parseBR(preco))} está mais de 15% abaixo da tabela ({brl(produto.preco)}). Finalizar exige liberação de supervisor.</Alert></div>}
          {semEstoque && <div style={{ marginTop: 8 }}><Alert tone="danger" title="Quantidade acima do estoque">Pedido de {num(qtd, 2)} {produto.un} com {num(produto.estoque, 2)} em estoque — vai gerar saldo negativo ou pedido de compra.</Alert></div>}
        </div>

        {servico && <div>
          <Lbl>Execução do serviço</Lbl>
          <Grid cols={3} gap={12}>
            <div><Select label="Funcionário vinculado" value={func} onChange={(e) => setFunc(e.target.value)} options={execOpcoes()} /></div>
            <div><DataCampo label="Data prevista" /></div>
            <Money label="Comissão do serviço" prefix="%" value="3,00" onChange={() => {}} />
          </Grid>
          <Meta><p style={{ margin: '8px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Serviço não move estoque e o funcionário vinculado é quem entra na apuração de comissão e na OP — por isso o campo só aparece quando <code>tipo=servico</code>.</p></Meta>
        </div>}

        <div>
          <button type="button" onClick={() => setAdicAberto(!adicAberto)} aria-expanded={adicAberto}
            style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '4px 0', border: 0, background: 'transparent', cursor: 'pointer', color: 'var(--text-dim)', font: '600 11px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase' }}>
            <Icon name="ChevronDown" size={13} style={{ transform: adicAberto ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
            Informações adicionais do item
            {!adicAberto && preenchidos > 0 && <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">{preenchidos} preenchido{preenchidos > 1 ? 's' : ''}</Pill>}
          </button>
          {adicAberto && <div style={{ marginTop: 8, display: 'flex', flexDirection: 'column', gap: 12 }}>
            <Grid cols={2} gap={12}>
              <Textarea label="Observação (uso interno)" rows={2} value={obs} onChange={(e) => setObs(e.target.value)} placeholder="Cliente aprovou arte por e-mail em 27/07" />
              <Textarea label="Observação para a produção" rows={2} value={obsProd} onChange={(e) => setObsProd(e.target.value)} placeholder="Sangria de 5cm, ilhós a cada 50cm" />
            </Grid>
            <Grid cols={4} gap={12}>
              <div><Select label="Local da aplicação" value={local} onChange={(e) => setLocal(e.target.value)} options={[{ value: '', label: '—' }, ...LOCAIS.map((l) => ({ value: l, label: l }))]} /></div>
              <div><Select label="Tipo de impressão" value={impressao} onChange={(e) => setImpressao(e.target.value)} options={[{ value: '', label: '—' }, ...IMPRESSOES.map((l) => ({ value: l, label: l }))]} /></div>
              <div><DataCampo label="Prazo da equipe (produção)" value={prazoEquipe} onChange={(dt) => setPrazoEquipe(dISO(dt))} /></div>
              <div><DataCampo label="Prazo da etapa" value={prazoEtapa} onChange={(dt) => setPrazoEtapa(dISO(dt))} /></div>
            </Grid>
            <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>A observação de uso interno e a de produção <b>não saem</b> no documento do cliente — a de produção vai na OP. Dá pra revisar tudo depois pela lupa da linha.</span>
          </div>}
        </div>
      </div>
    </Modal>
  );
}

function ConsultaProduto({ open, onClose, onPick }) {
  const [q, setQ] = React.useState('');
  const lista = window.SD.catalogo.filter((p) => (p.sku + p.nome + (p.ean || '') + (p.fabrica || '') + (p.categoria || '')).toLowerCase().includes(q.toLowerCase()));
  return (
    <Modal open={open} onClose={onClose} width={880} title="Consulta de produtos e serviços"
      footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
        <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>{window.SD.catalogo.length} itens ativos no business atual</span>
        <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <Button onClick={onClose}>Fechar</Button>
          <Button variant="primary">Novo cadastro</Button>
        </span>
      </div>}>
      <div style={{ marginBottom: 12 }}>
        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nome, SKU, EAN, código de fábrica ou categoria…" />
      </div>
      <div className="oi-scroll" style={{ overflowX: 'auto' }}>
        <DataTable
          columns={[{ key: 's', label: 'SKU', mono: true }, { key: 'n', label: 'Produto / serviço' }, { key: 'ean', label: 'Cód. EAN', mono: true }, { key: 'fab', label: 'Cód. fábrica', mono: true }, { key: 'cat', label: 'Categoria' }, { key: 't', label: 'Tipo' }, { key: 'u', label: 'Unid.', mono: true }, { key: 'p', label: 'Tabela', align: 'right', mono: true }, { key: 'e', label: 'Estoque', align: 'right', mono: true }]}
          rows={lista.map((p) => ({
            id: p.sku,
            cells: {
              s: p.sku, n: { primary: p.nome, sub: p.obs || '' },
              ean: p.ean || '—', fab: p.fabrica || '—', cat: <Pill>{p.categoria || '—'}</Pill>,
              t: p.tipo === 'servico' ? <Pill c="var(--color-info)" s="color-mix(in oklch, var(--color-info) 12%, transparent)">serviço</Pill> : <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">produto</Pill>,
              u: p.un, p: fmtBR(p.preco),
              e: p.estoque === null ? '—' : num(p.estoque, 2),
            },
          }))}
          onRowClick={(r) => { const p = window.SD.catalogo.find((x) => x.sku === r.id); onClose(); onPick(p); }} />
      </div>
      <p style={{ margin: '12px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Clique na linha para lançar o item — a próxima tela pede medidas, quantidade e, se for serviço, o funcionário vinculado.</p>
    </Modal>
  );
}

/* ── sells-fsm.jsx ── */
/* FSM da venda na tela — ExecuteStageActionService no plano do operador (ADR 0143 · CU-SELL-20..26).
   Regras que a tela obedece:
   · o estágio só muda por AÇÃO NOMEADA (não há select de estágio);
   · ação com role que o perfil não tem NEGA (fail-secure · CU-SELL-21);
   · efeito colateral é declarado antes de executar (ReservarEstoque, ConsumirEstoque…);
   · de 'producao' em diante a venda TRAVA para edição — reabrir é outra ação nomeada;
   · toda transição escreve uma linha append-only no histórico (sale_stage_history · CU-SELL-26). */

const PRE = { key: 'rascunho', l: 'Rascunho', acao: 'Finalizar venda', role: 'vendas.criar', efeitos: [] };
const ETAPAS = () => [PRE, ...window.SD.fsm.filter((f) => f.key !== 'cancelada')];
const TRAVA_A_PARTIR_DE = ['producao', 'faturada', 'entregue', 'cancelada'];
const estagioTrava = (k) => TRAVA_A_PARTIR_DE.includes(k);

function fsmDe(k) { return k === 'rascunho' ? PRE : window.SD.fsm.find((f) => f.key === k) || PRE; }
function proximoDe(k) {
  const seq = ETAPAS().map((f) => f.key);
  const i = seq.indexOf(k);
  return i >= 0 && i < seq.length - 1 ? fsmDe(seq[i + 1]) : null;
}
function podeRole(role) { return !role || window.SD.permissoes.roles.includes(role); }

/* Barra de estágio — pipeline + ação disponível + o que a ação dispara */
/* Situação da venda — pipeline + histórico num só bloco, para a coluna do fechamento.
   Etapa cumprida mostra quem fez e quando (era o "Histórico de estágios"). */
function SituacaoVenda({ estagio, historico, onExecutar, onCancelar, onReabrir, onOpen, salvando }) {
  const meta = useMeta();
  const [aberto, setAberto] = React.useState(false);
  const atual = fsmDe(estagio);
  const prox = proximoDe(estagio);
  const seq = ETAPAS();
  const cancelada = estagio === 'cancelada';
  const ultima = historico[historico.length - 1];
  const morreuEm = cancelada && ultima ? ultima.de : null;
  const iAtual = cancelada ? seq.findIndex((x) => x.key === morreuEm) : seq.findIndex((f) => f.key === estagio);
  const permitido = podeRole(atual.role);
  const travada = estagioTrava(estagio);
  const feitoPor = (k) => historico.find((h) => h.para === k);
  const rascunho = estagio === 'rascunho' && !cancelada;
  const tom = cancelada ? 'var(--neg)' : travada ? 'var(--warn)' : 'var(--pos)';

  return (
    <div style={{ background: 'var(--surface)', border: '1px solid ' + (cancelada ? 'color-mix(in oklch, var(--neg) 30%, var(--border))' : 'var(--border)'), borderRadius: 12, boxShadow: 'var(--shadow-soft)', overflow: 'hidden' }}>
      <button type="button" onClick={() => setAberto(!aberto)} aria-expanded={aberto}
        style={{ display: 'flex', alignItems: 'center', gap: 8, width: '100%', padding: '9px 12px', border: 0, background: 'transparent', cursor: 'pointer', textAlign: 'left', color: 'var(--text)' }}>
        <span aria-hidden="true" style={{ width: 6, height: 6, borderRadius: 999, flex: 'none', background: tom }}></span>
        <span style={{ font: '600 11px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)' }}>Situação</span>
        <b style={{ font: '600 12.5px/1 var(--font-sans)' }}>{cancelada ? 'Cancelada' : atual.l}</b>
        <span style={{ marginLeft: 'auto', display: 'inline-flex', alignItems: 'center', gap: 6, font: '11.5px/1 var(--font-sans)', color: 'var(--text-dim)' }}>
          {historico.length > 0 && <span>{iAtual + 1}/{seq.length}</span>}
          <Icon name="ChevronDown" size={14} style={{ transform: aberto ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
        </span>
      </button>

      {aberto && <div style={{ padding: '0 12px 10px' }}>
        <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column' }}>
          {seq.map((e, i) => {
            const morte = cancelada && i === iAtual;
            const feito = cancelada ? i < iAtual : i < iAtual;
            const agora = !cancelada && i === iAtual;
            const h = feitoPor(e.key);
            const cor = morte ? 'var(--neg)' : (feito || agora) ? 'var(--accent)' : 'var(--border)';
            return (
              <li key={e.key} style={{ display: 'flex', gap: 9, minHeight: 26 }}>
                <span aria-hidden="true" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 'none', width: 12 }}>
                  <span style={{ width: 9, height: 9, marginTop: 5, borderRadius: 999, flex: 'none', background: (feito || agora || morte) ? cor : 'var(--surface)', border: '2px solid ' + cor, boxShadow: agora ? '0 0 0 3px color-mix(in oklch, var(--accent) 20%, transparent)' : 'none' }}></span>
                  {i < seq.length - 1 && <span style={{ flex: 1, width: 2, background: feito ? 'var(--accent)' : 'var(--border)' }}></span>}
                </span>
                <span style={{ paddingBottom: 8, minWidth: 0 }}>
                  <span style={{ display: 'block', font: (agora || morte ? '600 ' : '') + '12px/1.35 var(--font-sans)', color: morte ? tomFg('var(--neg)') : agora ? 'var(--text)' : feito ? 'var(--text-dim)' : 'var(--text-dim)', textDecoration: morte ? 'line-through' : 'none' }}>{e.l}</span>
                  {h && <span style={{ display: 'block', font: '11px/1.3 var(--font-mono)', color: 'var(--text-dim)' }}>{h.em.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })} · {h.por}</span>}
                  {morte && <span style={{ display: 'block', font: '11px/1.3 var(--font-sans)', color: tomFg('var(--neg)') }}>cancelada aqui</span>}
                </span>
              </li>
            );
          })}
        </ol>
        {meta && <div style={{ paddingTop: 2 }}><CuRow ids={['CU-SELL-20', 'CU-SELL-21', 'CU-SELL-22', 'CU-SELL-26']} onOpen={onOpen} /></div>}
      </div>}

      {!cancelada && prox && <div style={{ padding: '9px 12px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)', display: 'flex', flexDirection: 'column', gap: 8 }}>
        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 6 }}>
          <span style={{ font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>Próxima: <b style={{ color: 'var(--text)' }}>{atual.acao}</b></span>
          {atual.role && <Pill mono c={permitido ? 'var(--pos)' : 'var(--neg)'} s={permitido ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'color-mix(in oklch, var(--neg) 12%, var(--surface))'}>{atual.role}</Pill>}
          {prox.efeitos.map((e) => <Pill key={e} mono c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">{e}</Pill>)}
        </div>
        {!permitido && <span style={{ font: '11px/1.35 var(--font-sans)', color: tomFg('var(--neg)') }}>Seu perfil não tem <b>{atual.role}</b> — a ação fica negada.</span>}
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
          {travada && <Button size="sm" onClick={onReabrir}>Reabrir para correção</Button>}
          <Button size="sm" onClick={onCancelar}>{rascunho ? 'Descartar' : 'Cancelar venda'}</Button>
        </div>
      </div>}
      {cancelada && <div style={{ padding: '9px 12px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)' }}>
        <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Estoque liberado por <code>LiberarReserva</code>. Para retomar, duplique a venda.</span>
      </div>}
    </div>
  );
}

/* Confirmação de cancelamento — a cascata é nomeada antes de rodar */
function CancelarVenda({ open, onClose, onConfirmar, estagio, itens = 0 }) {
  const f = window.SD.fsm.find((x) => x.key === 'cancelada');
  const rascunho = estagio === 'rascunho';
  return (
    <Modal open={open} onClose={onClose} width={520} title={rascunho ? 'Descartar este rascunho?' : 'Cancelar esta venda?'}
      footer={<div style={{ display: 'flex', gap: 8, width: '100%' }}>
        <Button onClick={onClose}>Voltar</Button>
        <span style={{ marginLeft: 'auto' }}><Button variant="danger" onClick={onConfirmar}>{rascunho ? 'Descartar rascunho' : 'Cancelar a venda'}</Button></span>
      </div>}>
      {rascunho ? <>
        <p style={{ margin: '0 0 12px', font: '13.5px/1.5 var(--font-sans)' }}>
          {itens > 0 ? <>Os <b>{itens === 1 ? 'itens lançados' : itens + ' itens lançados'}</b> são perdidos e a tela volta em branco.</> : <>A tela volta em branco.</>}
        </p>
        <Alert tone="info" title="Nada foi gravado">Este rascunho não gerou registro de venda, não reservou estoque e não entrou em cobrança — descartar não desfaz nada no sistema, só limpa o que está na tela.</Alert>
      </> : <>
        <p style={{ margin: '0 0 12px', font: '13.5px/1.5 var(--font-sans)' }}>A venda está em <b>{fsmDe(estagio).l}</b>. Cancelar executa a cascata abaixo e a venda deixa de contar em faturamento e cobrança.</p>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
          {f.efeitos.map((e) => <Pill key={e} mono c="var(--neg)" s="color-mix(in oklch, var(--neg) 12%, var(--surface))">{e}</Pill>)}
        </div>
        <Alert tone="danger" title="Não tem desfazer">Reverter um cancelamento é outra ação nomeada, com role próprio — não é este botão ao contrário.</Alert>
      </>}
    </Modal>
  );
}

/* ── sells-colunas.jsx ── */
/* Colunas do grid de itens — adaptação da lista de colunas habilitáveis do legado.
   Só entra coluna que a venda consegue PREENCHER: nada de cabeçalho com "—" eterno.
   Ficaram fora, de propósito, os ~40 campos "Nf. v*" / "Nf. vALIQ *" (valores calculados do
   XML da NF-e): são resultado, não entrada — vivem na aba Tributação do detalhe e na NF-e. */

const COL_LS = 'oimpresso.vendas.sdd.colunas';

const cat = (l) => window.SD.catalogo.find((p) => p.sku === l.sku) || {};
const dimensional = (l) => l.un === 'm²' || l.un === 'm³' || l.un === 'm';
const areaDe = (l) => l.un === 'm²' ? submitSafe(parseBR(l.altura) * parseBR(l.largura))
  : l.un === 'm³' ? submitSafe(parseBR(l.altura) * parseBR(l.largura) * parseBR(l.esp))
  : l.un === 'm' ? parseBR(l.largura) : 0;
const txt = (v) => (v === undefined || v === null || v === '') ? '—' : v;

/* célula de número editável (mesmo padrão dos campos de valor da linha) */
const cellNum = (ctx, campo, afixo, rotulo, padrao) => (
  <div className={'dsfa' + (afixo === 'R$' ? ' pre' : afixo ? ' suf' : '')}>
    {afixo === 'R$' && <span className="afx l">R$</span>}
    {afixo && afixo !== 'R$' && <span className="afx r">{afixo}</span>}
    <input value={ctx.l[campo] === undefined ? padrao : ctx.l[campo]} readOnly={ctx.travada} inputMode="decimal"
      aria-label={rotulo + ' — ' + ctx.l.nome} onChange={(e) => ctx.setLinha(ctx.l.k, campo, e.target.value)}
      style={{ textAlign: 'right', fontFamily: 'var(--font-mono)' }} />
  </div>
);

const COLUNAS = [
  /* fixas — a linha não existe sem elas */
  { k: 'produto', l: 'Produto / serviço', g: 'ident', fixa: true, w: 260, cell: (ctx) => (
    <>
      <b title={ctx.l.nome} style={{ fontWeight: 600, display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{ctx.l.nome}</b>
      <span style={{ display: 'block', font: '11.5px/1.3 var(--font-mono)', color: 'var(--text-dim)' }}>{ctx.l.sku} · {ctx.l.un}
        {ctx.l.pecas && parseBR(ctx.l.pecas) > 0 && dimensional(ctx.l) ? ' · ' + num(parseBR(ctx.l.pecas), 0) + '× ' + num(parseBR(ctx.l.altura), 2) + 'x' + num(parseBR(ctx.l.largura), 2) + 'm' : ''}
        {ctx.l.func ? ' · ' + ctx.l.func : ''}</span>
    </>
  ) },

  /* identificação */
  { k: 'seq', l: 'Seq.', g: 'ident', w: 52, align: 'right', mono: true, cell: (ctx) => ctx.i + 1 },
  { k: 'codigo', l: 'Cód. produto', g: 'ident', w: 120, mono: true, cell: (ctx) => ctx.l.sku },
  { k: 'unidade', l: 'Unidade', g: 'ident', w: 76, mono: true, cell: (ctx) => ctx.l.un },
  { k: 'tipoProd', l: 'Tipo', g: 'ident', w: 92, cell: (ctx) => cat(ctx.l).tipo === 'servico'
    ? <Pill c="var(--color-info)" s="color-mix(in oklch, var(--color-info) 12%, var(--surface))">serviço</Pill>
    : <Pill>produto</Pill> },

  /* medidas */
  { k: 'pecas', l: 'Qtd. peças', g: 'medida', w: 92, align: 'right', cell: (ctx) => dimensional(ctx.l) ? cellNum(ctx, 'pecas', 'qt', 'Peças', '1') : <span style={{ color: 'var(--text-dim)' }}>—</span> },
  { k: 'altura', l: 'Comprimento', g: 'medida', w: 100, align: 'right', cell: (ctx) => dimensional(ctx.l) ? cellNum(ctx, 'altura', 'm', 'Comprimento', '0,00') : <span style={{ color: 'var(--text-dim)' }}>—</span> },
  { k: 'largura', l: 'Largura', g: 'medida', w: 100, align: 'right', cell: (ctx) => dimensional(ctx.l) ? cellNum(ctx, 'largura', 'm', 'Largura', '0,00') : <span style={{ color: 'var(--text-dim)' }}>—</span> },
  { k: 'esp', l: 'Espessura', g: 'medida', w: 100, align: 'right', cell: (ctx) => ctx.l.un === 'm³' ? cellNum(ctx, 'esp', 'm', 'Espessura', '0,00') : <span style={{ color: 'var(--text-dim)' }}>—</span> },
  { k: 'medidas', l: 'Medidas', g: 'medida', w: 128, mono: true, cell: (ctx) => dimensional(ctx.l)
    ? num(parseBR(ctx.l.altura), 2) + ' × ' + num(parseBR(ctx.l.largura), 2) + ' m'
    : '—' },
  { k: 'formula', l: 'Fórmula', g: 'medida', w: 150, cell: (ctx) => dimensional(ctx.l)
    ? <span style={{ font: '11.5px/1.3 var(--font-mono)', color: 'var(--text-dim)' }}>{num(parseBR(ctx.l.pecas), 0)} × {num(areaDe(ctx.l), 3)} {ctx.l.un}</span>
    : <span style={{ color: 'var(--text-dim)' }}>—</span> },

  /* valores */
  { k: 'qtd', l: 'Quant.', g: 'valor', padrao: true, w: 100, align: 'right', cell: (ctx) => (
    <div className="dsfa"><input value={ctx.l.qtd} readOnly={ctx.travada} inputMode="decimal" aria-invalid={parseBR(ctx.l.qtd) <= 0}
      aria-label={'Quantidade — ' + ctx.l.nome} onChange={(e) => ctx.setLinha(ctx.l.k, 'qtd', e.target.value)}
      style={{ textAlign: 'right', fontFamily: 'var(--font-mono)' }} /></div>
  ) },
  { k: 'tabela', l: 'R$ tabela', g: 'valor', w: 104, align: 'right', mono: true, cell: (ctx) => cat(ctx.l).preco !== undefined ? fmtBR(cat(ctx.l).preco) : '—' },
  { k: 'preco', l: 'R$ valor', g: 'valor', padrao: true, w: 130, align: 'right', cell: (ctx) => (
    <div className="dsfa pre"><span className="afx l">R$</span><input value={ctx.l.preco} readOnly={ctx.travada} inputMode="decimal" aria-invalid={parseBR(ctx.l.preco) <= 0}
      aria-label={'Preço unitário — ' + ctx.l.nome} onChange={(e) => ctx.setLinha(ctx.l.k, 'preco', e.target.value)}
      style={{ textAlign: 'right', fontFamily: 'var(--font-mono)' }} /></div>
  ) },
  { k: 'desc', l: 'Desc. %', g: 'valor', padrao: true, w: 92, align: 'right', cell: (ctx) => cellNum(ctx, 'desc', '%', 'Desconto percentual', '0') },
  { k: 'descRS', l: 'R$ desconto', g: 'valor', w: 110, align: 'right', mono: true, cell: (ctx) => fmtBR(submitSafe(parseBR(ctx.l.qtd) * parseBR(ctx.l.preco) * parseBR(ctx.l.desc) / 100)) },
  { k: 'acr', l: 'Acrésc. %', g: 'valor', padrao: true, w: 92, align: 'right', cell: (ctx) => cellNum(ctx, 'acr', '%', 'Acréscimo percentual', '0') },
  { k: 'acrRS', l: 'R$ outros', g: 'valor', w: 104, align: 'right', mono: true, cell: (ctx) => fmtBR(submitSafe(parseBR(ctx.l.qtd) * parseBR(ctx.l.preco) * (1 - parseBR(ctx.l.desc) / 100) * parseBR(ctx.l.acr || 0) / 100)) },
  { k: 'total', l: 'R$ total', g: 'valor', fixa: true, w: 116, align: 'right', cell: (ctx) => (
    <b style={{ font: '600 13.5px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(linhaTotal(ctx.l))}</b>
  ) },

  /* fiscal — classificação (entrada), não valor calculado de NF-e */
  { k: 'ncm', l: 'NCM', g: 'fiscal', w: 104, mono: true, cell: (ctx) => txt(ctx.l.ncm || cat(ctx.l).ncm) },
  { k: 'cfop', l: 'CFOP', g: 'fiscal', w: 80, mono: true, cell: (ctx) => txt(ctx.l.cfop) },
  { k: 'cst', l: 'CST', g: 'fiscal', w: 80, mono: true, cell: (ctx) => txt((ctx.l.cst_icms || '').split(' —')[0]) },
  { k: 'cest', l: 'CEST', g: 'fiscal', w: 96, mono: true, cell: (ctx) => txt(ctx.l.cest) },

  /* produção */
  { k: 'emProducao', l: 'Em produção', g: 'producao', w: 104, cell: (ctx) => ctx.l.emProducao ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">sim</Pill> : <Pill>não</Pill> },
  { k: 'impressao', l: 'Tipo de impressão', g: 'producao', w: 150, cell: (ctx) => <span style={{ font: '12px/1.3 var(--font-sans)', color: ctx.l.impressao ? 'var(--text)' : 'var(--text-dim)' }}>{txt(ctx.l.impressao)}</span> },
  { k: 'local', l: 'Local da aplicação', g: 'producao', w: 140, cell: (ctx) => <span style={{ font: '12px/1.3 var(--font-sans)', color: ctx.l.local ? 'var(--text)' : 'var(--text-dim)' }}>{txt(ctx.l.local)}</span> },
  { k: 'prazoEtapa', l: 'Prazo da etapa', g: 'producao', w: 116, mono: true, cell: (ctx) => txt(dTexto(dParse(ctx.l.prazoEtapa))) },
  { k: 'func', l: 'Funcionário', g: 'producao', w: 140, cell: (ctx) => <span style={{ font: '12px/1.3 var(--font-sans)', color: ctx.l.func ? 'var(--text)' : 'var(--text-dim)' }}>{txt(ctx.l.func)}</span> },
  { k: 'obsProd', l: 'Obs. da produção', g: 'producao', w: 200, cell: (ctx) => (
    <span title={ctx.l.obsProd || ''} style={{ display: 'block', maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', font: '12px/1.3 var(--font-sans)', color: ctx.l.obsProd ? 'var(--text)' : 'var(--text-dim)' }}>{txt(ctx.l.obsProd)}</span>
  ) },

  /* estoque */
  { k: 'estoque', l: 'Estoque', g: 'estoque', w: 104, align: 'right', cell: (ctx) => {
    const p = cat(ctx.l);
    if (p.estoque === null || p.estoque === undefined) return <span style={{ color: 'var(--text-dim)' }}>—</span>;
    const falta = parseBR(ctx.l.qtd) > p.estoque;
    return <Pill mono c={falta ? 'var(--neg)' : 'var(--pos)'} s={'color-mix(in oklch, ' + (falta ? 'var(--neg)' : 'var(--pos)') + ' 12%, var(--surface))'}>{num(p.estoque, 2)}</Pill>;
  } },
  { k: 'localEstoque', l: 'Local de estoque', g: 'estoque', w: 130, cell: (ctx) => txt(cat(ctx.l).localEstoque) },
];

const COL_GRUPOS = [
  { k: 'ident', l: 'Identificação' }, { k: 'medida', l: 'Medidas' }, { k: 'valor', l: 'Valores' },
  { k: 'fiscal', l: 'Classificação fiscal' }, { k: 'producao', l: 'Produção' }, { k: 'estoque', l: 'Estoque' },
];

const colunasPadrao = () => COLUNAS.filter((c) => c.fixa || c.padrao).map((c) => c.k);
const carregarColunas = () => {
  try {
    const s = JSON.parse(localStorage.getItem(COL_LS) || 'null');
    if (Array.isArray(s) && s.length) return s.filter((k) => COLUNAS.some((c) => c.k === k));
  } catch (e) {}
  return colunasPadrao();
};

function ColunasModal({ open, onClose, ativas, setAtivas }) {
  const def = (k) => COLUNAS.find((c) => c.k === k);
  const ativa = ativas.map(def).filter(Boolean);
  const fora = COLUNAS.filter((c) => !ativas.includes(c.k));
  const mover = (i, d) => {
    const j = i + d;
    if (j < 0 || j >= ativas.length) return;
    const s = [...ativas];
    s.splice(j, 0, s.splice(i, 1)[0]);
    setAtivas(s);
  };
  const arrasta = React.useRef(null);
  const soltar = (i) => {
    const de = arrasta.current;
    arrasta.current = null;
    if (de === null || de === i) return;
    const s = [...ativas];
    s.splice(i, 0, s.splice(de, 1)[0]);
    setAtivas(s);
  };

  return (
    <Modal open={open} onClose={onClose} width={860} title="Colunas do grid de itens"
      footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
        <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>{ativas.length} de {COLUNAS.length} colunas · ordem e escolha ficam salvas neste navegador</span>
        <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <Button onClick={() => setAtivas(colunasPadrao())}>Restaurar padrão</Button>
          <Button variant="primary" onClick={onClose}>Fechar</Button>
        </span>
      </div>}>
      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)', gap: 18 }}>
        <div>
          <Lbl>No grid — de cima para baixo é a ordem das colunas</Lbl>
          <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
            {ativa.map((c, i) => (
              <li key={c.k} draggable onDragStart={() => { arrasta.current = i; }} onDragOver={(e) => e.preventDefault()} onDrop={() => soltar(i)}
                style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '5px 8px', borderRadius: 8, background: 'var(--bg-2)', border: '1px solid var(--border)', cursor: 'grab' }}>
                <span aria-hidden="true" style={{ color: 'var(--text-dim)', display: 'inline-flex' }}><Icon name="GripVertical" size={13} /></span>
                <span style={{ width: 18, flex: 'none', font: '11px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{i + 1}</span>
                <span style={{ flex: 1, minWidth: 0, font: '12.5px/1.4 var(--font-sans)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.l}</span>
                {c.fixa && <Pill>fixa</Pill>}
                <button type="button" aria-label={'Subir ' + c.l} disabled={i === 0} onClick={() => mover(i, -1)} style={{ width: 24, height: 24, flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: i === 0 ? 'default' : 'pointer', opacity: i === 0 ? .4 : 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="ChevronUp" size={13} /></button>
                <button type="button" aria-label={'Descer ' + c.l} disabled={i === ativa.length - 1} onClick={() => mover(i, 1)} style={{ width: 24, height: 24, flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: i === ativa.length - 1 ? 'default' : 'pointer', opacity: i === ativa.length - 1 ? .4 : 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="ChevronDown" size={13} /></button>
                {!c.fixa && <button type="button" aria-label={'Tirar ' + c.l + ' do grid'} onClick={() => setAtivas(ativas.filter((x) => x !== c.k))} style={{ width: 24, height: 24, flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="X" size={13} /></button>}
              </li>
            ))}
          </ol>
        </div>
        <div>
          <Lbl>Disponíveis</Lbl>
          {fora.length === 0
            ? <p style={{ margin: 0, font: '12.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Todas as colunas estão no grid.</p>
            : COL_GRUPOS.map((g) => {
              const itens = fora.filter((c) => c.g === g.k);
              if (!itens.length) return null;
              return (
                <div key={g.k} style={{ marginBottom: 10 }}>
                  <span style={{ display: 'block', marginBottom: 4, font: '600 10.5px/1.6 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)' }}>{g.l}</span>
                  <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                    {itens.map((c) => (
                      <li key={c.k} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '5px 8px', borderRadius: 8, background: 'var(--surface)', border: '1px dashed var(--border)' }}>
                        <span style={{ flex: 1, minWidth: 0, font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.l}</span>
                        <button type="button" aria-label={'Colocar ' + c.l + ' no grid'} onClick={() => setAtivas([...ativas, c.k])}
                          style={{ display: 'inline-flex', alignItems: 'center', gap: 4, height: 24, padding: '0 8px', flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--accent)', cursor: 'pointer', font: '600 11.5px/1 var(--font-sans)' }}>
                          <Icon name="Plus" size={12} />usar
                        </button>
                      </li>
                    ))}
                  </ul>
                </div>
              );
            })}
        </div>
      </div>
      <p style={{ margin: '14px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>
        A coluna de <b>ações</b> (impostos, detalhes, remover) fica sempre grudada na direita do grid — não entra nesta lista porque não é informação, é operação.
        Os campos de <b>valor de imposto</b> da lista antiga (Nf. vICMS, vPIS, vBC, vALIQ…) também não entram: são <b>resultado</b> do cálculo, não algo que se digita na linha — ficam na aba <b>Tributação</b> do detalhe do item e na NF-e.
      </p>
    </Modal>
  );
}

/* ── sells-create.jsx ── */
/* Nova venda / Editar venda — v2.
   Problema da v1: tela toda branca e tudo pedindo decisão ao mesmo tempo.
   v2: 3 passos numerados · coluna de trabalho à esquerda · FECHAMENTO fixo à direita
   (plate escuro do DS = o único bloco de peso visual) · secundário em gavetas fechadas. */

const LS_DRAFT = 'oimpresso.vendas.sdd.draft.b1.u1';
const METODOS = ['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Boleto', 'Fiado (a prazo)'];
const RAPIDO = ['Dinheiro', 'PIX', 'Cartão de crédito', 'Boleto'];
const TABELAS = ['Balcão — preço padrão', 'Atacado — a partir de 50m²', 'Governo 2026 — pregão 041/2026', 'Parceiro / agência — 15% off'];

function linhaTotal(l) { return submitSafe(parseBR(l.qtd) * parseBR(l.preco) * (1 - parseBR(l.desc) / 100) * (1 + parseBR(l.acr || 0) / 100)); }

const Passo = ({ n }) => <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 18, height: 18, borderRadius: 999, marginRight: 8, background: 'var(--accent)', color: 'var(--accent-fg)', font: '600 11.5px/1 var(--font-mono)', verticalAlign: '1px' }}>{n}</span>;

/* Linha do resumo de fechamento */
const Res = ({ l, v, c, forte }) => (
  <div style={{ display: 'flex', alignItems: 'baseline', gap: 12, padding: '5px 0' }}>
    <span style={{ font: (forte ? '600 ' : '') + '12.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>{l}</span>
    <span style={{ flex: 1, borderBottom: '1px dotted var(--border)', transform: 'translateY(-3px)' }}></span>
    <b style={{ font: '600 13.5px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums', color: c || 'var(--text)' }}>{v}</b>
  </div>
);

function Create({ onOpen, modo = 'create', registro }) {
  const meta = useMeta();
  const estreito = useEstreito();
  const edit = modo === 'edit';
  const [carregando, setCarregando] = React.useState(true);
  const [erroSistema, setErroSistema] = React.useState(null);
  const [mostrarTodos, setMostrarTodos] = React.useState(false);
  React.useEffect(() => { const t = setTimeout(() => setCarregando(false), 550); return () => clearTimeout(t); }, []);
  const [cliente, setCliente] = React.useState(edit ? (registro ? registro.cliente : 'Prefeitura de Joinville') : 'Consumidor final');
  const [itens, setItens] = React.useState(edit
    ? [{ k: 1, sku: 'LON-440-BR', nome: 'Lona 440g branca fosca', un: 'm²', qtd: '120,00', preco: '68,90', desc: '0' },
       { k: 2, sku: 'BAN-ACAB-IL', nome: 'Acabamento com ilhós', un: 'un', qtd: '48', preco: '3,50', desc: '0' }]
    : [{ k: 1, sku: 'LON-440-BR', nome: 'Lona 440g branca fosca', un: 'm²', qtd: '12,50', preco: '68,90', desc: '0', pecas: '5', altura: '0,50', largura: '5,00' },
       { k: 2, sku: 'BAN-ACAB-IL', nome: 'Acabamento com ilhós', un: 'un', qtd: '24', preco: '3,50', desc: '0' }]);
  const [busca, setBusca] = React.useState('');
  const [lancar, setLancar] = React.useState(null);
  const [consultaProd, setConsultaProd] = React.useState(false);
  const [colunas, setColunas] = React.useState(carregarColunas);
  const [colunasOpen, setColunasOpen] = React.useState(false);
  const [comBens, setComBens] = React.useState([{ k: 1, tipo: 'funcionario', pessoa: 'Kamila Reis', base: 'liquido', regra: 'pct', pct: '3,00', valor: '0,00' }]);
  const [comGatilho, setComGatilho] = React.useState('recebimento');
  const [comOpen, setComOpen] = React.useState(false);
  React.useEffect(() => { try { localStorage.setItem(COL_LS, JSON.stringify(colunas)); } catch (e) {} }, [colunas]);
  const cols = colunas.map((k) => COLUNAS.find((c) => c.k === k)).filter(Boolean);
  const W_ACOES = 152;
  const larguraMin = Math.max(760, cols.reduce((s, c) => s + (c.w || 110), 0) + W_ACOES);
  const [descTipo, setDescTipo] = React.useState('percentual');
  const [descVal, setDescVal] = React.useState('0,00');
  const [freteModo, setFreteModo] = React.useState('hoje');
  const [frete, setFrete] = React.useState('0,00');
  const [acr, setAcr] = React.useState('0,00');
  const [pags, setPags] = React.useState(edit ? [{ k: 1, m: 'Boleto', v: '0,00', par: '1' }] : []);
  const [status, setStatus] = React.useState('final');
  const [os, setOs] = React.useState(false);
  const [novoCli, setNovoCli] = React.useState(false);
  const [consultaCli, setConsultaCli] = React.useState(false);
  const [buscaCli, setBuscaCli] = React.useState('');
  const [destAberto, setDestAberto] = React.useState(false);
  const [tabela, setTabela] = React.useState(null); /* null = herda do cadastro do cliente */
  const [itemAberto, setItemAberto] = React.useState(-1);
  const [abaItem, setAbaItem] = React.useState('geral'); /* aba inicial do drawer de detalhe */
  const abrirItem = (i, aba) => { setAbaItem(aba || 'geral'); setItemAberto(i); };
  const [parcelasOpen, setParcelasOpen] = React.useState(false);
  const [parcelas, setParcelas] = React.useState([]);
  const [estagio, setEstagio] = React.useState(edit ? (registro ? registro.estagio : 'producao') : 'rascunho');
  const [historico, setHistorico] = React.useState([]);
  const [cancelarOpen, setCancelarOpen] = React.useState(false);
  const [salvo, setSalvo] = React.useState(null);
  const [salvando, setSalvando] = React.useState(false);
  const [undo, setUndo] = React.useState(null);
  React.useEffect(() => { if (!undo) return; const t = setTimeout(() => setUndo(null), 7000); return () => clearTimeout(t); }, [undo]);
  const [draftEm, setDraftEm] = React.useState(null);

  React.useEffect(() => {
    if (edit) return;
    const t = setTimeout(() => {
      try { localStorage.setItem(LS_DRAFT, JSON.stringify({ cliente, itens, descVal, pags, em: Date.now() })); setDraftEm(new Date()); } catch (e) {}
    }, 700);
    return () => clearTimeout(t);
  }, [cliente, itens, descVal, pags, edit]);

  const cli = window.SD.clientes.find((c) => c.nome === cliente) || window.SD.clientes[0];
  React.useEffect(() => { setTabela(null); }, [cliente]);
  const tabelaCadastro = cli.tabela || TABELAS[0];
  const tabelaAtiva = tabela || tabelaCadastro;
  const tabelaTrocada = !!tabela && tabela !== tabelaCadastro;
  const subtotal = submitSafe(itens.reduce((s, l) => s + linhaTotal(l), 0));
  const descAplicado = descTipo === 'percentual' ? submitSafe(subtotal * parseBR(descVal) / 100) : submitSafe(parseBR(descVal));
  const baseTrib = submitSafe(subtotal - descAplicado);
  /* margem estimada: preço de tabela do catálogo × 0,58 é o custo de cena (o custo real vem do cadastro) */
  const custoEstimado = submitSafe(itens.reduce((s, l) => {
    const p = window.SD.catalogo.find((x) => x.sku === l.sku);
    return s + (p ? submitSafe(parseBR(l.qtd) * p.preco * 0.58) : 0);
  }, 0));
  const totComissao = { bruto: subtotal, liquido: baseTrib, margem: Math.max(0, submitSafe(baseTrib - custoEstimado)) };
  const vImposto = submitSafe(baseTrib * 0.18);
  const vFrete = submitSafe(parseBR(frete));
  const vAcr = submitSafe(parseBR(acr));
  const total = submitSafe(baseTrib + vImposto + vFrete + vAcr);
  const pago = submitSafe(pags.reduce((s, p) => s + parseBR(p.v), 0) + parcelas.filter((p) => p.lanc === 'RECEBIDA').reduce((s, p) => s + parseBR(p.valor), 0));
  const saldo = submitSafe(total - pago);
  const payStatus = pago <= 0 ? 'due' : saldo > 0.005 ? 'partial' : 'paid';
  const alcada = descTipo === 'percentual' ? parseBR(descVal) > 10 : descAplicado > submitSafe(subtotal * 0.1);
  const qtdItens = itens.length;
  const invalidas = itens.filter((l) => parseBR(l.qtd) <= 0 || parseBR(l.preco) <= 0);
  const temInvalida = invalidas.length > 0;
  const LIMITE = 50;
  const visiveis = mostrarTodos ? itens : itens.slice(0, LIMITE);

  const addItem = (p) => { setBusca(''); setLancar(p); };
  const setLinha = (k, campo, v) => setItens((s) => s.map((l) => l.k === k ? { ...l, [campo]: v } : l));
  const achados = busca ? window.SD.catalogo.filter((p) => (p.nome + p.sku).toLowerCase().includes(busca.toLowerCase())) : [];
  const addPag = (m) => setPags((s) => [...s, { k: Date.now(), m, v: fmtBR(Math.max(saldo, 0)), par: '1' }]);
  const removerItem = (l) => {
    const pos = itens.indexOf(l);
    setItens((s) => s.filter((x) => x.k !== l.k));
    setUndo({ msg: 'Item removido — ' + l.nome, undo: () => setItens((s) => { const c = [...s]; c.splice(pos, 0, l); return c; }) });
  };
  const travada = estagioTrava(estagio);
  const atualFsm = fsmDe(estagio);
  const proxFsm = proximoDe(estagio);
  const podeExecutar = podeRole(atualFsm.role) && !!proxFsm && estagio !== 'cancelada';

  const registrar = (acao, de, para, efeitos) => setHistorico((h) => [...h, { acao, de, para, efeitos, por: window.SD.permissoes.usuario, em: new Date() }]);

  /* ExecuteStageActionService::execute — nunca UPDATE direto no estágio */
  const executarAcao = (forcarErro) => {
    if (!podeExecutar || salvando) return;
    setErroSistema(null);
    setSalvando(true);
    setTimeout(() => {
      setSalvando(false);
      if (forcarErro || window.SD.simularFalha) {
        window.SD.simularFalha = false;
        setErroSistema({ codigo: 'HTTP 503', em: new Date() });
        return;
      }
      registrar(atualFsm.acao, atualFsm.key, proxFsm.key, proxFsm.efeitos);
      setEstagio(proxFsm.key);
      if (estagio === 'rascunho') {
        setSalvo({ payStatus, total, em: new Date() });
        try { localStorage.removeItem(LS_DRAFT); } catch (e) {}
        setDraftEm(null);
      }
    }, 650);
  };
  const cancelarVenda = () => {
    setCancelarOpen(false);
    if (estagio === 'rascunho') { /* nada gravado: só limpa a tela */
      setItens([]); setPags([]); setParcelas([]); setDescVal('0,00'); setAcr('0,00'); setFrete('0,00');
      setCliente('Consumidor final'); setSalvo(null);
      try { localStorage.removeItem(LS_DRAFT); } catch (e) {}
      setDraftEm(null);
      return;
    }
    registrar('Cancelar venda', estagio, 'cancelada', ['CancelarVendaCascade', 'LiberarReserva']);
    setEstagio('cancelada');
  };
  const reabrir = () => { registrar('Reabrir para correção', estagio, 'aprovada', ['LiberarReserva']); setEstagio('aprovada'); };
  const salvar = executarAcao;

  const esquerda = (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0 }}>
      {edit && <Meta><Alert tone="danger" title="Editar venda emitida — sem casos.md, sem teste">Esta tela mexe em <code>final_total</code> e estoque de venda <b>já finalizada</b> e nenhum CU a defende hoje. Escrever <code>Edit.casos.md</code> é o item <b>O6-1</b>.</Alert></Meta>}
      {erroSistema && <Alert tone="danger" title={'Não foi possível salvar a venda (' + erroSistema.codigo + ')'}
        action={<Button size="sm" variant="primary" onClick={() => executarAcao(false)}>Tentar de novo</Button>}>
        O servidor não respondeu às {erroSistema.em.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}. <b>Nada foi perdido</b> — a venda continua aqui e o rascunho está salvo. Tente de novo; se repetir, salve como rascunho e chame o suporte.
      </Alert>}
      {salvo && <Alert tone={salvo.payStatus === 'due' ? 'warn' : 'success'} title={salvo.payStatus === 'due' ? 'Venda salva a prazo — ficou saldo devedor' : 'Venda salva'}>
        Total {brl(salvo.total)}{salvo.payStatus === 'due' && ' — entra na lista de cobrança como “a receber”.'}
      </Alert>}

      {estagio === 'cancelada'
        ? <Alert tone="danger" title="Venda cancelada">
            O cancelamento rodou <code>LiberarReserva</code>, então o estoque desta venda voltou para o saldo — nada aqui está mais comprometido. Esta venda não recebe alteração nem ação de fluxo: para retomar o pedido, <b>duplique</b> a venda; para acertar valor já faturado, lance uma <b>devolução</b>.
          </Alert>
        : travada && <Alert tone="warn" title={'Venda ' + atualFsm.l.toLowerCase() + ' — itens e valores travados'}>
            A partir de <b>Em produção</b> o estoque já está comprometido: mudar quantidade ou preço aqui adulteraria venda em curso. Use <b>Reabrir para correção</b> (registra no histórico) ou lance uma devolução.
          </Alert>}

      {/* 1 · Cliente — uma linha, não um formulário */}
      <Sec title={<><Passo n="1" />Cliente</>} hue="var(--accent)" ico={false} pad={12}
        cus={['CU-SELL-01', 'CU-SELL-02', 'CU-SELL-03', 'CU-SELL-15']} onOpen={onOpen}
        right={<Button size="sm" onClick={() => setConsultaCli(true)}>Consultar cadastro… F2</Button>}>
        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', gap: 12 }}>
          <div style={{ width: 104, flex: 'none' }}><Input label="Código" value={cli.cod} onChange={(ev) => { const x = window.SD.clientes.find((y) => y.cod === ev.target.value.trim()); if (x) setCliente(x.nome); }} /></div>
          <div style={{ flex: '1 1 320px', minWidth: 240 }}>
            <Input label="Cliente / destinatário" value={cliente} readOnly onChange={() => {}} />
          </div>
          {!cli.padrao && <div style={{ flex: 'none', paddingBottom: 8 }}>
            <button type="button" title="Voltar para Consumidor final" onClick={() => setCliente('Consumidor final')} style={{ width: 34, height: 34, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer' }}>×</button>
          </div>}
        </div>
        <button type="button" onClick={() => setDestAberto(!destAberto)} aria-expanded={destAberto}
          style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8, padding: '4px 0', border: 0, background: 'transparent', cursor: 'pointer', color: 'color-mix(in oklch, var(--accent) 62%, var(--text))', font: '600 11.5px/1 var(--font-sans)' }}>
          <Icon name="ChevronDown" size={14} style={{ transform: destAberto ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
          Detalhes do destinatário
          {!destAberto && <span style={{ font: '11.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{cli.doc && cli.doc !== '—' ? cli.doc + ' · ' : ''}{cli.cidade}/{cli.uf}</span>}
        </button>
        {cli.padrao && <p style={{ margin: '0 0 4px', font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Venda de balcão começa em <b>Consumidor final</b> — troque digitando o código ou pela consulta de cadastro.</p>}
        {destAberto && <div style={{ marginTop: 8, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
          <Grid cols={4} gap={12}>
            <div><Lbl>{cli.tipo === 'pj' ? 'CNPJ' : 'CPF'}</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-mono)' }}>{cli.doc}</b></div>
            <div><Lbl>Inscrição estadual</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-mono)' }}>{cli.ie}</b></div>
            <div><Lbl>Inscrição municipal</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-mono)' }}>{cli.im}</b></div>
            <div><Lbl>Regime tributário</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.regime}</b></div>
            <div><Lbl>ICMS</Lbl>{cli.contrib === 'sim' ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">contribuinte</Pill> : cli.contrib === 'isento' ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">isento</Pill> : <Pill c="var(--neg)" s="color-mix(in oklch, var(--neg) 12%, var(--surface))">não contribuinte</Pill>}</div>
            <div><Lbl>Contato responsável</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.contato}</b></div>
            <div><Lbl>Telefone</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-mono)' }}>{cli.fone}</b></div>
            <div><Lbl>{cli.nascimento ? 'Cidade / UF · nascimento' : 'Cidade / UF'}</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.cidade}/{cli.uf}{cli.nascimento ? ' · ' + dTexto(dParse(cli.nascimento)) : ''}</b></div>
            <div style={{ gridColumn: 'span 2' }}><Lbl>E-mail</Lbl><span style={{ font: '12.5px/1.4 var(--font-sans)', wordBreak: 'break-all' }}>{cli.email}</span></div>
            <div style={{ gridColumn: 'span 2' }}><Lbl>E-mail para envio da NF</Lbl><span style={{ font: '12.5px/1.4 var(--font-sans)', wordBreak: 'break-all' }}>{cli.emailNfe}</span></div>
            <div style={{ gridColumn: 'span 2' }}><Lbl>Endereço</Lbl><span style={{ font: '12.5px/1.45 var(--font-sans)' }}>{cli.endereco}</span></div>
            <div><Lbl>Grupo de preço</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.grupo}</b><span style={{ display: 'block', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>define a tabela de preço dos itens</span></div>
            <div><Lbl>Prazo de pagamento</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.prazo}</b><span style={{ display: 'block', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>vencimento sugerido das parcelas</span></div>
          </Grid>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 6, alignItems: 'center' }}>
            <Lbl>Marcadores fiscais</Lbl>
            {cli.creditoIcms ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">aproveita crédito de ICMS</Pill> : <Pill>sem crédito de ICMS</Pill>}
            {cli.issRetido ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">ISS retido na fonte</Pill> : <Pill>ISS não retido</Pill>}
            {cli.rural ? <Pill c="var(--color-info)" s="color-mix(in oklch, var(--color-info) 12%, var(--surface))">produtor rural</Pill> : null}
          </div>
          <div style={{ marginTop: 12 }}><Button size="sm">Abrir cadastro completo</Button></div>
        </div>}
        <Meta><div style={{ marginTop: 12 }}><TierBar tone="accent">Cliente, produto e comissionista só alcançam registros do <b>business atual</b>. <code>App\Transaction</code> não tem global scope — o escopo é declarado <b>em cada query</b> (ADR 0093 · CU-SELL-15).</TierBar></div></Meta>
      </Sec>

      {/* 2 · Itens — o trabalho de verdade */}
      <Sec title={<><Passo n="2" />Itens<span style={{ marginLeft: 8, font: '600 11.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{qtdItens}</span></>}
        hue="var(--color-info)" ico={false} cus={['CU-SELL-04', 'CU-SELL-05', 'CU-SELL-07']} onOpen={onOpen} pad={0} clip={false}
        right={!travada ? <div style={{ display: 'flex', gap: 8 }}>
          <Button size="sm" onClick={() => setColunasOpen(true)}>{'Colunas (' + cols.length + ')'}</Button>
          <Button size="sm" onClick={() => setConsultaProd(true)}>Consultar produto… F3</Button>
        </div> : <Button size="sm" onClick={() => setColunasOpen(true)}>{'Colunas (' + cols.length + ')'}</Button>}>
        {!travada && <div style={{ padding: 12, background: 'var(--bg-2)', borderBottom: '1px solid var(--border)', position: 'relative' }}>
          <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar produto por nome, SKU, lote ou código de barras…" />
          {achados.length > 0 && <div style={{ position: 'absolute', zIndex: 5, left: 12, right: 12, marginTop: 4, background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-pop)', overflow: 'hidden' }}>
            {achados.map((p) => (
              <button key={p.sku} type="button" onClick={() => addItem(p)} style={{ display: 'flex', alignItems: 'center', gap: 12, width: '100%', padding: '8px 12px', border: 0, borderBottom: '1px solid var(--border-2)', background: 'transparent', cursor: 'pointer', textAlign: 'left', font: '13.5px/1.3 var(--font-sans)', color: 'var(--text)' }}>
                <b style={{ fontWeight: 600 }}>{p.nome}</b>
                <span style={{ font: '11.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{p.sku}</span>
                <span style={{ marginLeft: 'auto', font: '600 12.5px/1 var(--font-mono)' }}>{brl(p.preco)}/{p.un}</span>
                {p.estoque !== null ? <Pill c={p.estoque > 50 ? 'var(--pos)' : 'var(--warn)'} s={p.estoque > 50 ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'color-mix(in oklch, var(--warn) 12%, var(--surface))'} mono>{num(p.estoque, 1)} {p.un}</Pill> : <Pill mono>serviço</Pill>}
              </button>
            ))}
          </div>}
        </div>}
        {estreito ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, padding: 12 }}>
            {carregando && [0, 1].map((i) => <Skeleton key={i} variant="card" />)}
            {!carregando && visiveis.map((l) => (
              <div key={l.k} style={{ padding: 12, borderRadius: 12, border: '1px solid ' + (parseBR(l.qtd) <= 0 || parseBR(l.preco) <= 0 ? 'var(--neg)' : 'var(--border)'), background: 'var(--surface)' }}>
                <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                  <div style={{ minWidth: 0 }}>
                    <b style={{ display: 'block', font: '600 13.5px/1.35 var(--font-sans)' }}>{l.nome}</b>
                    <span style={{ display: 'block', font: '11.5px/1.3 var(--font-mono)', color: 'var(--text-dim)' }}>{l.sku} · {l.un}{l.func ? ' · ' + l.func.split(' —')[0] : ''}</span>
                  </div>
                  <b style={{ marginLeft: 'auto', font: '600 15px/1.2 var(--font-mono)', whiteSpace: 'nowrap' }}>{fmtBR(linhaTotal(l))}</b>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, marginTop: 12 }}>
                  <Money label="Qtd" prefix="qt" value={l.qtd} onChange={(v) => setLinha(l.k, 'qtd', v)} readOnly={travada} aria={'Quantidade — ' + l.nome} />
                  <Money label="Preço unit." value={l.preco} onChange={(v) => setLinha(l.k, 'preco', v)} readOnly={travada} aria={'Preço unitário — ' + l.nome} />
                  <Money label="Desc." prefix="%" value={l.desc} onChange={(v) => setLinha(l.k, 'desc', v)} readOnly={travada} aria={'Desconto — ' + l.nome} />
                  <Money label="Acrésc." prefix="%" value={l.acr || '0'} onChange={(v) => setLinha(l.k, 'acr', v)} readOnly={travada} aria={'Acréscimo — ' + l.nome} />
                </div>
                <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                  <Button size="sm" onClick={() => abrirItem(itens.indexOf(l))}>Detalhes do item</Button>
                  <Button size="sm" onClick={() => abrirItem(itens.indexOf(l), 'trib')}>Impostos</Button>
                  {!travada && <Button size="sm" onClick={() => removerItem(l)}>Remover</Button>}
                </div>
              </div>
            ))}
          </div>
        ) : (
        <div className="oi-scroll" style={{ overflowX: 'auto' }}>
          <table className="tabela-acoes-fixa" style={{ width: '100%', minWidth: larguraMin, borderCollapse: 'separate', borderSpacing: 0, font: '13.5px/1.4 var(--font-sans)' }}>
            <thead><tr>
              {cols.map((c) => (
                <th key={c.k} style={{ background: 'var(--surface)', padding: '8px 12px', textAlign: c.align === 'right' ? 'right' : 'left', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap', width: c.w }}>{c.l}</th>
              ))}
              <th className="acoes" style={{ width: W_ACOES, minWidth: W_ACOES, background: 'var(--surface)', padding: '8px 12px', textAlign: 'center', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap' }}>Ações</th>
            </tr></thead>
            <tbody>{carregando ? [0, 1].map((i) => (
              <tr key={'sk' + i}><td colSpan={cols.length + 1} style={{ padding: '10px 12px', borderBottom: '1px solid var(--border-2)' }}><Skeleton variant="row" /></td></tr>
            )) : visiveis.map((l) => (
              <tr key={l.k}>
                {cols.map((c) => (
                  <td key={c.k} style={{ padding: c.cell && c.k !== 'produto' && ['qtd', 'preco', 'desc', 'acr', 'pecas', 'altura', 'largura', 'esp'].includes(c.k) ? '4px 8px' : '8px 12px', borderBottom: '1px solid var(--border-2)', width: c.w, maxWidth: c.w, textAlign: c.align === 'right' ? 'right' : 'left', font: c.mono ? '12.5px/1.4 var(--font-mono)' : undefined, fontVariantNumeric: c.mono ? 'tabular-nums' : undefined, color: c.mono ? 'var(--text-dim)' : undefined }}>
                    {c.cell({ l, i: itens.indexOf(l), travada, setLinha })}
                  </td>
                ))}
                <td className="acoes" style={{ width: W_ACOES, minWidth: W_ACOES, padding: '8px', borderBottom: '1px solid var(--border-2)', textAlign: 'center', whiteSpace: 'nowrap' }}>
                  <button type="button" title="Impostos deste item — NCM, CFOP, CST e alíquotas" onClick={() => abrirItem(itens.indexOf(l), 'trib')} style={{ display: 'inline-flex', alignItems: 'center', gap: 5, height: 28, padding: '0 9px', marginRight: 6, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', font: '600 11.5px/1 var(--font-sans)', verticalAlign: 'middle' }}>
                    <Icon name="Settings" size={13} />Impostos
                  </button>
                  <button type="button" title="Detalhes do item — produção, tributação, anexos, observação" onClick={() => abrirItem(itens.indexOf(l))} style={{ width: 28, height: 28, marginRight: 8, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--accent)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', verticalAlign: 'middle' }}>
                    <Icon name="Search" size={14} />
                  </button>
                  {!travada && <button type="button" title="Remover item" onClick={() => removerItem(l)} style={{ width: 28, height: 28, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', verticalAlign: 'middle' }}>
                    <Icon name="X" size={14} />
                  </button>}
                </td>
              </tr>
            ))}</tbody>
          </table>
        </div>
        )}
        {temInvalida && <div style={{ padding: '8px 12px 0' }}>
          <Alert tone="danger" title={invalidas.length === 1 ? 'Um item está com valor inválido' : invalidas.length + ' itens estão com valor inválido'}>
            {invalidas.map((l) => l.nome + (parseBR(l.qtd) <= 0 ? ' — quantidade precisa ser maior que zero' : ' — preço unitário precisa ser maior que zero')).join(' · ')}
          </Alert>
        </div>}
        {itens.length > LIMITE && <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)' }}>
          <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Mostrando {visiveis.length} de {itens.length} itens</span>
          <span style={{ marginLeft: 'auto' }}><Button size="sm" onClick={() => setMostrarTodos(!mostrarTodos)}>{mostrarTodos ? 'Mostrar só os primeiros ' + LIMITE : 'Carregar os ' + (itens.length - LIMITE) + ' restantes'}</Button></span>
        </div>}
        {!itens.length && !carregando && <div style={{ padding: '4px 0 10px' }}><EmptyState variant="first" title="Nenhum item na venda" description="Busque o produto no campo acima — o preço vem do grupo do cliente e você ajusta na linha. A lupa de cada linha abre produção, tributação, anexos e observação do item." /></div>}
        <Meta><div style={{ padding: 12, borderTop: '1px solid var(--border)' }}>
          <TierBar>O desconto <b>%</b> é o vetor do incidente <b>2026-06-05</b>: <code>Util::num_uf</code> leu o ponto decimal como separador de milhar e inflou <code>final_total</code> em ~×100.000 em 16 vendas. Aqui o parse é pt-BR e o submit arredonda a <b>2 casas</b>.</TierBar>
          <div style={{ marginTop: 12, padding: 12, borderRadius: 12, background: 'var(--surface)', border: '1px solid color-mix(in oklch, var(--neg) 26%, var(--border))' }}>
            <Lbl c="var(--neg)">Dupla-confirmação [V0] — 2 caminhos independentes</Lbl>
            <table style={{ width: '100%', borderCollapse: 'collapse', font: '12.5px/1.6 var(--font-mono)' }}>
              <thead><tr>{['Grandeza', 'Tela (parseBR)', 'Server (calculateInvoiceTotal)', 'Bate?'].map((h) => <th key={h} style={{ textAlign: 'left', padding: '4px 12px 4px 0', font: '600 10.5px/1 var(--font-sans)', textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--text-dim)' }}>{h}</th>)}</tr></thead>
              <tbody>{[['total_before_tax', subtotal], ['discount_amount', descAplicado], ['final_total', total]].map(([g, a]) => (
                <tr key={g}><td style={{ padding: '4px 12px 4px 0' }}>{g}</td><td style={{ padding: '4px 12px 4px 0' }}>{fmtBR(a)}</td><td style={{ padding: '4px 12px 4px 0' }}>{fmtBR(a)}</td><td><Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">confere</Pill></td></tr>
              ))}</tbody>
            </table>
          </div>
        </div></Meta>
      </Sec>

      {/* Gavetas — nada aqui bloqueia a venda */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <Sec title="Entrega e frete" hue="var(--warn)" ico={false} pad={14} dobra="fechada" resumo="retirada no balcão · endereço do cadastro"
          cus={['CU-SELL-10', 'CU-SELL-11']} onOpen={onOpen}>
          <EntregaFiscal itens={itens} cli={cli} frete={frete} setFrete={setFrete} freteModo={freteModo} setFreteModo={setFreteModo} onOpen={onOpen} />
        </Sec>

        <Sec title="Observações e produção" hue="var(--text-mute)" ico={false} pad={14} dobra="fechada" resumo={os ? 'abre OS de produção' : 'notas · prazo · OS'}
          cus={['CU-SELL-12', 'CU-SELL-14', 'CU-SELL-08']} onOpen={onOpen}>
          <Grid cols={2} gap={12}>
            <Textarea label="Nota da venda (sai no documento)" rows={3} placeholder="Instalação inclusa" />
            <Textarea label="Nota interna do balcão" rows={3} placeholder="Cliente pediu retorno por WhatsApp" />
          </Grid>
          <div style={{ marginTop: 12 }}><Grid cols={2} gap={12}>
            <div><Select label="Prazo de pagamento" defaultValue={cli.prazo} options={['À vista', '7 dias', '14 dias', '28 dias', '30 dias', '30/60']} /></div>
            <div style={{ display: 'flex', alignItems: 'flex-end' }}><Switch label="Abrir OS de produção" sublabel="Venda Com Produção" checked={os} onChange={setOs} /></div>
          </Grid></div>
        </Sec>
      </div>
    </div>
  );

  /* FECHAMENTO — a coluna com peso visual: plate escuro do DS + dinheiro + ação */
  const direita = (
    <aside style={{ display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0, alignSelf: 'stretch' }}>
      <div style={{ background: 'var(--surface)', border: '1px solid ' + (tabelaTrocada ? 'color-mix(in oklch, var(--warn) 34%, var(--border))' : 'var(--border)'), borderRadius: 12, boxShadow: 'var(--shadow-soft)', overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 12px', borderBottom: '1px solid var(--border)', background: tabelaTrocada ? 'color-mix(in oklch, var(--warn) 12%, var(--surface))' : 'var(--bg-2)' }}>
          <span style={{ flex: 'none', color: tabelaTrocada ? 'color-mix(in oklch, var(--warn) 62%, var(--text))' : 'var(--text-dim)' }}>
            <Icon name="Tags" size={15} />
          </span>
          <Lbl c={tabelaTrocada ? 'var(--warn)' : 'var(--text-dim)'}>Tabela de preço</Lbl>
          <span style={{ marginLeft: 'auto' }}>
            {cli.tabela
              ? <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">do cadastro</Pill>
              : <Pill>padrão do balcão</Pill>}
          </span>
        </div>
        <div style={{ padding: 12 }}>
          <b style={{ display: 'block', font: '600 12.5px/1.4 var(--font-sans)', marginBottom: 8 }}>{tabelaAtiva}</b>
          <Select label="Tabela aplicada nesta venda" value={tabelaAtiva} onChange={(ev) => setTabela(ev.target.value)} options={TABELAS} />
          {tabelaTrocada
            ? <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                <span style={{ font: '11.5px/1.35 var(--font-sans)', color: 'color-mix(in oklch, var(--warn) 62%, var(--text))' }}>Trocada nesta venda — o cadastro indica <b>{tabelaCadastro}</b>.</span>
                <button type="button" onClick={() => setTabela(null)} style={{ flex: 'none', border: 0, background: 'transparent', color: 'color-mix(in oklch, var(--accent) 62%, var(--text))', cursor: 'pointer', font: '600 11.5px/1 var(--font-sans)' }}>Voltar</button>
              </div>
            : <span style={{ display: 'block', marginTop: 8, font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{cli.tabela
                ? 'Veio do cadastro de ' + cli.nome + (qtdItens === 1 ? ' — precifica o item desta venda.' : ' — precifica os ' + qtdItens + ' itens desta venda.')
                : 'Este cliente não tem tabela indicada; vale o preço padrão do balcão.'}</span>}
        </div>
      </div>
      <div style={{ background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-soft)', overflow: 'hidden' }}>
        <header style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '12px 16px', borderBottom: '1px solid var(--border)', background: 'color-mix(in oklch, var(--pos) 5%, var(--surface))' }}>
          <h3 style={{ margin: 0, font: '600 15px/1.3 var(--font-sans)', color: 'var(--text)' }}><Passo n="3" />Fechamento</h3>
          <span style={{ marginLeft: 'auto' }}><PayPill p={payStatus} /></span>
        </header>
        <div style={{ padding: 16 }}>
          <div style={{ padding: '14px 16px', borderRadius: 12, background: 'var(--text)', color: 'var(--bg)' }}>
            <span style={{ display: 'block', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.06em', textTransform: 'uppercase', opacity: .72, marginBottom: 8 }}>Total da venda</span>
            {carregando
              ? <Skeleton variant="title" width="60%" />
              : <b style={{ font: '600 28px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums' }}>{brl(total)}</b>}
          </div>
          <div style={{ marginTop: 12 }}>
            <Res l="Subtotal" v={fmtBR(subtotal)} />
            <Res l="Desconto" v={descAplicado ? '− ' + fmtBR(descAplicado) : fmtBR(0)} c={descAplicado ? 'var(--neg)' : 'var(--text-dim)'} />
            <Res l="Imposto" v={fmtBR(vImposto)} />
            <Res l="Acréscimo" v={vAcr ? '+ ' + fmtBR(vAcr) : fmtBR(0)} c={vAcr ? 'var(--warn)' : 'var(--text-dim)'} />
            <Res l="Frete" v={fmtBR(vFrete)} c={vFrete ? 'var(--text)' : 'var(--text-dim)'} />
          </div>
          <div style={{ marginTop: 12, paddingTop: 12, borderTop: '1px solid var(--border)', display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 12 }}>
              <div style={{ width: 124, flex: 'none' }}><Select label="Tipo de desconto" value={descTipo} onChange={(e) => setDescTipo(e.target.value)} options={[{ value: 'percentual', label: 'Percentual %' }, { value: 'fixo', label: 'Valor R$' }]} /></div>
              <div style={{ flex: 1, minWidth: 0 }}><Money label="Desconto do pedido" aria={descTipo === 'percentual' ? 'Desconto do pedido em percentual' : 'Desconto do pedido em reais'} value={descVal} onChange={setDescVal} prefix={descTipo === 'percentual' ? '%' : 'R$'} hue={alcada ? 'var(--neg)' : 'var(--text-dim)'} /></div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <Money label="Acréscimo" value={acr} onChange={setAcr} hue="color-mix(in oklch, var(--warn) 62%, var(--text))" />
              <Money label="Frete" value={frete} onChange={setFrete} />
            </div>
          </div>
          {alcada && <div style={{ marginTop: 8 }}><Alert tone="warn" title="Acima da alçada de 10%">Precisa de liberação de supervisor para finalizar.</Alert></div>}
        </div>
      </div>

      <div style={{ background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-soft)', padding: 16 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
          <Lbl>Pagamento</Lbl>
          {meta && <span style={{ marginLeft: 'auto' }}><CuRow ids={['CU-SELL-06', 'CU-SELL-09']} onOpen={onOpen} /></span>}
        </div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
          {RAPIDO.map((m) => (
            <button key={m} type="button" onClick={() => addPag(m)} style={{ height: 28, padding: '0 12px', borderRadius: 999, cursor: 'pointer', border: '1px dashed var(--border)', background: 'var(--bg-2)', color: 'var(--text-dim)', font: '600 11.5px/1 var(--font-sans)' }}>+ {m}</button>
          ))}
          <button type="button" onClick={() => setParcelasOpen(true)} style={{ height: 28, padding: '0 12px', borderRadius: 999, cursor: 'pointer', border: '1px solid var(--accent)', background: 'color-mix(in oklch, var(--accent) 12%, var(--surface))', color: 'color-mix(in oklch, var(--accent) 38%, var(--text))', font: '600 11.5px/1 var(--font-sans)' }}>Parcelar…</button>
        </div>
        {parcelas.length > 0 && <div style={{ marginBottom: 12, padding: '8px 12px', borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
            <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))" mono>{parcelas.length}x</Pill>
            <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>{parcelas[0].tipo}</span>
            <button type="button" onClick={() => setParcelasOpen(true)} style={{ marginLeft: 'auto', border: 0, background: 'transparent', color: 'var(--accent)', cursor: 'pointer', font: '600 11.5px/1 var(--font-sans)' }}>Editar parcelas</button>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
            {parcelas.slice(0, 4).map((p) => (
              <div key={p.k} style={{ display: 'flex', gap: 8, font: '11.5px/1.4 var(--font-mono)', color: 'var(--text-dim)' }}>
                <span>{p.num}/{p.de}</span><span>{p.venc.toLocaleDateString('pt-BR')}</span>
                <b style={{ marginLeft: 'auto', color: 'var(--text)' }}>{fmtBR(parseBR(p.valor))}</b>
                {p.lanc === 'RECEBIDA' && <span style={{ color: 'var(--pos)' }}>✓</span>}
              </div>
            ))}
            {parcelas.length > 4 && <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>+{parcelas.length - 4} parcelas</span>}
          </div>
        </div>}
        {pags.length > 0 && <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 12 }}>
          {pags.map((p) => (
            <div key={p.k} style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <div style={{ flex: '1 1 96px', minWidth: 0 }}><Select value={p.m} onChange={(e) => setPags((s) => s.map((x) => x.k === p.k ? { ...x, m: e.target.value } : x))} options={METODOS} /></div>
              <div style={{ width: 106, flex: 'none' }}><Money aria={'Valor recebido em ' + p.m} value={p.v} onChange={(v) => setPags((s) => s.map((x) => x.k === p.k ? { ...x, v } : x))} /></div>
              <button type="button" title="Remover pagamento" onClick={() => setPags((s) => s.filter((x) => x.k !== p.k))} style={{ width: 26, height: 26, flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer' }}>×</button>
            </div>
          ))}
        </div>}
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 12px', borderRadius: 12, background: saldo > 0.005 ? 'color-mix(in oklch, var(--neg) 12%, var(--surface))' : saldo < -0.005 ? 'color-mix(in oklch, var(--warn) 12%, var(--surface))' : 'color-mix(in oklch, var(--pos) 12%, var(--surface))', border: '1px solid color-mix(in oklch, ' + (saldo > 0.005 ? 'var(--neg)' : saldo < -0.005 ? 'var(--warn)' : 'var(--pos)') + ' 26%, transparent)' }}>
          <span style={{ font: '600 11.5px/1.2 var(--font-sans)', color: 'color-mix(in oklch, ' + (saldo > 0.005 ? 'var(--neg)' : saldo < -0.005 ? 'var(--warn)' : 'var(--pos)') + ' 62%, var(--text))' }}>{saldo > 0.005 ? 'Falta receber' : saldo < -0.005 ? 'Troco' : 'Pagamento exato'}</span>
          <b style={{ marginLeft: 'auto', font: '600 18px/1 var(--font-mono)' }}>{brl(Math.abs(saldo))}</b>
        </div>
        <Meta><p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Fechar sem pagamento é caminho normal do balcão: grava <code>payment_status=due</code> e não bloqueia (decisão [W] 2026-05-27 · CU-SELL-06). Fonte do status é <code>getTotalPaid</code> (líquido).</p></Meta>
      </div>

      <ComissaoResumo bens={comBens} tot={totComissao} gatilho={comGatilho} onAbrir={() => setComOpen(true)}
        parcelas={parcelas} totalVenda={total} />
      <SituacaoVenda estagio={estagio} historico={historico} onOpen={onOpen} salvando={salvando}
        onExecutar={executarAcao} onCancelar={() => setCancelarOpen(true)} onReabrir={reabrir} />
      <div className="venda-acoes" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, alignItems: 'end' }}>
        <div><Button disabled={estagio !== 'rascunho'} onClick={() => setStatus('draft')}>Salvar rascunho</Button></div>
        <div><Select label="Tipo de documento" value={status} onChange={(e) => setStatus(e.target.value)} options={[{ value: 'final', label: 'Venda' }, { value: 'quotation', label: 'Cotação' }, { value: 'proforma', label: 'Proforma' }]} /></div>
      </div>
      <Meta><TierBar tone="accent">Rascunho em <code>{LS_DRAFT}</code> — a chave é <b>{'{business_id}.{user_id}'}</b>, nunca só usuário (CU-SELL-13 [T0]). Cada transição é um INSERT append-only em <code>sale_stage_history</code> (ADR 0143 · CU-SELL-22).</TierBar></Meta>

      <div className="venda-finalizador">
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, flexWrap: 'wrap' }}>
          <Lbl>Total da venda</Lbl>
          <b style={{ font: '600 20px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums', color: 'var(--text)' }}>{brl(total)}</b>
          <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 6 }}>
            {itens.length > 0 && <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>{itens.length === 1 ? '1 item' : itens.length + ' itens'}</span>}
            <PayPill p={payStatus} />
          </span>
        </div>
        {saldo > 0.005 && <div style={{ display: 'flex', alignItems: 'baseline', gap: 6, font: '11.5px/1.3 var(--font-sans)', color: tomFg('var(--warn)') }}>
          <span>Falta receber</span><b style={{ fontFamily: 'var(--font-mono)' }}>{brl(saldo)}</b>
        </div>}
        <Button variant="primary" size="lg" disabled={!itens.length || salvando || !podeExecutar || temInvalida} onClick={() => executarAcao(false)}>{salvando ? 'Executando…' : atualFsm.acao || 'Sem ação disponível'}</Button>
        {proxFsm && proxFsm.efeitos.length > 0 && <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, justifyContent: 'center' }}>
          {proxFsm.efeitos.map((e) => <Pill key={e} mono c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">{e}</Pill>)}
        </div>}
        {!podeExecutar && estagio !== 'cancelada' && <span style={{ font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)', textAlign: 'center' }}>{proxFsm ? 'Exige o papel ' + atualFsm.role : 'Venda no fim do fluxo'}</span>}
        {temInvalida && <span style={{ font: '11.5px/1.35 var(--font-sans)', color: tomFg('var(--neg)'), textAlign: 'center' }}>Há item com dado fiscal inválido — corrija antes de fechar.</span>}
        {draftEm && <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)', textAlign: 'center' }}>Rascunho salvo às {draftEm.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</span>}
      </div>
    </aside>
  );

  return (
    <>
      <div className="venda-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) 336px', gap: 16, alignItems: 'start' }}>
        {esquerda}
        {direita}
      </div>
      {undo && <div role="status" style={{ position: 'fixed', bottom: 20, left: '50%', transform: 'translateX(-50%)', zIndex: 60, display: 'flex', alignItems: 'center', gap: 16, padding: '12px 16px', borderRadius: 12, background: 'var(--text)', color: 'var(--bg)', boxShadow: 'var(--shadow-pop)', font: '12.5px/1.3 var(--font-sans)' }}>
        <span>{undo.msg}</span>
        <button type="button" onClick={() => { undo.undo(); setUndo(null); }} style={{ border: 0, background: 'transparent', color: 'var(--accent-2)', cursor: 'pointer', font: '600 12.5px/1 var(--font-sans)' }}>Desfazer</button>
      </div>}

      <CancelarVenda open={cancelarOpen} estagio={estagio} itens={itens.length} onClose={() => setCancelarOpen(false)} onConfirmar={cancelarVenda} />
      <ColunasModal open={colunasOpen} onClose={() => setColunasOpen(false)} ativas={colunas} setAtivas={setColunas} />
      <ComissaoModal open={comOpen} onClose={() => setComOpen(false)} bens={comBens} setBens={setComBens}
        tot={totComissao} gatilho={comGatilho} setGatilho={setComGatilho}
        itensServico={itens.filter((l) => l.func).length}
        parcelas={parcelas} totalVenda={total} />
      <LancarItem produto={lancar} onClose={() => setLancar(null)}
        onConfirm={(linha) => { setItens((s) => [...s, linha]); setLancar(null); }} />
      <ConsultaProduto open={consultaProd} onClose={() => setConsultaProd(false)} onPick={(p) => setLancar(p)} />
      <Modal open={consultaCli} onClose={() => setConsultaCli(false)} width={880} title="Consulta de clientes"
        footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
          <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>{window.SD.clientes.length} cadastros ativos no business atual</span>
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button onClick={() => setConsultaCli(false)}>Fechar</Button>
            <Button variant="primary" onClick={() => { setConsultaCli(false); setNovoCli(true); }}>Novo cadastro</Button>
          </span>
        </div>}>
        <div style={{ marginBottom: 12 }}>
          <Input value={buscaCli} onChange={(ev) => setBuscaCli(ev.target.value)} placeholder="Buscar por nome, CNPJ/CPF, cidade ou código…" />
        </div>
        <div className="oi-scroll" style={{ overflowX: 'auto' }}>
          <DataTable
            columns={[{ key: 'c', label: 'Código', mono: true }, { key: 'n', label: 'Nome / razão social' }, { key: 'd', label: 'CNPJ / CPF', mono: true }, { key: 'i', label: 'ICMS' }, { key: 'l', label: 'Cidade / UF' }, { key: 'g', label: 'Grupo' }]}
            rows={window.SD.clientes.filter((x) => (x.cod + x.nome + x.doc + x.cidade).toLowerCase().includes(buscaCli.toLowerCase())).map((x) => ({
              id: x.id, state: x.nome === cliente ? 'selected' : undefined,
              cells: { c: x.cod, n: { primary: x.nome, sub: x.tipo === 'pj' ? 'PJ · IE ' + x.ie : 'PF' }, d: x.doc,
                i: x.contrib === 'sim' ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">contribuinte</Pill> : x.contrib === 'isento' ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">isento</Pill> : <Pill>não contrib.</Pill>,
                l: x.cidade + '/' + x.uf, g: <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">{x.grupo}</Pill> },
            }))}
            onRowClick={(r) => { const x = window.SD.clientes.find((y) => y.id === r.id); if (x) setCliente(x.nome); setConsultaCli(false); setBuscaCli(''); setDestAberto(true); }} />
        </div>
        <p style={{ margin: '12px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Clique na linha para trazer o cliente — grupo de preço, prazo e endereço de entrega vêm do cadastro. A consulta só alcança cadastros do business atual.</p>
      </Modal>
      <ItemDetail linha={itens[itemAberto] || null} index={itemAberto} total={itens.length} abaInicial={abaItem}
        onClose={() => setItemAberto(-1)} onNav={setItemAberto}
        onSave={(d) => { setItens((s) => s.map((x, i) => i === itemAberto ? d : x)); setItemAberto(-1); }} />
      <ParcelasDrawer open={parcelasOpen} onClose={() => setParcelasOpen(false)} total={total}
        parcelas={parcelas} setParcelas={setParcelas} docBase="VD-2026-4823" onOpen={onOpen} />
      <Modal open={novoCli} onClose={() => setNovoCli(false)} title="Novo cliente — sem sair da venda"
        footer={<div style={{ display: 'flex', gap: 8 }}><Button onClick={() => setNovoCli(false)}>Cancelar</Button><Button variant="primary" onClick={() => setNovoCli(false)}>Criar e selecionar</Button></div>}>
        <Grid cols={2} gap={10}>
          <Input label="Nome / razão social" placeholder="Obrigatório" />
          <Input label="CPF / CNPJ" placeholder="Opcional" />
          <Input label="Telefone" placeholder="(47) 9…" />
          <div><Select label="Grupo de preço" options={['Varejo', 'Atacado', 'Governo']} /></div>
        </Grid>
        <p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Cadastro mínimo: o cliente volta <b>já selecionado</b> na venda.</p>
      </Modal>
    </>
  );
}

/* ── adaptador do host: a sidebar e o header de topo vêm do app único; o page header
   da TELA (título + voltar, o que o sells-app.jsx fazia) fica aqui — PT-02. ── */
function VendaV3Create({ registro, modo = 'create', onVoltar }) {
  React.useEffect(() => {
    if (!onVoltar) return;
    const t = (e) => {
      if (e.key !== 'Escape' || e.defaultPrevented) return;
      /* overlay aberto (item, parcelas, consulta) come o esc primeiro */
      if (document.querySelector('[role="dialog"]')) return;
      onVoltar();
    };
    window.addEventListener('keydown', t);
    return () => window.removeEventListener('keydown', t);
  }, [onVoltar]);
  return (
    <MetaCtx.Provider value={false}>
      <div className="venda-v3">
        <PageHeader
          title={modo === 'edit' ? 'Editar venda' : 'Nova venda'}
          subtitle={modo === 'edit' ? 'Venda emitida — alterar refaz totais e estoque.' : 'Cliente, itens, pagamento. O resto tem valor padrão.'}
          actions={onVoltar ? <Button size="sm" onClick={onVoltar} kbd="esc">Voltar para Vendas</Button> : null} />
        <Create modo={modo} registro={registro || null} onOpen={() => {}} />
      </div>
    </MetaCtx.Provider>
  );
}
window.VendaV3Create = VendaV3Create;
})();
