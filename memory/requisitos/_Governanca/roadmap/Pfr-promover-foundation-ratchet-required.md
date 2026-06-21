---
roadmap_item: Pfr
slug: promover-foundation-ratchet-required
onda: 0
status: proposed
depende_de: []
destrava: [P13]
related_adrs: [275, 271, 261, 279]
esforco_estimado: "0.5d codável + IA-pair (margem 2x = 1d) · + 14d de relógio real (janela advisory, NÃO comprime) + ~5min Wagner-flip"
---

# Pfr · Promover `foundation-ratchet` a required (o 1º dente SDD em L3)

> **DECISÃO Wagner 2026-06-21:** `foundation-ratchet` é o **1º gate SDD a virar `required`** (BLUEPRINT-SDD-ONDA1 Gap 3), antes do GT-G3 (P13, que vira o 2º dente). Motivo: já tem selftest 13/13, baseline armado com medição real, é 1 sinal determinístico (vs 3 do GT-G3) e **não depende de P05/P08** — é o dente mais estável pra abrir o precedente "1ª decisão SDD em L3".

## Problema (o que está quebrado, em 2-3 frases)

A tese central do SDD — "catraca que impede regressão" — **não existe em `main` como gate required**: 0 dos 18 required checks são gates SDD (`governance/required-checks-baseline.json:18-40`). O `foundation-ratchet` é o candidato pronto para virar o 1º required SDD: já roda always-run (sem `paths:`), é determinístico (Node puro, sem MySQL), tem selftest 13/13 provando que MORDE, e o baseline está armado com medição real do repo (`n_quarantine:127`, `n_refresh_database:15`, `n_business_first:75`). Mas hoje é **advisory** — fora do branch protection e com a palavra "advisory" no nome do job → vermelho não bloqueia merge, vira só annotation no job summary.

## Causa-raiz (evidência VERIFICADA — file:line reais)

