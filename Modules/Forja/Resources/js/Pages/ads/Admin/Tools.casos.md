---
id: resources-js-pages-ads-admin-tools-casos
casos: Forja · catálogo de tools dos agentes + audit das execuções · /ads/admin/tools
irmaos: Tools.charter.md (lei) · Tools.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
por_que: o que dura não é o grid de cards — é "toda execução fica registrada" e "dá pra saber, antes de rodar, se a tool escreve". O layout pode mudar; esses dois contratos não.
owner: wagner
last_run: "2026-10-07"
---

# Casos de uso — /ads/admin/tools

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — nunca do `.tsx`:
> - [`Tools.charter.md`](Tools.charter.md) (lei, `status: draft`) — Mission *"distinguindo read-only de escrita"* + *"audit log das execuções recentes — a transparência Tier 0 de tudo que foi rodado"*; Goals (badge Read-only × Write, KPIs); Automation hook *"toda invocação grava no audit log"*;
> - [ADR 0053](../../../../../../../memory/decisions/0053-mcp-server-governanca-como-produto.md) — §Arquitetura técnica item 9 *"`mcp_audit_log` toda chamada"* + Pilar 4 (audit imutável de **toda** chamada) + a tabela de tools com a coluna **"Destrutiva"** (a natureza da tool é declarada no catálogo);
> - [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — o registro carrega o `business_id` de quem executou.
>
> Persona: **[W]**, auditando o que os agentes podem fazer e o que foi feito. Nenhum UC usa biz=4 — o usuário do teste nasce no tenant fictício **98** ([ADR 0358](../../../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

> ⚖️ **Onde estes UC rodam:** [`Modules/Forja/Tests/Feature/ToolsContratoTest.php`](../../../../../Tests/Feature/ToolsContratoTest.php), na lane `PHP / Pest (Forja · MySQL)` ([`forja-pest.yml`](../../../../../../../.github/workflows/forja-pest.yml)) — **advisory**: reprova visível, não bloqueia merge (o context não está nas listas de [`governance/required-checks-baseline.json`](../../../../../../../governance/required-checks-baseline.json)). Todos nascem 🧪: Pest local é proibido ([ADR 0062](../../../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)), o `✅` vem do manifesto derivado do JUnit, nunca à mão (G-7).

> 🔎 **Por que o teste usa tools falsas:** executar uma tool real (Boost, `write_file`, `git_commit_wip`) tocaria banco, arquivo ou git de verdade. O contrato desta tela é o do **catálogo** e do **audit**, não o de cada tool — então o teste registra 3 tools falsas (leitura que dá certo, leitura que lança, escrita) num `ToolRegistry` real vinculado ao container.

## UC-TOOLS-01 — Toda execução grava no audit log, no business da sessão — inclusive a que falha `[must]` `[T0]`
Status: 🧪 (1 teste cita este UC — executa uma tool que dá certo **e** uma que lança, e exige uma linha nova para cada. Auditar só o sucesso esconderia justamente o que se quer investigar depois.)
*Dado* [W] logado num business; *quando* executa uma tool pelo "Executar" (`POST /ads/admin/tools/{name}/execute`); *então* nasce uma linha em `mcp_tool_executions` com o nome da tool, se ela é de leitura, se deu certo, o erro (quando houve) e o **`business_id` da sessão**.
**Pronto quando:** 2 execuções geram exatamente 2 linhas novas; a que deu certo tem `ok = true`; a que lançou tem `ok = false` e erro preenchido; as duas carregam o business do usuário (98), não o default `1` da coluna.

## UC-TOOLS-02 — O catálogo distingue tool de leitura de tool de escrita `[must]`
Status: 🧪 (1 teste cita este UC — com uma tool de cada natureza no catálogo, e conferindo que os KPIs batem com a lista: KPI que não bate com o que a tela mostra é número fabricado.)
*Dado* o catálogo com tools de leitura e de escrita; *quando* a tela carrega as props deferidas (`tools_by_category`, `kpis`); *então* toda tool vem marcada como leitura ou escrita, e os KPIs somam leitura + escrita = total listado.
**Pronto quando:** a tool de leitura vem `is_read_only = true`, a de escrita `false`, nenhuma tool vem sem a marca, e `read_only + write = total = nº de tools listadas`.

## UC-TOOLS-03 — Executar tool exige permissão própria `[must]` `[T0]`
Status: 🧪 (1 teste cita este UC — [`ForjaToolsPermissaoTest.php`](../../../../../Tests/Feature/ForjaToolsPermissaoTest.php), com controle positivo: a MESMA chamada passa e audita quando o usuário tem a permissão.)
Fonte: decisão [W] **D7** (2026-10-07, thread 11 do playbook Forja — *"permissão própria"*).
*Dado* um usuário logado **sem** `forja.tools.execute`; *quando* chama `POST /ads/admin/tools/{name}/execute`; *então* recebe 403 e nenhuma linha nasce em `mcp_tool_executions`.
**Pronto quando:** sem a permissão → 403 e 0 linhas novas; com ela → 200 e 1 linha nova.

## UC-TOOLS-04 — O audit da tela é da empresa da sessão `[must]` `[T0]`
Status: 🧪 (1 teste cita este UC — com âncora positiva: a execução do 98 **aparece**; sem ela, "o 99 não aparece" passaria com lista vazia.)
Fonte: D7 (*"auditoria filtrada por empresa"*) + [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
*Dado* execuções gravadas no business 98 e no 99; *quando* um usuário do 98 abre a tela; *então* `recent_executions` traz a do 98 e não a do 99, e `kpis.executions_7d` conta só o 98.
**Pronto quando:** id da linha 98 presente, id da linha 99 ausente, KPI = contagem do 98 nos últimos 7 dias.

## UC-TOOLS-05 — O autor da execução é o usuário logado `[must]`
Status: 🧪 (1 teste cita este UC.)
Fonte: D7 (*"autor = usuário logado"*) + charter (*"quem disparou"*).
*Quando* um usuário com a permissão executa uma tool; *então* `triggered_by` é o `username` dele, nunca `'wagner'` fixo.
**Pronto quando:** `triggered_by` = username do logado e `business_id` = o dele.

---

## [BACKLOG] — declarado no charter, ainda sem teste que o defenda

Prosa honesta: nenhum item abaixo tem teste citando um UC. Vira UC quando ganhar teste — não antes ([`how-trabalhar.md`](../../../../../../../memory/how-trabalhar.md) §Pedido de tela/feature).

- ~~[BACKLOG] **`[T0]` Escopo do audit exibido**~~ → virou **UC-TOOLS-04** (D7, 2026-10-07: por empresa).
- ~~[BACKLOG] **Quem pode executar tool de escrita.**~~ → virou **UC-TOOLS-03** (D7: permissão `forja.tools.execute`, vale para leitura e escrita).
- ~~[BACKLOG] `triggered_by` fixo `'wagner'`~~ → virou **UC-TOOLS-05** (D7: username do logado).
- [BACKLOG] Abrir a tela não executa nada (Anti-hook do charter) — sem teste que o exercite; contrato numa fonte só.
- [BACKLOG] Tool de escrita pede `confirm()` antes do POST — comportamento só de front, sem E2E.
- [BACKLOG] O audit mostra as últimas 20 execuções, sem paginação, e não tem rota de remoção (Non-Goals do charter) — sem teste que o cite.
