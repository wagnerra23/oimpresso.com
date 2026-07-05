---
title: "Onda 2 do plano de aprofundamento — Compras 58→74 + AUDITORIA PaymentGateway (Check X zerado)"
slug: onda2-avaliacoes-compras-paymentgateway
kind: session
date: "2026-07-05"
topic: "execução da Onda 2 do PLANO-APROFUNDAMENTO-AVALIACOES — módulos Tier-0 mais fracos (Compras/PaymentGateway): diagnóstico module:grade CT100, fix de 2 falso-negativos do grader, testes canônicos Compras, AUDITORIA PaymentGateway que zera o Check X"
authors: [C]
prs: [3832, 3833, 3834]
related_adrs:
  - "0155-module-grade-rubrica-v3"
  - "0170-paymentgateway-extracao-camada-cobranca"
  - "0093-multi-tenant-isolation-tier-0"
  - "0101-tests-business-id-1-nunca-cliente"
tags: [onda2, module-grade, compras, paymentgateway, check-x, falso-negativo]
---

# Onda 2 — Compras + PaymentGateway: de nota-fria pra diagnóstico + fix medido

## Contexto

Execução da **Onda 2** do [PLANO-APROFUNDAMENTO-AVALIACOES](../requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md) (PR #3820, lido da branch `claude/plano-sem-onda-3` — ainda não mergeado). Pré-reqs cumpridos: Compras/SPEC.md + PaymentGateway SPEC + ADR 0170 (status `later` = docs only) + §REGRA MESTRE lidos ANTES de qualquer Edit. Descoberta inicial importante: **fichas + inventários + batch já existiam** (Onda 2.1 do programa-ondas, 2026-07-03, PRs #3708-#3715 e #3736) — pelo T6, nada foi recriado; a sessão entregou só o que faltava.

## O que foi feito

1. **Diagnóstico `module:grade --detail --evolve` no CT100** (staging, código = origin/main): Compras **60/100**, PaymentGateway **64/100** (baseline 58/60 — ambos já subindo).
2. **Descoberta central: ~10 pontos do gap do Compras eram falso-negativo de DETECTOR, não gap de código.** D8.a não via `throttle:60,1` declarado em array de middleware (forma canônica UltimatePOS — o throttle REAL existe desde o audit sênior Gap #3); D4.d não via `OtelHelper::spanBiz` (que o D9.a JÁ reconhece — ADR 0156 §Errata 1) nem `Spatie\Activitylog`. Fix no grader com 3 testes pareados (cenários 18-20) — [PR #3833](https://github.com/wagnerra23/oimpresso.com/pull/3833).
3. **Pacote Compras ≥70 sem tocar valor/estoque** — [PR #3834](https://github.com/wagnerra23/oimpresso.com/pull/3834): testes canônicos `ComprasSmokeRoutesTest` (catraca do throttle + 404 defense-in-depth + 403) e `ComprasScaffoldTest` (nWidart + Install ADR 0024 + catraca retention ≥1825d) · `retention_days: 1825` no module.json (fiscal 5 anos) · `na_justified_v3` D6.c no SPEC (paginate no controller opera query do Service com joins+with; N+1 travado por `ComprasListagemNPlusUmTest` #3715).
4. **AUDITORIA PaymentGateway** — [PR #3832](https://github.com/wagnerra23/oimpresso.com/pull/3832): diagnóstico D1-D9 + leitura de risco REGRA MESTRE (tabela caminho-de-valor × defesa × residual) + separação falso-negativo vs gap real (nº 1: **D9.a 0/23 services sem OTel** — observabilidade de dinheiro). Só diagnóstico, zero implementação (ADR 0170 later). **Zera o Check X** — provado por contrafactual (sem o doc: flag 1 `PaymentGateway`; com o doc: 0).

## Números (module:grade CT100, evidência literal nos PRs)

| Módulo | baseline v3.5.5 | hoje (main) | pós-#3834 | pós-#3833+#3834 |
|---|---|---|---|---|
| Compras | 58 | 60 | 69 | **74 ✅ ≥70** |
| PaymentGateway | 60 | 64 | — | 64 (estável — gaps reais continuam visíveis) |

Amostra anti-regressão com grader novo: Governance 88→89 · Jana 71→74 · Financeiro 82→83 · Crm 87→88 (monotônico ↑, detecção só adiciona caminhos).

## Pest CT100 (container oimpresso-staging, MySQL real, biz=1)

- Cenários 18-20 grader: **3 passed (7 assertions)**.
- Compras Smoke+Scaffold: **9 passed (19 assertions)**.
- Regressão `--filter=ModuleGradeService`: 60 passed / 4 failed — as mesmas 4 falhas ocorrem no código ANTIGO (expectativas stale de AssetManagement/D2.c no ambiente staging): **0 falhas novas**.

## Lições

- **Antes de "consertar o módulo", audite o DETECTOR.** Dois dos cinco top-gaps do Compras eram regex míope — consertar o grader beneficia os 36 módulos e evita trabalho de gaming (ex.: reescrever rota só pra casar com o regex).
- **Hardlink resolve o que symlink quebra em worktree de teste no CT100**: `ln -s vendor` faz o autoload resolver `__FILE__` pro checkout ERRADO (classes velhas com arquivos novos — o primeiro re-grade deu 69 "sem explicação"); `cp -al` dentro do MESMO filesystem (bind-mount! `/tmp` é outro device e o hardlink falha silencioso criando dirs vazios) dá autoload correto. Receita: worktree em `/var/www/html/.claude/worktrees/<x>` + `cp -al vendor` + `.env` + skeleton `storage/framework/*`.
- **na_justified_v3 é a ferramenta certa pra falso-negativo de heurística per-file** quando existe teste físico provando o invariante (D6.c + ComprasListagemNPlusUmTest) — declarado, auditável no PR, precedente Financeiro D1.c.

## Pendências (decisão humana)

- **Merge dos 3 PRs** = Wagner (R10). Ordem sugerida: #3833 → #3834 → #3832 (o re-grade 74 exige os dois primeiros; baseline reconcilia via snapshot).
- **Batch de tasks** dos inventários (Compras 2026-07-03 + PaymentGateway 2026-07-03 + evolve_tasks dos grades) — aguarda aprovação Wagner; nenhuma task auto-criada.
- Gaps honestos que FICAM no Compras: D5 cliente real (decisão ADR 0105), D1.a entities, D4.b FSM, D7.a PiiRedactor.
