---
id: resources-js-pages-governance-custos-charter
page: /governance/custos
component: resources/js/Pages/governance/Custos.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
related_runbook: memory/requisitos/Governance/RUNBOOK-custos.md
owner: wagner
status: draft
last_validated: "2026-08-05"
parent_module: Governance
related_us: [US-COPI-070]
related_adrs: [366, 114, 104, 101, 93, 86, 29]
tier: B
charter_version: 1
---

# Page Charter — /governance/custos (DRAFT)

> **Status:** draft. Porte de `Jana/Admin/Custos/Index` para a Governança
> ([ADR 0366](../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-B/§D-C item 2 —
> o `Jana/Chat.charter.md` já mandava *"custo vai pra /governance — Wagner-only"*).
> Herda os Non-Goals/Anti-hooks do charter de origem, que **Wagner ainda não ratificou**; continua `draft`
> até ele aprovar (nenhuma inferência nova foi adicionada neste porte).
>
> Backend: `Modules\Governance\Http\Controllers\CustosController@index` (rota `governance.custos.index`,
> permissão **`jana.admin.custos.view`** — nome legado preservado de propósito, ver Anti-hooks).

---

## Mission

Dar ao auditor do business a visão consolidada de quanto a IA custou no período escolhido — mês atual,
mês anterior, últimos 90 dias ou range customizado. Responde *"quanto gastei de IA e quem consumiu"* com
KPIs de custo em R$, tokens, mensagens e usuários ativos, gráfico de gasto diário e breakdown por usuário.
Fundamenta a decisão de ROI (Onda 1) sem depender do superadmin.

---

## Goals — Features (faz)

- Strip de sub-navegação da Governança (`GovernancaSubNav active="custos"`) — a lista vem dos ghosts do `DataController`, não é duplicada aqui.
- 4 KPIs do período (`KpiGrid`/`KpiCard`): custo em R$, mensagens, tokens consumidos, usuários ativos.
- Filtro de período por preset (`mes_atual`, `mes_anterior`, `90d`, `custom`) e, no modo `custom`, range De/Até via form.
- Gráfico de área SVG inline (sem dep externa) do gasto diário no período, com total agregado.
- Tabela "Por usuário" com conversas, mensagens, tokens e R$ aproximado, mais linha de total.
- Mostra contexto de pricing (modelo base e câmbio BRL/USD) lido de `config('copiloto.ai.*')`.
- Partial reload (`router.get` com `only: [...]`) ao trocar filtro — não retrafega `pricing` (config estática).

---

## Non-Goals — Features (NÃO faz)

> Herdados do charter de origem — **pendentes de ratificação [W]**, não são inferência nova deste porte.

- ❌ Não expõe custo cross-business — cada auditor vê só o `business_id` da própria sessão.
- ❌ Não edita/ajusta preços de modelo nem câmbio pela tela (config, não formulário).
- ❌ Não exporta CSV/PDF do relatório.
- ❌ Não faz forecast/projeção de custo futuro — só mostra o realizado do período.

---

## UX targets

- p95 < 1500ms (auditor) ; cabe em 1280px (ROTA LIVRE) ; `AppShellV2` + `GovernancaSubNav`.
- Labels de formulário associados (`<Label htmlFor>` + `id`) — a origem tinha 3 violações
  `jsx-a11y/label-has-associated-control` grandfathered; arquivo novo não herda grandfather.

---

## Automation hooks (faz)

- Consumo de IA popula o painel automaticamente conforme a Jana é usada (agregação em `CustosService::painel`).
- Troca de filtro dispara partial reload server-driven (`kpis`, `por_usuario`, `serie_diaria`, `periodo`, `filters`).

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ Não faz polling nem auto-refresh — só re-busca quando o usuário troca filtro/aplica range.
- ❌ Não muta dados em GET — a rota é read-only (dashboard).
- ❌ Não dispara alerta/notificação de estouro de custo a partir desta tela.
- ❌ **Não renomeia a permissão** no movimento de tela: o gate segue `jana.admin.custos.view`.
  Rename exige ADR + migration de `permissions`/`role_has_permissions` + re-atribuição por business.
- ❌ **Não reintroduz `Inertia::defer`** em `kpis`/`por_usuario`/`serie_diaria` sem o wrap `<Deferred>`
  no frontend — o HOTFIX de 2026-05-25 existe porque defer sem wrap dá tela branca em prod.
- ❌ Não importa nada de `@/Pages/Jana/**`.

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks (pendência herdada — nunca foi ratificada na origem)
- [ ] Smoke visual 1280/1440 (screenshot) na rota nova
- [ ] Ghost `custos` no `DataController` da Governança (senão a tela nasce órfã)
- [ ] Redirect 301 da rota antiga `/ia/admin/custos` ativo
- [ ] Confirmar com Wagner se export CSV/PDF e alerta de custo entram no escopo ou viram Non-Goal firme
