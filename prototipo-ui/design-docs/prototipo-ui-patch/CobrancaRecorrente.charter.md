---
page: /recurring-billing (Cobrança Recorrente · Assinaturas)
component: cobranca-recorrente-page.jsx (window.CobrancaRecorrentePage) · repo alvo resources/js/Pages/RecurringBilling/Index.tsx (+ Planos/Faturas/Configuracoes)
owner: wagner
status: aprovado visual (F2 · [W] 2026-06-03) — proposta de charter, vira oficial no git main
last_validated: "2026-06-03"
parent_module: RecurringBilling
related: [Financeiro.charter.md, ds-v5/components.css, ADR 0110]
related_adrs: [93, 110, 190, 235]
persona: Eliana [E] (financeiro escritório) + Larissa (balcão 1280px)
tier: A
charter_version: 1
---

# Page Charter — Cobrança Recorrente (Assinaturas)

> **Status:** [W] **aprovou a tela** 2026-06-03 ("essa é minha tela · trava a tela · page charter"). Este charter **trava** o conceito pra não regredir (L-14 charter-first). Vira oficial quando mirrorado pro git `main` (L-13).
> **Padrão visual:** Cockpit V2 quente (ADR 0110) · DS = `stone` + roxo `var(--accent)` (ADR 0235/0190) · molde do drawer = `Financeiro.charter.md`.
> **Origem do domínio:** git `Modules/RecurringBilling` (DataController.modifyAdminMenu, RecurringBillingController, SubscriptionIndexPresenter, web.php). Reescreve a RecurringBilling do git (que estava em Tailwind cru zinc/violet) na linguagem do DS.

---

## Mission

Ver e operar os **contratos recorrentes** da gráfica numa tela só: quem paga em dia, quem está retentando/falhou, quanto entra por mês (MRR), e agir (pausar/cancelar/reativar/diagnosticar/cobrar) sem sair da tela. A pergunta que responde primeiro é **"quem precisa de ação agora?"** (falhas/retentando), não "qual meu faturamento de competência?".

---

## Layout aprovado (TRAVADO — 3 colunas)

> Estrutura da tela aprovada por [W] 2026-06-03 (screenshot). Não regredir pra 1 coluna.

- **Header limpo:** título "Cobrança recorrente" + linha mono `N ATIVAS · MRR R$ X · CHURN N%`. **Sem fileira de abas colada no título.**
- **Sub-nav segmentada À DIREITA, AO LADO do primary "+ Nova assinatura"** (controle, não subtítulo): `Assinaturas · Planos · Faturas · Configurações` (labels verbatim do git). Lê como controle agrupado com a ação, não como ghost-button do título.
- **KPI strip warm (4):** hero **MRR** (card escuro `var(--text)`, NÃO zinc, com sparkline) · Churn este mês · Próxima cobrança · Retentado/falhos ("requer ação").
- **Corpo em 3 colunas:**
  - **Col 1 — Filtros:** "Mostrar só favoritos" · **Próxima cobrança** (Qualquer data/Hoje/Amanhã/Esta semana/Próx. 30 dias) · **Status** (Todas/Em dia/Retentando/Falharam/Pausadas/Canceladas, com dot warm) · **Plano** (lista com valor + nº ativas).
  - **Col 2 — Lista de assinaturas:** busca (`/`) · linha = ★favorito + avatar (hash hue) + cliente + `plano · ciclo · desde` + status pill warm + método·valor. Ativa = realce `accent-soft` + barra lateral roxa.
  - **Col 3 — Drawer de detalhe** (lateral, no molde do Financeiro): avatar+CNPJ+PDF+status · card **Próxima cobrança** / **Ação manual** (warm por status) · KV (Plano·Ciclo·Desde·Cobranças pagas·Falhas·LTV·Contato) · bloco **Fiscal** (NFe/NFS-e badge + reenviar) · **Histórico de pagamentos** (heatmap 12 meses warm) · **Notas & eventos** (timeline + input).

---

## Goals — Features (PRECISA TER)

**Aprovado / manter:**
- 4 KPIs warm com hero MRR escuro `var(--text)` + sparkline
- Status visual mapeado do git: **em dia · retentando N/3 (dots) · falhou N× · pausada · cancelada** (escala warm emerald/amber/rose/stone)
- Drawer lateral rico (próxima cobrança · KV · fiscal · heatmap pagamentos · notas/eventos) — **mesmo molde do Financeiro**
- Filtros em coluna (favoritos · próxima cobrança · status · plano)
- Busca `/` · favoritar ★ · ações no rodapé do drawer (pausar/cancelar/reativar/diagnosticar/reenviar nota)
- Sub-nav segmentada ao lado do primary

**Sub-telas (rotas reais do git — Assinaturas é o hub):**
- **Planos** — CRUD (nome·ciclo·valor·tipo fiscal·trial) + distribuição por ciclo
- **Faturas** — lista por status × gateway (Inter/C6/Asaas) + KPI pago no mês + cancelar
- **Configurações** — gateways · régua de cobrança (dunning) · NF-e/NFS-e auto · webhooks

**Roadmap (hoje mock):** cobrança/retry real por gateway · régua dunning automática · emissão fiscal vinculada (status SEFAZ) · histórico de pagamentos real (backend).

---

## Non-Goals (NÃO faz)

- ❌ Cobrança via WhatsApp cliente-facing (proibição charter)
- ❌ Detalhe em **modal central** — canon = **drawer lateral**
- ❌ Emissão fiscal completa (vai pros módulos NFe/NFSe — aqui só vínculo + status)
- ❌ Gateway/banking config dentro da tela de assinaturas (vai em Configurações)

---

## UX Anti-patterns (REPROVADO — não repetir)

- ❌ **Ghost buttons / sub-nav colados no TÍTULO ("page header")** — [W] 2026-06-03: *"eu odeio page header… o coisa difícil de assimilar"*. Sub-nav **sempre agrupada com o botão de ação**, à direita; nunca como subtítulo.
- ❌ **`rounded-2xl`+ · cor crua (zinc frio · violet ≠ roxo 235) · `bg-zinc-900` hero · `font-bold` em h1** — eram as 6 divergências da RecurringBilling do git vs DS. O charter mata todas.
- ❌ **Reestruturar/mexer no que já está aprovado** — [W] 2026-06-03: *"estragou o que estava ótimo"* (L-28). Alvo mínimo.
- ❌ Regredir pra 1 coluna (sem filtros / sem drawer lateral).

---

## UX Targets

- Cabe em 1280px sem scroll horizontal (Larissa ROTA LIVRE) — 3 colunas reflua, drawer 560px
- h1 peso normal (não bold) · KPI hero grande · escala warm semântica, zero cor crua
- Ação no drawer < 1 clique no hover · 0 erros JS console

---

## Refs

- `Financeiro.charter.md` — molde do drawer + escala warm (tela irmã)
- git `Modules/RecurringBilling/Http/Controllers/{RecurringBillingController,DataController}.php`, `Presenters/SubscriptionIndexPresenter.php`, `Routes/web.php`
- ADR 0110 (Cockpit V2) · 0190 (primary roxo universal) · 0235 (roxo canon) · 0093 (multi-tenant Tier 0)
- Arquivos Cowork: `cobranca-recorrente-page.{jsx,css}` · rota `recurring` + ghosts `rb-*` em `data.jsx`/`app.jsx`
