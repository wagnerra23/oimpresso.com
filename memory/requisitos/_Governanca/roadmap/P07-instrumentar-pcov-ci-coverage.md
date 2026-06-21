---
roadmap_item: P07
slug: instrumentar-pcov-ci-coverage
onda: 3
status: proposed
depende_de: []
destrava: [P13]
related_adrs: [0275, 0279, 0062, 0273]
esforco_estimado: "0.8d codável (IA-pair) + 3 nightlies (3d relógio) p/ a 1ª medição válida + 14d relógio p/ a catraca C2 contar"
---
# P07 · Instrumentar pcov no CI (destrava coverage_pct / catraca C2)

## Problema (o que está quebrado, em 2-3 frases)
A métrica `coverage_pct` do scorecard SDD está `not_yet_measured` porque **nenhuma fonte coleta cobertura de teste**. Sem número, a catraca C2 (cobertura, 14d advisory, FP<5%) não pode nem começar a contar a janela — está bloqueada a montante. O resultado é uma das 7 métricas SDD permanentemente em estado de forma ("vai medir um dia") em vez de medida.

## Causa-raiz (evidência VERIFICADA — file:line reais confirmados no repo)
Três pontos confirmados no repo real `D:\oimpresso.com` em 2026-06-21:

1. **`.github/workflows/ci.yml:32`** — `coverage: none` no `shivammathur/setup-php@v2` (nenhuma extensão de coverage). E **`ci.yml:110`** — `vendor/bin/pest ... --no-coverage` explícito. Além disso o lane required (`ci.yml:105-110`) roda só um **subconjunto curado** vindo de `.github/ci-sqlite-pest.list`, não a suíte inteira — medir coverage ali daria número falso-baixo.
2. **`scripts/governance/sdd-scorecard.mjs:255-256`** — `coverage_pct` é hardcoded `notYet('up', 'só sobe (catraca C2)', 'pcov instrumentado em CI (P0-2) — hoje coverage: none')`. É um placeholder honesto: nunca mente 0, fica `not_yet_measured` até a 1ª medição real.
3. **`scripts/tests/ct100-fullsuite.sh`** (a nightly que roda a suíte INTEIRA no CT100) — grep por `pcov|coverage|clover|xdebug` retorna **vazio**. A pest roda em **`ct100-fullsuite.sh:223`**: `exec php -d memory_limit=2G vendor/bin/pest --log-junit /artifacts/junit.xml --colors=never` — sem flag de coverage. A imagem docker é `oimpresso/mcp:latest` (**`ct100-fullsuite.sh:21`**, "PHP 8.4 ZTS + pdo_mysql") e grep por `pcov` em `docker/` retorna vazio → **pcov não está na imagem**.

ADR 0275:50 fixa a fonte canônica: `coverage_pct` = `pest --coverage` (pcov) na **nightly**, baseline 0 (sem instrumentação, `coverage: none`), "só sobe". ADR 0275:68 e o BLUEPRINT-SDD-ONDA1.md:68 explicitam: **coverage só é honesto na fonte que roda a suíte inteira** (CT100 full-suite), nunca no lane sqlite curado.

## Estado atual no repo (o que achei ao verificar agora)
- `governance/sdd-scorecard.json:94-102` (origin/main e local) → `coverage_pct: not_yet_measured`, source "pcov instrumentado em CI (P0-2) — hoje coverage: none". **Evidência do prompt confirmada 100%.**
- **Divergência menor reportada:** o prompt diz `git show origin/main:.github/workflows/ci.yml | grep coverage/pcov = vazio`. A causa do "vazio" no meu primeiro teste foi mangling de path no shell (backslash); ao ler o arquivo real **a string `coverage:` EXISTE — mas com valor `none`** (`ci.yml:32`) e `--no-coverage` (`ci.yml:110`). Ou seja: a conclusão ("sem instrumentação") está CORRETA; só não é literalmente "grep vazio". Não há pcov/xdebug/clover no ci.yml.
- **Boa notícia (reuso):** `.github/workflows/mutation-gate.yml:43-49` JÁ usa `coverage: pcov` no setup-php (escopo `app/Services`, advisory). Serve de referência para a config de pcov, mas é GH Actions (não CT100) — não é a fonte honesta da suíte inteira.
- **Transporte já pronto (reuso direto):** `ct100-fullsuite.sh:269-292` computa o floor (`floor-compute.mjs`) e faz `git push -f [skip ci]` pra branch órfã `governance/nightly-floor` via deploy key `/root/.ssh/oimpresso_floor_deploy`; `sdd-scorecard.yml:65-72` materializa o arquivo no CI antes de medir; `sdd-scorecard.mjs:117 measureFullSuiteFloor()` lê com fallback honesto. **Este é o trilho exato que coverage deve reusar** — não precisa construir transporte novo.
- O scorecard local diverge de origin/main (re-run: `anchor_coverage` 7.5→7, `full_suite` agora measured localmente), mas o fato `coverage_pct: not_yet_measured` vale nos dois.

