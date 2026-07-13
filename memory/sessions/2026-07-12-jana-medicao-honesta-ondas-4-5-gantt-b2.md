# Sessão 2026-07-12 — Medição honesta da Jana (Ondas 4-5) + Roadmap Gantt drag-drop (B2)

**TL;DR:** Wagner pediu pra medir o IA-OS/Jana ("quero medir", "meu foco é máquina"). Verificação-de-máquina (docker/ClickHouse/git, não doc) provou que o SPEC **subcontava** a maturidade da Jana (dizia 91%, real ~97%). Reconciliei 5 US com evidência, fechei o gap Tier 0 do Langfuse (tag business_id) e construí+verifiquei em prod o único gap real que restava: **drag-drop reschedule no Roadmap Gantt (B2)**. 6 PRs mergeados. `Jana` subiu 71→73 no Module Grades.

## Contexto de entrada

Sessão começou com Wagner sondando o "IA-OS" (camada C de governança, [ADR 0334](../decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)) e querendo **medir**. Meu checkout estava −5074 commits do main (stale) — todo trabalho foi feito em worktrees frescos off `origin/main`.

## O que aconteceu (arco)

1. **Correção de premissa (drift sentinel + Langfuse):** o loop-fechar-check e o SPEC diziam que "drift sentinel" e "Langfuse" estavam pendentes. Verifiquei na máquina: `jana-ragas-canary.yml` roda **diário verde** e o Langfuse está **vivo no CT 100** (6 containers up 2 semanas, 3.226 traces via ClickHouse, agents instrumentados). O doc é que estava atrás. → **PR #4133** (fecha drift US-COPI-117, abre US-132, reconcilia 2 âncoras mortas `.ps1`→`.mjs`).

2. **Reconciliação Ondas 4-5 (PR #4144):** verifiquei por máquina cada US. **6 de 7 done** — US-COPI-108 (Langfuse) parcial→done, US-COPI-110 (time-decay K1, método `applyTimeDecay()` wired no MeilisearchDriver) todo→done, US-COPI-109 (charters, meta "Tier A" **superseded pela [ADR 0225](../decisions/0225-skills-tier-a-recalibracao-claude-4.8.md)**) →done. Maturidade real ~97%, doc dizia 91%.

3. **US-COPI-132 — tag business_id no Langfuse (PR #4145):** gap Tier 0 achado na verificação — traces tinham só tag `['live']`, business_id só no metadata. Fix em `LangfuseClient::traceEvent()` + 2 Pest.

4. **US-COPI-111 Gantt — o único gap real (PRs #4147, #4159, #4186):**
   - Pré-flight (charter, R7) pegou que o US-111 apontava pra **página errada** (`ProjectMgmt/Roadmap`) e que o Gantt SVAR de LEITURA já estava construído em `Jana/Admin/Roadmap.tsx` (595 linhas), roteado e no menu.
   - Wagner escolheu **B2** (construir o drag-drop). Decisão de domínio: só o **prazo** (`due_date`) é arrastável — `started_at` é lifecycle-managed. Reusei o `TaskCrudService` canônico (mesma via do MCP tasks-update).
   - **Gate visual R7/R1:** eu NÃO consegui dirigir o drag via automação (viewport 696×333 CSS, chart de 220px, barras off-screen). Wagner arrastou em prod → **evidência objetiva: "Listar Budget" Duration 6→8** (due_date estendido, servido pelo backend). Round-trip testemunhado. US-111 →done (PR #4186).

## Artefatos gerados

- 6 PRs mergeados: #4133, #4144, #4145, #4147, #4159, #4186
- Feature nova: endpoint `RoadmapController@updateSchedule` (PATCH gated `jana.mcp.tasks.write`) + drag wiring em `Roadmap.tsx` + 3 Pest em `RoadmapControllerTest.php`
- Charter `Jana/Admin/Roadmap.charter.md` amendado (drag-drop reschedule vira Goal, override Wagner)

## Lições catalogadas (perenes)

1. **Doc mente por baixo:** o SPEC subcontava maturidade porque 2 US prontas (Langfuse, time-decay) estavam marcadas incompletas. Verificação-de-máquina > confiar no status: do doc.
2. **Gate visual R1 quase pegou falso-done:** automação de browser NÃO consegue dirigir o drag do SVAR Gantt (viewport pequeno + chart 220px + barras off-screen). Só o drag real do humano + delta objetivo (Duration 6→8) fecha. Nunca declarar "funciona" sem ver funcionar.
3. **Armadilhas do anchor-lint (reincidi 3×):** (a) a palavra "**mé-todo**" contém o substring "todo" → dispara `PLACEHOLDER_RE`; (b) qualquer token em backtick com "/" (URL, nome-de-página `Jana/Admin/Roadmap`, fórmula `age/half_life`) vira **path morto** (`anchored_dead`). Regra: no anchor, só o path REAL do arquivo em backtick; resto em prosa.
4. **Pré-flight (charter) tem ROI alto:** pegou US-111 apontando pra página errada + o Gantt já construído — evitou eu reconstruir do zero.
5. **biz=4 em teste** → guarda Tier 0 `BusinessIdGuardTest` (ADR 0101). Teste sempre biz=1.

## Pointers detalhados

- Handoff: [2026-07-12-2245-jana-medicao-honesta-gantt-b2.md](../handoffs/2026-07-12-2245-jana-medicao-honesta-gantt-b2.md)
- Régua do IA-OS enfileirada como chip (`task_d3584787`, skill `reguas-do-sistema`) — rodando em sessão separada
