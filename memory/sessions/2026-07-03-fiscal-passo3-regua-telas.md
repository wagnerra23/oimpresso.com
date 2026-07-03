---
date: '2026-07-03'
topic: 'Fiscal Passo 3 — régua por tela (casos_coverage + d1_calculo nas 7 sub-páginas)'
authors: [C]
outcomes:
  - '7 .casos.md criados (Cockpit/Nfe/Nfse/Dfe/Eventos/Config/Sped)'
  - '7 scorecards estendidos com casos_coverage + d1_calculo'
  - 'Débito unânime = UC-traceability G-2 (0 UC-id apesar de baseline Feature forte)'
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0320-programa-ondas-regua-correcao
  - 0062-separacao-runtime-hostinger-ct100
---

# Session log — 2026-07-03 · Fiscal Passo 3 (régua por tela)

> **Owner:** Wagner "merge e vai" 2026-07-03 · **Base:** worktree fresco `origin/main` @ `cf8565ede5`
> Sequência: Passo 1 (ficha, PR #3738) → Passo 2 (inventário + 2 US, PR #3753) → **Passo 3 (este)**.

## TL;DR

Passo 3 do programa de ondas do **Fiscal**: régua por tela nas **7 sub-páginas** (Cockpit, Nfe, Nfse, Dfe, Eventos, Config, Sped). Para cada tela — `casos_coverage` (comportamentos duráveis derivados dos testes Feature reais) + `d1_calculo` (dente de cálculo) apensados ao scorecard existente, + um `.casos.md` ao lado do `.tsx`. Derivação via **4 agentes paralelos** lendo Controller+Tests+charter de cada tela. **Achado unânime:** o débito das 7 telas é **rastreabilidade G-2 (ADR 0264)**, não ausência de teste — há baseline Feature forte (Cockpit 11 casos, Sped 22, etc.) mas **0 UC-id** citado por teste. **d1_calculo aplica só na Sped** (gera valores fiscais no TXT EFD): `cross_check: true` (SpedMotorTributarioIntegrationTest cruza motor vs fallback com números), mas `golden: false` (o "golden" é reflection/source-grep, não valida bytes vs SEFAZ). Read-only — 14 arquivos de doc/scorecard, zero código.

## O que foi feito

1. Confirmado que Fiscal já tinha 7 scorecards (UX notas: cockpit 82, nfe 84, nfse 76, dfe 79, eventos 75, config 80, sped 68) + 7 charters, mas **sem `casos_coverage` e 0 UC-FISCAL-***.
2. Verificado `casos-coverage-guard.mjs`: G-2 (UC declarado exige teste que cite), G-5 (owner+last_run). Usei **backlog bullets** (não `## UC-*` headings) → não cria órfão. Adicionar casos.md **melhora** o baseline (não piora).
3. 4 agentes paralelos derivaram os casos por tela a partir dos testes reais (nomes de casos Pest citados, sem invenção).
4. Consolidação: 7 `.casos.md` (`resources/js/Pages/Fiscal/`) + 7 scorecards estendidos (`memory/governance/scorecards/screens/fiscal-*.yaml`) + este log.

## Achados por tela (baseline Feature real)

| Tela | UX | Casos c/ teste | Tier 0 destacado | d1_calculo |
|---|---|---|---|---|
| Cockpit | 82 | 11 casos (3 arquivos) | KPIs + cache isolados por biz | não |
| Nfe | 84 | 3 leitura + 14 contratos ação | contagem cross-tenant | não |
| Nfse | 76 | 6 casos (render skipa por schema race `emitted_at`) | isolamento + 403 | não |
| Dfe | 79 | 6 casos (Histórico é mock) | isolamento + whitelist 4 ações | não |
| Eventos | 75 | 3 leitura + mutações que geram eventos | append-only + isolamento | não |
| Config | 80 | ConfigControllerTest + SimplesOnlyGate(4) + Config(3) | senha cert hidden + isolamento + feature-gate 503/403 | não |
| **Sped** | 68 | **22 casos** | cross-tenant RuntimeException | **✅ aplica** (medio; cross_check ✔, golden ✘) |

**Débitos honestos catalogados** (não bug, mas registrados): Nfse render `markTestSkipped` (schema race `nfse_emissoes`), Dfe aba Histórico = `mockHistorico()`, Cockpit tabela de notas = `notasMock`, Config `seriesMock`, Sped Bloco H esqueleto + sem golden do TXT + saldo credor placeholder 0.

## Entregáveis

- 7× `resources/js/Pages/Fiscal/<Tela>.casos.md` (novos)
- 7× `memory/governance/scorecards/screens/fiscal-<tela>.yaml` (estendidos: +casos_coverage +d1_calculo)
- `memory/sessions/2026-07-03-fiscal-passo3-regua-telas.md` (este)

## Próximo (programa de ondas Fiscal)

- **Passo 4** — catraca + sentinela: `casos-gate` passa a defender os UCs quando forem wired (cada backlog vira `UC-FISCAL-NN` no mesmo PR que adicionar o id ao teste que já existe — edição de 1 linha).
- **Trabalho de código real** (sessão dedicada, não read-only): US-FISCAL-021 (P0 IBS/CBS, prazo 03/08/2026) + wire dos UC-ids nos testes existentes.

## Notas de processo

- Read-only: zero mudança em código de `Modules/` ou `.tsx`. Só docs/scorecards/casos.
- Base `origin/main` fresca (worktree novo `claude/fiscal-regua-onda`).
- casos.md usam backlog bullets (sem `## UC-*` sem teste) → não quebra G-2 do casos-coverage-guard.
