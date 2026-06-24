---
date: "2026-06-23"
topic: "Reabertura da robustez da âncora sob a premissa corrigida (~150 clientes fiscais, não 1) — 2 dos 4 gaps mudaram, guarda anti-over-correção, nova ordem de prioridade"
authors: [C]
type: session
module: governance
pii: false
related_adrs:
  - 0303-anchor-lint-wired-testado-sa-a2-bis
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# Robustez da âncora — re-score sob ~150 clientes fiscais

## TL;DR

Wagner corrigiu a premissa "cliente único" ([escala-migracao-carteira-delphi.md](../reference/escala-migracao-carteira-delphi.md)): são ~150 clientes Delphi migrando pro online. Workflow ultracode (9 agentes: 4 gaps × avaliador+adversário → síntese) reabriu os 4 gaps que o design tinha descartado como "over-engineering". Resultado **honesto**: a escala mudou **1 gap inteiro + 1 metade**, NÃO tudo — o adversário guardou contra a over-correção "150 justifica robustez". Ganho de nota **modesto (~+2-3 → ~73-74)**, concentrado onde a escala torna **fail-silent fiscal** crítico. **Ironia capturada:** o avaliador deferiu a sentinela SEFAZ ancorando num `_pendente_` STALE (US-NFE-002 SPEC:52), e o adversário provou que a emissão EXISTE (`NfeService.php:661,852` cStat hardcoded; SPEC:725 "pipeline fechado em main") — um workflow anti-anchor-rot caiu numa âncora podre.

## Veredito por gap

| Gap | Revisado | Razão |
|---|---|---|
| **rename-proof** (P6) | **ainda-defer** | Escala não toca: rename é **fail-LOUD** (`existsSync` false → `anchored_dead` 🔴, `anchor-lint.mjs:233-244`), nunca mascara bug-como-verde. 150 amplificam fail-SILENT; rename está do lado ruidoso. Frequência de rename = f(código), não f(clientes). AST viola fs-puro. |
| **verde-real** (P4) | **vale-PARCIAL** | **A escala INVERTE.** `modules-pest.yml` roda NfeBrasil em **sqlite** (`:88-89`, cabeçalho admite "sqlite mascara bugs"). **34/45 testes chamam `markTestSkipped`**; R-NFE-001/003/005 são MySQL-only (UNIQUE/lock = no-op no sqlite). `verde@` desse lane certifica verde uma suite **que não rodou as regras fiscais** → ×150 = nota errada ×150. Correção do adversário: a "nightly CT100" do design **NÃO existe** — clonar `financeiro-pest.yml` (mysql:8.0) num lane novo. **Per-método NÃO vale** (granularidade = f(formato), não f(clientes)). |
| **bidirecional** (P5) | **vale-PARCIAL** | ADVISORY-report vale (US fiscal sem prova ×150). ENFORCE não (dívida US↔teste é brownfield, f(código)). Adversário corrigiu magnitude: **~3 US** disparam no NfeBrasil, não 22 (os testes existem; o que falha é a âncora — já pego pelos dead_tests reconciliados). |
| **sefaz-sentinela** | **vale-PARCIAL** (CHECK fs-puro vale-AGORA; FETCH-scrape defere) | **Adversário derrubou a premissa falsa do avaliador.** Emissão EXISTE (`EmitirNfceJob.php`, `NfeService.php:661,852,1454,1518` cStat hardcoded; SPEC:725 + `auto_emission_enabled=1`). CHECK fs-puro (catálogo committed dos cStat + catraca de frescor) tem subject REAL agora, ~0.5-0.75d. FETCH-scrape do portal SEFAZ defere-permanente (fonte machine-readable não existe → scrape frágil PIOR a ×150). |

## Nova ordem de prioridade (pós-G1, dado 150 fiscais)

Eixo = **fail-silent fiscal ×150**, não "ruído tolerável de 1 cliente":

1. **verde-real / lane MySQL** (~1-2d) — **maior risco fiscal.** Único gap onde o verde pode MENTIR silencioso e perigoso hoje (34/45 testes pulando no sqlite). Clonar `financeiro-pest.yml` → lane NfeBrasil/Fiscal MySQL, `verde@` aponta pra ele, gate trata `skipped→não-verde` (junit-summary já distingue skipped de passed, `:61/:115`). Pré-req: reconciliar dead_tests — **✅ FEITO (#3312)**.
2. **sefaz CHECK fs-puro** (~0.5-0.75d) — barato, subject real agora. `cstat-catalog.json` dos cStat hardcoded + catraca de frescor. Ressalva: a catraca só vale se o bump exigir diff no CONTEÚDO, não só na data (senão é teatro).
3. **bidirecional req_sem_filho ADVISORY** (~0.5d) — restrito a US `Implementado em:` preenchido + não-dormente; entra no `anchor_coverage` como 🟡 (advisory que ninguém olha = mentira). Ilumina ~3 US no NfeBrasil.

Total da onda: **~2.5-3.25 dev-days**.

## O que CONTINUA defer (guarda anti-over-correção)

A maioria dos defers AINDA vale: rename-proof inteiro (fail-loud), verde per-método (f(formato)), bidirecional ENFORCE (brownfield), sefaz FETCH-scrape (fonte não existe → 150 tornam o scrape PIOR). A escala move SÓ o eixo fail-silent-fiscal.

## Nota projetada

| | G1 | + onda revisada |
|---|---|---|
| P4 prova-comportamento | 6 | ~8 (verde no lane MySQL real) + 0.5-1 (sefaz CHECK) |
| P5 bidirecional | 7.5 | ~8.0-8.5 (req_sem_filho advisory) |
| P7 não-gameável | 6.5 | ~7 (fecha a brecha do verde-sqlite-tudo-skipped) |
| **TOTAL** | **~71** | **~73-74 honesto** (não os +6.5 de um re-score ingênuo) |

A correção mais importante foi **factual, não de julgamento**: a emissão fiscal existe, e o `verde@` de hoje sairia de um lane sqlite onde a maioria dos testes fiscais PULA — esse é o risco real que a escala de 150 clientes torna crítico.
