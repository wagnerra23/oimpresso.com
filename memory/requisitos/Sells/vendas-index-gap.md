---
id: requisitos-sells-vendas-index-gap
tela: Sells/Index
map_json: n/a (pendente decisão [W] — a mesma tela já tem memory/requisitos/Sells/vendas.map.json (gap_fonte vendas-gap.md); 2º map = régua duplicada — ver §Reconciliação 2026-09-06)
---

# Gap map — Sells/Index (Lista de Vendas) × mockup Cowork `vendas-page.jsx`

> ## ⚠️ Reconciliação 2026-09-06 — dois gaps para UMA tela; proposta de dono (⛔ decisão [W])
>
> A tela `Sells/Index` tem **dois** gap.md em `memory/requisitos/Sells/`: [`vendas-gap.md`](vendas-gap.md) (2026-06-22, [#3343](https://github.com/wagnerra23/oimpresso.com/pull/3343)) e este (2026-06-30, [#3451](https://github.com/wagnerra23/oimpresso.com/pull/3451)). O lote [#6897](https://github.com/wagnerra23/oimpresso.com/pull/6897) gerou um 2º map por este e a refutação r5 apontou a duplicação — o map saiu. Evidência medida em 2026-09-06 (`origin/main` 80bc4ef8b9):
>
> | eixo | `vendas-gap.md` + `vendas.map.json` | este arquivo |
> |---|---|---|
> | frontmatter máquina-legível (`tela`/`prototipo`/`tela_viva`) | sim (`vendas-gap.md:1-10`) | só `id` até hoje (`tela` adicionado nesta reconciliação) |
> | partes | 6 | 8 (por região) |
> | âncoras com linha real (`grep -n`) | **11 de 12** (5 partes duplas + `devolucoes-relatorios` com vivo `TODO` — não há tela) — [#4087](https://github.com/wagnerra23/oimpresso.com/pull/4087) "por grep real"; `prototipo_sha sha256:0462e8dec988`, 0 DRIFT no `design-code-map-check` | 0 |
> | `resources/js/Pages/Sells/Index.charter.md` cita | nenhum dos dois (`related_prototype`/`bundle_source` = `vendas-page.jsx`, `:6-7`) | nenhum dos dois |
> | quem cita (`git grep -l`, fora do `doc-id-index.json`) | `08-handoff.md` · `handoffs/2026-06-22-2324-*` · `sessions/2026-06-23-arte-handoff-*` · `vendas.map.json` | só o selftest do `prototipo-ui/gerar-contrato.mjs:153-158` (nome usado como fixture de desambiguação) e um comentário em `scripts/pr-critic/coleta.mjs:14` |
>
> **O que este arquivo tem e o dono não tem** (conteúdo a absorver, não a perder): §Veredito "Adotar" #1–#4 (barra de totalizadores · CTA "→ ver estouradas" · barra de progresso no KPI PIX · sub-views de saved view) + "Verificar antes" (`SellsTabelaUnificada`/`SaleSheet`) + "Não adotar". Estado hoje em `resources/js/Pages/Sells/Index.tsx`: `totals` é buscado (`:499`, `:725`) mas há **0** render de `vd-totalbar`; contador "estouradas" em `:1074` sem CTA; PIX em `:1465-1469` sem barra — os 3 primeiros "Adotar" seguem abertos.
>
> **Proposta (⛔ [W] decide):** dono = `vendas-gap.md` + `vendas.map.json`. Ao ratificar: (1) acrescentar 4 linhas à tabela de partes do `vendas-gap.md` (as 4 do "Adotar", Ação nos termos do §Veredito — ⚠️ Tier 0 no #1: "só exibir o número já computado pelo backend, não recalcular"); (2) `node prototipo-ui/gerar-map.mjs Sells/vendas --atualizar` (preserva as 11 âncoras; 4 partes novas nascem `TODO` e ganham linha por grep real); (3) este arquivo vira registro datado com ponteiro. O inverso (dono = este) custa refazer 11 âncoras e reapontar 4 citações. **Enquanto [W] não decide:** `map_json: n/a` no frontmatter — fora do denominador do `design-code-map-check`, nenhum 2º map é gerado.

> Fase 1 (read-only) da skill `aplicar-prototipo`. Compara a tela VIVA com o mockup do bundle Cowork importado pra staging.
>
> - **VIVO:** `resources/js/Pages/Sells/Index.tsx` (1808 linhas)
> - **MOCKUP:** `_cowork-handoff-staging/oimpresso-erp-conunica-o-visual/project/vendas-page.jsx` (1910 linhas, contém `VendasListPage` + `VendaDetailDrawer` + `VendaCreateDrawer`)
> - Data análise: 2026-06-30

## Contexto importante

O VIVO **já nasceu como cópia visual de um mockup Cowork desta mesma família** (KB-9.75, prototype chat10 2026-05-16, score 9.75/10 — ver header do `Index.tsx`). Em seguida o VIVO foi cabeado a backend real (`/sells-list-json`, Inertia::defer, FSM live biz=1, integração Vendas×Oficina ADR 0192, multi-tenant Tier 0). O mockup importado agora é uma **iteração lateral** (linhagem "A+" 2026-05-14, com refinos [W] de 2026-06-11/12) — em vários pontos o VIVO está **à frente** (dados reais), e em alguns o mockup traz **layout/ideias novas** ainda não no VIVO.

`_pendente_` = não consegui confirmar no escopo lido (não inventar).

---

## Comparação por parte

### 1. Header / PageHeader
- **Mockup tem que vivo NÃO tem:** sub-nav em página via `window.VdModNav here="lista"` logo abaixo do header (refino [W] 2026-06-11). VIVO usa `vd-visoes-menu` (dropdown "Visões") em vez de sub-nav inline.
- **Vivo tem que mockup não tem:** subtitle métrica **live** vindo de `rows` reais (`X vendas · Y faturado · N estouradas`); CTA "Nova venda" condicionado a `permissions.create`; busca ⌘K movida pra barra de tabs (decisão Wagner 2026-05-21, divergência intencional vs mockup que deixa ⌘K no header).
- **Por quê:** sub-nav inline é decisão de IA/posicionamento; o VIVO resolveu com dropdown "Visões" + `SellsTabsVisao`. Adotar a sub-nav inline exigiria alinhar com PageHeader canon (ADR 0180/0182) — **não é gap claro**, é divergência de navegação já decidida.
- **Esforço/risco:** M / médio (mexe em navegação canônica). **Não adotar sem decisão Wagner.**

### 2. KPIs / cards de resumo
- **Mockup tem que vivo NÃO tem (gaps reais candidatos):**
  - **(a) PIX hoje com barra de progresso** (`vd-pix-prog` + `kpi_pix / kpi_total`) como 4º card no Foco=Caixa. VIVO tem "Pagos hoje" no Foco=Caixa e um 5º KPI "PIX hoje" **sem barra de progresso** (só texto `% do faturamento`). Mockup tem a barrinha visual de share PIX.
  - **(b) Card "A receber" com CTA "→ ver estouradas"** + classe `vd-kpi-alert` quando há overdue (botão que seta saved view `atrasadas`). VIVO mostra os counts SLA + ageing bar mas **não tem o botão de atalho** nem o destaque visual de alerta no card.
  - **(c) Foco=Comissão como KPI "Comissões do mês"** (valor R$ de comissão + barra de meta agregada `vd-meta-lbl`). VIVO mostra "Top vendedor (mês)" (nome + total) no Foco=Comissão — conceito **diferente** (top seller vs total comissão+meta).
- **Vivo tem que mockup não tem:** breakdown por source (Balcão/Oficina/Online) no hero — na verdade **ambos têm** (paridade ADR 0192); deltas reais via `coworkAggregates` (Inertia::defer) em vez de hardcode `↑ +18% vs ontem` do mockup; tag "todas origens".
- **Por quê adotar (a) e (b):** baratos, puramente visuais, melhoram leitura pra Larissa (PIX-first vestuário). (c) é decisão de produto — "Comissões do mês" pode ser mais útil que "Top vendedor", mas muda semântica do card; precisa de dado real de comissão agregada (hoje só `topSeller` vem do backend).
- **Esforço/risco:** (a) P/baixo · (b) P/baixo · (c) M/médio (precisa backend de comissão agregada + meta por vendedor).

### 3. Filtros / busca / Foco / Visões
- **Mockup tem que vivo NÃO tem:**
  - Saved views com **sub-views ricas**: `pendentes:bruna` (por vendedor, filhos dinâmicos), `faturadas:b2b`/`b2c` (com/sem CNPJ). VIVO tem saved views planas + branch "Por origem" (Balcão/Oficina/Online) — **só a origem é expansível**; falta o tree por-vendedor e B2B/B2C.
  - Item "Salvar vista atual…" no fim do dropdown (placeholder).
  - Toolbar de filtros posicionada **acima da lista** numa linha só (status tabs + Foco + Visões + busca juntos — refino [W] 2026-06-11/12). VIVO separa em `vd-toolbar` (topo) + `vd-tabs-row` (pills + Visão + ⌘K + Filtros avançados).
- **Vivo tem que mockup não tem (vivo à frente):** busca **server-side** (`/sells-list-json?q=`, debounce 300ms) vs filtro client-side do mockup; barra colapsável "Filtros avançados" com `SellsDateFilter` (7 campos de data: emissão/atualização/nfe/faturamento/envio/competência/prometido — US-SELL-018/021); hint de filtro de data stale (fix bug "vendas só até dia 14"); persistência Tier 0 per-business (`oimpresso.sells.b<bizId>.*`).
- **Por quê:** sub-views por vendedor + B2B/B2C são **gap real de UX útil** (segmentação fina). Mas o backend hoje filtra por `payment_status` server-side; replicar tree client-side sobre `rows` da página é viável (mockup faz client-side também).
- **Esforço/risco:** sub-views vendedor/B2B-B2C M/médio · "Salvar vista" G/grande (precisa persistência + backend) · reposicionar toolbar P-M/baixo (mas mexe em layout decidido).

### 4. Tabela / lista
- **Mockup tem que vivo NÃO tem (visíveis no mockup, _pendente_ confirmar no componente do vivo):** O VIVO delega a tabela a `SellsTabelaUnificada` (componente externo, **não lido nesta análise**). O mockup renderiza a `<table>` inline com colunas: Venda · Data · Cliente · **Atendido por** (avatar vendedor/mecânico + origem) · Origem · Pipeline (stepper FSM) · Fiscal (NF-e/NFS-e badges) · Pagamento (+SLA pill) · Total · **Comissão** · Status. Também:
  - **Linha de mecânica** com placa Mercosul (`VdPlate`) + veículo + km quando `v.vertical === "mec"`. VIVO tem `vehicle_plate` no tipo `SaleRow` (ADR 0251) — _pendente_ confirmar se `SellsTabelaUnificada` renderiza a placa.
  - **Ações inline por linha** (`vd-row-actions`): baixar DANFE PDF, baixar XML (quando NF-e ok), imprimir recibo. _pendente_ confirmar no `SellsTabelaUnificada`.
  - **Skeleton de loading** (6 linhas `vd-sk-row`). VIVO passa `loading` ao componente — _pendente_ confirmar skeleton.
- **Vivo tem que mockup não tem:** colunas **derivadas da tab Visão** (Operacional/Financeira/Produção via `visibleColumns` + `COLUMNS_*`), coluna Comissão condicionada a `coworkCommissionEnabled` (setting `sales_cmsn_agnt`), `QuickPaymentPopover` ancorado por linha (US-SELL-042), navegação cross-módulo `onPickOs` (roteia OS-/SO- pra Repair/OficinaAuto, ADR 0265).
- **Por quê:** o grosso da tabela vive em `SellsTabelaUnificada` — **a verdade do gap de tabela exige ler esse componente** (fora do escopo deste arquivo). Marcado `_pendente_`.
- **Esforço/risco:** _pendente_ até inspecionar `_components/SellsTabelaUnificada.tsx`.

### 5. Ações por linha
- Coberto em §4 (ações inline DANFE/XML/recibo no mockup). VIVO: ações via drawer (`SaleSheet`) + `QuickPaymentPopover` + bulk bar. **Gap potencial:** atalhos de ação direto na linha sem abrir drawer — _pendente_ confirmar em `SellsTabelaUnificada`.

### 6. Drawer / Sheet de detalhe
- **Mockup tem que vivo NÃO tem (_pendente_ confirmar — VIVO usa `SaleSheet`, não lido):** o `VendaDetailDrawer` do mockup tem 5 tabs (Itens · Fiscal · Pagamento · Timeline · ✦ IA), cards fiscais ricos (`VdFiscalCard`: timeline emissão, chave SEFAZ copiável, CC-e, CTAs DANFE/XML/Enviar), painel "próxima ação FSM" (`VdNextActionPanel`), comentários inline por item, edição inline de item com delta de total, lançamentos financeiros vinculados (`FIN_ROWS` por `#V-`), OS vinculadas, mensagem pra cliente, modo apresentação, transcript PDF, recibo térmico, orçamento A4. VIVO tem `SaleSheet` com `initialAiOpen` (painel ✦ IA existe).
- **Por quê:** o VIVO já tem drawer com IA; paridade fina (tabs/cards fiscais/financeiro vinculado) **exige ler `_components/SaleSheet.tsx`** — fora do escopo. `_pendente_`.
- **Esforço/risco:** _pendente_.

### 7. Paginação / footer / totalizadores
- **Mockup tem que vivo NÃO tem (gap real candidato):** **barra de totalizadores** (`vd-totalbar`) abaixo da tabela — soma do filtro atual: `N vendas · X pagas · Y a receber` + **Comissão total** + **Total do filtro** (refino [W] 2026-06-12 "preciso dos totalizadores"). VIVO tem `totals` no state (`TotalsSummary`: count/sum_final_total/sum_total_paid/sum_due vindo de `/sells-list-json`) **mas não renderiza a barra de totalizadores** — o dado existe e está ocioso.
- **Vivo tem que mockup não tem:** paginação real server-side (`meta.current_page/last_page`, botões Anterior/Próxima) — mockup não pagina (lista mock).
- **Por quê adotar:** **gap real e de alto valor** — o backend já devolve `totals`, só falta renderizar. Larissa/Wagner pediram totalizadores explicitamente no mockup. Baixo esforço, dado já disponível.
- **Esforço/risco:** **P / baixo** (renderizar `totals` que já chega no payload; cuidado Tier 0 cálculo de valor — apenas exibe número já computado pelo backend, não recalcula).

### 8. Estados vazios
- **Mockup tem que vivo NÃO tem:** empty states **contextuais por saved view** (`atrasadas` → "Tudo dentro do prazo ✓"; `rejeitadas` → "Zero rejeições da SEFAZ ✓"; `favoritas` → "Foque uma linha com J/K e aperte B"; `pendentes`/`faturadas` próprios). VIVO delega empty a `SellsTabelaUnificada` — _pendente_ confirmar se tem empty contextual; provável que tenha empty genérico.
- **Por quê adotar:** baixo esforço, melhora percepção. Depende de onde o empty é renderizado (componente externo).
- **Esforço/risco:** P/baixo (se for no componente) — _pendente_ localizar.

### Bonus — Create (fora do escopo da tela Index, mas presente no mockup)
- O mockup traz `VendaCreateDrawer` (wizard 4 steps: Cliente→Itens→Pagamento→Confirmar, com modo editar). VIVO usa página `/sells/create` (link, não drawer). **Divergência arquitetural já decidida** (VIVO navega pra página dedicada). Não é gap da Index. **Não adotar** o drawer aqui.

---

## Veredito final

**ADOTAR-PARCIAL** — o VIVO está à frente do mockup no que importa (dados reais, server-side, multi-tenant Tier 0, FSM live, integração Oficina). O mockup é uma iteração lateral com alguns ganhos visuais/de produto pequenos e bem localizados. **Não é ADOTAR-FORTE** (mockup não é estado-da-arte acima do vivo) nem **MOCKUP-STALE puro** (há 3-4 ganhos reais a colher).

### Adotar (ordenado por ROI):
1. **Barra de totalizadores** (`vd-totalbar`) abaixo da tabela — **P, alto valor**, dado (`totals`) já chega no payload e está ocioso. Pedido explícito de Wagner no mockup. ⚠️ Tier 0 cálculo: só **exibir** o número já computado pelo backend, não recalcular no front.
2. **CTA "→ ver estouradas" + destaque de alerta no card "A receber"** quando há overdue — **P**, puro front, melhora ação rápida.
3. **Barra de progresso visual no KPI PIX hoje** (`vd-pix-prog`) — **P**, puro front (dado `pixHojeTotal`/`faturadoHojeTotal` já existe em `coworkAggregates`).
4. **Sub-views de saved view** (por vendedor em "Pendentes"; B2B/B2C em "Faturadas") — **M**, client-side sobre `rows`, paridade com tree de origem já existente.

### Verificar antes de decidir (precisa ler componentes externos — não lidos nesta fase):
- `_components/SellsTabelaUnificada.tsx` — confirmar ações inline por linha (DANFE/XML/recibo), placa Mercosul, skeleton, empty states contextuais.
- `_components/SaleSheet.tsx` — confirmar paridade do drawer (tabs Itens/Fiscal/Pagamento/Timeline/IA, cards fiscais, financeiro vinculado, próxima-ação FSM).

### Não adotar (divergência intencional já decidida):
- Sub-nav inline no header (VIVO usa dropdown "Visões" + `SellsTabsVisao`).
- ⌘K no header (VIVO moveu pra barra de tabs — Wagner 2026-05-21).
- Create como drawer wizard (VIVO usa página `/sells/create`).
- Filtros client-side (VIVO é server-side, superior).
- "Top vendedor" → "Comissões do mês": só com decisão de produto + backend de comissão agregada.