## Objetivo / DoD (critério de pronto OBJETIVO e checável)
1. `scripts/tests/ct100-fullsuite.sh` roda a full-suite com pcov ligado e gera um clover (ex `storage/coverage/clover.xml`) DENTRO do container, no CT100 (NUNCA no Hostinger — ADR 0062).
2. O `coverage_pct` é extraído do clover e publicado no transporte existente (campo novo em `governance/nightly-floor.json` OU `governance/nightly-coverage.json` na mesma branch órfã).
3. `sdd-scorecard.mjs` ganha `measureCoverage()` (espelho de `measureFullSuiteFloor`, mesmo fallback `notYet` honesto) e troca o hardcode da linha 255 por essa leitura.
4. Após **3 nightlies válidas consecutivas**, `governance/sdd-scorecard.json` reporta `coverage_pct: measured` com um número real ≥0; a catraca C2 começa a contar os 14d advisory.
5. O contrato de determinismo é preservado: `coverage_pct` NÃO traz timestamp/sha no corpo do scorecard (`_meta.determinismo` — re-run sem mudança no repo = diff vazio).

## Passos (ordenados, concretos)
1. **Confirmar com Wagner** se a imagem `oimpresso/mcp:latest` (CT100) tem pcov no PHP CLI. Se não: `pecl install pcov` (+ `docker-php-ext-enable pcov` ou equivalente Alpine `apk add php84-pecl-pcov`) no Dockerfile/provisionamento da imagem do CT100 — **NUNCA no Hostinger**. Mirror do padrão de `apk add mariadb-client` já existente em `ct100-fullsuite.sh:205-208`. [HUMANO-LIMITADO]
2. Editar `ct100-fullsuite.sh:223`: trocar a invocação por `php -d memory_limit=2G -d pcov.enabled=1 -d pcov.directory=. vendor/bin/pest --log-junit /artifacts/junit.xml --coverage-clover /artifacts/clover.xml --colors=never`. Manter `--log-junit` (FV-F1 não pode quebrar). Coverage é aditivo; falha de coverage NÃO derruba o run de diagnóstico (mesma filosofia "fail é DADO" do floor).
3. Criar `scripts/tests/coverage-compute.mjs` (irmão de `floor-compute.mjs`): lê o(s) clover(s) das últimas N nightlies, extrai `line-rate`/`coverage_pct`, e escreve em `governance/nightly-coverage.json` (schema versionado, igual `nightly-floor/v1`).
4. Estender o bloco `[floor]` de `ct100-fullsuite.sh:269-292`: rodar `coverage-compute.mjs` e adicionar `governance/nightly-coverage.json` ao mesmo commit `[skip ci]` na branch órfã `governance/nightly-floor` (reuso de deploy key + push).
5. Editar `sdd-scorecard.yml` (materialização, ~linha 65): além de `nightly-floor.json`, materializar `nightly-coverage.json` da branch órfã antes de medir.
6. Adicionar `measureCoverage(coveragePath)` em `sdd-scorecard.mjs` (espelho exato de `measureFullSuiteFloor`, linhas 117-130: `existsSync` → `notYet`; JSON inválido → `notYet`; sem campo numérico → `notYet`; senão `measured`). Plugar em `metrics.coverage_pct` no lugar do hardcode de `sdd-scorecard.mjs:255-256`.
7. Escrever meta-teste `scripts/governance/sdd-coverage-read.test.mjs` (espelho de `sdd-floor-read.test.mjs`, já referenciado em `sdd-scorecard.yml:60`): cobre os 4 lados (ausente / JSON inválido / sem número / measured) — prova que o read-side NÃO mente 0.
8. Após 3 nightlies measured, editar `governance/sdd-scorecard-baseline.json` setando `coverage_pct` armado (ADR 0275 §3 — a mecânica `--ratchet` em `sdd-scorecard.mjs:293` já honra `armed` por métrica). [janela de relógio]

## Arquivos a tocar (lista real)
- `scripts/tests/ct100-fullsuite.sh` (linhas 223 e bloco 269-292) — ligar pcov + publicar coverage no transporte
- `scripts/tests/coverage-compute.mjs` — **NOVO** (irmão de `scripts/tests/floor-compute.mjs`)
- `scripts/governance/sdd-scorecard.mjs` (substituir hardcode linhas 255-256; adicionar `measureCoverage()` espelhando 117-130; mapa de stream linha 102 já tem `coverage_pct: 'FV'`)
- `scripts/governance/sdd-coverage-read.test.mjs` — **NOVO** (espelho de `scripts/governance/sdd-floor-read.test.mjs`)
- `.github/workflows/sdd-scorecard.yml` (~linha 65 — materializar `nightly-coverage.json`; ~linha 60 — registrar o novo meta-teste)
- `governance/sdd-scorecard-baseline.json` (PR separado, pós-3-nightlies — armar)
- Dockerfile/provisionamento da imagem `oimpresso/mcp:latest` no CT100 (fora deste repo? confirmar com Wagner — não há `docker/**` com pcov) — instalar a extensão pcov

