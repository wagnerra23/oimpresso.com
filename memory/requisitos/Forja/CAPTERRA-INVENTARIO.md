---
id: requisitos-project-mgmt-capterra-inventario
title: "CAPTERRA-INVENTARIO — Forja"
slug: capterra-inventario-projectmgmt
type: inventario
status: aceito
generated_at: 2026-08-04
generated_by: reauditoria-2026-08-04
source_ficha: CAPTERRA-FICHA.md
source_spec: SPEC.md
---

<!-- CONSOLIDAÇÃO 2026-08-04: o conteúdo abaixo veio de `INVENTARIO.md` (irmão órfão do mesmo módulo).
     Medido: `CAPTERRA-INVENTARIO.md` existe em 11 módulos e é citado 10x pela skill `comparativo-do-modulo`
     e pelo comando /comparativo; `INVENTARIO.md` existia em 1 (só a Forja) e não era citado por máquina alguma.
     A reauditoria de 2026-08-04 foi escrita no órfão por engano e movida pra cá. -->

# CAPTERRA-INVENTÁRIO — Forja

> **Reauditado 2026-08-04.** A redação anterior era de **2026-05-08** e estava ~3 meses stale: **9 das 24 capacidades subiram de bucket sem que ninguém reauditasse** — todas por trabalho que **já tinha shipado** entre maio e agosto, nenhuma por trabalho feito nesta reauditoria.
> Fontes: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (24 capacidades) + `Modules/Forja/` + `Modules/Forja/Resources/js/Pages/Forja/`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md). ADR mãe redesign: [0100](../../decisions/0100-projectmgmt-ui-redesign.md).
> Detalhe por US vive no [SPEC.md](SPEC.md) — este doc **não** recopia critério de aceite.

## Como reproduzir esta auditoria

Tudo abaixo foi medido em **2026-08-04**, num worktree fresco de `origin/main` (0 commits atrás). Cada número na tabela carrega o comando que o produziu — se a data incomodar, **re-rode o comando; não edite o número** ([proibicoes §5 2026-07-17](../../proibicoes.md)).

| Pergunta | Porta viva (o dono da resposta) | Resultado em 2026-08-04 |
|---|---|---|
| Quais rotas o módulo expõe? | `Read Modules/Forja/Http/routes.php` | 6 grupos: `/project-mgmt`, `/project-mgmt/install`, `/api/mcp`, `/api/cc`, `/team-mcp`, `/forja`, `/ads/admin` |
| Quantos testes Pest? | `Glob Modules/Forja/Tests/Feature/*.php` | **51 arquivos** |
| Que artefatos cada tela tem? | `npm run screen-coverage:report` | Forja: **9 telas · 9 charter · 0 E2E · 8 scorecard** |
| Cobertura de casos/UC? | `node scripts/casos-coverage-guard.mjs --report` | Forja: **0 de 9 telas com `casos.md`** |
| Há cycle ativo? | tool MCP `cycles-active` | **Nenhum cycle ATIVO em COPI** |
| Backlog vivo do módulo? | tool MCP `tasks-list module:Forja` | 7 tasks ativas — **6 em `review`** (US-TR-304..311), 1 em `todo` |
| Fila de triagem do projeto? | Daily brief **#461** (2026-08-04) | **663 US não atribuída · 519 sem dono · mais antiga 95d** |

---

## Resumo por bucket

| Bucket | 2026-05-08 | **2026-08-04** | Δ |
|---|---|---|---|
| ✅ APROVADO | 6 | **14** | **+8** |
| 🟡 PARCIAL | 5 | **1** | −4 |
| ❌ AUSENTE | 13 | **9** | −4 |
| **Total** | 24 | **24** | — |

**Os +8 líquidos vieram de 9 movimentos, todos por capacidade que JÁ TINHA SHIPADO** — zero código foi escrito nesta reauditoria:

| Movimento | Capacidades | Evidência (medida 2026-08-04) |
|---|---|---|
| ❌ → ✅ (4) | #6 Cmd+K · #9 @mentions · #10 Watchers · #12 Atalhos J/K/E/A | ver tabela detalhada |
| 🟡 → ✅ (4) | #1 Drag-drop · #4 Tests Pest · #13 Subtasks · #15 Triage | ver tabela detalhada |
| ❌ → 🟡 (1) | #19 Dependencies | `DetailSheet.tsx:515-521` já renderiza `dependencies[]` |

