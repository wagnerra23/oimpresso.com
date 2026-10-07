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

---

## [BACKLOG] — declarado no charter, ainda sem teste que o defenda

Prosa honesta: nenhum item abaixo tem teste citando um UC. Vira UC quando ganhar teste — não antes ([`how-trabalhar.md`](../../../../../../../memory/how-trabalhar.md) §Pedido de tela/feature).

- [BACKLOG] **`[T0]` Escopo do audit exibido** — o charter marca como *"[inferência pendente]"* e deixa nas §Pendências *"confirmar escopo multi-tenant do audit `mcp_tool_executions` (cross-business vs por business)"*. Hoje a lista de execuções recentes e o KPI `executions_7d` **não** filtram por `business_id` (a escrita grava o business; a leitura não o usa). Se a tela deve ser por business ou repo-wide é decisão [W] — e o [`ADS/SPEC.md`](../../../../../../../memory/requisitos/ADS/SPEC.md) US-ADS-001 já pede exatamente essa classificação para os `DB::table('mcp_*')` crus.
- [BACKLOG] **Quem pode executar tool de escrita.** O charter diz *"exige aprovação Wagner, HiTL-2"* e a ADR 0053 Pilar 3 prevê perfis sem destrutivas (*"Luiz: read-only, sem destrutivas"*), mas a rota `POST /ads/admin/tools/{name}/execute` só exige `auth` — a aprovação existe apenas como `confirm()` no front. Se falta trava no servidor é decisão [W].
- [BACKLOG] `triggered_by` do audit é a string fixa `'wagner'`, não o usuário que executou — o charter promete *"quem disparou"* no audit. Sem fonte que fixe o formato do campo, fica como pergunta.
- [BACKLOG] Abrir a tela não executa nada (Anti-hook do charter) — sem teste que o exercite; contrato numa fonte só.
- [BACKLOG] Tool de escrita pede `confirm()` antes do POST — comportamento só de front, sem E2E.
- [BACKLOG] O audit mostra as últimas 20 execuções, sem paginação, e não tem rota de remoção (Non-Goals do charter) — sem teste que o cite.
