# Gap — Cobrança Recorrente (mockup Cowork vs tela viva)

> **Fase 1 da skill `aplicar-prototipo` — read-only.** Mapeia o que o mockup PROPÕE que a tela viva não tem, e onde está STALE (atrás do vivo).
> **Data:** 2026-06-30 · **Tipo:** mapeamento semântico (mockup simplifica de propósito).
>
> - **TELA VIVA:** `resources/js/Pages/RecurringBilling/Index.tsx` (1926 linhas, MWART v9,75, +24 Ondas, Inertia/React real, backend wired)
> - **MOCKUP:** `_cowork-handoff-staging/.../project/cobranca-recorrente-page.jsx` (378 linhas, mock client-side, `window.CobrancaRecorrentePage`)
>
> Regra Tier 0: `business_id` global scope intocável; qualquer item que toque VALOR/cobrança/estoque é Tier 0 — aqui só descrevo o visual e marco ⚠️.

---

## Veredito: **MOCKUP-STALE** (com 2 adoções parciais visuais de baixo risco)

A tela viva está **muito à frente** do mockup em quase todas as partes: backend real wired (POST/PUT/fetch), drawer de criação completo com busca de cliente debounced, editar cobrança, timeline append-only persistente, favoritos persistentes, atalhos de teclado, CmdPalette ⌘K, troubleshooters, modo apresentação, tour, KPIs deferidos do presenter PHP. O mockup é uma **reescritura visual** (linguagem DS "Cockpit V2 warm" — stone + roxo `var(--accent)`) sobre dados mock, e ainda traz **domínio errado** (WR2 Sistemas/gráfica, não o vertical vivo) + abas Planos/Faturas/Config como **placeholders honestos** ("não fingir pronto") que no vivo **já são Pages reais** (rotas dedicadas Ondas 6/7/8).

Logo: **nada de funcional a adotar**. O que o mockup oferece de genuíno é **direção visual** (2 ideias de UX abaixo) — e mesmo essas competem com o design já aprovado da tela viva, então só entram com gate visual + OK do Wagner.

### ADOTAR-PARCIAL (só visual, P, baixo risco — sujeito a gate F1.5 + Wagner)
1. **PeriodBar compartilhado do Financeiro** (`window.FinPeriodBar` + `finPeriodWindow`) — filtro de período por campo de data (`prox`/`inicio`) reusando o componente do Financeiro. Hoje o vivo tem presets "Próxima cobrança" próprios (any/today/tomorrow/week/month/custom). **Ideia a avaliar:** unificar a UX de período com o Financeiro. Mas é só consistência visual — o vivo já cobre o caso (inclusive intervalo custom server-side). **Não urgente.**
2. **Drawer com aba "✦ IA"** dentro do próprio drawer (mockup separa Detalhes | ✦ IA em tabs). No vivo a IA é o `JanaPanel` inline no fim do drawer. Reorganizar IA numa aba é cosmético.

---

## Mapa por parte

