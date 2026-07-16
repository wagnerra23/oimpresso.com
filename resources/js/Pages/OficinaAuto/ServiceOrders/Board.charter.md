---
page: /oficina-auto/ordens-servico
component: resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
visual_source: oficina-page.jsx
related_us: [US-OFICINA-004, US-OFICINA-006]
owner: wagner
status: live
last_validated: "2026-06-11"
parent_module: OficinaAuto
states: [default, empty, dark]  # gate L2 — loading podado (render == default, md5 #3288) + error removido (toast não determinístico, md5 #3290) · sync com tests/Browser/visreg-states.json
related_adrs:
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0129-state-machine-canonica-fsm-rbac
  - 0093-multi-tenant-isolation-tier-0
tier: A
charter_version: 6
---

# Charter — Oficina Auto · workspace de OS (tela unificada)

> **Tela ÚNICA** servida em `/oficina-auto/ordens-servico` **e** `/oficina-auto/ordens-servico/board`
> (mesmo componente). Toggle **Quadro · Lista · Grade · Fila** — as 4 views in-page sobre o MESMO
> payload `columns`, com KPIs + abas de box + toolbar **compartilhados** (1 componente de cada).
> Unificação [W] 2026-06-11: no demo são ABAS, não páginas — manter Index/Board separados duplicava
> os componentes. A página `OficinaAuto/ServiceOrders/Index` foi **aposentada** (deletada).

## Mission
Dar ao operador da oficina (Martinho · mecânica pesada de caminhão) uma visão de
fluxo de trabalho das Ordens de Serviço — da recepção do veículo à retirada pelo
cliente — onde mover um card **executa a transição de etapa de verdade** (FSM),
não só reorganiza visualmente.

## Contexto de domínio (anti-confusão)
Martinho é **oficina de mecânica pesada** (caminhão entra pra manutenção/troca de
peça — quase concessionária/loja de peça). **NÃO é locação de caçamba.** O nome
"caçamba" nos artefatos legados (`cacamba_*`, `ProducaoOficina`) é equívoco já
corrigido pela [ADR 0194]. Este quadro roda no processo FSM **`oficina_mecanica_os`**
(nome correto), distinto do board de caçamba (ProducaoOficina/Index).

## Goals
- Colunas **data-driven** pelas etapas reais do FSM (Recepção → Diagnóstico →
  Aguardando aprovação → Aguardando peças → Em execução → Pronto p/ retirar).
- **Duas portas** pra mesma máquina FSM (Onda 1.5 · paridade Cowork): (a) **drag**
  entre colunas e (b) **botão de ação** no card (Triagem→/Enviar orçamento→/Peças
  chegaram→/Concluir→/Entregar→). Ambas → confirmação → **ExecuteStageActionService**
  (audit em `sale_stage_history`). Nunca UPDATE direto em `current_stage_id`.
- Card legível na frente do cliente: **foto real** (sem placeholder de texto),
  contador **DVI x/y** (checklist), placa Mercosul, valor, mecânico, prazo, **km de
  entrada**, **barra de progresso** (% DVI decidido) e linha **"últ."** (última
  transição FSM auditada — dado real, sem mock).
- **KPIs com sublinha** (Recepção · Em diagnóstico · Aguardando peças · Em execução ·
  Urgentes · **Valor em curso** = faturamento previsto), 5 clicáveis como filtro.
- **Abas de box/elevador** (filtro client-side com contador) + menu Visão (Foco
  Etapa/Box/Mecânico · Densidade) — re-pivot client-side sem round-trip.
- **Barra de views canon** (`.ofc-view-toolbar` · Onda 2 · [W] 2026-06-11): o toggle
  de views + o botão **Visão** moram na **barra da busca** (`[busca + contador] |
  [toggle] | [Visão]`), não no header — que fica só com `Imprimir fila` + `Nova OS`.
- **Toggle de 4 views — TODAS in-page** (tela unificada · [W] 2026-06-11): **Quadro** ·
  **Lista** · **Grade** · **Fila**. Trocam só o miolo sobre o mesmo payload `columns`;
  KPIs/abas/toolbar continuam acima (compartilhados). View persistida em `localStorage`
  `oficinaBoard.view` + refletida em `?view=` (shareable). **Lista** = tabela rica (OS ·
  PLACA Mercosul · VEÍCULO+km · CLIENTE · ETAPA dot+nome · BOX · MECÂNICO · PRAZO ·
  VALOR). **Fila** = master-detail (lista persistente + detalhe inline read-only com
  pipeline+meta+timeline ao vivo + rail Apps Vinculados); a edição completa abre o
  drawer rico via "Abrir OS completa". **Onda 2 (v5 · 2026-06-11): o detalhe da Fila
  virou RICO INLINE** — o centro renderiza o `ServiceOrderRichBody` (DVI semáforo /
  Fotos & Laudo / Peças & mão-de-obra / Checklist de etapa / Pipeline FSM / Linha do
  tempo), editável inline. É o **MESMO corpo do drawer** (RichSheet = wrapper Sheet +
  body; Fila = body inline) — 1 corpo, 2 chrome, **zero duplicação**. O drawer
  `ServiceOrderSheet` simples foi aposentado; `ServiceOrderRichBody`/`RichSheet` é o
  único drawer de OS.
