---
id: requisitos-brief-deprecation-plan
---

# DEPRECATION-PLAN — Modules/Brief

> **Status:** 📋 Planejado · **Owner:** [W] · **Decisão:** [W] 2026-07-30 (*"todos esses eu vou deletar"*)
> **Ordem no conjunto:** **3º de 6** — [proposal da ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
> ⚠️ **O módulo mais barato em código e o mais caro em processo.** 35 arquivos, 4 acopladores — e sustenta uma skill **Tier A always-on**.

## Fase 1 — Inventário

**Gerado:** [`SUPERFICIE.md`](SUPERFICIE.md) — **35 arquivos em 9 papéis** (`module-surface.mjs Brief --write`). Frescor 2026-07-30: `--check` **exit 0**.

Contornos: **0** telas `.tsx` · 2 arquivos em `Routes/` · **1 tool MCP** (`brief-fetch`) · **0** cron em `Kernel.php`.

## Fase 2 — Estado em produção (medido ANTES de planejar)

**Sistema medido:** `APP_ENV=live` · `u906587222_oimpresso` · 385 tabelas · **2026-07-30**.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Estado |
|---|---|
| `mcp_briefs` | **438 linhas** |
| `mcp_weekly_digests` | existe · 0 linhas |
| `mcp_doc_summaries` | existe · 0 linhas |

⚠️ **`Modules/Brief` não DECLARA nenhuma migration própria** — `Schema::create` do módulo devolve vazio. As 3 tabelas acima têm **outro dono** (`Modules/Jana`). **Corolário duro:** quem apagar este módulo **não deve apagar `mcp_briefs`** sem antes medir quem mais escreve nela. Apagar código ≠ apagar dado, e aqui os dois donos são diferentes.

## Fase 3 — Acoplamento externo

`git grep -lF 'Modules\Brief\'` fora da pasta → **4 arquivos**:

| Acoplador | Natureza |
|---|---|
| `Modules/Governance/Tests/Feature/AgentOutcomeBriefSectionServiceTest.php` | teste — sai com o Governance (6º) |
| `Modules/Jana/Mcp/OimpressoMcpServer.php` | **registra a tool `brief-fetch`** — Jana **sobrevive** |
| `Modules/Manufacturing/Console/Commands/ManufacturingHealthCommand.php` | Manufacturing **sobrevive** |
| `scripts/governance/system-map.mjs` | gerador do `PAINEL-SISTEMA` — **sobrevive** |

**Dois dos quatro sobrevivem ao conjunto** — logo exigem patch, não morrem de graça.

## Fase 4 — Decisão por tabela

| Tabela | Decisão | Por quê |
|---|---|---|
| `mcp_briefs` | **PRESERVE** — não é do Brief | 438 linhas, dono é `Modules/Jana`. Mexer aqui é escopo do Jana, que sobrevive. |
| `mcp_weekly_digests` | **PRESERVE** (dono Jana) | 0 linhas, mas a decisão não é deste plano |
| `mcp_doc_summaries` | **PRESERVE** (dono Jana) | idem |

**Este plano não dropa nada.** É o único dos 6 assim.

## Fase 5 — Riscos Tier 0

| # | Risco | Severidade | Mitigação |
|---|---|---|---|
| **R1** | **`brief-fetch` morre e o protocolo de sessão vai com ele.** É skill **Tier A always-on** (`CLAUDE.md` passo 1) + hook `SessionStart` + [ADR 0091](../../decisions/0091-daily-brief.md). Todo agente do time começa a sessão por ela. | **ALTA — a mais grave dos 6** | Decidir o receptor da tool **antes** da E3. Candidato natural: `Modules/Jana`, que já a registra no `OimpressoMcpServer`. |
| **R2** | `system-map.mjs` referencia o namespace → o gerador do `PAINEL-SISTEMA` quebra, e com ele o inventário vivo de módulos | média | Patch no `.mjs` na mesma leva |
| **R3** | `ManufacturingHealthCommand` quebra (módulo vivo) | média | Patch ou remoção da seção de brief |
| **R4** | PII / cross-tenant / volume | **nenhum** | o módulo não tem tabela própria |

Nenhum check **required** cita Brief.

## Roadmap

| Etapa | O que | Gate [W] |
|---|---|---|
| **E1** | **Decidir o destino do `brief-fetch`** (R1) — migrar pro Jana ou aposentar a skill Tier A | ✋ **[W] decide — bloqueia tudo** |
| **E2** | Patch nos 2 sobreviventes: `system-map.mjs` + `ManufacturingHealthCommand` | ✋ [W] aprova |
| **E3** | Migrar a tool MCP pro receptor + atualizar `CLAUDE.md` (passo 1) e o hook `SessionStart` | ✋ [W] aprova |
| **E4** | Remover `Modules/Brief/` + rotas + `modules_statuses.json` | ✋ [W] aprova |
| **E5** | Smoke real: `brief-fetch` responde pelo receptor (ou a skill sai do CLAUDE.md) | ✋ [W] confere |
| **E6** | Lápide §5 + `BRIEFING` final | — |

**Sem DROP de tabela em nenhuma etapa.**

## Resíduo honesto

- **CT 100 não medido** — e aqui importa mais que nos outros: o MCP server (`mcp.oimpresso.com`) é quem **serve** a tool.
- **Não medi quem mais escreve em `mcp_briefs`** além do Brief. Antes de qualquer decisão sobre a tabela isso precisa de medição própria — a afirmação "o dono é o Jana" vem das migrations, não de rastrear escrita.
- **Pest não rodado** (Tier 0 → CT 100).