1. **O gate não está nos required.** `governance/required-checks-baseline.json` lista 18 contexts congelados (17 em `classic_protection.contexts` linhas 18-36 + 1 em `rulesets.contexts` "Governance Gate" linha 41); **nenhum é o `foundation-ratchet`**. `capturado_em: 2026-06-20` (linha 6), `enforcement_level: everyone` (linha 16) = `enforce_admins: true`.
2. **O context do check carrega "advisory" no nome.** O status check context que o GitHub vê é o **`name:` do job** — `.github/workflows/foundation-ratchet.yml:28`: `Foundation ratchet (advisory · quarentena/RefreshDatabase/Business::first)`. O `name:` do workflow (linha 1) também: `Foundation Ratchet (advisory)`. Promover exige **renomear tirando "advisory"** (BLUEPRINT Gap 3 receita ponto 3a + ADR 0275 §5: required que carrega "advisory" no nome é auto-contraditório).
3. **DIVERGÊNCIA do template P13 — NÃO existe `continue-on-error` neste workflow.** Diferente do `sdd-scorecard.yml` (que tem 3× `continue-on-error: true` nos sinais), o `foundation-ratchet.yml` **não tem nenhum** `continue-on-error` (arquivo inteiro, 39 linhas; os 2 steps `run:` linhas 36 e 38 são hard). Ele é advisory **apenas** por (a) estar fora do branch protection e (b) o nome conter "advisory". Logo o "flip" é mais simples que o de P13: **renomear o job + adicionar o context renomeado ao baseline no MESMO PR** — não há `continue-on-error` a remover.
4. **A catraca MORDE de fato (exit 1).** `scripts/tests/foundation-ratchet.mjs:118-133`: `pioras = rows.filter(status==='SUBIU')` (linha 118), `fail = pioras.length > 0 || semRazao.length > 0` (linha 119), `if (fail) { ...; process.exit(1); }` (linhas 130-133). Subir qualquer contador acima do baseline OU um marcador `@group legacy-quarantine` sem `quarantine-reason:` a ≤3 linhas (linhas 81-82, `REASON` regex linha 34) → exit 1.
5. **O selftest prova que morde (13/13).** `scripts/tests/foundation-ratchet.test.mjs` — 13 `check()` (verificado: `node scripts/tests/foundation-ratchet.test.mjs` → `(13/13)` + `[PASS] catraca MORDE`). Cobre: boa→exit 0; ruim→exit 1 acusando `n_refresh_database 0→1`, `n_business_first 0→1` e "SEM quarantine-reason"; `--json` fiel; `--write` recusa subir sem `--force`; melhora→convite; job summary; menção≠uso (conserto raiz FV-Q1). O workflow roda o selftest ANTES do gate (`foundation-ratchet.yml:35-36`) — "quem vigia os vigias".
6. **O baseline está armado com medição real.** `scripts/tests/baselines/foundation-ratchet-baseline.json` — `generated_by: "...--write"`, `counters: { n_quarantine: 127, n_refresh_database: 15, n_business_first: 75 }`. Medido no repo (regra anti-stale do `.mjs:5-6`), não no plano. Não há flag `armed` aqui (≠ scorecard) — o baseline **é** o armamento: existir + bater = gate vivo.
7. **Precedente de risco confirmado.** ADR 0275 §1 + a tabela §5 citam `visual-regression` promovido sem janela e mergeando vermelho 2× em 24h (PRs #2544/#2548). É a razão dura de não flipar antes da janela de 14d com 0 FP.

## Estado atual no repo (o que achei ao verificar agora)

- `.github/workflows/foundation-ratchet.yml` existe, always-run (`on: pull_request: branches:[main]` + `workflow_dispatch`, linhas 14-17), sem `paths:` → roda em TODO PR. Cabeçalho (linhas 9-12) documenta a regra: "ADVISORY de nascença ... Promoção a required SÓ via calendário de promoções".
- `scripts/tests/foundation-ratchet.mjs` + `.test.mjs` + `baselines/foundation-ratchet-baseline.json` presentes e consistentes. Selftest verde local (13/13).
- `governance/required-checks-baseline.json`: 18 contexts, nenhum SDD. Consumido por `scripts/governance/protection-drift.mjs` (linha 37) + `.github/workflows/protection-drift.yml`.
- **DIVERGÊNCIA 1 (do template P13):** sem `continue-on-error` — o flip é renomear + baseline, não remover `continue-on-error`. Documentado na causa-raiz #3.
- **DIVERGÊNCIA 2 (foundation-ratchet NÃO está na tabela explícita do ADR 0275 §5):** a tabela de critérios pré-escritos (`0275...md:84-91`) lista R1/C2/T1/T2/A10/G3 — **não** FV-Q1/foundation-ratchet por nome. O critério aplicável vem do BLUEPRINT Gap 3 (linha 169: "14 dias advisory + FP<5%", espelhando C2/G3) + da regra-guarda-chuva 0275 §5 ponto 1-4 (gate novo nasce advisory, máx 1/semana, Wagner flipa, evidência linkada). **Registro aqui pra não inventar critério:** janela = **7 execuções diárias verdes + 0 falso-positivo em 14d** (mesmo critério G3/C2, o mais estrito dos dois) — confirmado por Wagner como o critério deste dente.
- `roadmap/` é **untracked no `main`** (sessões paralelas escrevem os 13 itens). Este arquivo cria o dir + o 14º item.

## Objetivo / DoD (critério de pronto OBJETIVO e checável)

`foundation-ratchet` está **required** em `main` E o counterfactual prova que MORDE:

1. `gh api repos/wagnerra23/oimpresso.com/branches/main/protection` lista o context renomeado do job nos `required_status_checks.contexts`.
2. `governance/required-checks-baseline.json` foi atualizado **no mesmo PR do flip** incluindo esse context exato (senão `protection-drift.mjs` acusa 🟡 drift — ADR 0275 §5; required NOVO no vivo sem entry no baseline = aviso).
3. O `name:` do job (`foundation-ratchet.yml:28`) e o `name:` do workflow (linha 1) foram renomeados **tirando "advisory"** no mesmo PR. (Não há `continue-on-error` a remover — ver causa-raiz #3.)
4. **Counterfactual provado:** um PR sintético que sobe `n_quarantine` (ou adiciona `@group legacy-quarantine` sem `quarantine-reason:`) faz o check **falhar (exit 1) e BLOQUEAR o merge** — não só annotation. Run linkada no PR de promoção (ADR 0275 §5 ponto 4: evidência = runs, não narração).
5. Evidência de **7 execuções diárias verdes consecutivas + 0 falso-positivo em janela de 14 dias** (BLUEPRINT Gap 3 / ADR 0275 §5 critério G3-equivalente). **Janela inicia 2026-06-21; elegível ~2026-07-05.**

## Passos (ordenados, concretos)

1. **Iniciar a janela advisory (relógio real, 14d) — JÁ COMEÇA.** O workflow já é advisory always-run; basta começar a registrar verde/vermelho por dia a partir de **2026-06-21**. Como é só `pull_request` + `workflow_dispatch` (não tem cron), a "execução diária" vem dos PRs do dia + disparos manuais `workflow_dispatch`. Critério: 7 dias com run verde + 0 FP em 14d. **Elegível ~2026-07-05.**
2. **Construir o counterfactual reproduzível (codável).** Criar `scripts/governance/counterfactual-foundation-ratchet.sh` (ou um step no próprio workflow) que: (a) copia o root de teste/baseline num sandbox tmp, (b) injeta uma regressão sintética — adiciona um `@group legacy-quarantine` novo (sem `quarantine-reason:`) OU sobe `n_quarantine`, (c) roda `node scripts/tests/foundation-ratchet.mjs --root <sandbox> --baseline <sandbox>/baseline.json`, (d) asseite `exit 1` + saída casando `/catraca FALHOU/` ou `/SEM quarantine-reason/`. **Reaproveitar a fixture `bad`** que o selftest já usa (`scripts/tests/fixtures/foundation-ratchet/bad/`, `.test.mjs:26`) — não criar fixture nova.
3. **Validar o flip em modo-sombra (opcional, mais forte).** Rodar o counterfactual num PR-draft real e confirmar que o job-context fica vermelho. Como NÃO há `continue-on-error`, o job já falha hoje em PR ruim — a diferença advisory→required é só o branch protection bloquear o botão merge.
4. **Pacote de promoção (PR único — codável, sem flip).** Num só PR: (a) renomear `foundation-ratchet.yml` `name:` (linha 1) e o job `name:` (linha 28) tirando "advisory"; (b) adicionar o context novo a `governance/required-checks-baseline.json` `classic_protection.contexts` (18→19); (c) citar ADR 0275 + BLUEPRINT Gap 3 no corpo + linkar a run do counterfactual (exit 1) + a evidência de 7/14d verdes. **Este é o PR DRAFT que esta tarefa abre — só mergeia pós-14d.**
5. **Wagner-flip (humano, ~5min, NÃO codável).** Wagner roda `gh api repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks/contexts -X POST -f 'contexts[]=<context EXATO renomeado>'`. ADR 0275 §5 ponto 3: só Wagner toca branch protection. Confirmar consumo da vaga semanal (máx 1 promoção/semana civil, ADR 0275 §5 ponto 2).
6. **Verificação pós-flip (codável).** Rodar o counterfactual contra `main` protegido e confirmar que o merge é BLOQUEADO (não só annotation). Confirmar `protection-drift.mjs` verde (baseline e protection batem; sem 🔴/🟡).

## Arquivos a tocar (lista real)

- `.github/workflows/foundation-ratchet.yml` — renomear `name:` (linha 1: `Foundation Ratchet (advisory)`) e o job `name:` (linha 28: `Foundation ratchet (advisory · quarentena/RefreshDatabase/Business::first)`) tirando "advisory". **Sem `continue-on-error` a remover.**
- `governance/required-checks-baseline.json` — +1 context em `classic_protection.contexts` (hoje 18 total; vira 19), no MESMO PR do flip.
- `scripts/governance/counterfactual-foundation-ratchet.sh` — **novo** (node/bash puro; reusa fixture `bad`) OU step embutido no workflow.
- `scripts/tests/fixtures/foundation-ratchet/bad/` — reusado (já existe; não criar).
- **Wagner-only (não-arquivo):** o clique `gh api` de branch protection.
- Possível: RUNBOOK leve "como promover um gate a required" (counterfactual → evidência → PR baseline → Wagner-flip → watchdog) — BLUEPRINT Gap 3 linha 192 sugere virar template canônico pros próximos flips (C2, A10, G3).

## Gate / counterfactual (COMO eu provo que o gate MORDE)

**O diff que DEVE dar exit 1 e bloquear o merge:** um PR que adicione, em qualquer teste, um marcador `@group legacy-quarantine` **sem** `quarantine-reason:` a ≤3 linhas:

```php
// @group legacy-quarantine        ← sem quarantine-reason: nas 3 linhas vizinhas
```

`foundation-ratchet.mjs` mede `n_quarantine` (sobe vs baseline 127 → 128 = `pioras`, linha 118) **e** registra `semRazao` (linha 82) → `fail=true` (linha 119) → `process.exit(1)` (linha 132). Saída: `✗ catraca FALHOU — fundação piorou` + `✗ marcador legacy-quarantine SEM quarantine-reason:`. Casa as regex do selftest `.test.mjs:29-31` (`/SEM quarantine-reason/`).

**Variante equivalente:** adicionar `uses(RefreshDatabase::class)` num teste novo → `n_refresh_database` sobe (15→16) → exit 1. OU `Business::first()` cru → `n_business_first` sobe (75→76) → exit 1.

**Prova de que MORDE (não só annotation):** após o flip, o job-context aparece em `required_status_checks.contexts` → GitHub bloqueia o botão merge enquanto vermelho. **Prova de que NÃO morde hoje (só vira annotation):** o mesmo diff num PR atual faz o job ficar vermelho, mas como o context está FORA do branch protection, o merge não é bloqueado. A transição advisory→required (adicionar ao branch protection) É a diferença observável.

**Counterfactual negativo (anti-falso-flip):** se o counterfactual NÃO reproduzir exit 1, o flip NÃO acontece — exatamente o pecado do `visual-regression` que o ADR 0275 §5 evita.

## Dependências (e por quê)

- **Nenhuma dependência dura de outro Pxx.** É o motivo de Wagner ter escolhido este como 1º dente: NÃO depende de P05 (grandfather), P08 (nightly verde) nem P01/P02 (floor full-suite). O baseline já está armado e a métrica é determinística e estável (contadores congelados, burn-down só desce).
- **Destrava P13 indiretamente** abrindo o precedente "1ª decisão SDD em L3" + criando o RUNBOOK template de promoção que P13 (GT-G3, 2º dente) reusa.
- **Risco de race (ADR 0275 R1, `strict:true`/merge queue):** NÃO se aplica aqui — foundation-ratchet é determinístico, Node puro, sem estado compartilhado mutável (2 PRs verdes isolados não podem mascarar regressão um do outro). O anti-race é pré-req do full-suite (R1), não deste gate. (BLUEPRINT Gap 3 linha 152 confirma.)

## Esforço (recalibrado ADR 0106)

- **Codável (10x IA-pair + margem 2x):** counterfactual reproduzível (0.2d) + pacote de PR (renomear 2 nomes, editar baseline, RUNBOOK leve) (0.2d) + verificação pós-flip (0.1d) = **~0.5 dev-day**.
- **Relógio do mundo real (humano-limitado, NÃO comprime):**
  - **14 dias** de janela advisory (7 execuções diárias verdes + 0 FP). Não acelera: depende de runs reais. **Janela inicia 2026-06-21, elegível ~2026-07-05.** Como o workflow não tem cron, garantir ≥1 run/dia (PRs do dia ou `workflow_dispatch` manual).
  - **~5 min Wagner-flip** — o clique `gh api` de branch protection (ADR 0275 §5 ponto 3).
  - **1 vaga/semana civil** — se outra promoção consumir a vaga, Pfr espera a próxima semana. **Como é o 1º dente decidido, ele tem prioridade na 1ª vaga elegível.**
- **Total honesto:** ~0.5d de código pronto numa tarde, mas o marco só fecha após **14 dias de relógio** (elegível ~2026-07-05) + o flip humano.

## Kill-criteria / risco (quando parar ou reabrir)

- **PARAR o flip se** a janela de 14d acumular ≥1 falso-positivo (run vermelha por manutenção/flakiness, não por regressão real) → reabrir a janela do zero. Critério G3-equivalente exige 0 FP. (Risco baixo: o gate é determinístico — FP só viria de um `--write --force` mal-justificado ou um rename de módulo que afrouxa contagem legitimamente; ambos são diffs visíveis no PR.)
- **NÃO flipar antes de 2026-07-05** (fim da janela) — flipar antes é o erro literal do `visual-regression` (ADR 0275 §1, PRs #2544/#2548), e abrir o precedente "1ª decisão SDD em L3" com um gate que ainda não provou 14d de estabilidade queima a confiança no programa inteiro.
- **NÃO renomear o context SÓ no baseline sem renomear no workflow (ou vice-versa)** — o context do GitHub é o `name:` do job; se os dois divergirem, `protection-drift.mjs` acusa drift e o required aponta pra um context que não existe (merge trava pra sempre). Os 3 (workflow `name:`, baseline entry, `gh api` do Wagner) têm que carregar a **string idêntica**.
- **ORDEM CRÍTICA DO MERGE (por quê o PR é DRAFT):** `protection-drift.mjs:101-103` trata "context no baseline mas NÃO no vivo" como `missing` → **🔴 exit 1**. Como este PR já adiciona o context ao `required-checks-baseline.json`, mergeá-lo **antes** do Wagner-flip (`gh api` que põe o context na live protection) faz o `protection-drift` ficar VERMELHO e bloquear `main`. Sequência obrigatória: **(1) janela 14d verde → (2) Wagner roda o `gh api` adicionando o context à live protection → (3) SÓ ENTÃO mergear este PR** (com o baseline já batendo a live). É exatamente por isso que o PR nasce DRAFT e só sai de draft pós-2026-07-05 + flip.
- **DEMOÇÃO** (se promovido e gerar atrito real): exige PR + ADR (ADR 0275 §5 ponto 5) — demoção invisível de 1 clique está barrada.
- **Conflito de ordem — RESOLVIDO 2026-06-21:** Wagner decidiu foundation-ratchet como 1º dente (BLUEPRINT Gap 3), GT-G3 (P13) como 2º. Se a ordem reabrir, Pfr e P13 disputam a vaga semanal — Pfr tem prioridade.
