# 2026-07-02 — SDD P07: coverage vira 2ª invocação separada (floor nunca refém do pcov)

**Contexto:** ADR 0275 §2 (catraca C2 `coverage_pct`). O pcov já entrou na imagem `oimpresso/mcp`; o read-side (`sdd-scorecard.mjs::measureCoverage`) já lê `governance/nightly-coverage.json` com fallback honesto `not_yet_measured`. Faltava o write-side produzir um `clover.xml` real sem derrubar o diagnóstico do floor.

## O que foi provado nesta sessão

### 1. A 1ª nightly instrumentada (combinada) MORREU e levou o floor junto — regressão FV-F1
Run `20260702-073601` rodou a versão antiga do harness (pcov na **mesma** invocação `vendor/bin/pest`):
- Morreu **silenciosa aos 5933/11144 testes (~53%)** — sem fatal impresso, shim containerd morto 09:03:23 (padrão de kill externo por pressão de memória; swap do CT100 chegou a 2.4G).
- `junit.xml` saiu **0 bytes** → **noite de floor perdida**. "coverage é aditivo" era promessa, não arquitetura.

Isso confirma empiricamente por que a separação era estrutural, não cosmética.

### 2. Correção: coverage em 2ª invocação SEPARADA (PR #3622 — mergeado)
- `scripts/tests/ct100-fullsuite.sh`:
  - **Run 1** (diagnóstico): `php -d memory_limit=2G vendor/bin/pest --log-junit ...` **sem** pcov → junit/floor sagrados.
  - **Run 2** (`[P07 coverage]`, container próprio `oimpresso-fullsuite-cov`, **após** o junit salvo): `php -d memory_limit=6G -d pcov.enabled=1 -d pcov.directory=. -d 'pcov.exclude=~(vendor|node_modules|storage)~' vendor/bin/pest --coverage-clover /artifacts/clover.xml`.
  - Falha no run 2 ⇒ só `coverage_pct` fica `not_yet_measured` (o `coverage-compute` valida o clover: truncado/ausente não conta); o diagnóstico da noite **já está em disco**.
- **Deploy CT100:** cópia deployada `/opt/oimpresso-fullsuite/ct100-fullsuite.sh` md5 `1369a929…` = `origin/main` (idêntico). Cron `0 2 * * *` já aponta pra ela.
- Checks do PR #3622: 65 pass / 2 skipping. Mergeado (`0bb65dd1`).

### 3. Sonda empírica (manual, hoje, em vez de esperar as 02:00)
`/opt/oimpresso-fullsuite/probe-coverage.sh` — replica **exatamente** o bloco `[P07 coverage]` (6G, pcov, suíte inteira) apontando o clover pra `probe-cov-20260702-131028/` (sem tocar em `runs/` nem no lock do cron). HEAD testado `111de201`.
- **Resultado-chave:** a 6G a sonda **ultrapassou folgado os ~53%** onde o run a 2G morreu — swap estável em 2G (vs 2.4G do kill), sem OOM, produzindo PASS/WARN normalmente por >1h. ⇒ **6G resolve o gargalo de memória** que matava a instrumentação combinada.
- Clover só flusha **no fim** da suíte (pcov acumula e escreve uma vez). Sonda seguia rodando ao fim da sessão (nohup destacado, independente da sessão Claude).

## Estado do pipeline P07 (mapa)
- ✅ pcov na imagem `oimpresso/mcp:latest` (confirmado `php -m | grep pcov`).
- ✅ Write-side: harness separado, deployado, cron ativo.
- ✅ Read-side: `measureCoverage()` lê `governance/nightly-coverage.json` (schema `nightly-coverage/v1`); sem número → `not_yet_measured` (nunca mente 0).
- ✅ Transporte: branch órfã `governance/nightly-floor` viva (`floor_count=298`); `coverage-compute.mjs` publica `nightly-coverage.json` no mesmo push `[skip ci]`.
- ⏳ `coverage_pct` = `not_yet_measured` até o **1º clover válido** (sonda de hoje OU nightly 02:00) e depois **3 medições válidas consecutivas** (ADR 0275 §3) pra ARMAR o floor em `governance/sdd-scorecard-baseline.json`.

## Próximos passos (não feitos — dependem de dado que ainda está sendo produzido)
1. **Validar o 1º clover real** (sonda `probe-cov-20260702-131028/clover.xml` ao terminar, ou nightly `runs/<TS>/clover.xml` das 02:00) → rodar `coverage-compute.mjs` e conferir `coverage_pct` numérico em `nightly-coverage.json` na órfã.
2. **Confirmar `measureCoverage` sai de `not_yet_measured`** com o valor live.
3. **Acumular 3 medições válidas consecutivas** (ADR 0275 §3) e **ARMAR `coverage_pct`** no baseline via PR (mesma receita do `anchor_coverage`, PR #3586: floor no valor live, `nota_armamento` com proveniência de hashes, counterfactual local exit 1).
4. Com o mapa de coverage vivo, escopar **T1** (mapa teste↔arquivo via `--coverage-php` per-test) e **T2** (lane TDAD-lite de impactados-no-PR).
   - ⚠️ **Promover qualquer coisa a `required` exige REABRIR a ADR 0314** (política `required = só-Tier-0`). Ver `memory/proibicoes.md` §ideias descartadas (entrada 2026-07-01 foundation-ratchet). Não mergear required no calado.

## Artefatos no CT100
- `/opt/oimpresso-fullsuite/probe-coverage.sh` — script da sonda (reutilizável).
- `/opt/oimpresso-fullsuite/probe-cov-20260702-131028/` — clover + `cov-out.txt` da sonda.
- `/opt/oimpresso-fullsuite/.probe-last-dir` — ponteiro pro último dir de sonda.