- **Grade** (Onda 2 · canon `.ofc-grade`): varredura client-side **veículo × etapa**
  — cada linha é uma OS (placa Mercosul + modelo + cliente), cada coluna é uma etapa
  FSM do payload `columns`, e a **marca** cai na célula da **etapa atual** (tom da
  coluna + glifo semântico). Respeita busca + KPI-filtro + aba de box; legenda
  data-driven. Independe do foco (Box/Mecânico é pivot só do Quadro).
- Densidade compacta @1280 (monitor do operador) via **@container** (não @media).
- Reusar o canon: KanbanDndProvider, DragConfirmDialog, ServiceOrderRichSheet,
  MercosulPlate (estender, não recriar — §10.4).

## Non-Goals
- NÃO cria OS inline pelo drag (usar "Nova OS").
- NÃO emite documento fiscal nem dispara cobrança (fiscal real espera [W]).
- As 4 views derivam do MESMO payload `columns` (workspace do pipeline mecânica não-
  terminal) — não há mais página Index separada. A Lista/Fila aqui **não** são uma
  segunda implementação: são views in-page sobre os mesmos cards (zero duplicação).
- A **Grade** NÃO inventa cobertura serviço×sintoma (a heurística do protótipo Cowork
  fica fora · gate `no-mock-in-prod`): a marca espelha só a **etapa FSM real** da OS.
- NÃO mexe no board de caçamba (ProducaoOficina) nem no processo `cacamba_*` legado.
- NÃO faz **drag** pra etapas terminais (Entregue/Cancelado/Garantia). **EXCEÇÃO
  Onda 1.5** (emenda v3 · [W] 2026-06-10): o **botão "Entregar →"** do card em
  `pronto_retirada` executa a transição terminal `entregar` → `entregue` (sai do
  quadro) via FSM service. Cancelado/Garantia continuam só pelo drawer.
- NÃO inventa campo sem lastro: ETA-diag, "Encomendado: peça chega X" e "Pago" do
  protótipo NÃO têm coluna real → **omitidos** (gate `no-mock-in-prod`). Reentram
  quando houver schema de estoque/pagamento.

## UX targets
- Mover card → etapa avançada + toast em < 1.5s (1 request FSM + reload parcial).
- Card sem foto **esconde** o thumb (ícone câmera discreto sinaliza ação; sem texto "inacabado").
- Distinção visual clara entre "Aguardando aprovação" (âmbar · OK do cliente) e
  "Aguardando peças" (violeta · peça física).
- Acessível: cards com `role=button` + teclado (Enter abre; dnd-kit Space/setas movem).

## Automation hooks
- `router.reload({ only: ['columns','kpis'] })` pós-transição (SPA parcial).
- Drawer `ServiceOrderRichSheet` reusa FsmActionPanel (StartPipeline pra OS sem etapa).

## Anti-hooks (o que NUNCA fazer aqui)
- Nunca `$order->update(['current_stage_id' => ...])` — só via FSM service.
- Nunca hardcodar colunas: ler do payload `columns` (etapas reais do processo).
- Nunca exibir vocabulário de caçamba/locação (m³, diária, recolhimento) — é carro.
- Nunca cor de status crua `text-rose/emerald-700` — usar `text-destructive`/`text-success`.
- Nunca **`bg-white`/`text-white`** (nem `bg-black`/`text-black`): são cor CRUA e **não têm par
  dark** — o tema é resolvido pelos tokens, então branco hardcodado continua branco no dark.
  Superfície = `bg-card`; sobre `bg-primary` = `text-primary-foreground` (e `/20` pra overlay).
  **Não confie no `ds/no-raw-palette-color`**: o seletor dele exige step numérico
  (`bg-<cor>-<n>`), então `bg-white` (sem escala) passa batido — a regra do lint não cobre
  este caso. _Origem (2026-07-16): a tela tinha 14 `bg-white` e ZERO `dark:`; no dark o
  header renderizava branco com `text-foreground` claro por cima — o título "Oficina Auto"
  media **1.10:1** de contraste (WCAG AA exige 4.5:1), i.e. branco-sobre-branco, invisível.
  Pior: a baseline `oficina-os · dark` do gate visual estava FOTOGRAFANDO o bug e o travando
  como contrato de não-regressão. Migrado pros tokens: 1.10:1 → 12.40:1._

## UCs cobertos (PRECISA TER · rastreável · §10.4 [CC])

> Casos de Uso ("A tela precisa:") amarrados a GUARD Pest `uc-<id>` via [`prototipo-ui/audit/uc-registry.json`](../../../../../prototipo-ui/audit/uc-registry.json).
> ✅ presente+travado (some o elemento = build vermelho) · 🟡 gap (acende no `protocol_freshness`).

- ✅ **UC-02** (`uc-02`) — triar a fila + alocar mecânico/box: visão de ocupação do pátio, fila ordenável, atribuição por arraste (drag-drop).
