---
id: requisitos-ads-deprecation-plan
---

# DEPRECATION-PLAN — Modules/ADS

> **Status:** 📋 Planejado · **Owner:** [W] · **Decisão:** [W] 2026-07-30 (*"todos esses eu vou deletar"*)
> **Ordem no conjunto:** **4º de 6** — [proposal da ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
> 🔴 **O único dos 6 com volume alto e escrita ATIVA.** Não é zumbi: gravou hoje.

## Fase 1 — Inventário

**Gerado:** [`SUPERFICIE.md`](SUPERFICIE.md) — **152 arquivos em 14 papéis** (`module-surface.mjs ADS --write`), o maior dos 6. Frescor 2026-07-30: `--check` **exit 0**.

Contornos: **19 telas** `.tsx` (o maior número do conjunto) · 2 arquivos em `Routes/` · **0** tools MCP · **0** cron em `Kernel.php`.

## Fase 2 — Estado em produção (medido ANTES de planejar)

**Sistema medido:** `APP_ENV=live` · `u906587222_oimpresso` · 385 tabelas · **2026-07-30**. Existência por `information_schema.tables`.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Linhas | Escrita mais recente | Tenants |
|---|---:|---|---|
| **`mcp_dual_brain_decisions`** | **36.607** | **2026-07-30 08:02:11** | `business_id=[1]` |
| `mcp_governance_rules` | 4 | 2026-05-04 20:15 | — |
| `mcp_decision_thresholds` | 1 | — | — |
| `mcp_decision_patterns` · `mcp_confidence_scores` · `mcp_projects` · `mcp_project_parts` · `mcp_tool_executions` · `mcp_file_locks` · `mcp_user_module_access` · `mcp_decision_links` | **0** cada | — | — |

**Consequência 1 — o módulo está VIVO.** 36.607 decisões de dual-brain, a última **poucas horas antes desta medição**. Isto **não** é o caso do SRS (0 linhas, nunca usado) — aqui há trilha auditável real e o dado precisa de decisão explícita.

**Consequência 2 — o volume está em UMA tabela.** 36.607 das 36.612 linhas (**99,99%**) vivem em `mcp_dual_brain_decisions`. As outras 10 tabelas somam **5 linhas**. Isso simplifica a Fase 4: **uma** decisão difícil, dez triviais.

**Consequência 3 — tenant único.** Todo o volume é `business_id=1` (o negócio interno), **não** o cliente piloto biz=4. Isso **remove o risco de impacto em cliente**, mas **não** remove o dever de guarda da trilha.

⚠️ CT 100 **não medido**.

## Fase 3 — Acoplamento externo

`git grep -lF 'Modules\ADS\'` fora da pasta, sem `memory/`/`.claude/` → **5 arquivos**:

| Acoplador | Sobrevive ao conjunto? |
|---|---|
| `Modules/KB/Http/Controllers/Admin/GraphController.php` | ✅ **sim** — exige patch |
| `Modules/ProjectMgmt/Http/Controllers/Admin/ProjectsController.php` | ✅ **sim** — exige patch |
| `Modules/TeamMcp/Http/Controllers/Admin/TeamScopesController.php` | ❌ morre (5º) |
| `Modules/TeamMcp/Http/Controllers/Admin/ToolsController.php` | ❌ morre (5º) |
| `tests/Feature/Skills/SkillsServiceTest.php` | ✅ sim — teste a re-apontar |

**3 dos 5 sobrevivem.** E o ADS **consome** TeamMcp de volta (`Http/Requests/ExecuteToolRequest.php`, `Routes/web.php`) — é o **ciclo** `ADS ↔ TeamMcp` que obriga a ordem do conjunto.

## Fase 4 — Decisão por tabela

| Tabela | Decisão | Por quê |
|---|---|---|
| **`mcp_dual_brain_decisions`** | ⛔ **DECISÃO [W] — ARCHIVE ou DROP** | 36.607 linhas de trilha de decisão auditável, escrita ativa. **Não é decisão de agente.** Se ARCHIVE: dump + retenção declarada. Se DROP: [W] assume a perda por escrito. |
| `mcp_governance_rules` (4) · `mcp_decision_thresholds` (1) | **ARCHIVE trivial** (5 linhas — cabem num seeder) | volume desprezível, mas são configuração, não lixo |
| as outras 8 (0 linhas) | **DROP** | arquivar zero linhas é cerimônia vazia (precedente T1..T7 do SRS) |

**Ordem obrigatória:** DROP **depois** do refactor (lição E3 do SRS — dropar antes *"derrubaria produção"*).

## Fase 5 — Riscos Tier 0

| # | Risco | Severidade | Mitigação |
|---|---|---|---|
| **R1** | **Perda de 36.607 linhas de trilha de decisão** sem decisão explícita | **ALTA** | Fase 4 linha 1 — gate [W] obrigatório antes de qualquer migration |
| **R2** | **Escrita ativa durante a remoção.** Gravou às 08:02 de hoje; um DROP a quente pode falhar ou perder escrita em vôo | **ALTA** | Achar e desligar o produtor **antes** (não medido: quem escreve? cron? job? request?) |
| **R3** | 3 acopladores sobreviventes (KB, ProjectMgmt, teste) quebram | média | Patch na mesma leva |
| **R4** | **19 telas** somem | média | Decidir receptor por tela antes da E3 |
| **R5** | Skill `ads-route`/`ads-decision-flow` em `.claude/skills/` ficam órfãs | baixa | Aposentar as skills junto |
| **R6** | cross-tenant | **nenhum** | volume 100% em `business_id=1` |

Nenhum check **required** cita ADS.

## Roadmap

| Etapa | O que | Gate [W] |
|---|---|---|
| **E1** | **Achar o produtor** das 36.607 linhas (R2) e medir CT 100 | — |
| **E2** | ⛔ **[W] decide ARCHIVE vs DROP** de `mcp_dual_brain_decisions` (R1) | ✋ **bloqueia tudo** |
| **E3** | Desligar o produtor · patch nos 3 sobreviventes · decidir receptor das 19 telas | ✋ [W] aprova |
| **E4** | Remover `Modules/ADS/` + telas + rotas + permissions + skills · aposentar `modules_statuses.json` | ✋ [W] aprova |
| **E5** | Migration: ARCHIVE/DROP conforme E2 | ✋ [W] aprova |
| **E6** | Smoke real em prod: rotas em 301/410 + estado das tabelas conforme E2 | ✋ [W] confere |
| **E7** | Lápide §5 + `BRIEFING` final | — |

## Resíduo honesto

- **Não sei quem escreve** em `mcp_dual_brain_decisions` — medi o dado, não o produtor. É o **pré-requisito nº 1** (E1), e a pergunta "quem grava" se responde no runtime (`schedule:list`, fila, log), **nunca** parseando código (lápide §5 2026-07-17).
- **CT 100 não medido.**
- **As 19 telas não avaliadas uma a uma** — dono: `screen-coverage:report`.
- **Pest não rodado** (Tier 0 → CT 100).
