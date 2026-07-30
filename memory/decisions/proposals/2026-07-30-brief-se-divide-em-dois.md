---
status: proposal
title: "O Brief não tem um destino — tem dois: o brief de desenvolvimento vai pra Forja, o brief do cliente já está na Jana"
proposed_by: Claude — decisão [W] 2026-07-30 "Brief para o MCP e outro para Jana"
proposed_at: 2026-07-30
relates_to:
  - 0091-daily-brief
  - 0140-jana-pro-produto-comercial-saas
  - 0053-mcp-server-governanca-como-produto
  - 0093-multi-tenant-isolation-tier-0
---

# O Brief se divide em dois

> **Destrava a E1 do [`Brief/DEPRECATION-PLAN`](../../requisitos/Brief/DEPRECATION-PLAN.md)**, que é
> bloqueante e está parada em *"[W] decide o destino do `brief-fetch`"* — o **#3** da
> [ordem topológica](2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md), com `Admin` (#2)
> já removido ([#5062](https://github.com/wagnerra23/oimpresso.com/pull/5062)).
>
> Irmã de [MCP é Forja](2026-07-30-mcp-e-forja-jana-e-usuario.md). Podem ser decididas separadamente.

## A descoberta: são dois produtos com o mesmo nome

O plano de deprecação trata "o Brief" como uma coisa só e procura **um** receptor. Medido, são
**dois**, e eles não se tocam:

| | `Modules/Brief` | `BriefDiarioAgent` (dentro de `Modules/Jana`) |
|---|---|---|
| Tabela | `mcp_briefs` — **438 linhas, a coluna `business_id` NÃO EXISTE** | nenhuma — gera na hora |
| Tenant | **inexistente por construção** | **`businessId` no constructor** (*"Tier 0 mecânico — nunca no prompt"*) |
| Conteúdo | cycle, HITL pendente, PRs, ADRs 24h, SDD, charters apodrecendo, migration aging | faturamento, metas, *"como tá meu negócio"* |
| Gatilho | hook `SessionStart` + tool MCP `brief-fetch` | user digita **"brief"** no chat `/ia` |
| Permission | `brief.access` → concedida **só em biz=1** | via `jana.chat` |
| Telas `.tsx` | **0** | o próprio chat |
| Canon | [ADR 0091](../0091-daily-brief.md) | [ADR 0140](../0140-jana-pro-produto-comercial-saas.md) |

**Prova de que são independentes:** `BriefDiarioAgent`, `BriefDiarioChatTrigger` e
`BriefDiarioService` **não referenciam `mcp_briefs`** em lugar nenhum — varredura em
`Modules/Jana/Ai/Agents/` e `Modules/Jana/Services/`, zero ocorrências. O brief do cliente não lê
o brief do dev.

## Decisão proposta

1. **`Modules/Brief` → Forja.** É infraestrutura de desenvolvimento: sem tenant, sem tela, 438
   linhas de estado do *projeto*, consumido por hooks/skills/agents do `.claude`. Vai junto com o
   MCP (proposta irmã), **não** para a Jana.
2. **`BriefDiarioAgent` fica na Jana, onde já está.** É produto do cliente, tenant-scoped, ADR 0140.
   **Nada a fazer** — esta metade da decisão é confirmar que já está certo.

Ou seja: o `Brief/DEPRECATION-PLAN` não erra ao dizer que o módulo sai. Erra ao apontar **um**
receptor, e ao apontá-lo para a Jana.

## O que muda no plano mergeado

| Plano diz | Passa a dizer |
|---|---|
| `brief-fetch` → *"Candidato natural: `Modules/Jana`, que já a registra no `OimpressoMcpServer`"* | **Forja** — o registro no `OimpressoMcpServer` é consequência de o servidor MCP morar na Jana hoje, que é justamente o que a proposta irmã corrige |
| E1 *"migrar pro Jana **ou** aposentar a skill Tier A"* | nenhuma das duas: **migrar pra Forja** |

O `OimpressoMcpServer` deixa de ser argumento: ele mesmo vai pra Forja.

## Por que o `brief-fetch` não pode simplesmente morrer

O risco R1 do plano já classifica como **"a mais grave dos 6"**, e a medição confirma o alcance —
`brief-fetch` é citado por:

- `CLAUDE.md` **passo 1** do protocolo de sessão (skill `brief-first`, **Tier A always-on**)
- hook `SessionStart` [`brief-fetch-curl.mjs`](../../../.claude/hooks/brief-fetch-curl.mjs) + `tier-a-banner.mjs`
- **6 agentes** (`capterra-senior`, `como-integrar`, `estado-da-arte`, `memoria-senior`, `migracao-officeimpresso`, `tela-venda-arte`)
- 6 skills · [ADR 0091](../0091-daily-brief.md)
- **438 briefs** gerados — o de hoje saiu há ~2h (`Brief #438`)

Toda sessão de todo agente do time começa por ele.

## Riscos

| # | Risco | Grau | Contenção |
|---|---|---|---|
| R1 | Tool some e o protocolo de sessão vai junto | **ALTA** | Migrar **antes** de remover; o hook já é fail-open (cai no índice de handoffs) — degrada, não quebra |
| R2 | `LeaseBriefSectionService` importa `Modules\Governance`, que morre no **#6** | média | O plano já registra como buraco: *"não medi de onde vem o lease"*. Ou acha o receptor do sinal, ou a seção sai do brief |
| R3 | `SkillTierReviewCommand` sem receptor natural (tema de `.claude/skills/`, que não é módulo Laravel) | baixa | Buraco declarado no plano; segue declarado — **não invento dono** |
| R4 | Confundir os dois briefs numa migração automatizada | média | O nome é o mesmo; o predicado seguro é a **tabela** (`mcp_briefs`) e o **namespace** (`Modules\Brief\` × `Modules\Jana\Ai\Agents\BriefDiario`) |

## Execução proposta

| Fase | O quê | Reversível? |
|---|---|---|
| **B1** | Confirmar que o `BriefDiarioAgent` fica — **zero código** | ✅ |
| **B2** | Mover a tool `brief-fetch` + `Modules/Brief/**` pra Forja (PHP-only) | ⚠️ |
| **B3** | Atualizar `CLAUDE.md` passo 1 + hook `SessionStart` + os 6 agentes | ✅ |
| **B4** | Smoke real: `brief-fetch` responde pelo receptor com token do time | — |
| **B5** | Só então remover `Modules/Brief/` | ❌ |

## Gate de reversão

Se após B2 o hook `SessionStart` cair em fallback (a mensagem
`=== [brief-fetch hook] FALLBACK ATIVADO ===` volta a aparecer), **para** — é exatamente o sintoma
que o incidente de 2026-07-29 produziu, e ele é visível na primeira sessão.

## Aberto — precisa de [W]

1. **`mcp_briefs` (438 linhas):** vai junto pra Forja, ou fica onde está? A tabela é `mcp_*` e não
   tem `business_id` — não há razão técnica pra mover, só de coerência de nome.
2. **O sinal de *lease*** (R2): existe receptor, ou a seção sai do brief quando o Governance cair?
