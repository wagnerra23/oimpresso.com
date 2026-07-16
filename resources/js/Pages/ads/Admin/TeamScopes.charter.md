---
page: /ads/admin/team-scopes
component: resources/js/Pages/ads/Admin/TeamScopes.tsx
related_prototype: n/a (herda PT-01 Lista; matriz de permissões em tabela — segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: TeamMcp
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/team-scopes (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/TeamMcp/Http/Controllers/Admin/TeamScopesController@index` (rota `ads.admin.teamscopes.index`; controller em TeamMcp, URL sob `/ads`). Wagner define quem pode tocar quais módulos — camada extra acima do PolicyEngine. Props via `Inertia::defer`.

---

## Mission
Dar ao Wagner o controle granular de qual dev pode ler/escrever/executar-tools/commitar em cada módulo. É a fonte de verdade que o `WriteFileTool`/`UserScopeService::canWriteToPath()` consulta no servidor ANTES de deixar um dev junior (via token MCP dele) escrever ou commitar — sem entrada aqui, DENY default. Regra do servidor vence a regra local do editor.

---

## Goals — Features (faz)
- KPIs: devs do business, quantos têm acesso ativo, total de grants (pares user × module).
- Sidebar de usuários do business (defer) + matriz de permissões do user selecionado (defer).
- Tabela por módulo com 4 switches: read, write, execute tools, commit.
- `grant` → `POST /ads/admin/team-scopes/grant`; `revoke` (com `confirm()`) → `POST .../revoke`.
- Painel explicativo do fluxo servidor > local e do endpoint `GET /api/ads/scope/check` que o Claude Code consulta.
- Skeletons enquanto defer resolve; guardas defensivas pra `undefined`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não substitui as 3 camadas canônicas de habilitação por business (pacotes superadmin / recursos do negócio / permissions Spatie) — é camada extra de escopo de escrita por dev.
- ❌ Não gerencia usuários (criar/editar/remover) — só concede/revoga acesso a módulos.
- ❌ Não mostra devs de outro business — lista é scopada por `business_id` da sessão. [Tier 0 multi-tenant]
- ❌ Não aplica escopo no editor local do dev — só barra commit/write no servidor.

---

## UX targets
- p95 < 1500ms (admin), defer entregando shell antes da matriz cara ; cabe em 1280px ; AppShellV2.

---

## Automation hooks (faz)
- `users` via `UserScopeService::listUsersWithAccess($businessId)` (JOIN + agregação por módulo); `modules` via scan filesystem de `Modules/` — ambos deferidos.
- Toggle de switch chama `grant` imediatamente (preserva scroll, recarrega estado).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Revogar acesso exige `confirm()` — não dispara sozinho.
- ❌ Não concede acesso por default — ausência de grant = DENY no servidor.
- ❌ Não vaza grants entre businesses — sempre resolve `business_id` da sessão.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar que `grant`/`revoke` validam `business_id` do user-alvo (não só da sessão)