## Gate / counterfactual (COMO eu provo que a peça MORDE)
P07 **não é um gate required em si** — é a infra que destrava o gate C2 (que P13 promove). A prova de que P07 funciona é objetiva e em camadas:

1. **Meta-teste hard (morde em PR):** `sdd-coverage-read.test.mjs` roda no `sdd-scorecard.yml` como step hard (igual ao `sdd-floor-read.test.mjs`). **Counterfactual:** um diff que faça `measureCoverage()` retornar `0` quando o arquivo está ausente (em vez de `notYet`) DEVE dar **exit 1** no meta-teste. Se passar verde, o teste não morde — reabrir.
2. **Determinismo (morde em PR):** o step "Determinismo — 2ª run = diff vazio" (`sdd-scorecard.yml`) DEVE continuar verde após plugar coverage. **Counterfactual:** se eu vazar um timestamp/sha do clover pro corpo do scorecard, a 2ª run dá diff ≠ vazio e o step acusa.
3. **Evidência objetiva de fechamento (relógio):** após 3 nightlies, `node scripts/governance/sdd-scorecard.mjs` produz `coverage_pct.status == "measured"` com `value` numérico ≥0 (não null). **Checável:** `cat governance/sdd-scorecard.json | jq '.metrics.coverage_pct.status'` == `"measured"`. Antes disso, fica honestamente `not_yet_measured` — o que é o comportamento correto, não falha.

## Dependências (e por quê)
- `depende_de: []` — o transporte (branch órfã + materialização + `measureFullSuiteFloor` como template) **já existe e está operacional** (verificado: `ct100-fullsuite.sh:269-292`, `sdd-scorecard.yml:65-72`, `sdd-scorecard.mjs:117`). P07 só **reusa** esse trilho. Não bloqueia em P0-1 (já feito).
- `destrava: [P13]` — P13 é o "1º gate SDD required com counterfactual". C2 (catraca coverage) só pode ser promovida a required depois que `coverage_pct` produz número e roda 14d advisory com FP<5% (ADR 0275 §5, linha 87). Sem P07, P13 (se escolher C2 como o gate-piloto) não tem métrica para promover.

## Esforço (recalibrado ADR 0106)
**Codável (IA-pair, 10x + margem 2x):** ~0.8d — editar o `pest` call, escrever `coverage-compute.mjs` + `measureCoverage()` + meta-teste (todos espelhos de código que já existe e funciona; risco baixo porque o padrão floor é o gabarito). É copy-adapt, não invenção.

**Humano-limitado / relógio do mundo real (NÃO comprime com IA):**
- **Decisão Wagner:** confirmar/instalar pcov na imagem `oimpresso/mcp:latest` do CT100 (`pecl install pcov`). Janela = quando Wagner abrir o provisionamento. Bloqueante para a 1ª medição.
- **3 nightlies consecutivas válidas** para `coverage_pct` virar `measured` e armar = **~3 dias de relógio** (nightly roda 02:00). Não acelera.
- **14 dias advisory + FP<5%** para a catraca C2 contar e ficar elegível a required (ADR 0275 §5) = **14 dias de relógio**. Esse prazo é de P13/promoção, mas P07 é o gatilho que faz o cronômetro começar.

## Kill-criteria / risco (quando parar ou reabrir)
- **Tier-0 (parar imediatamente):** se a instrumentação de pcov acabar tocando o runtime Hostinger (deploy.yml / produção), viola ADR 0062 e degrada prod. Mitigação dura: o step de coverage vive SÓ em `ct100-fullsuite.sh`, NUNCA em `deploy.yml`. Reabrir como incidente se aparecer pcov em qualquer path Hostinger.
- **pcov estoura o orçamento de tempo da nightly:** pcov é 2-4× mais rápido que Xdebug (ADR/blueprint), mas a full-suite já é pesada (`TIMEOUT_S`). Se a nightly passar a estourar timeout por causa do coverage, desligar o `--coverage-clover` (volta a só floor) e medir coverage em cadência mais rara (ex semanal) — coverage não pode derrubar o floor, que é a métrica viva.
- **Número de coverage absurdamente baixo/instável entre runs:** se `coverage_pct` oscilar muito (ex loader-blockers do `ct100-fullsuite.sh:255-259` mudam o conjunto de arquivos exercitados), NÃO armar a catraca — manter advisory até estabilizar. A regra "só sobe" pressupõe medição estável; medir antes de armar.
- **Reabrir se:** a auditoria adversarial (skill `sdd-avaliar`) detectar que `coverage_pct` virou `measured` mas com fonte que não é a suíte inteira (ex alguém ligou no lane sqlite por engano) — isso seria "métrica de forma" (número falso-baixo que infla a ilusão), exatamente o que ADR 0275:68 proíbe.
