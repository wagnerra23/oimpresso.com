---
slug: 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
number: 258
title: "Processo de ADR estado-da-arte — índice gerado + supersede atômico + status-mutável (modelo Log4brains)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-07"
supersedes: ['0028-adrs-numeracao-monotonica']
module: governance
related:
  - 0028-adrs-numeracao-monotonica
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
  - 0180-drift-numero-adr-0178-conflito-paralelo
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0257-adr-status-lifecycle-kind-modelo-canonico
---

# ADR 0258 — Processo de ADR estado-da-arte

## Contexto

O processo caseiro de gestão de ADR do oimpresso acumulou dor (auditoria 2026-06-07, PR #2390): **4 índices manuais que mentem** vs disco (README parou no ADR 0023; `_INDEX-LIFECYCLE` dizia 119, disco tem 245), **rebaixamentos que "voltam"** (status editado num lugar só), e o gate `block-adr-edits` **bloqueando até a transição de status**. "Quantos ADRs ativos?" não tinha resposta limpa.

Wagner pediu pesquisa do estado-da-arte ("quem melhor se destaca e por quê"). Conclusão abaixo.

## Estado-da-arte (pesquisa 2026)

| Referência | Por que se destaca |
|---|---|
| **Log4brains** (thomvaill) | Índice **auto-gerado** dos arquivos + git log → nunca drifta. ADR imutável: "só o status muda". Publica site estático. Padrão-ouro docs-as-code. |
| **adr-tools** (Nat Pryce) / **pyadr** | **Supersessão num comando atômico**: cria a nova E marca a antiga `superseded` + linka, de uma vez. |
| **MADR** | Template padronizado (status + links de supersessão). Default do Log4brains. |
| **AWS Prescriptive Guidance** | Processo formal: aceito = imutável; mudar = nova ADR + marca antiga `superseded`; dono responsável. |
| **Fitness functions / operationalizing ADRs** | Cada ADR não-aposentada → mapeia pra um check automático no CI (anti-drift). |
| **RFC process** (Rust/Google/Doctolib) | Fluxo de decisão em escala com lifecycle (Draft→Active→Superseded/Retired). Michael Nygard = criador do ADR. |

## As 3 coisas que o mundo faz e o oimpresso errava

1. **Índice é GERADO, não mantido à mão** (Log4brains). O oimpresso tinha 4 manuais → drift. *Certo: 0 índices manuais + 1 gerador.*
2. **Supersessão é 1 comando atômico** (adr-tools/pyadr) — status da antiga + link da nova juntos. O oimpresso fazia em 2 lugares na mão → divergia ("rebaixei e voltou"). *Certo: 1 comando, 1 fonte.*
3. **"Imutável" = CONTEÚDO imutável, status PODE mudar** (AWS/Log4brains/Nygard). O gate `block-adr-edits` bloqueava até o status — mais rígido que a indústria, e era esse excesso que fazia o rebaixamento não pegar. *Certo: conteúdo append-only, status editável sob controle (ADR 0257).*

## Decisão

**Adotar o modelo Log4brains** (não a ferramenta npm — o oimpresso tem stack MCP/git própria), em 3 peças:

1. **Índice gerado** — `scripts/governance/adr-index-generate.mjs` é a fonte única; os 4 índices manuais (README/INDEX_TEMATICO/_INDEX-LIFECYCLE/INDEX) são aposentados/rebaixados a ponteiro. ✅ **FEITO (PR #2391)** — 260 arquivos · 245 números · **230 ativos** · 13 colisões · 14 alertas de supersessão.
2. **Supersede atômico** — 1 comando que cria a nova ADR + marca a antiga (status/lifecycle/superseded_by) numa transação só. 🟡 a construir.
3. **Status-mutável sob append-only** — gate libera editar status/lifecycle/kind (não o corpo) via label `adr-metadata-normalization`. ✅ **na ADR 0257 (PR #2387)**.

**Catraca (anti-drift, fitness-function):** `--check` do gerador no CI bloqueia PR cujo índice esteja desatualizado; `memory-health` (ADR 0256) ganha checks de colisão + supersede-integrity. 🟡 wire pendente.

## Consequências

- ✅ "Quantos ADRs ativos?" = `lifecycle: ativo` no índice gerado (230). Reproduzível.
- ✅ Drift de índice impossível (derivado + catraca `--check`).
- ✅ Rebaixamento "pega" (supersede atômico + gate libera status).
- ✅ Alinha com a indústria; para de reinventar.
- ⚠️ Custo: construir peça #2 + wire CI. Aposentar 4 índices manuais com cuidado (`_INDEX-LIFECYCLE` é consumido pelo `decisions-search`).

## Implementação (ondas)
- ✅ Peça #1 (gerador) — PR #2391
- ✅ Peça #3 (gate status-mutável) — PR #2387 (ADR 0257)
- 🟡 Peça #2 (supersede atômico) — comando novo
- 🟡 Catraca — `--check` no CI + checks no `memory-health`
- 🟡 Aposentar os 4 índices manuais → banner pro `_INDEX-GENERATED`

`status: proposto` — Wagner ratifica.

## Refs
- Pesquisa: [Log4brains](https://github.com/thomvaill/log4brains) · [adr.github.io/adr-tooling](https://adr.github.io/adr-tooling/) · [AWS ADR process](https://docs.aws.amazon.com/prescriptive-guidance/latest/architectural-decision-records/adr-process.html) · [fitness functions](https://dev.to/alexandreamadocastro/stop-architecture-drift-operationalizing-adrs-with-automated-fitness-functions-22oi) · [Rust RFC](https://rust-lang.github.io/rfcs/0002-rfc-process.html)
- Auditoria: `memory/governance/AUDITORIA-CONFLITOS-ADR-2026-06-07.md` (PR #2390)
- Construído: `scripts/governance/adr-index-generate.mjs` (PR #2391) · ADR 0256 · ADR 0257
