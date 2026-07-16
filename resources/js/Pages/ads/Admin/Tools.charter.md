---
page: /ads/admin/tools
component: resources/js/Pages/ads/Admin/Tools.tsx
related_prototype: n/a (herda PT-01 Lista; catálogo de cards em grid — segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: TeamMcp
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/tools (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/TeamMcp/Http/Controllers/Admin/ToolsController@index` (rota `ads.admin.tools.index`; controller vive em TeamMcp, URL mantida sob `/ads`). Catálogo das tools que agentes podem invocar, com `Inertia::defer` nas 3 props caras.

---

## Mission
Mostrar ao admin todas as tools que os agentes podem invocar, agrupadas por categoria, distinguindo read-only (chamável direto) de escrita (exige aprovação Wagner, HiTL-2), com schema inspecionável e um "Try it" pra executar sob demanda. Além do catálogo, dá o audit log das execuções recentes — a transparência Tier 0 de tudo que foi rodado.

---

## Goals — Features (faz)
- KPIs (defer): total de tools, read-only, escrita, categorias, execuções 7d.
- Tools agrupadas por categoria (defer) em grid de cards; badge Read-only vs Write (HiTL-2).
- Por tool: descrição, schema de input (details), e "Try it" que faz `POST /ads/admin/tools/{name}/execute` com input JSON.
- Confirmação obrigatória (`confirm()`) antes de executar tool de escrita.
- Audit de execuções recentes (defer): ok/erro, tool, read/write, quem disparou, duração, timestamp, erro.
- Skeletons enquanto as props deferidas resolvem; guardas defensivas pra `undefined` no first render.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não cadastra/edita tools pela UI — o registro vive em código (`ToolRegistry` + wrappers Boost).
- ❌ Não remove entradas do audit log (`mcp_tool_executions` é append-only Tier 0).
- ❌ Não pagina o audit — mostra as últimas 20 execuções.
- ❌ Não expõe execuções de outro business sem escopo — escopo/tenant do audit revisar com Wagner. [inferência pendente]

---

## UX targets
- p95 < 1500ms (admin), com defer entregando shell antes das props caras ; cabe em 1280px ; AppShellV2.

---

## Automation hooks (faz)
- Props caras via `Inertia::defer`: `tools_by_category`, `recent_executions`, `kpis` — executam após first paint (partial reload não paga o custo se não pedidas).
- "Executar" chama `POST /ads/admin/tools/{name}/execute`; toda invocação grava no audit log.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Tool de escrita nunca roda sem `confirm()` do humano (HiTL-2).
- ❌ Não executa nada no page load — só por clique em "Executar".
- ❌ Não faz polling do audit log.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar escopo multi-tenant do audit `mcp_tool_executions` (cross-business vs por business)