> ⚠️ **Errata do resumo anterior.** O bloco "Resumo" de 2026-05-08 declarava **13 ✅ / 0 🟡 / 11 ❌**, número que **contradizia a própria tabela detalhada do mesmo documento** (que trazia 6 ✅ / 5 🟡 / 13 ❌ — os 🟡 eram #1, #4, #13, #14, #15). O Δ acima usa a **tabela** como base, porque é ela que carrega evidência por linha. Registrado, não apagado.

**Diagnóstico 2026-08-04:** o gap que motivou o redesign (ADR 0100) **fechou**. Drag-drop, Cmd+K, Detail Sheet, atalhos, mentions, watchers, subtasks e triage estão em código, com rota, com teste — e o caso dos atalhos tem lane de CI própria (`forja-shortcuts-gate.yml`, PR #5261, HEAD do main em 2026-08-04). O que sobra em ❌ **não é dívida da mesma natureza**: são 9 features cuja premissa de origem (Jira/Linear) não se sustenta num time de 5 pessoas — ver a seção "Premissa da Jira que NÃO vale aqui".

**O gap real hoje não está na tabela de capacidades, está na cobertura de contrato:** `0 de 9` telas da Forja têm `casos.md`. Contexto obrigatório pra não inflar: **E2E 0/9 na Forja NÃO é fraqueza específica dela** — o projeto inteiro está em **9/207 (4,3%)**, medido pelo mesmo `screen-coverage:report`. Charter, a Forja tem 100% (9/9), igual ao projeto (207/207).

---

## Inventário detalhado

| # | Capacidade | Score | 05-08 | **08-04** | Evidência medida em 2026-08-04 | Falta |
|---|---|---|---|---|---|---|
| **1** | Kanban board drag-drop completo | P0 | 🟡 | **✅** | `Components/board/BoardColumn.tsx` (`onDragOver:39`, `onDrop:44`, `onDragStart:64`) + `BoardController::updateStatus` com optimistic-lock `expected_updated_at` → **409 Conflict** (`:233-254`) + `BoardControllerTest.php` | — |
| 2 | Backlog priorização + bulk operations | P0 | ✅ | **✅** | `Backlog/Index.tsx` + rota `POST /backlog/bulk` (`routes.php:136`) | — |
| 3 | My Work + Inbox unread badges | P0 | ✅ | **✅** | `MyWork/Index.tsx` + 3 rotas inbox (`:85-94`); **Inbox ganhou tela própria** (`Pages/Forja/Inbox/Index.tsx` + `InboxController` + 3 rotas `:122-130`) | — |
| **4** | Multi-tenant + Permissions cobertos por Pest | P0 | 🟡 | **✅** | **51 arquivos** em `Modules/Forja/Tests/Feature/` — incl. `MultiTenantProjectTest`, `MultiTenantTokenIsolationTest`, `ActorPermissionMatrixTest`, `CrossTenantSaturationTest`, `LgpdComplianceTest` | — (a afirmação "0 tests, `Tests/` não existe" é **falsa** desde antes desta medição) |
| 5 | Filters URL state-driven | P0 | ✅ | **✅** | localStorage + URL state em Board + Backlog | — (backend é #14, e a premissa dele não vale aqui) |
| **6** | Search global Cmd+K | P0 | ❌ | **✅** | `SearchController.php` + rota `GET /project-mgmt/search` (`:78`) + `Components/CommandPalette.tsx` + atalho global `AppShellV2.tsx:340` (`metaKey\|ctrlKey` + `k`) + `SearchControllerTest.php` | — |
| **7** | Cycle close UI | P1 | ❌ | **❌** | Tool MCP `cycles-close --rollover` (CLI). **Zero rota** de close em `routes.php` | Ver "Recomendo NÃO fazer" — não há cycle ativo hoje |
| **8** | Sprint/Cycle planning UI (add-to-cycle) | P1 | ❌ | **❌** | **Zero rota** `add-tasks` em `routes.php` | idem #7 |
| **9** | Comments com @mentions | P1 | ❌ | **✅** | Rotas `POST /board/{id}/comment` (`:56`) + `GET /board/users/suggest` (`:60`) + aba "Comentários" no `DetailSheet.tsx:366` | — |
| **10** | Watchers UI (follow/unfollow) | P1 | ❌ | **✅** | Rotas `POST\|DELETE /board/{id}/watch` (`:64-70`) + aba "Watchers" (`DetailSheet.tsx:369`, lista em `:753-761`) | — |
| **11** | Centrifugo presence | P1 | ❌ | **❌** | Centrifugo provisionado ([ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)); **zero canal** de presence no módulo | Ver "Recomendo NÃO fazer" — 5 pessoas |
| **12** | Atalhos keyboard J/K/E/A + `?` | P1 | ❌ | **✅** | `Board/_components/useBoardShortcuts.ts` (`?`:79, `/`:95, `j`:106, `k`:110, `Enter`:114, `e`:118, `a`:122) + `ShortcutsOverlay.tsx` + **lane de CI dedicada** `.github/workflows/forja-shortcuts-gate.yml` (PR #5261) | — |
| **13** | Subtasks (1 nível + completion) | P1 | 🟡 | **✅** | Rota `POST /board/{id}/subtask` (`:73`) + aba Subtasks (`DetailSheet.tsx:368`, toggle `:308`) | — |
| **14** | Saved views **backend** (localStorage → DB) | P1 | 🟡 | **❌** | Tabela `mcp_views` existe; **zero rota** `/views` CRUD | Rebaixado a não-fazer: o valor de usuário já está coberto por #5 (localStorage). Compartilhar view entre 5 pessoas não tem sinal |
| **15** | Triage view dedicada | P1 | 🟡 | **✅** | `TriageController` + `Pages/Forja/Triage/Index.tsx` + `_components/TriageDossier.tsx` + 5 rotas (`assign:102`, `dossier:107`, `aprovar:110`, `rejeitar:113`, `fundir:116`). **Entregou além do escopo original** — o dossiê read-only + fluxo aprovar/rejeitar/fundir não estava na proposta de maio | — |
| 16 | Activity feed timeline | P1 | ✅ | **✅** | `Activity/Index.tsx` + rota `:144` | — |
| 17 | Burndown chart | P1 | ✅ | **✅** | `Burndown/Index.tsx` + rota `:148` | — |
| 18 | Roadmap quarterly | P1 | ✅ | **✅** | `Roadmap/Index.tsx` + rota `:140` | — |
| **19** | Dependencies | P2 | ❌ | **🟡** | **Não é ausente:** `DetailSheet.tsx` já tipa `dependencies: Dependency[]` (`:118`) e **renderiza a seção** com contagem (`:515-521`) | Falta **criar/validar** dependência (não falta exibir). Grafo visual: ver "Recomendo NÃO fazer" |
| **20** | Custom fields per project | P2 | ❌ | **❌** | — | Premissa não vale — ver seção própria |
| **21** | Workload view | P2 | ❌ | **❌** | — | Sem sinal (5 pessoas, WIP máx 1-2 por [regras-time](../../regras-time.md)) |
| **22** | Time tracking interno | P2 | ❌ | **❌** | `estimate_h` em `mcp_tasks` | Premissa não vale — não faturamos por hora |
| **23** | Templates de epic/cycle | P2 | ❌ | **❌** | Tabela `mcp_issue_templates` (uso parcial) | Acoplado ao rito de cycle (#7/#8) |
| **24** | Automation rules (when X then Y) | P2 | ❌ | **❌** | — | **Única ❌ com premissa que vale MAIS aqui** — ver seção própria |

---

## Premissa da Jira que NÃO vale aqui (não copiar)

> Esta seção existe porque copiar solução sem checar premissa é erro catalogado ([proibicoes §5 2026-07-16](../../proibicoes.md)): *pesquisa de mercado informa a decisão, não a substitui*. Cada linha responde **"que premissa do modelo deles sustenta essa solução, e ela vale AQUI?"**.

| Feature (Jira/mercado) | Premissa que a sustenta lá | Nossa realidade (medida) | Veredito |
|---|---|---|---|
| **Custom fields por projeto** (#20) | Dezenas de times, cada um com taxonomia própria; o admin não pode antecipar os campos | **5 pessoas, 1 taxonomia** ([regras-time](../../regras-time.md)). `mcp_components` já dá categorização leve | Não copiar |
| **JQL / query language** | Milhares de issues; achar exige linguagem de consulta | **Cmd+K (#6) + filtros (#5) já cobrem** o volume atual | Não copiar |
| **Workflow designer / schemes** | Governança entre times com processos divergentes | Fluxo fixo já vive em `McpTask::TRANSITIONS` — e é o mesmo pra todo mundo | Não copiar |
| **Portfolio Plans / marketplace** | N times + fornecedores terceiros, com permissionamento cruzado | **1 time, 1 permission** (`jana.mcp.usage.all` — é a única checada nas rotas do módulo) | Não copiar |
| **Time tracking** (#22, Tempo/Productive) | Faturar cliente por hora trabalhada | **Não faturamos por hora.** O custo que de fato importa aqui — o do agente — já é medido por `scripts/governance/agent-cost-per-pr.mjs` | Não copiar |
| **Presence real-time** (#11) | Muita gente na mesma tela; colisão de edição é frequente | **5 pessoas**, WIP máx 1-2 cada. Colisão inexistente — e o caso de escrita concorrente que existe já está coberto pelo **409 optimistic-lock** (#1) | Não copiar |
| **Sprint ceremony** (#7 close, #8 add-to-cycle, velocity) | Time que compromete escopo numa janela fixa e mede velocity contra ela | **Não há cycle ativo hoje** (`cycles-active` → "Nenhum cycle ATIVO em COPI", 2026-08-04). Construir UI de rito pra rito que não acontece é fabricar demanda | Não copiar **enquanto** não houver cycle vivo |

---

## Onde a premissa vale MAIS aqui que na Jira

**Automação de fila (#24) — e só ela.**

Na Jira, *automation rules* existe porque **humano esquece de triar**: alguém abre a issue e ela apodrece no backlog até uma cerimônia semanal.

Aqui a premissa é **mais forte, não mais fraca**, porque **o criador é agente**: tasks nascem via `tasks-create` (tool MCP) durante uma sessão e o autor **some quando a sessão fecha** — não há "alguém que abriu" pra cobrar depois. Recibo, medido no Daily Brief **#461 (2026-08-04)**:

- **663 US não atribuída**
- **519 sem dono**
- mais antiga: **95 dias**

Uma regra do tipo *"task criada por agente sem owner há N dias → notifica [W] / entra na Triage"* teria aqui um alvo que a Jira não tem: um produtor automático de trabalho órfão em escala. A superfície humana pra isso **já existe e já shipou** (#15 Triage, com dossiê + aprovar/rejeitar/fundir) — falta o gatilho que empurra pra ela.

> ⚠️ Isto é a **premissa**, não a aprovação. Antes de virar máquina, vale a régua do projeto: começar **advisory**, medir falso-positivo **antes** de instalar, e conferir se o dono do tema não é a tool `triage` que já existe (estender > paralelo).

---

## O que a Forja tem que a Jira não tem

> Constatação datada (**2026-08-04**), não claim de superioridade. Não há aqui comparação de capacidade ponta-a-ponta com a Jira, e nenhuma destas peças foi benchmarkada contra concorrente — ver a tabela de claims refutadas em [proibicoes §5 2026-07-09](../../proibicoes.md), que é o motivo desta ressalva.

- **Tasks legíveis E escrevíveis por agente** via tools MCP (`tasks-list`, `tasks-detail`, `tasks-create`, `tasks-update`, `tasks-comment`) — o mesmo backlog que o humano vê na tela, sem camada de integração.
- **`mcp_task_memory_links`** — vínculo task ↔ ADR/SPEC materializado (US-TR-308, chips de memória no drawer).
- **Ingest de sessões Claude Code do time** — `POST /api/cc/ingest` (`routes.php:248`) + tela `/team-mcp/cc-sessions` + busca.
- **Daily Brief gerado** — `POST /api/mcp/tools/brief-fetch` (`routes.php:232`); o brief #461 é a fonte dos números desta auditoria.
- **Loop de handoff governado** — `POST /forja/handoff/{slug}/lever` (`routes.php:338`) com `HandoffLeverService` como fonte única (mesma mutação do tool MCP `handoff-lever`).

O que estas 5 peças têm em comum: assumem que **um dos operadores do sistema é um agente**. É a premissa que a Jira não tem porque não precisava ter.

---

## Propostas (aguardando aprovação [W])

> **O detalhe e o critério de aceite vivem no [SPEC.md](SPEC.md)** — este doc lista só prioridade, esforço e **a premissa que sustenta cada uma**. Sem premissa declarada, não entra.
>
> ⚠️ **Recibo de estado:** medido em 2026-08-04, o `SPEC.md` ainda **não** continha os IDs `US-FORJA-001..008` (grep sem resultado); a numeração viva lá é a legada `PMG-NNN` das Fases 3-5. Sessão irmã está escrevendo o detalhe. Se ao ler isto os IDs divergirem, **o SPEC é o dono** — corrija esta lista, não o SPEC.

| # | Tema | Prio | Esforço | Premissa que sustenta |
|---|---|---|---|---|
| 001 | Casos/UC das 9 telas (`casos.md`) | P0 | M | `casos-gate` é required; 0/9 hoje. É contrato executável, não doc |
| 002 | Gatilho de fila órfã → Triage | P0 | S | 519 tasks sem dono, criador-agente some (seção acima) |
| 003 | Fechar as 6 US-TR em `review` | P0 | S | Backlog vivo do módulo — 6 de 7 ativas estão paradas em review |
| 004 | Criar/validar dependência (#19) | P1 | M | Exibir já existe; criar não. Fecha meia-capacidade, não abre nova |
| 005 | E2E da Board (drag-drop + atalhos) | P1 | M | O comportamento mais caro de regredir é o único com lane de CI só de unit |
| 006 | Cobertura de scorecard (8/9 → 9/9) | P2 | S | Higiene; a porta viva já aponta o faltante |
| 007 | Activity feed: filtros + permalink | P2 | S | Refino de capacidade ✅, não capacidade nova |
| 008 | Burndown multi-cycle | P3 | M | **Bloqueada por premissa**: depende de haver cycle. Não iniciar enquanto `cycles-active` estiver vazio |

---

## Recomendo NÃO fazer

> Não é "backlog frio" — é recusa com razão de premissa. Reabrir exige sinal novo, não vontade nova. Régua: [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — *backlog só recebe item se cliente paga + reporta OU métrica detecta drift*.

| Item | Razão de premissa |
|---|---|
| **PMG-016 — Grafo de dependências** (#19 parte visual) | O `DetailSheet` **já mostra** as dependências em lista com contagem. Grafo resolve navegação em rede densa; com 7 tasks ativas não há rede densa. Fazer a metade que falta (criar/validar) é proposta 004 — o **desenho** do grafo não é |
| **PMG-017 — Time tracking** (#22) | Não faturamos por hora. O custo que importa (agente) já tem dono: `agent-cost-per-pr.mjs`. Somar um 2º medidor duplica régua consolidada |
| **PMG-011 — Centrifugo presence** (#11) | 5 pessoas, WIP 1-2. A colisão real (escrita concorrente) já está coberta pelo 409 do #1. Presence resolveria um problema que a medição não mostra existir |
| **PMG-019 — Custom fields** (#20) | 1 taxonomia, 1 time. Exige migration nova + render dinâmico pra flexibilidade que ninguém pediu |
| **PMG-020 — Templates de epic/cycle** (#23) | Acoplado ao rito de cycle. **Morre junto** se o rito for aposentado — e hoje não há cycle ativo. Fazer agora é apostar num rito que não está acontecendo |
| **PMG-023/024/025 — Dark mode toggle · Roadmap drag horizontal · Public share link** | **Sem sinal** (ADR 0105): nenhum cliente reportou, nenhuma métrica detectou drift. Hipótese sem sinal vira ADR de feature wish, não US ativa |

---

## Próxima reauditoria sugerida

**2026-11-04** (trimestral) ou após fechar as propostas 001-003. Se a data chegar e ninguém rodar, os números acima ficam **datados, não errados** — re-rode os comandos da seção "Como reproduzir".

## Insumos preservados de PR #197

O PR #197 mirou no `Modules/Project` legacy (queue-for-delete Fase 3.8) e foi mergeado com disclaimer pivot. Os critérios UX do `CHARTER-board.md` legacy foram **todos portados**: anatomia 4 regiões (TopBar / FilterBar / Kanban / DetailSheet), 6 fluxos críticos, 8 estados de UI, anti-padrões (modal full-screen / `window.location.reload`). ADR 0099 (`aceito`, conteúdo redirecionado pra Fase 3.8 deletion) é o doc de transição entre os 2 esforços.
