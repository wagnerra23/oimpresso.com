---
roadmap_item: P15
slug: done-comportamento-evidencia-alvo
onda: 3
status: em_execucao
depende_de: []
destrava: []
related_adrs: [0256, 0302, 0275, 0314, 0334]
esforco_estimado: "~1d codável (IA-pair): extensão doneness-lint + matcher do hook; advisory, ZERO gate required novo (lei 0314)"
---

# P15 · "Done = comportamento" + evidência do ambiente-alvo

## Problema (2-3 frases)

O adversário de verificação 2026-07-13 (`wf_33e38126`, 14 agentes) deu placar **3 TESTADO_REAL · 5 PARCIAL · 0 NARRADO** na leva de 10 PRs e cravou: **5 de 8 PRs de código pararam antes do ambiente-alvo**, e "done deixou de ser gate" — US-COPI-110 marcada `done` com DoD 0/10 (o score do time-decay é descartado pelo RRF; `RrfReranker.php:81`) e US-COPI-108 com checkbox `[x]` objetivamente falso (services Ragas chamam `Http::` fora do listener Langfuse). Veredito do juiz: *"progresso de honestidade, não de teste"*.

## Causa-raiz

- O `doneness-lint` (ADR 0302) pega âncora `_pendente_` vs `status: todo`, mas **não cruza `status: done` com o DoD da própria US** — done com checklist desmarcado passa liso.
- O hook `block-claim-without-evidence.ps1` cobre claims de deploy/infra crítica (`.htaccess`, Middleware, routes...), mas **não cobre scripts CT100/cron** (`scripts/tests/ct100-*.sh`, comandos artisan agendados) — exatamente onde a leva parou antes do alvo (#4192 dry-run-only, #4193 smoke adiado, #4199 estreando direto na nightly).
- Padrão reincidente "teste criado mas não registrado" reencarnou em `.mjs`/`.sh` (meta-teste do distiller órfão de CI; wrapper sem selftest) — tratado no chip A3, mas sem catraca que impeça o PRÓXIMO.

## Entregas (todas advisory — lei ADR 0314: required = só Tier-0)

1. **doneness-lint v2:** US com `status: done` E DoD/aceite com checkbox desmarcado → 🔴 no lint (advisory no PR). É checagem de **consistência interna** (o doc contradiz a si mesmo), não gate-de-presença (lápide §5 de 2026-07-01 não se aplica — não exigimos "arquivo X no diff", exigimos que o done não minta pro próprio DoD).
2. **Matcher estendido do `block-claim-without-evidence`:** PR que toca `scripts/tests/ct100-*.sh`, `docker/oimpresso-mcp/scripts/*` ou adiciona schedule no `Kernel.php` exige evidência de execução no ambiente-alvo (output com timestamp/host) no body OU `<!-- alvo-pendente: <razão + quando> -->` explícito — transforma a pendência silenciosa em pendência declarada e rastreável.
3. **Selftest-registry sweep:** guard barato (governance-script-tests) que compara `scripts/**/*.test.mjs` existentes vs invocados em `.github/workflows/**` — teste novo órfão avermelha o advisory (mata a reencarnação do "Tests sem phpunit.xml").
4. **Checkpoint quinzenal:** re-rodar o adversário de verificação (workflow já roteirizado, `adversario-leva-teste-conhecimento`) sobre a leva das 2 semanas — é ele que mede se o P15 mordeu.

## Critério de saída (números, não narração)

- Placar do checkpoint seguinte: **≥6/8 TESTADO_REAL** (era 3/8).
- **0 US `done` com DoD aberto** no doneness-lint (hoje: ≥2 na Jana).
- **0 teste `.mjs` órfão** de workflow no sweep.

## Origem

Adversário de verificação 2026-07-13 (`wf_33e38126`) + plano 3-frentes aprovado por Wagner na mesma data (ver `_ROADMAP.md` §Atualização 2026-07-13). Regra-mãe: ADR 0256 (derivado+enforçado — done-por-comportamento) + ADR 0334 (camada C em manutenção: estas entregas fecham furo de mecanismo EXISTENTE, não criam gate novo).

## Execução (2026-07-13 — status `proposed` → `em_execucao`)

Entregas 1-3 implementadas em PR (todas advisory, lei ADR 0314):

1. **doneness-lint v2** — kind novo `conflito_done_dod_aberto` (done × checkbox `[ ]` na seção DoD/aceite); sempre reporta 🔴, só morde em `--check --dod` (opt-in de promoção futura). **Baseline honesto no dia do land: 62 US `done` com DoD aberto** (Sells 20 · Whatsapp 11 · Financeiro 8 · Jana 7 incl. US-COPI-107..113 · Infra 6 · RecurringBilling 6 · NfeBrasil 3 · OficinaAuto 1) — por isso advisory-com-contagem, não fail. Selftest: `scripts/governance/doneness-lint.test.mjs` (registrado no `governance-script-tests.yml`).
2. **Matcher alvo no hook** — `block-claim-without-evidence.mjs` ganhou dimensão paralela `evaluateAlvo` (paths `scripts/tests/ct100-*.sh` · `docker/oimpresso-mcp/scripts/*` · `app/Console/Kernel.php`): exige output com timestamp+host OU tag `<!-- alvo-pendente: razão + quando -->`; escape valves preservadas; advisory exit 0 (ADR 0224).
3. **Sweep selftest-registry** — `scripts/governance/selftest-registry-check.mjs` (advisory + `--check` de promoção + `--selftest` hermético). **Órfãos no dia do land: 10** (4 hooks + `feature-lint` + `ghost-fix` + `negocio-vs-governanca-ratio` + `sdd-distiller-freshness` [fecha com PR #4205/A3] + 2 de `scripts/qa/`).

**Checkpoint quinzenal (entrega 4): próximo adversário de verificação (`adversario-leva-teste-conhecimento`) roda ~27/jul/2026** sobre a leva das 2 semanas — é ele que mede o critério de saída (≥6/8 TESTADO_REAL · 0 done com DoD aberto no delta novo · 0 teste órfão novo).