| Parte | Gap real (mockup→vivo) | Vivo-à-frente (STALE) | Por quê | Esforço · risco |
|---|---|---|---|---|
| **Header / hero** | Mockup tem eyebrow "Assinaturas · Cobrança Recorrente" + subtítulo contextual ("Junho 2026 · WR2 Sistemas"). | Vivo: `<h1>` + linha-status `N ATIVAS · MRR · CHURN%` derivada dos KPIs reais; botão "Nova assinatura" **wired** (abre drawer real, atalho N). | Mockup tem subtítulo **com domínio errado** (WR2 gráfica). Vivo deriva do presenter real. | — · — (nada a fazer) |
| **KPIs** | Mockup: 4 cards (MRR hero, Churn, Próximas cobranças, **A recuperar** = soma de falhas/retentando). | Vivo: 4 KPIs **deferidos** (`<Deferred data="kpis">`) do `SubscriptionIndexPresenter`, MRR hero com **sparkline SVG**, delta vs mês anterior. ⚠️ toca valor. | Vivo computa server-side (Tier 0 correto). Mockup soma client-side mock. | — · — |
| → *micro-gap* | Card **"A recuperar" (R$ a recuperar)** — soma valor das cobranças falhou+retentando. O vivo mostra **contagem** ("Retentado falhos" = `failed_count`), não o **valor a recuperar**. ⚠️ toca valor | — | Pode ser um KPI útil (valor em risco). Mas exige cálculo no presenter PHP, não no front. | P-M · **Tier 0 ⚠️** (só propor; cálculo no backend + dupla confirmação) |
| **Filtros / busca** | Mockup: PeriodBar (campo data + período) + busca `/` por cliente/CNPJ/plano. | Vivo: coluna de filtros (favoritos toggle persistente, presets "Próxima cobrança" incl. **custom server-side**, status com dots, lista de planos com contagem, **MRR filtrado** ao vivo) + busca `/` server-side (cliente/CNPJ/OS). | Vivo tem filtro **muito** mais rico e tudo server-side via `applyFilters` (Inertia partial reload `only:['subscriptions','kpis']`). | — · — |
| **Tabela / lista** | Mockup: linha = star, avatar, título, sub (`plano · ciclo · desde`), pill status + `método · valor`. | Vivo: idêntico em estrutura **+** star **persistente** (POST `/favorite`), navegação `j/k`, seleção ativa com borda primary, skeleton `<Deferred>`, contador `filtrados/total`. | Mockup é subconjunto visual do vivo. | — · — |
| **Ações por linha / drawer** | Mockup drawer: header + tabs Detalhes\|IA, card próxima cobrança, KV grid, nota pinada, PaymentHeat 12-cells, bloco fiscal + "Reenviar nota", timeline (3 eventos **mock estáticos**), footer Pausar/Diagnosticar/Reativar/Cancelar (**botões sem ação**). | Vivo (`DetailDrawer`): tudo isso **wired** — botão PDF/print extrato, card próxima cobrança, KV grid (incl. OS, motivo churn), nota pinada, `PaymentHistory`, `SubscriptionTimeline` **real** (fetch `/events`, POST nota persistente append-only), `JanaPanel`, ações **executáveis** (Pausar/Cancelar com motivo/Reativar/Diagnosticar→troubleshooter, Editar→drawer). ⚠️ ações tocam cobrança. | Mockup drawer é **decorativo** (handlers vazios); vivo é operacional. | — · — |
| → *única ideia visual* | Drawer **lateral overlay** com aba **✦ IA** separada + footer fixo de ações. Vivo usa coluna-3 fixa (3-col body) com IA inline. | — | Layout overlay vs 3-col é decisão de design já tomada no charter do vivo (3-col base). Reorganizar IA em aba é cosmético. | P · baixo (só com gate F1.5) |
| **Criação (Nova assinatura)** | Mockup: botão "Nova assinatura" **sem drawer** (não implementado). | Vivo: `NewSubscriptionDrawer` completo (Sheet DS) — busca cliente **debounced** (`/contacts/search`), plano opcional auto-preenche valor+ciclo, valor/ciclo/data/gateway(Inter/Asaas)/forma/descrição, validação, POST `/recurring-billing`. ⚠️ cria cobrança. | Mockup nem tenta; vivo é Onda 21 live. | — · — |
| **Editar cobrança** | Ausente no mockup. | Vivo: `EditSubscriptionDrawer` (PUT, edita valor/ciclo/forma; cliente/plano/data imutáveis). ⚠️ toca valor. | — | — · — |
| **Abas Planos/Faturas/Config** | Mockup: **placeholders honestos** descrevendo o que falta. | Vivo: **Pages reais** com rotas dedicadas (`/recurring-billing/planos|faturas|configuracoes`, Ondas 6/7/8). | Mockup admite que não fez; vivo já fez. | — · — |
| **Domínio / dados** | Mockup: mock gráfica WR2 (Padaria, Auto Posto…), persona "Eliana [E] / Larissa". | Vivo: dados reais do presenter, multi-tenant `business_id`. | Mockup tem **domínio de exemplo**, não fonte de verdade. | — · — (não importar dados) |

---

## Notas Tier 0 (não inventar)
- **KPI "A recuperar"** e qualquer card que some valor de cobranças falhas = **Tier 0 valor**: só descrever; se adotado, cálculo vai no `SubscriptionIndexPresenter` (PHP) com dupla confirmação + antes→depois (regra-mestre cálculo de valor). Não computar no front a partir de `next_value` mock.
- Rotas/handlers do mockup (`window.__selectRoute`, `FinPeriodBar`) são do harness de protótipo Cowork — **não** são paths do app. Equivalentes vivos: rotas Inertia `/recurring-billing/*` + `applyFilters`.
- Nenhum dado/CNPJ do mockup deve entrar no app (são fictícios de exemplo).

## Conclusão para a fila de aplicação
**Não enfileirar aplicação de tela.** Mockup está STALE vs o vivo (24 Ondas à frente, backend wired). As 2 ideias visuais (PeriodBar unificado, aba ✦ IA no drawer) são _pendentes_ de decisão do Wagner sob gate visual F1.5 — não são gap funcional. Eventual KPI "valor a recuperar" é proposta Tier 0 separada (backend), não import de protótipo.
