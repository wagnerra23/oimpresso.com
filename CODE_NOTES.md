# CODE_NOTES — retorno [CL]→[W]/[CC] (canal Code→Cowork)

> Cowork é read-only no git. Este arquivo é o retorno do Code sobre handoffs processados.

---

## [PROCESSADO 2026-06-17] Forja › aba MCP — superfície do handoff (Fase 1 · ADR 0283)

**Handoff:** `prototipo-ui-patch/PROMPT_PARA_CODE_FORJA-HANDOFF-SURFACE.md`
**Branch:** `feat/forja-mcp-handoff-surface` (worktree off `origin/main` @ 70cb5b195 — §10.4 validado)
**Premissa confirmada @main:** Fase 0 mergeada (#2904/2905/2906/2908) — `cowork_handoffs`,
`HandoffPendingTool`/`HandoffAckTool`, `HandoffStaleAlertCommand`, `McpIngestHeartbeat` existem.
Não recriado nada da Fase 0.

### Entregue (PR-A / PR-B / PR-C)

- **PR-A · backend** — `ForjaController@mcp` deixou de ser `renderTab('mcp')` (mock puro) e passou a
  projetar dado REAL via `Inertia::defer` (`handoffs` + `heartbeat`), espelhando `triagem()`/`quadro()`.
  - **Desvio do literal "1 controller method":** a projeção foi pra um **`Services/Forja/ForjaMcpService`**
    novo, porque é o padrão CANÔNICO do controller (backlog/quadro/changelog já delegam a `*Service`;
    §10.4 "main vence"). Isso deixou a lógica **unit-testável** sem HTTP/auth. O método `mcp()` ficou
    fininho. Removi o helper `renderTab()` (ficou órfão — evita dead-code/larastan).
  - **Status REAIS** (não o vocabulário do protótipo): `pending/applied/rejected/stale/superseded`.
  - **`stale` derivado na LEITURA** (`pending` + idade > 3d) — robusto, não espera o cron.
  - **Gate** derivado do `gate_status` com a **MESMA regra verde do `HandoffAckTool`**
    (`conformance && critique_score>=80 && a11y`): `verde/vermelho/rodando(applied sem gate)/na`.
    Nunca pinta verde sem ler.
  - Mais recente por slug (maior `version`), **excluindo `superseded`**, limit 200. Tier 0: repo-wide
    (sem `business_id`), com o marker que o `NoMissingTenantScopeRule` exige.
- **PR-B · frontend** — `ForjaMcp.tsx` ganhou a seção `data-testid="forja-mcp-handoffs"` no TOPO
  (acima de contrato/tokens/auditoria, que seguem MOCKADO). `Deferred` envolve **só** a seção nova →
  o contrato estático continua pintando na hora (sem regressão de 1º paint). Tem: título, filtros por
  status com contagem (`todos/pendente/aplicado/rejeitado/parado`), item com slug `vN` · tela · resumo
  (1ª linha do `body_md`) · ⚿ sig · `N arq` · gate (dot colorido, drill pro PR) · `PR ↗` · idade · autor,
  e **empty-state = heartbeat** ("transporte sem sinal" vira alerta vs "ocioso"). DS v6: só tokens
  semânticos, `tabular-nums`, `inline-flex/grid`, `data-testid`.
- **PR-C · contrato** — 2 linhas no array `TOOLS`: `handoff-pending` (PERMITIDO · assinado) e
  `handoff-ack` (PROPÕE · 422 sem gate verde).
- **Test** — `Modules/TeamMcp/Tests/Feature/ForjaMcpServiceTest.php` (13 casos, tabela sintética como
  `HandoffToolsTest`): exclusão de superseded, maior-version-por-slug, derivação de stale, as 4 saídas
  do gate, serialização (files_count/signed/resumo) e heartbeat (silent/recente/teto).

### Decisões / TODOs (honestidade)

- **Levers (re-disparar / devolver ao [CC] / supersede)** — renderizadas mas `disabled` com tooltip
  "Roteia via tool MCP — Fase 2 (ADR 0283)". **NÃO simulam sucesso** e **SEM botão de merge** (Tier 0:
  o merge é o 1-clique do [W]). Optei por `disabled`+TODO em vez de criar endpoints HTTP/rota stub pra
  manter o PR cirúrgico (não inventei `POST` que não existe). **Fase 2** = wire das levers às tools MCP.
- **Conflito gate×CI real (A3 do adversário)** — deixado como TODO consciente: o gate é lido do
  `gate_status` (verdade do ack), sem cruzar com os required checks do PR via GitHub API. Não inventei
  "verde". Follow-up se quiser fechar 100%.
- **Sinal no Quadro (contador pending na aresta F1→F3)** — NÃO feito aqui (toca `ForjaQuadroService` +
  `ForjaQuadro.tsx`, fora do escopo cirúrgico). Vira task (o handoff permitia "senão vira task").
- **Charter** (`Cockpit.charter.md`) — a bullet do MCP ainda diz "MOCKADO por design"; continua certo
  pra contrato/tokens/auditoria. Atualizar pra citar a seção Handoffs REAL é follow-up (evitei mexer em
  design-memory neste PR).
- **Verificação visual** — não rodei preview local: o app é Laravel/Inertia (sem PHP local) e a seção
  depende de dado real de `cowork_handoffs`. Validação visual fica pro smoke pós-merge contra prod
  (skill `tela-smoke-pos-merge`, rota `/forja/mcp`) + os gates de CI (conformance/foundation/pageheader/
  a11y/visual-regression).

### Arquivos
- `Modules/TeamMcp/Services/Forja/ForjaMcpService.php` (novo)
- `Modules/TeamMcp/Http/Controllers/ForjaController.php` (mcp() + import; removido renderTab órfão)
- `resources/js/Pages/team-mcp/Forja/_components/ForjaMcp.tsx` (seção Handoffs + props + PR-C)
- `resources/js/Pages/team-mcp/Forja/Cockpit.tsx` (props deferidas + repasse)
- `Modules/TeamMcp/Tests/Feature/ForjaMcpServiceTest.php` (novo)
