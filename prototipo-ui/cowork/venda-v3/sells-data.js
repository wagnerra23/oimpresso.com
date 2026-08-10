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
