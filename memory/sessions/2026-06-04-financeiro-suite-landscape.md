---
date: "2026-06-04"
hour: "20:30 UTC"
topic: "Diagnóstico landscape da suíte Pest Financeiro contra schema baseline + catraca allowlist (follow-up US-FIN-052)"
authors: [C]
us: [US-FIN-052]
related_adrs: [0249-qa-de-tela-sustentavel-screen-grade-e2e-catraca]
---

# Financeiro — landscape da suíte Pest contra o baseline (follow-up #2240)

**TL;DR:** Com #2240 mergeado (schema baseline + lane MySQL), rodei a suíte Financeiro
INTEIRA no CI. Estado: **172 failed · 270 passed · 133 skipped** (1197 asserts).
A maioria das falhas NÃO é regressão de negócio — é (a) testes `RefreshDatabase`/`migrate:fresh`
que dropam o schema e **envenenam** os demais, (b) asserts em assets estáticos ausentes no CI,
(c) env/container (CacheManager binding). Plano: **catraca allowlist** — a lane roda só o
verde-comprovado e cresce por lote. Batch 1 sobe a cobertura de 1 → 10 arquivos (87 testes, 348 asserts, verde).

## Como foi medido

- Worktree `fin-suite-green` em `origin/main` (já com #2240).
- 4 rodadas `workflow_dispatch` do `financeiro-pest.yml`: suíte cheia (resumo), JUnit (veio 0 bytes),
  output completo (truncou em fatal de constante), e **loop por-arquivo** (`for f in ...; pest "$f"`).
- ⚠️ O loop por-arquivo compartilha o MESMO MySQL sequencialmente e **NÃO re-semeia entre arquivos**
  → os testes `RefreshDatabase` no meio (alfabético: CaixaMov, CashRegister) rodam `migrate:fresh`,
  dropam tabelas e **limpam o biz=1 seedado** → tudo depois (Dre, Fluxo, Unificado, Titulo…) skipa
  por `Business::first() == null`. Logo, a massa de "skip" do loop é artefato, não estado real.

## Categorias de falha (causa-raiz → leverage)

| # | Categoria | Arquivos | ~Fails | Causa-raiz | Fix |
|---|---|---|---:|---|---|
| B | `migrate:fresh`/`RefreshDatabase` dropa FK + limpa seed | CaixaMovimentoFreshness, CashRegisterBridge, MultiTenantComprehensive | ~28 | `RefreshDatabase` re-migra do zero → `SQLSTATE 3730 Cannot drop fin_titulos referenced by fin_bank_statement_lines`; e envenena os demais | Converter pra `DatabaseTransactions` (rollback, sem re-migrate) + self-seed adaptado ao biz=1 já presente |
| C | Mock\* família — assert em asset estático | MockOndaSidebarWrap (20), MockOndaEditBridge (14), MockOndaConferidoBridge (12), MockCoworkMode (1) | ~47 | `file_exists('public/cowork-preview/_oimpresso-*.js')` = false no CI (asset não versionado / era Cowork-mock) | Decidir: versionar asset, OU skip-when-absent, OU deprecar testes da era Cowork-mock |
| A | Container binding | CobrancaControllerTest | 15 | `BindingResolutionException: Unresolvable dependency [$app] in CacheManager` em rota com cache | Bind/mock cache no env de teste OU corrigir bootstrap da rota |
| D | Asserts Inertia/shape (negócio) | CoworkBundleIntegral (9), OndaCommentsAuditBridge (17), Drawer/Onda5-9 (~25), Wave23/25 (3) | ~54 | shape canon / prop Inertia / dados — precisa investigação por-caso (alguns reais, alguns stale) | Per-caso |
| E | Misc | Bridge (4), CaixaController (1), OnCobrancaPaga (2), Onda26InterWebhook (2), MultiTenantIsolation (1) | ~10 | variados | Per-caso |

## Verde-robusto JUNTO (allowlist Batch 1 — 10 arquivos, 87 passed verde)

Advisor/Onda31AdvisorPortal · BankStatementLineModel · Onda10Canon100Percent ·
Onda8cSparklineReal · PluggyIntegration · TituloRepositoryWave18 ·
Wave27Polish · Wave28Polish · AccountsLegacyMapMultiTenant ·
UnificadoCanceladoArquivadosKpi (guard #2240).

**Verde sozinho mas NÃO-junto** (inserem dados → colidem no DB compartilhado;
entram em batch futuro com isolamento): Onda23OcrBoleto (UniqueConstraint boletos),
BackfillExtratoOfx (colide com conta seedada committed mesmo com DatabaseTransactions).
Lição: rodar tudo numa invocação só = DB + estado compartilhado; allowlist só aceita
arquivo state-independent OU com cleanup/isolamento provado.

## Plano catraca (US-FIN-053)

1. **Batch 1 (este PR):** lane vira allowlist verde — cobertura 1 → 12 arquivos. Zero risco.
2. **Batch 2:** Categoria B — converter `RefreshDatabase` → `DatabaseTransactions`, remover o envenenamento. Destrava ver o estado real do resto.
3. **Batch 3:** Categoria C — decisão Wagner: deprecar vs skip-when-absent vs versionar asset Cowork-mock.
4. **Batch 4+:** A, D, E por-caso.

Cada batch adiciona arquivos à allowlist da lane (ratchet) — a lane nunca regride.
