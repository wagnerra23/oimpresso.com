# US-MCP-017 — Tool MCP `module-state <modulo>` (CQRS projection per bounded context)

> **Convenção do ID:** `US-MCP-NNN` — convenção herdada de [Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md](../Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) (US-MCP-001..016 já tomadas).
> **Origem:** [G3 do dossier 2026-05-15 — estado-da-arte memória Claude Code](../../sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md) §6 + §8.
> **Aprovação Wagner:** 2026-05-15 17h após audit memória — 87/100 → 97/100 com Fase 4.
> **Estimate:** recalibrado por [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — fator 10x IA-pair + margem 2x.

## 1. Premissas

- **Time MCP entrante (4 devs)** — Felipe (Sells/Comissao), Maiara (CRM/Comunicação), Eliana (Financeiro/PontoWr2), Luiz (Mwart/Repair). Cada um vai pegar bounded context novo nas próximas 2-4 semanas. Sem `module-state`, onboarding por módulo = ler 50+ session logs cronologicamente.
- **Cada módulo é bounded context DDD** ([memory/requisitos/<Mod>/](../) — 37 SPECs já existem hoje). Cada bounded context tem ubiquitous language local + SPEC + RUNBOOK + CAPTERRA-FICHA opcional.
- **Handoffs per-session = event stream** (write-side imutável, [ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md)). Esta tool **não duplica storage** — apenas projeta read-side derivada on-demand.
- **CQRS / Event Sourcing applied** (§2.7 dossier audit memória) — session log é event stream; `module-state` é projection denormalizada read-optimized.
- **Tier 0 multi-tenant preservado** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — output do tool não cruza `business_id` entre tenants quando módulo tem dados scoped.

## 2. Caso de uso primário

Felipe entra dia 1 no time MCP. É designado pra Sells. Em vez de ler ~12 session logs do mês passado + abrir SPEC.md gigante + procurar PRs no `git log`, chama:

```
module-state Sells
```

Recebe em <2s um relatório consolidado de ~600-800 tokens:

- Cycle ativo com goals do módulo
- Tasks ativas dele (doing/review/blocked) — top 8
- ADRs top-5 que tocam Sells (semantic match)
- 3 últimos handoffs que mencionaram Sells (data + 1 linha)
- 5 últimos PRs mergeados tocando `Modules/Sells/`
- Charter status: tem? live? draft? quantas Pages cobertas?
- RUNBOOK ativo (qual + última edição)
- SPEC summary: N US total · N done · N todo · N blocked
- CAPTERRA: ✅ aprovado / 🟡 parcial / ❌ ausente (counts) se ficha existe
- Drift detection: charter morto >60d? RUNBOOK desatualizado vs último PR? US stale doing >7d sem commit?

Em ~1min Felipe sabe o que está vivo, onde dói, e qual a próxima ação razoável — sem leitura cronológica.

## 3. Não-objetivos (fora de escopo)

- **NÃO substituir `brief-fetch`** — brief é global (cycle + HITL + decisões 24h cross-módulo); `module-state` é local (1 bounded context).
- **NÃO criar nova storage de "estado por módulo"** — projeção é derivada on-demand do event stream + tools MCP existentes. Cache opcional (TTL 5min).
- **NÃO chamar LLM por default** — princípio 2 Constituição v2 (tiered cost). Todas agregações são SQL+FS+git+tools MCP existentes. Síntese é rule-based markdown. Em fase 2 (backlog) pode ganhar `--narrative=true` opcional via gpt-4o-mini, mas default = R$ 0/call.
- **NÃO mutação** — read-only. Para mutação use `tasks-create`, `tasks-update`, `tasks-comment` existentes.
- **NÃO descobrir módulos automaticamente** via filesystem scan — lista canônica deriva de `memory/requisitos/<Mod>/SPEC.md` glob (single source of truth — `git ls-tree`).

## 4. Aceitação (DoD)

- [ ] Tool `ModuleStateTool` registrada em `OimpressoMcpServer::$tools` com schema input/output
- [ ] Input: `{module: string}` (case-sensitive — match com pasta `memory/requisitos/<Mod>/`)
- [ ] Output markdown estruturado ~600-800 tokens (campos definidos §5)
- [ ] Cobertura ≥30 módulos top-level conhecidos do oimpresso (cross-check com `memory/requisitos/*/SPEC.md` glob — hoje 37 SPECs)
- [ ] Validação input: módulo inexistente → erro útil ("Modulo X não encontrado. Disponíveis: A, B, C...")
- [ ] Pest 5+ tests: smoke happy path + Tier 0 multi-tenant + module not found + cache hit + drift detection
- [ ] Performance <2s P95 (cache 5min como brief-fetch — ADR 0091)
- [ ] Multi-tenant: quando módulo tem dados scoped por `business_id` (ex: Whatsapp, Sells), filtrar tasks/sessões por business do user que chamou. Cross-tenant explicit denied no schema (sem `business_id` parameter — herda do `Request::user()`).
- [ ] Docs README na pasta atualizado + entrada nova em `OimpressoMcpServer.php` comment block
- [ ] Cache table `mcp_module_state_cache` migrada com TTL (similar `mcp_handoff_diffs`)
- [ ] Smoke real biz=1 (Larissa ROTA LIVRE) com módulo `Whatsapp` (32 tasks ativas em maio/2026) + módulo `Sells` (denso de PRs)

## 5. Shape do response (markdown estruturado)

```markdown
# module-state · Sells

**Bounded context:** Modules/Sells/ · multi-tenant: yes
**Última atualização:** 2026-05-15 17:42 BRT · cache_hit: false · gerado_em: 1.3s

## Cycle ativo
- **CYCLE-06** [doing] — Pivot Martinho-FSM · goal: 80% sells via FSM até 2026-05-22
- Goals tocando Sells: 3/5 · achievement: 65%

## Tasks ativas (8 de 14)
- **US-SELL-011** [doing] [p0] (felipe) — FSM tabelas + state machine pivot
- **US-SELL-008** [review] [p1] (wagner) — Auto-commission rule engine
- **US-SELL-005** [blocked] [p1] (felipe) — Integração Pix automático
  ⛔ bloqueada por: US-INFRA-001 GrowthBook
- ...

## ADRs aplicáveis (top 5 semantic match)
- ADR 0129 — Sells FSM tabelas + state machine
- ADR 0093 — Multi-tenant isolation Tier 0 (Sells é scoped por business_id)
- ADR 0070 — Jira-style task management
- ADR 0104 — MWART canon process (Sells/Index.tsx tem charter)
- ADR 0114 — Cowork loop formalizado (Sells protótipos)

## Handoffs recentes que mencionaram Sells (3)
- 2026-05-14 22:30 — US-SELL-011 FSM tabelas (Felipe)
- 2026-05-13 18:00 — Onda 5 consolidação (Wagner)
- 2026-05-10 22:30 — Pivot cycle 05→06 FSM (Wagner)

## PRs mergeados tocando Modules/Sells/ (últimos 5, 30d)
- #845 feat(financeiro): F3 Boletos refator (toca Sells/Comissao) — wagner 2026-05-13
- #820 fix(sells): cache pos_settings invalidation — wagner 2026-05-10
- #815 feat(sells): FSM stub + ADR 0129 — wagner 2026-05-09
- ...

## Charter
- 2/4 Pages com charter: `Index.tsx` (live) + `Create.tsx` (draft)
- Pages sem charter: `Edit.tsx`, `Reports.tsx`

## RUNBOOK
- `Modules/Sells/RUNBOOK-pos-fsm.md` — última edição 2026-05-11 (4d atrás)

## SPEC
- Total: 24 US · done: 12 · review: 2 · doing: 3 · blocked: 1 · todo: 6
- Última US adicionada: US-SELL-024 (2026-05-13)

## CAPTERRA inventário (capacidade vs mercado)
- ✅ APROVADO: 8 features baseline (POS, ticket, devolução, ...)
- 🟡 PARCIAL: 4 features (auto-comissão WIP, integração Pix WIP)
- ❌ AUSENTE: 3 features (multi-loja matriz/filial, e-commerce export)

## Drift detection
- ⚠️ US-SELL-005 `doing` há 9 dias sem commit (stale_doing >7d)
- ⚠️ Charter `Edit.tsx` ausente apesar de ser página em prod
- ✅ RUNBOOK fresco (<7d desde último PR tocando módulo)
```

Schema TypeScript-like:

```ts
type ModuleStateResponse = {
  module: string;                           // input echoed
  bounded_context_path: string;             // "Modules/Sells/"
  multi_tenant: boolean;
  generated_at: string;                     // ISO
  cache_hit: boolean;
  duration_ms: number;
  cycle_ativo: { key: string; status: string; goal: string; goals_tocando_modulo: number; } | null;
  tasks_ativas: Array<{ task_id: string; status: string; priority: string; owner: string|null; title: string; blocked_by?: string[]; }>;
  adrs_aplicaveis: Array<{ slug: string; title: string; }>;        // top 5 semantic
  handoffs_recentes: Array<{ date: string; slug: string; sumario_1line: string; }>;  // 3
  prs_recentes: Array<{ number: number; title: string; author: string; date: string; }>;  // 5
  charter: { total_pages: number; live: number; draft: number; sem_charter: string[]; };
  runbooks_ativos: Array<{ path: string; last_edit: string; idade_dias: number; }>;
  spec_summary: { total: number; done: number; review: number; doing: number; blocked: number; todo: number; ultima_us_adicionada?: string; };
  capterra: { aprovado: number; parcial: number; ausente: number; } | null;
  drift: Array<{ tipo: string; severidade: 'info'|'warn'|'alert'; mensagem: string; }>;
};
```

## 6. Tarefas técnicas (alto nível — detalhe no RUNBOOK)

1. Criar `Modules/Jana/Mcp/Tools/ModuleStateTool.php` (NÃO neste PR — esta US gera **apenas** o SPEC + RUNBOOK)
2. Implementar 9 coletores internos privados (cada um best-effort, falha gracefully):
   - `coletarCycle($module)` — query `mcp_cycles` join goals
   - `coletarTasksAtivas($module, $businessId)` — query `mcp_tasks` filtro module + scope tenant
   - `coletarAdrsAplicaveis($module)` — chama `DecisionsSearchTool` internamente com query = nome módulo (semantic match top 5)
   - `coletarHandoffsRecentes($module)` — `Glob memory/handoffs/*.md` + grep nome módulo (top 3 por data)
   - `coletarPrsRecentes($module)` — `gh pr list --search "path:Modules/<X>/ merged:>=<30d>"`
   - `coletarCharter($module)` — `Glob Modules/<X>/**/*.charter.md` + parse frontmatter `status: live|draft`
   - `coletarRunbooks($module)` — `Glob memory/requisitos/<X>/RUNBOOK*.md` + stat mtime
   - `coletarSpecSummary($module)` — read `memory/requisitos/<X>/SPEC.md` + regex count US por status (OU query `mcp_tasks` com `module:<X>`)
   - `coletarCapterra($module)` — read `memory/requisitos/<X>/CAPTERRA-INVENTARIO.md` (se existe) + parse buckets
   - `detectarDrift($module, $signals)` — regras: doing >7d sem commit, charter morto >60d, RUNBOOK >60d, US blocked >30d
3. Cache layer (5min TTL) na tabela `mcp_module_state_cache` (migration nova)
4. Registrar em `OimpressoMcpServer::$tools` (Page 2 knowledge cluster — última)
5. Pest 5 tests:
   - Smoke happy path (Whatsapp, biz=1)
   - Tier 0 multi-tenant (biz=4 não vê tasks scoped do biz=1 em módulo Sells)
   - Module not found (returns helpful error + sugestões)
   - Cache hit (segunda chamada <100ms)
   - Drift detection (mock US doing >7d → output flagga warn)
6. Documentar em README do pacote MCP + comentário inline OimpressoMcpServer
7. Smoke real biz=1 com Whatsapp (32 tasks) + Sells (denso PRs) — validação manual Wagner

## 7. Estimate

- **Calibrado [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) fator 10x IA-pair:** ~8h codáveis (9 coletores × 30-40min cada + cache + register + Pest)
- **Margem 2x (princípio Constituição v2 confiabilidade com fallback):** 12-16h total
- **Humano-limitado (não acelera):** smoke real biz=1 Wagner valida = 30min relógio mundo
- **Total efetivo:** ~14h IA-pair = ~1.75 dev-day (PR ≤300 linhas + 1 migration + Pest)

Quebra detalhada:
| Fase | Esforço IA-pair |
|---|---:|
| Skeleton ModuleStateTool + schema | 0.5h |
| 9 coletores internos | 4-5h |
| Cache layer + migration | 1h |
| Register em OimpressoMcpServer | 0.2h |
| Pest 5 tests | 2h |
| Drift detection rules | 1.5h |
| Docs README + comentário inline | 0.5h |
| Smoke real biz=1 | 1h |
| Refactor pós-review | 1h |

## 8. Pré-requisitos

- ✅ `brief-fetch` tool existente — referência de pattern response shape ([Modules/Brief/Mcp/Tools/BriefFetchTool.php](../../../../Modules/Brief/Mcp/Tools/BriefFetchTool.php))
- ✅ `tasks-list` com `module:` parameter implementado ([Modules/Jana/Mcp/Tools/TasksListTool.php](../../../../Modules/Jana/Mcp/Tools/TasksListTool.php))
- ✅ `decisions-search` semantic match funcional (FULLTEXT MySQL)
- ✅ `HandoffDiffTool` como pattern multi-fonte (gh + git log + DB) — proven 2026-05-13
- ✅ Multi-tenant isolation ADR 0093 + global scope nos Models existentes
- ✅ Pasta `memory/handoffs/` populada (ADR 0130 vigente desde 2026-05-10)

## 9. Anti-padrões a evitar

1. **Duplicar storage de event stream** — handoffs/sessions ficam append-only canônicos, esta tool NUNCA escreve neles. Read-side apenas.
2. **Cache TTL muito longo (>30min)** — info muda em deploy/incident; 5min é o sweet spot (igual brief-fetch).
3. **Sem cobertura Tier 0 multi-tenant** — módulos com biz=1 (oimpresso) vs biz=4 (ROTA LIVRE) não podem vazar tasks/handoffs scoped. Schema NÃO aceita `business_id` parameter (sempre herda do `Request::user()`).
4. **Glob em filesystem direto sem fallback** — se pasta `memory/requisitos/<X>/` não existe, retornar erro útil ("módulo X não encontrado, disponíveis: ..."), não 500.
5. **Coletor síncrono blocking** — `gh pr list` pode demorar 10s+; usar timeout 5s + fallback empty array (pattern HandoffDiffTool linha 188).
6. **Sintetizar via LLM por default** — princípio 2 Constituição v2 (tiered cost). Markdown rule-based primeiro. LLM opcional fase 2 (`--narrative=true`, custo ~R$ 0.005).
7. **Hardcode lista de módulos** — derivar de `Glob memory/requisitos/*/SPEC.md` (single source of truth).
8. **Output gigante (>2000 tokens)** — top-N (8 tasks, 5 ADRs, 3 handoffs, 5 PRs) com truncamento. Se user quer mais, chama tools específicas.

## 10. Áreas cinzentas (parent precisa confirmar com Wagner antes de implementar)

1. **Multi-tenant edge: módulos cross-tenant** (`Jana`, `Infra`, `Mcp` próprio) — Jana é projeto inteiro, Infra é repo-wide. Decisão recomendada: tools sem business_id scoping para esses módulos (retorna tudo); módulos com scope retornam só business do user.
2. **Lista canônica de módulos** — usar `Glob memory/requisitos/*/SPEC.md` (37 hoje) ou hardcoded array? Recomendado: glob (zero manutenção quando módulo novo nasce).
3. **Cache invalidação** — TTL 5min é seguro? Webhook GitHub poderia invalidar cache ao push em `Modules/<X>/`? Recomendado: TTL 5min puro (paridade brief-fetch); webhook invalidation é P3 backlog.
4. **Drift rules thresholds** — `doing >7d sem commit` é igual `tasks-health`; `charter >60d` é arbitrário. Recomendado: usar mesmos thresholds de `TasksHealthTool` quando aplicável; charter/RUNBOOK 60d como default ajustável via config.
5. **Granularidade módulo** — `Modules/Sells` vs `Modules/Sells/Compras` (sub-módulos drift). Recomendado: top-level apenas; sub-módulos só se houver pasta `memory/requisitos/Sells/Compras/SPEC.md`.

## 11. Refs

- [Dossier 2026-05-15 — Arte memória Claude Code](../../sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md) §6 + §8 (origem)
- [ADR 0130 — Handoff append-only + MCP-first](../../decisions/0130-handoff-append-only-mcp-first.md) (event stream canônico)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md) (isolation by default)
- [ADR 0091 — Daily Brief brief-fetch](../../decisions/0091-daily-brief.md) (pattern cache + tool MCP)
- [ADR 0094 — Constituição v2 §princípio 2 tiered cost](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0106 — Recalibração velocidade fator 10x IA-pair](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) (estimate convention)
- [Modules/Jana/Mcp/Tools/HandoffDiffTool.php](../../../Modules/Jana/Mcp/Tools/HandoffDiffTool.php) (pattern multi-fonte gh+git+DB best-effort)
- [Modules/Jana/Mcp/Tools/TasksListTool.php](../../../Modules/Jana/Mcp/Tools/TasksListTool.php) (filtro module:)
- [Modules/Jana/Mcp/Tools/DecisionsSearchTool.php](../../../Modules/Jana/Mcp/Tools/DecisionsSearchTool.php) (semantic match top-N)
- [RUNBOOK detalhado](runbooks/RUNBOOK-module-state-tool.md) — passo-a-passo implementação

## 12. Lifecycle

- **Status inicial:** `todo` · priority: `p1` · owner: `wagner` · sprint: pós-time-MCP-entrar
- **Gate de ativação:** ver dossier §10 Q3 — esperar 2 semanas pós-time MCP entrar; se 3+ vezes Felipe/Maiara/Eliana/Luiz perguntar "qual estado do módulo X" sem brief responder, implementar ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado).
- **Done quando:** ✅ Pest 5/5 passa + smoke biz=1 Whatsapp+Sells OK + Wagner valida output em sessão real + tool registrada em `OimpressoMcpServer` em produção.
