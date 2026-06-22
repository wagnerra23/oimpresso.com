---
date: "2026-06-21"
topic: "Verificação do estado real da rede de enforcement (Onda 0) contra o código vivo"
authors: [C]
type: verificacao-tecnica
tema: rede-seguranca-enforcement-onda0
escopo: estado real dos 4 tijolos da rede anti-quebra (caminho do dinheiro, floor/nightly, pcov, scorecard/migration, gates required) verificado contra o código vivo
metodo: workflow de verificação (5 verificadores read-only paralelos, schema estruturado, refutando claims do blueprint contra git)
autor: claude-code (Opus 4.8) — sessão Wagner
status: verificação concluída; alimenta a proposta da Onda 0
adrs_citados: [0066, 0093, 0175, 0212, 0273, 0275, 0279]
proposta: memory/decisions/proposals/onda-0-rede-seguranca-enforcement.md
blueprint: memory/sessions/2026-06-21-blueprint-sdd-vertical-viva.md
---

# Verificação — estado real da rede de enforcement (Onda 0)

## TL;DR

Antes de escrever o plano da Onda 0 ("garantir que o sistema não quebre de novo"), 5 verificadores read-only checaram cada tijolo da rede contra o código vivo. **Resultado: a rede está bem mais avançada do que o [blueprint](2026-06-21-blueprint-sdd-vertical-viva.md) (baseado no audit de 20/jun) afirmava.** Várias peças fecharam entre 18-20/jun. O que sobra de verdade são **4 bricks pequenos e nítidos**, sendo a peça-chave (promover 1 gate SDD a `required`) já *pronta pra acontecer*. Isto corrige o blueprint e dimensiona a Onda 0 pra baixo.

## Estado real por tijolo (verificado)

| Tijolo | Blueprint dizia | Realidade verificada | Veredito |
|---|---|---|---|
| **1 · Caminho do dinheiro** | "num_uf inflou ×100k, sem rede" | `num_uf` **já consertado** (heurística pt-BR `app/Utils/Util.php:31-104`, 27/mai); ponte Observers OK e idempotente (`Modules/Financeiro/Observers/TransactionObserver.php`, `TransactionPaymentObserver.php`); `Adr0175ObserverContaOpcionalTest.php` existe. **MAS:** sem teste dedicado pra `num_uf` nem pro +3h ([ADR 0066](../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)) | **PARCIAL** — falta travar (characterization) o que já funciona |
| **2a · Floor + nightly** | "transportar floor CT100→main; conflito 274 vs 1514" | Floor real = **274** (`governance/nightly-floor.json`); "1514/1500-3000" era extrapolação já descartada. **Transporte CT100→main FECHADO** (handoff 18/jun, [ADR 0279](../decisions/0279-sdd-medir-governar-floor-nightly.md), branch órfã `governance/nightly-floor`, lido em `sdd-scorecard.yml`). JUnit wired (`ci.yml:110`, `financeiro-pest.yml:117`) | **✅ FEITO** |
| **2b · pcov / coverage_pct** | "ligar pcov → número real" | `ci.yml:32` `coverage: none`; `:110` `--no-coverage`; sem pcov nas extensions; `phpunit.xml` sem `<coverage>`; nenhum gate de cobertura de código | **❌ ABERTO** (único buraco de medição real) |
| **2c · Migration scorecard** | "aplicar em prod (≥1 row)" | Migration **existe** (`Modules/Governance/Database/Migrations/2026_06_12_100000_create_mcp_sdd_scorecard_history_table.php`); snapshot **agendado live 07:10 BRT** (`app/Console/Kernel.php:327`). Aplicação em prod **não verificável daqui** | **MÁQUINA PRONTA** — falta confirmar prod |
| **3 · 1º gate required + counterfactual** | "0 dos required são SDD; promover 1" | **Confirmado: 0 dos 17 required são SDD** (`governance/required-checks-baseline.json`). `multi-tenant-gate.yml` existe e é skip-as-pass (promovível sem deadlock). `NoMissingTenantScopeRule` = **117 violations** no baseline; `NoSilentFallbackRule` = 104; `NoNopMutationControllerRule` = 2 | **❌ ABERTO** — peça-chave, e pronta |
| **4 · Ratchet / armamento** | "regra de armamento + tamper-guard" | [ADR 0275](../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §3 (3 medições → arma) + §5 (máx 1 promoção/semana); `baseline-tamper-guard.mjs` mergeado (#3128); `gate-selftest.mjs` cobre **5 catracas** (good/bad fixtures) | **✅ FEITO** (operar, não construir) |

## Correções ao blueprint

1. **Floor: 274, não "1514"** — número real medido (interseção de 3 runs nightly); a faixa "1.500-3.000" era extrapolação pré-medição, já refutada pelo handoff de 18/jun.
2. **"Transportar floor CT100→main" não é trabalho aberto** — fechou em 18/jun ([ADR 0279](../decisions/0279-sdd-medir-governar-floor-nightly.md)). `full_suite_pass_rate` já lê o floor real.
3. **"8/8 gate-selftest"** — na verdade são **5 catracas** (knowledge-drift, foundation-ratchet, ledger-check, sdd-scorecard, memory-health), cada uma com fixture good+bad. O "8/8" do blueprint era impreciso.
4. **num_uf não é mais bug aberto** — está consertado; o risco é *regressão silenciosa* por falta de teste.

## Implicação pra Onda 0

A rede de medição/observabilidade está ~feita. O que falta é **fechar o laço de governar**: (A) travar o caminho do dinheiro com testes, (B) ligar pcov, (C) confirmar a migration em prod, (D) **promover o 1º gate SDD a required com counterfactual** — sendo D a maior alavanca e a única que converte "mede" em "governa". Plano executável na [proposta](../decisions/proposals/onda-0-rede-seguranca-enforcement.md).

## Fontes

Verificação por workflow de 5 agentes read-only (308k tokens, 178 leituras) contra `origin/main` em 2026-06-21. Paths e números citados acima foram confirmados arquivo-a-arquivo; o que não pôde ser confirmado (aplicação da migration em prod) está marcado como tal.
