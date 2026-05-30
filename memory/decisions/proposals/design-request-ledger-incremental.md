---
slug: design-request-ledger-incremental
title: "Design Request Ledger — camada incremental (idempotency key + delta manifest + checkpoint) em arquivos git"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-30"
module: governance
quarter: 2026-Q2
tags: [governance, design-system, incremental, idempotency, checkpoint, cdc, file-based, git-canon, claude-design]
related:
  - 0236-governanca-evolucao-doc-design
  - 0233-ativacao-memoria-momento-decisao
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0053-mcp-server-governanca-como-produto
authors: [W, C]
---

# Proposta — Design Request Ledger (camada incremental, file-based)

> **Origem:** Wagner 2026-05-30, fim de sessão. *"se adicionar requisitos técnicos, campos novos, a estrutura deve entender o que mudou e o design não precisar ler tudo... cada pedido deve ter um número e o claude entender que já processou."*
> **Correção 2026-05-30 (Wagner):** *"lembre que no design não tem o MCP. tem que ser arquivos bem organizados."* → **O Claude Design (Cowork) só enxerga ARQUIVOS** (GitHub + file-server), nunca o MCP do oimpresso. Logo o ledger é **arquivo em git**, não tabela no MCP. Ver [feedback-claude-design-so-arquivos](../../reference/feedback-claude-design-so-arquivos.md).

## Problema
O sistema de governança de design ([ADR 0236](../0236-governanca-evolucao-doc-design.md) + índice + pré-flight + golden + grade) é o **"o quê"**, mas falta a **incrementalidade**: hoje o Claude Design releria a tela inteira a cada pedido, sem saber o que mudou, nem até onde já fez, nem se já processou aquele pedido.

## Proposta — ledger de ARQUIVOS em git (`memory/governance/design-requests/`)
Cada pedido de design = 1 arquivo `REQ-NNN.md` versionado em git (o Claude Design lê direto):
```
memory/governance/design-requests/
  LEDGER.md         # índice = o "já processei?" (tabela REQ | tela | status | resultado)
  REQ-NNN.md        # 1 pedido: frontmatter + delta_manifest + checkpoint + resultado
```
Campos do `REQ-NNN.md`:
```
req: REQ-NNN        (idempotency key — Stripe pattern; nº monotônico = maior REQ na pasta + 1)
status: received → processing → done     # "já processei?" — REQ done = pula
delta_manifest:                          # "o que mudou?" — CDC + traceability ancorado por LINHA
  - tela / seção / campo afetado + âncora por linha real (TraceLLM: localized rewrite)
checkpoint:                              # "até onde fiz?" — Temporal/LangGraph; 1 seção, nunca no meio
  - seção: done | untouched
resultado: <hash/PR do diff aplicado>    # retorna igual no retry (Stripe: guarda resultado, não flag)
```
**Fluxo (sem DB):** pedido → próximo `REQ-NNN` → Claude Design lê `LEDGER.md` → se `done`, pula (idempotente) → senão lê **só o delta ancorado** (não a tela toda) → aplica regra do golden do arquétipo → grava checkpoint + resultado no `REQ-NNN.md` + linha no `LEDGER.md`. **Append-only** (REQ não se apaga).

**MCP = só espelho de leitura.** O webhook git→MCP indexa esses arquivos de graça (Claude Code/time buscam via `memoria-search`), mas **nunca é dependência nem canal pro Claude Design** — alinha [ADR 0061](../0061-conhecimento-canonico-git-mcp-zero-automem.md) (git é SSOT, MCP é índice sobre o git).

## Estado-da-arte (validação + grade da ideia do Wagner)
| Componente | Padrão SOTA | Nota |
|---|---|---:|
| nº de pedido / "já processei" | Idempotency Key (Stripe) — guarda resultado, não só flag | 8/10 |
| estrutura entende o que mudou | CDC + Requirements Traceability Matrix (TraceLLM/IncreRTL) — delta ancorado por linha | 7/10 |
| resume de onde parou | Durable execution / checkpoint (Temporal/LangGraph) — entre unidades, não no meio | 8/10 |
| state backend durável | **git versionado** (não DB) — append-only, auditável, **lido pelo consumidor sem MCP** | 9/10 |
| **Geral** | a intuição está no nível dos melhores; 20% = mecânica precisa (agora file-based) | **80/100** |

## Próximos passos
1. ✅ **Anti-dup confirmado** (`decisions-search`, MCP ativo): não existe `mcp_design_requests` nem ledger prévio.
2. ✅ **Scaffold de arquivos criado** — `memory/governance/design-requests/{LEDGER,_TEMPLATE-REQ}.md` (este PR).
3. **Aceitar via emenda ao [ADR 0236](../0236-governanca-evolucao-doc-design.md) OU ADR novo** — formaliza o ledger file-based como mecanismo canon (status hoje: **scaffold pré-ADR**).
4. Skill `design-memoria-reprocess` (gatilhos G1/G2/G3) passa a abrir/atualizar `REQ-NNN`.
5. (opcional) `ScreenGradeCommand` grava o `resultado` (nota pós) no REQ quando rodar.

> **Removido da v1:** *"Time MCP implementa a tabela no CT 100"* — desnecessário; é arquivo, eu mesmo crio. O MCP só indexa por webhook.

## Sources (pesquisa 2026-05-30)
Stripe idempotent requests · LangGraph durable execution · Diagrid "checkpoints ≠ durable execution" · TraceLLM/IncreRTL (arXiv) traceability incremental · [ADR 0061](../0061-conhecimento-canonico-git-mcp-zero-automem.md) (git SSOT).
