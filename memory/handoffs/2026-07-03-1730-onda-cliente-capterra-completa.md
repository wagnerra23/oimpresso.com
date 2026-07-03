---
date: "2026-07-03"
time: "17:30 BRT"
slug: onda-cliente-capterra-completa
tldr: "Onda Cliente do programa de ondas completa (4 passos): CAPTERRA-FICHA nota 65 + INVENTARIO + 7 US no MCP (US-CRM-079..085) + 7 scorecards + catraca provada. Achado nº1: módulo PII-heavy sem direito ao esquecimento do titular (DsrService não cobre contacts, LGPD Art.18). Incidente: #3732 squash-mergeado incompleto por desync GitHub, corrigido via #3742."
prs: [3732, 3742, 3745, 3750]
decided_by: [W]
related_adrs: [0089-capterra-driven-module-evolution, 0093-multi-tenant-isolation-tier-0, 0105-cliente-como-sinal-guiar-sem-mandar, 0301-separar-cliente-deprecar-crm-pipeline]
next_steps: ["Executar US-CRM-079 (P0 anonimização fiscal-aware do titular — LGPD Art.18)", "Executar US-CRM-080 (P0 teste cross-tenant no App\\Contact pai)", "Atribuir owner/prioridade às 7 US no MCP (hoje unowned)"]
---

# Handoff — Onda Cliente (adversário de mercado) completa

## Estado MCP no momento do fechamento

- **cycles-active:** nenhum cycle ativo em COPI (off-cycle — o programa de ondas é transversal, `parent_plan=programa-ondas`).
- **my-work (@wagner):** 30 tasks (8 review, 8 blocked, 14 todo). As 7 US novas (US-CRM-079..085) foram criadas **unowned** — ainda propagando via webhook (~2min) e sem owner/prioridade atribuídos.
- **decisions-search:** governança da onda ancorada em ADR 0089 (Capterra-driven) + 0101 (Charter-Capterra) + 0320 (proposta programa-ondas). Nenhuma ADR nova nesta sessão.
- **handoffs irmãos 2026-07-03:** 0835 Sells, 1015 Compras, 1044 Financeiro, 1215 RecurringBilling, 1703 Produto — este fecha a **Onda 5 Cliente** (não duplica; é módulo distinto).

## O que aconteceu

Executei os **4 passos** da onda Cliente (standalone, OK [W] progressivo: "sim vai" → "Vai" → "merge e passo 4" → "merge, salve tudo"):

1. **Adversário (`capterra-senior`)** — 2 agents paralelos (grounding de código + pesquisa de 10 concorrentes BR foco LGPD). Nota de capacidade **65/100** (entre Bling ~60 e Conta Azul ~66, abaixo de Omie ~72).
2. **Gaps+backlog (`/comparativo`)** — INVENTARIO (✅7·🟡11·❌1) + 7 US no MCP + §3-bis no SPEC (v2.4).
3. **Régua por tela** — 7 scorecards com `casos_coverage` (0% — sem e2e/casos) + `d1_calculo` (só Ledger toca valor 🟡).
4. **Catraca+sentinela** — emergente; provei o bloqueio (ratchet barra `cliente-show` 86→70, exit 1) + registrei Onda 5 no PLANO-MESTRE.

**3 achados adversariais verificados no código** (o adversário ancora no código, não repete o claim da doc):
- `App\Contact` **NÃO tem global scope** — isolamento é `where('business_id')` manual; a SPEC/BRIEFING afirmam "global scope". Sem teste cross-tenant no `Contact` pai (só no filho `ContactAddress`).
- **DSR não cobre `contacts`** — direito ao esquecimento do titular (LGPD Art.18) ausente no módulo que É o repositório de PII. A máquina (`PiiRedactor`/`DsrService`) já existe → é a lane de mercado vazia mais valiosa (US-CRM-079).
- **Limite de crédito decorativo** — `isCustomerCreditLimitExeeded()` calcula mas é advisory; a venda não bloqueia.

## Artefatos gerados (todos em main)

- `memory/requisitos/Cliente/CAPTERRA-FICHA.md` (novo, ~230 linhas) — 10 seções, nota 65.
- `memory/requisitos/Cliente/CAPTERRA-INVENTARIO.md` (novo) — 19 caps em buckets + batch.
- `memory/requisitos/Cliente/SPEC.md` — §3-bis US-CRM-079..085 + frontmatter v2.4 (us_count 22).
- `memory/governance/scorecards/screens/cliente-{index,show,create,edit,ledger,import,map}.yaml` — +`casos_coverage`+`d1_calculo`.
- `memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` — Onda 5 Cliente (5.1-5.4).
- `memory/sessions/2026-07-03-capterra-cliente.md` — session log (Passos 1-4 + incidente).

## Persistência

- **git:** 4 PRs mergeados (#3732 FICHA · #3742 INVENTARIO+SPEC · #3745 scorecards · #3750 catraca+PLANO-MESTRE).
- **MCP:** 7 US US-CRM-079..085 criadas (`tasks-create`, tags `capterra-gap`/`onda-cliente`). Webhook sincroniza SPEC→DB no push.
- **BRIEFING:** não atualizado (a onda é benchmark/backlog, não muda capacidade entregue — BRIEFING reflete estado real, sem mudança de feature).

## Próximos passos pra retomar

`/continuar` → foco em **US-CRM-079** (P0 anonimização fiscal-aware do titular) — o diferencial nº1 + obrigação LGPD; a máquina `DsrService`/`PiiRedactor` já existe, falta apontar pra `contacts` preservando o registro fiscal. Depois US-CRM-080 (teste cross-tenant). Atribuir owner/prio às 7 US antes.

## Lições catalogadas

- **Desync de PR no GitHub (incidente novo):** o #3732 foi squash-mergeado no estado incompleto `0ee8cff5b` — o branch avançou (3 commits) mas o `headRefOid` do PR travou, então o CI não re-rodou e o merge pegou a árvore velha (sem INVENTARIO/SPEC, com session log de `topic` inválido). **Defesa:** ao empilhar commits num PR, conferir `gh pr view --json headRefOid` == `git ls-remote` antes de assumir CI; se travar, **PR novo de base fresca** (feito: #3742) em vez de insistir no branch.
- **Séparação Cliente≠CRM na prática:** `tasks-create module:Cliente` continua a série `US-CRM-` (078→079) porque o SPEC do cadastro usa esse prefixo (legado — código em `Modules/Crm`). Não confundir com o pipeline CRM depreciado (US-CRM-001..062).
- **Adversário ancora no código:** 2 claims da doc ("global scope", "cobertura de teste") estavam à frente do código — verificados à mão antes de publicar.

## Pointers detalhados (on-demand)

- Ficha: [CAPTERRA-FICHA.md](../requisitos/Cliente/CAPTERRA-FICHA.md) · Inventário: [CAPTERRA-INVENTARIO.md](../requisitos/Cliente/CAPTERRA-INVENTARIO.md)
- Session log: [2026-07-03-capterra-cliente.md](../sessions/2026-07-03-capterra-cliente.md)
- Plano: [PLANO-MESTRE.md](../requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md) §Status vivo (Onda 5)
