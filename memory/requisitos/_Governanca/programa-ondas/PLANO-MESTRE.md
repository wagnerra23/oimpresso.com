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

## Status vivo

<!-- catraca: não regride sem mudar status conscientemente · ADR 0294 -->
- **status:** ativo  <!-- proposto→ativo 2026-07-03: [W] aprovou ADR 0320 (Onda 0a) via "aprovado merge" (#3694) -->
- **owner:** W
- **criado:** 2026-07-02 · **reviewed_at:** 2026-07-03 · **próxima-revisão:** 2026-08-02
- **cycle:** off-cycle (programa transversal) · **execução:** `parent_plan=programa-ondas` — **Ondas 0+1+2+3 LANDADAS em paralelo 2026-07-03** (0a-0d, Sells 1.x, Compras 2.x, Financeiro ✅; ~24 PRs #3694-#3726) + **dente Produto** (#3730) e **dente Cliente** (#3731). DoD da Onda 1 batido. Resíduo: tasks MCP dos gaps a criar mediante OK [W]
- **gate-de-saída (DoD):** ✅ **BATIDO 2026-07-03** — dente de cálculo red/green no CT100 (15 passed, #3695) + `sells-create.yaml` exibindo UX 88 **e** `casos_coverage 0%/🔴` + template calibrado. Ondas seguintes: Produto → Cliente (com OK [W])
- **kill-condition:** ADR 0a rejeitada por [W], OU 2 cycles sem nenhuma etapa executada → status `abandonado` (não zumbi)
- **verdade-viva:** este doc (etapas na tabela abaixo; os arquivos-etapa detalham, o status vive AQUI — 1 plano = 1 registro no índice)

| Etapa | Arquivo | Task MCP | Status | Esforço (ADR 0106) |
|---|---|---|---|---|
| 0a ADR-proposta | onda-0-fundacao/0a | — | ✅ ADR 0320 (#3694) | ~2h |
| 0b Extensão da régua | onda-0-fundacao/0b | — | ✅ (#3698) | ~4h |
| 0c Sentinela de cadência | onda-0-fundacao/0c | — | ✅ (#3697) | ~4h |
| 0d Paridade de migração | onda-0-fundacao/0d | — | ✅ (#3696) | ~6h |
| 1.1 Adversário Sells | onda-1-sells/1.1 | — | ✅ CAPTERRA-FICHA nota 60 (#3699) | ~3h (agent) |
| 1.2 Gaps+backlog Sells | onda-1-sells/1.2 | ⚠️ tasks a criar (OK [W]) | ✅ US-SELL-054..057 no SPEC (#3702) | ~2h + OK [W] |
| 1.3 Régua nas telas Sells | onda-1-sells/1.3 | — | ✅ 8 scorecards (sells-create UX 88 · casos 0%🔴) | ~4h |
| 1.4 Dente de cálculo | onda-1-sells/1.4 | — | ✅ (#3695) | ~6h (CT100) — red/green CT100 (15 passed) |
| 1.5 Catraca+sentinela Sells | onda-1-sells/1.5 | — | ✅ (#3700) | ~2h — emergente de 0c+1.3+1.4 · verificado 2026-07-03 (sem gate novo) |
| **Onda 2 — Compras** (OK [W] 2026-07-03) | — | ⚠️ tasks a criar | ✅ ciclo completo | — |
| 2.1 Adversário Compras | (template) | — | ✅ CAPTERRA-FICHA capacidade **nota 34** + BRIEFING (#3719/#3714) | ~3h |
| 2.2 Gaps+backlog Compras | (template) | ⚠️ 10 US no INVENTARIO (#3717) | ✅ backlog materializado | ~2h |
| 2.3 Régua Compras/Index | (template) | — | ✅ charter + `compras-index.yaml` | ~2h |
| 2.4 Dente de cálculo Compras | (template) | — | ✅ `CalculoValorComprasTest` E2E valor+estoque `POST /purchases` (#3722) + lane MySQL (#3723) | ~4h (CT100) |
| **Onda 3 — Financeiro** (OK [W] 2026-07-03) | — | ⚠️ tasks a criar | ✅ camada de correção | — |
| 3.dente Financeiro | ancorado em [`_Roadmap_Faturamento.md`](../../_Roadmap_Faturamento.md#camada-de-correção-contínua-dente-de-cálculo--programa-de-ondas) (ADR 0320 — encaixe T6) | — | ✅ `calculatePaymentStatus`+`updateGroupTaxAmount` red/green CT100 (#3710) | ~6h |
| 3.régua Financeiro | ancorado no roadmap (ADR 0320) | — | ✅ charter+casos+régua CR/CP (#3712) → **decisão [W]: deprecar CR/CP → Unificado** (#3718) | ~4h |
| **Onda 4 — Produto** (OK [W] 2026-07-03) | — | ⚠️ tasks a criar | 🟡 só o dente por ora | — |
| 4.dente Produto | (template 1.4) | — | ✅ `CalculoValorProdutoTest` — motor preço/margem indefeso: markup/`calc_percentage`+`get_percent`+`getVariationGroupPrice`+combo, 21 passed CT100 (#3730) | ~4h (CT100) |

> Onda 3 (Financeiro) **encaixa no `_Roadmap_Faturamento.md`** por [ADR 0320](../../decisions/proposals/0320-programa-ondas-regua-correcao.md) (T6 — Faturamento é canon macro; correção transversal ancora lá, status vivo aqui). Não é doc paralelo. Mesmo padrão valerá pra NfeBrasil/RecurringBilling.

> Estimativas em horas-agente IA-pair (fator 10x ADR 0106); tarefas humano-limitadas (OK [W], canary) seguem relógio real.

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

## Encaixe na governança de planos existente (anti-paralelo)

- Este programa vive ao lado de [`_Governanca/roadmap/`](../roadmap/) (P01-P10, etapas SDD) **sem sobrepor**: aquele roadmap cuida da suíte/gates de Governance; este cuida do ciclo adversário+régua **por módulo de negócio**. Se qualquer etapa daqui colidir com um P-item de lá, o P-item vence e esta etapa vira referência a ele.
- Registro no índice de planos vivos: **só este arquivo** carrega o bloco `## Status vivo` (ADR 0294 — 1 plano = 1 registro no [`PLANS-INDEX-GENERATED.md`](../../_processo/PLANS-INDEX-GENERATED.md)); os arquivos-etapa apontam pra cá.
- Execução rastreada no MCP (`tasks-create` com `parent_plan=programa-ondas`, ADR 0070) — nunca status em markdown de etapa.

## Índice das etapas (arquivos)

| Etapa | Arquivo | O que entrega |
|---|---|---|
| Onda 0a | [onda-0-fundacao/0a-adr-proposta.md](onda-0-fundacao/0a-adr-proposta.md) | ADR que trava o mecanismo |
| Onda 0b | [onda-0-fundacao/0b-extensao-regua.md](onda-0-fundacao/0b-extensao-regua.md) | `casos_coverage` + dente de cálculo na régua |
| Onda 0c | [onda-0-fundacao/0c-sentinela-cadencia.md](onda-0-fundacao/0c-sentinela-cadencia.md) | sentinela `exposicao-tier0.mjs` + cron |
| Onda 0d | [onda-0-fundacao/0d-paridade-migracao.md](onda-0-fundacao/0d-paridade-migracao.md) | artefato+gate de paridade Blade↔React (a pior dimensão: 8/100) |
| Onda 1.1 | [onda-1-sells/1.1-adversario-capterra.md](onda-1-sells/1.1-adversario-capterra.md) | ficha de mercado de Sells |
| Onda 1.2 | [onda-1-sells/1.2-gaps-backlog-changelog.md](onda-1-sells/1.2-gaps-backlog-changelog.md) | inventário + backlog + changelog |
| Onda 1.3 | [onda-1-sells/1.3-regua-por-tela.md](onda-1-sells/1.3-regua-por-tela.md) | telas Sells gradeadas c/ comportamento |
| Onda 1.4 | [onda-1-sells/1.4-dente-calculo.md](onda-1-sells/1.4-dente-calculo.md) | teste que pega o `num_uf` |
| Onda 1.5 | [onda-1-sells/1.5-catraca-sentinela.md](onda-1-sells/1.5-catraca-sentinela.md) | trava os ganhos |
| Template | [template-onda-modulo.md](template-onda-modulo.md) | gabarito p/ próximos módulos |

## Sequência de execução

**Onda 0 primeiro** (a máquina) → **Onda 1 Sells** (calibra + prova) → **template** pronto → próximos módulos com OK [W]. A ADR-proposta (0a) abre tudo; nada de código antes dela.
