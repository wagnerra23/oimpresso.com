---
titulo: Programa de Ondas com Adversário por Módulo + Régua de Correção por Tela
status: proposto
owner: W
criado: '2026-07-02'
related_adrs:
  - '0256-knowledge-survival-meia-vida-catraca-sentinela'
  - '0264-governanca-executavel-trio-dominio-e2e'
  - '0271-revisao-gates-ci-estado-real-required-e-subtracao-segura'
  - '0105-cliente-como-sinal-guiar-sem-mandar'
---

# PLANO MESTRE — Programa de Ondas com Adversário por Módulo

> **Este é o plano mestre do sistema inteiro.** Índice das etapas (cada uma em seu arquivo).
> Origem: sessão 2026-07-02 — diagnóstico de que módulos de nota alta escondem cálculo de
> valor indefeso (a camada do incidente `num_uf`, R$ inflado ×100k).

## Por que existe

Telas migradas (`/perfil`) e módulos de nota alta (`Financeiro` 82) escondem **cálculo de
valor indefeso**. Verificado em `origin/main` (2026-07-02):

- **6/6 métodos de cálculo core sem teste** (`calculateInvoiceTotal`, `getTotalPaid`≠`getTotalAmountPaid`, `calculatePaymentStatus`, `updateGroupTaxAmount`, `recalculateSellLineTotals`).
- **211 telas Tier-0 sem teste de comportamento** (E2E=4 de 242 telas).
- **31 migrações Blade→React sem nenhuma verificação de paridade**, 0 gate.

A causa raiz: as **3 réguas do projeto não se sobrepõem** e deixam um buraco no meio —
`screen-grade` (UX), `module-grade` (estrutura), `.casos.md` (comportamento, **ortogonal, fora
das notas**). Ninguém liga "a tela funciona" à foto por tela. Nota de garantia ≈ **28/100**
ponderada por risco Tier-0.

## Princípio: reusar + plugar + encaixar (não construir paralelo)

O projeto **já tem todas as peças**. Este programa:
- **Reusa** — `capterra-senior` (adversário), `/comparativo` (gaps+backlog+changelog), `screen-grade`, a catraca/sentinela da [ADR 0256](../../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md).
- **Pluga** — a dimensão de comportamento/valor que falta na régua por tela (não funde — funde destruiria a clareza "tela bonita ≠ tela testada").
- **Encaixa** — respeita a regra T6: roadmaps ativos seguem (OficinaAuto Fase 3, PaymentGateway), e Financeiro/NfeBrasil/RecurringBilling entram no `_Roadmap_Faturamento.md` existente, não em paralelo.

## Régua + âncoras de estado-da-arte 2026 (nota atual)

| Âncora (o "topo" 2026) | Mede | oimpresso |
|---|---|---|
| Property-based + golden money datasets (fintech QA) | Cálculo de valor correto | **15** |
| Infection PHP (`min-msi 85 / covered 95`) | Testes pegam bug injetado | **20** (mutation-gate advisory, não roda c/ Pest) |
| Pact consumer-driven (interaction-level) | Contrato defendido | **25** (casos-gate required, 8% coberto) |
| Parallel-run / GitHub Scientist / strangler fig | Migração preservou função | **8** (zero mecanismo) |
| Coverage ratchet + Google Test Certified | Cobertura sobe até um piso | **40** (catraca sem piso) |
| Enforcement/durabilidade ([ADR 0256](../../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)) | Máquina que faz durar | **70 — mas guarda a porta errada** |

O insight: **D8=70 vs D1=15**. A máquina de governança é de classe mundial, mas protege
segurança (multi-tenant/PII/secrets) e é cega no cálculo de dinheiro. O caminho é
**reapontar**, não reconstruir — coerente com a fase de subtração da [ADR 0271](../../../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md).

## O ciclo-padrão de UMA onda (4 passos)

Cada onda de módulo roda estes 4 passos, reusando ferramentas que já existem:

1. **Adversário concorrente** — agente `capterra-senior` → `CAPTERRA-FICHA.md` (nota 0-100 vs 10-15 concorrentes, P0-P3).
2. **Gaps + backlog + changelog** — skill `/comparativo <Mod>` → `CAPTERRA-INVENTARIO.md` (3 buckets ✅🟡❌) + batch `tasks-create` (MCP) + US no SPEC + changelog.
3. **Régua por tela (com a dimensão que falta plugada)** — `screen-grade` (UX) **+ `casos_coverage`** (UCs que defendem + status) **+ dente de cálculo** (D1) se toca valor.
4. **Catraca** — trava nota + `casos-gate` + a sentinela de cadência reporta o débito das 3 camadas.

## Fila de ondas (T6 — encaixar, não duplicar)

- **Roadmaps ativos seguem intactos:** OficinaAuto Fase 3 (canary Martinho), PaymentGateway (smoke/canary).
- **Faturamento é o canon macro:** ondas de Financeiro / NfeBrasil / RecurringBilling **encaixam** em `_Roadmap_Faturamento.md`.
- **Novas ondas (operacionais sem programa), por exposição×débito:** **Sells (piloto) → Compras (nota 59) → Produto → Cliente**. Cada uma exige OK [W] antes de abrir.

## Índice das etapas (arquivos)

| Etapa | Arquivo | O que entrega |
|---|---|---|
| Onda 0a | [onda-0-fundacao/0a-adr-proposta.md](onda-0-fundacao/0a-adr-proposta.md) | ADR que trava o mecanismo |
| Onda 0b | [onda-0-fundacao/0b-extensao-regua.md](onda-0-fundacao/0b-extensao-regua.md) | `casos_coverage` + dente de cálculo na régua |
| Onda 0c | [onda-0-fundacao/0c-sentinela-cadencia.md](onda-0-fundacao/0c-sentinela-cadencia.md) | sentinela `exposicao-tier0.mjs` + cron |
| Onda 1.1 | [onda-1-sells/1.1-adversario-capterra.md](onda-1-sells/1.1-adversario-capterra.md) | ficha de mercado de Sells |
| Onda 1.2 | [onda-1-sells/1.2-gaps-backlog-changelog.md](onda-1-sells/1.2-gaps-backlog-changelog.md) | inventário + backlog + changelog |
| Onda 1.3 | [onda-1-sells/1.3-regua-por-tela.md](onda-1-sells/1.3-regua-por-tela.md) | telas Sells gradeadas c/ comportamento |
| Onda 1.4 | [onda-1-sells/1.4-dente-calculo.md](onda-1-sells/1.4-dente-calculo.md) | teste que pega o `num_uf` |
| Onda 1.5 | [onda-1-sells/1.5-catraca-sentinela.md](onda-1-sells/1.5-catraca-sentinela.md) | trava os ganhos |
| Template | [template-onda-modulo.md](template-onda-modulo.md) | gabarito p/ próximos módulos |

## Sequência de execução

**Onda 0 primeiro** (a máquina) → **Onda 1 Sells** (calibra + prova) → **template** pronto → próximos módulos com OK [W]. A ADR-proposta (0a) abre tudo; nada de código antes dela.
