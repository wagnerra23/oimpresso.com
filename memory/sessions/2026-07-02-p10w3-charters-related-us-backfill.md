---
date: "2026-07-02"
topic: "P10 wave 3 — backfill related_us nos charters de tela (join US→tela): 25 charters editados + 15 deferidos, refutador Fable 5 aprovou 65/66"
authors: [C]
type: execucao-campanha-sdd
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
---

# P10 wave 3 — backfill `related_us` nos charters (join US→tela)

## Objetivo

Preencher o campo canônico `related_us:` no frontmatter dos charters de tela
(`resources/js/Pages/**/*.charter.md`) que ainda não o tinham. Ponto de partida:
30 de 158 charters com o campo. O `related_us` é o join US→tela (fonte SA-A5/P10,
[ADR 0273](../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md));
o `us:` ad-hoc foi deprecado pelo PR #3157. Lint: `scripts/governance/charter-us-lint.mjs`
(advisory-de-nascença, `--check` só morde charter tocado). Schema:
`scripts/memory-schemas/charter.schema.json` (`related_us` opcional, grace-period).

## Método (Tier 0: nunca inventar US)

1. Base fresca de `origin/main` (worktree novo — checkout local estava −4586).
2. `git grep -L related_us` → 114 charters sem o campo.
3. Separei **trabalháveis** (draft/deprecated/live-com-sinal, 77) de **deferidos**
   (`status:live` sem sinal de prod, 37) — o gate required `charter-live-signal`
   barra tocar charter live sem sinal, então esses vão pra fila A6 §3-bis, não são editados.
4. Join derivado por evidência real, em ordem de força:
   - **join reverso da âncora** `**Implementado em:**` (ADR 0273): a US cujo anchor
     do SPEC cita o `.tsx` da tela;
   - citação explícita de rota/Page/Controller no texto da US;
   - `Inertia::render('<Pages/...>')` do Controller citado na US (telas sem anchor-`.tsx`).
5. Charter sem US identificável fica **sem o campo** (não se inventa slug) — catalogado
   como órfã no relatório.

## Resultado

- **25 charters editados** (só frontmatter — charter é canon de design, conteúdo intocado):
  Fiscal ×7, Cliente ×3, KB ×4, ProjectMgmt Inbox+Triage, Purchase/Create,
  RecurringBilling/Index, Auditoria/Index, Admin Index+FeatureFlags×2,
  team-mcp/Team/Index, superadmin/Usuario360 ×2.
- **15 joins deferidos** (charters `status:live` sem sinal) estacionados na fila A6 §3-bis
  — prontos pra aterrissar num PR trivial quando a tela ganhar smoke/prod-flag:
  Atendimento ×4, ProjectMgmt/Board, RecurringBilling Faturas/Planos/Configuracoes,
  Sells/Create, governance ×6.
- **Cobertura de charter:** 30/158 → 55/158 (edições) + 15 na fila.

## Verificação adversarial (protocolo G5)

Refutador Fable 5 (tier superior ao gerador Opus 4.8), sessão fresca, 100% dos anchors:
- **66 joins US verificados · 1 refutado · error_rate 1,5% < 2% → aprovado.**
- Refutado: `Purchase/Edit → US-COM-004` — a US é infra de deprecação de rota
  (persona "não user-facing", anchor só cita Controllers PHP), não descreve a tela Edit.
  **Removido antes do merge** (charter revertido → volta a ser órfã).
- Ressalvas não-bloqueantes (registradas na fila A6): `US-RECURRINGBILLING-007` é duplicata
  declarada de `US-RB-042` (listar só 042); `US-TR-301` tem homônimo em `TaskRegistry/SPEC`
  (webhook GitHub) — o join da tela Triage é o correto, ID reusado é hazard de rastreabilidade.
- Entry no ledger `governance/sdd-verification-ledger.json` (`SA-A5-P10w3-charters`, #3636);
  `ledger-check --pr 3636` → `ok:true`.

## PRs

- [#3633](https://github.com/wagnerra23/oimpresso.com/pull/3633) Fiscal (7)
- [#3634](https://github.com/wagnerra23/oimpresso.com/pull/3634) Cliente+KB (7)
- [#3635](https://github.com/wagnerra23/oimpresso.com/pull/3635) Gestão — ProjectMgmt+Purchase+RB+Auditoria (5)
- [#3636](https://github.com/wagnerra23/oimpresso.com/pull/3636) Admin+MCP (6) + fila A6 §3-bis + ledger + este log

## Órfãs (charter sem US identificável — ~62, ficam sem campo)

Produto ×8 (sem SPEC), Repair ×8 (SPEC = placeholder TODO), Sells ×6
(Show/Edit/Drafts/Quotations/Subscriptions/Caixa não citadas no SPEC), team-mcp ×4,
Cliente Map/Import/Ledger (só listadas na tabela de Pages, sem US própria),
TransactionPayment ×3 (sem SPEC; Edit cita RB-044 só negativamente), Stock* ×4,
Suporte ×2, ComVis/Manufacturing/Orcamento/Settings/User/governance-DsRollout ×1,
Admin GovernanceV4/RagQuality/ScreenReview. Fechar essas exige **criar/escrever US
no SPEC dono** (não é trabalho de backfill) — fora do escopo desta wave.

## Proposta pro Wagner (NÃO executada — decisão dele)

Cobertura de charter-us subiu mas continua parcial (~35% pós-merge). O gate
`charter-us-gate` é advisory-de-nascença (só morde charter tocado). **Não** recomendo
promover a required agora, nem criar catraca agregada no scorecard: (a) a cauda de
órfãs (~62) depende de **escrever US nova no SPEC**, não de join — uma catraca de
cobertura agregada pressionaria a inventar slug (anti-Tier-0); (b) alinhado à ADR 0314
(required = só Tier-0), `related_us` é rastreabilidade/higiene, não dinheiro/PII/fiscal.
Se Wagner quiser um floor, o caminho honesto é **por-charter no-new-lie** (o `--check`
atual já faz isso) + backfill incremental das órfãs conforme os SPECs ganham US —
não um número agregado que trava. Decisão fica com o Wagner.
