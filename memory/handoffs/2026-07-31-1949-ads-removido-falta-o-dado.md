---
date: "2026-07-31"
time: "19:49 BRT"
slug: ads-removido-falta-o-dado
tldr: "Modules/ADS foi a ZERO arquivos em 9 PRs no mesmo dia — mas o ciclo NÃO fechou: a migration ARCHIVE→DROP das 36.986 linhas não existe, a remoção não deployou (prod está no #5134), e 3 baselines ainda contam um módulo que não existe. O que os 4 chips paralelos acharam e eu não: mcp_decision_links era a TERCEIRA tabela com consumidor vivo — nem o meu C2 nem a E3 do adversário a viram."
prs: [5127, 5128, 5129, 5130, 5131, 5132, 5133, 5134, 5135]
decided_by: [W]
related_adrs:
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0087-drift-resolution-sem-mover-url
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0145-ia-administradora-pivot-ads-fsm-piloto-cobradora
next_steps:
  - "🔴 Dump ARCHIVE + migration DROP de mcp_dual_brain_decisions (36.986 linhas, preservar as 41 com resolved_by) — irreversível, ✅[W]+✅[F] pela matriz"
  - "Deployar o #5135 (prod está em 92cd9bff5 = #5134) + smoke pós-deploy"
  - "Limpar ADS de 3 baselines: module-grades (2), casos-coverage (19), eslint (29) — senão vira ⚠️ removed crônico como Brief/TeamMcp/Admin"
  - "Conferir se manter Pages/ads/Admin/Graph.tsx foi decisão consciente — o KB tem /kb/graph próprio com teste de contrato"
  - "Fora do escopo, aberto desde a manhã: Modules/Governance sem loadMigrationsFrom faz module:grade-snapshot e observability:aggregate-daily escreverem em tabelas inexistentes"
---

# ADS removido — mas o ciclo não fechou

> Continuação de [`2026-07-31-1636`](2026-07-31-1636-ads-incorporado-pelo-governance-3-de-7.md), que registrou 3 de 7 partes. Aquele handoff **não é editado** (append-only); este conta o resto do dia.

## O que aconteceu depois

`Modules/ADS/` foi de **152 → 0 arquivos** em **9 PRs**, todos mergeados no mesmo dia. Depois do handoff das 16:36, quatro sessões paralelas (chips) executaram as partes 3, 5, 6 e 7.

| PR | Parte | O quê |
|---|---|---|
| #5131 | 3 | `ToolRegistry` · `UserScopeService` · `ProjectDecomposerService` → Forja |
| #5132 | 5 | as 9 rotas saem do ADS — **URL inalterada** (ADR 0087) |
| #5133 | 7 | **ADR 0363** — Governance incorpora o ADS, supersede 0145 |
| #5134 | 6.1 | preserva o que sobrevive ao núcleo |
| #5135 | 6.2 | **remove o núcleo dual-brain** |

Zerados: `Modules/ADS/`, `scripts/dual-brain/`, as 2 skills `ads-*`, `config/retention.ads.php`, as referências no `phpunit.xml`. Em prod: **0** crons `ads:`, daemon `inactive`/`disabled`.

**A escrita parou de verdade, medido:** última linha em `mcp_dual_brain_decisions` às **15:50:26**; às 19:47 eram **0 escritas** nos últimos 60 e nos últimos 10 minutos. (O total subiu de 36.862 para 36.986 — os +124 são **anteriores** ao desligamento, não posteriores. Eu li isso como "não fecha" antes de conferir o `max(created_at)`; fechava.)

## O achado que nem eu nem o adversário pegamos

O chip da parte 6 escreveu uma errata nova no `DEPRECATION-PLAN` com o **C5**:

> *"`mcp_decision_links` é a **terceira** tabela com consumidor sobrevivente. O C2 não a olhou."*

Meu C2 pegou `mcp_dual_brain_decisions` (consumidor: `ProjectService:160`). A E3 do adversário pegou `mcp_projects`/`mcp_project_parts`. **A terceira estava na mesma lista de DROP, agrupada no "núcleo que morre", com consumidor vivo em `ProjectDecomposerService:118`** — e passou por duas revisões independentes sem ninguém ver. Se a remoção tivesse ido direto, derrubava a Forja pela terceira porta.

O chip também resolveu, sozinho, o acoplamento que eu tinha sinalizado na conversa mas que **não estava no prompt dele**: `DecisionLinksService` e `ProjectDecomposerAgent` foram pra Forja em vez de morrer, porque o #5131 os tinha tornado dependência de um módulo sobrevivente **depois** que o prompt foi escrito.

## O ciclo NÃO fechou — 4 pendências

**1. 🔴 O dado, e é o item central.** A migration de `DROP` **não existe** e o dump de `ARCHIVE` **não foi feito**. As **36.986 linhas** continuam no banco. A ADR 0363 decidiu `ARCHIVE → DROP` preservando as **41** com `resolved_by`. É o único passo irreversível da leva; migration destrutiva em prod é ✅[W] + ✅[F] pela matriz do `TEAM.md`.

**2. A remoção não deployou.** `HEAD` de prod = `92cd9bff5` (#5134). O #5135 não subiu e o smoke pós-deploy não foi feito.

**3. Três baselines ainda contam um módulo que não existe:** `governance/module-grades-baseline.json` (2), `scripts/casos-coverage-baseline.json` (19), `config/eslint-baseline.json` (29). Nenhuma bloqueia — são catracas só-desce — mas o `module-grades` passa a reportar **`⚠️ removed`** em todo PR, para sempre, como já fazem `Brief`, `TeamMcp` e `Admin`. É o vermelho crônico que o próprio baseline critica no comentário dele.

**4. Uma decisão que mudou sem registro.** Sobraram **10 arquivos** em `Pages/ads/`: `Graph`, `Projects`, `ProjectShow`, `Tools`, `TeamScopes` + charters. Os 4 últimos são corretos — as URLs foram congeladas, então os controllers da Forja renderizam `ads/Admin/*`. Mas o **`Graph`** eu tinha medido como morto (o KB tem `/kb/graph` próprio, com teste de contrato `UC-KBG-01..03`); o passo 1 patchou o `GraphController` em vez de removê-lo. Parece decisão consciente da sessão, mas diverge do que estava registrado.

## Sobre "ligar as máquinas"

Nenhuma máquina nova foi criada nesta leva, e isso está **certo** — o §5 proíbe somar gate redundante. As existentes (`deadlink-gate`, `anchor-lint`, `screen-coverage`) foram reconciliadas PR a PR. O que ficou pendente não é ligar máquina: é **limpar as 3 baselines** pra elas pararem de medir um fantasma.

## Erro meu registrado

Li o crescimento da tabela (36.862 → 36.986) como sinal de que o desligamento tinha falhado, e disse isso antes de medir `max(created_at)`. Fechava: as escritas eram anteriores. É a mesma classe do 503 que quase virei incidente hoje de manhã — **contagem sem timestamp não distingue "ainda acontece" de "aconteceu antes"**.

## Estado MCP no momento do fechamento

Não consultado (mesma razão do handoff das 16:36 — sessão saturada). Verificável em git/gh: 9 PRs mergeados (#5127..#5135), `main` em `d6d7edc8b66`, prod em `92cd9bff5`.
