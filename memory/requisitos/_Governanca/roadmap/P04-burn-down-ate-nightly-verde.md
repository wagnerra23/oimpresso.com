---
roadmap_item: P04
slug: burn-down-ate-nightly-verde
onda: 2
status: proposed
depende_de: [P03, P01, P02]
destrava: [P13]
related_adrs: [0275, 0279, 0062, 0101, 0276, 0106]
esforco_estimado: "~3-4d codavel (IA-pair, fan-out 1 modulo/agent) + 7+ noites de relogio real (R1 nao acelera)"
---

# P04 · Burn-down por modulo ate nightly VERDE

## Problema (o que esta quebrado, em 2-3 frases)
O nightly CT100 produz ~1100 arquivos nao-passando por noite (failed 355-397, errors 705-762) e o floor estavel (intersecao de 3 runs) e 295 arquivos. Enquanto esse numero nao cair a ZERO nao-quarentenado, o gate-mae `full_suite_pass_rate` (R1 do ADR 0275) nao pode virar `required` — entao P13 (promocao de gate SDD com dentes) fica bloqueado a montante. O risco honesto: zerar o numero quarentenando em massa (skipped ja esta em 2564, ~24%) mascara o pass-rate em vez de consertar o sistema.

## Causa-raiz (evidencia VERIFICADA — file:line reais que voce confirmou)

- **Floor estavel = 295 (nao 274).** A branch orfa write-side carrega o numero vivo: `git cat-file -p origin/governance/nightly-floor:governance/nightly-floor.json` → `floor_count: 295`, `intersection_of: 3`, runs `20260619` (failed 371/errors 762/skipped 2575), `20260620` (355/761/2579), `20260621` (397/705/**skipped 2564**), `computed_at: 20260621-020001`. HEAD da orfa = `798e88f962 2026-06-21 03:11 "chore(sdd): nightly floor 20260621-020001 [skip ci]"`.
- **DIVERGENCIA real (registre):** o scorecard canonico commitado em `main` (`governance/sdd-scorecard.json:45-75`) le `full_suite_pass_rate.value: 274` com `status: measured` — numero de UM nightly atras (runs ate 20260620, floor 274). O JSON em main esta **stale por 1 noite** vs a orfa (295). Isso e Gap 1a do BLUEPRINT-SDD-ONDA1 §59: o workflow `sdd-scorecard.yml` materializa a orfa no CI (`.github/workflows/sdd-scorecard.yml:65-69` faz `git fetch origin governance/nightly-floor` + `git show FETCH_HEAD:governance/nightly-floor.json`), mas **nao comita o JSON medido de volta no main** → o numero canonico defasa.
- **Distribuicao do burn-down confirmada parcialmente.** `memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md:80`: "B1 Financeiro **172** fails (continuacao US-FIN-053) · B2 NfeBrasil **79** · B3 matrix SQLite→MySQL ... · B4 tests/ raiz + trait WithSeededTenant". **O "residual ~490" da evidencia NAO esta escrito no plano-mae** — o plano so itemiza B1/B2/B3/B4 sem somar um residual nominal; 490 e numero derivado da orquestracao (failed+errors menos B1+B2), trate como estimativa, nao como fato do repo.
- **Definicao de floor (write-side):** `scripts/tests/floor-compute.mjs:43-66` — floor = intersecao dos arquivos com `failed>0 || errors>0` entre os ultimos 3 runs validos (run valido = `summary.json` coerente + `n_testcases>0`; junit 0b = run morto nao conta). `<2 runs validos → floor_count null → read-side not_yet_measured` (nunca mente 0).
- **Read-side:** `scripts/governance/sdd-scorecard.mjs:117 measureFullSuiteFloor()` le `governance/nightly-floor.json`; `:129` reporta `measured` com `value: floor_count`. Fallback honesto se ausente/invalido (`:120-126`).
- **Criterio R1 (promocao a required):** `memory/decisions/0275-...md:86` — "full-suite MySQL nao-quarentenado | **7 nightlies verdes consecutivas + p95 duracao <=25min + `strict: true` ou merge queue ativo** no momento do flip (anti-race de 2 PRs verdes isolados)". A regra de armamento (`:62`) exige 3 medicoes validas consecutivas antes de qualquer punicao.
- **Testes so no CT100.** `scripts/tests/ct100-fullsuite.sh:269-292` computa o floor e faz `git push -f [skip ci]` pra orfa via deploy key `/root/.ssh/oimpresso_floor_deploy`. ADR 0062 (Hostinger != CT100) e ADR 0101 (MySQL real, nao sqlite) — nunca rodar suite local.
- **US bloqueadoras em `review`, nao `done`:** `memory/requisitos/Governance/SPEC.md:339` US-GOV-018 `status: review`; `:393` US-GOV-020 `status: review`. O harness (US-GOV-018 Frente A) ja landou parcial (#2640: mariadb-client + ssl-verify=0; FK-off REVERTIDO net-harmful no ledger §E).

## Estado atual no repo (o que voce achou ao verificar agora)
- `memory/requisitos/_Governanca/roadmap/` existe mas estava **vazio** — este e o 1o item do roadmap; nenhum P01/P02/P03/P13 como arquivo irmao ainda (sao keys de orquestracao, nao docs commitados).
- Floor vivo (orfa) = 295; floor canonico (main scorecard) = 274 (stale 1 noite). Ambos reais, divergem por falta do commit-back do Gap 1a.
- skipped do ultimo run = 2564 (~24% da suite) — confirma o alerta da evidencia: quarentena em massa ja inflou o skip; burn-down honesto e consertar, nao quarentenar mais.
- Harness (P03 = US-GOV-018/020) ainda `review`; o B3 (matrix sqlite→MySQL) e justamente a fonte de "skips que viram fails novos" (plano-mae:80 "1.413 skips viram execucoes que voltam como fails novos"), por isso P04 depende de P03 estar fechado.

## Distribuição REAL medida — floor 298 (2026-07-01, pós-P02)

Puxada dos `summary.json` + `junit.xml` da nightly `20260701-020001` no CT100 (interseção de 3 runs: 20260628/20260630/20260701). **Supersede a estimativa stale do plano-mãe** — "B1 172 / B2 79" eram fails-de-**testcase**, não arquivos; o floor conta **arquivos**. P03 já executado (era-sqlite corruptores tier-A = 0), mas a nightly ainda carrega a cascata residual abaixo.

**298 arquivos-que-falham, por bucket:**

| Bucket | Arq | Bucket | Arq |
|---|---|---|---|
| tests/Feature (raiz) | 89 | Modules/Governance | 9 |
| Modules/OficinaAuto | 29 | Admin · Arquivos · Jana | 8 cada |
| Modules/PaymentGateway | 20 | Modules/Fiscal | 6 |
| Modules/KB | 17 | Modules/ComunicacaoVisual | 5 |
| Modules/NfeBrasil | 16 | ADS · ConsultaOs · Whatsapp | 4 cada |
| Modules/Financeiro | 14 | cauda ~18 módulos | ≤3 cada |
| Modules/Superadmin | 12 | | |

**Root-cause medido (1120 falhas de testcase, por classe de exceção):**
- **593 (53%) `Illuminate\Database\QueryException`** + 32 `UniqueConstraintViolation` + 19 `ModelNotFound` = **~57% erros de banco** (SQLSTATE `42S22` unknown-column, integrity-constraint) → **cascata de corrupção de schema / isolamento na conexão MySQL compartilhada** (o "lever" do P03/P04). **NÃO são 298 bugs independentes.**
- **322 (29%) `ExpectationFailedException`** — asserts reais (design-system ratchet R-DS-007, inertia asserts, cross-tenant scope) — trabalho per-teste genuíno, menor.
- Cauda: 40 `Error` · 36 `ErrorException` · 12 `PermissionDoesNotExist` · 12 `TypeError` (PaymentGatewayService)...

**Implicação pro burn-down:** maior alavanca = atacar o **isolamento** (a fatia DB de 57%), que pode derrubar centenas de `QueryException` de uma vez, ANTES de caçar asserts individuais. Piloto honesto: isolar/converter um cluster de corruptores de 1 conexão → 1 nightly → **MEDIR a queda do floor** (regra dura "MEDIR cada passo, nunca previsão-como-fato"). Lista dos 298 re-derivável da nightly viva no CT100 (`/opt/oimpresso-fullsuite/runs/<ts>/summary.json` → interseção).

## Objetivo / DoD (criterio de pronto OBJETIVO e checavel)
1. **7 nightlies CT100 consecutivas com `floor_count: 0`** (ou floor so de arquivos quarentenados-com-razao + dono, NUNCA por quarentena nova de teste de produto), lidas da branch orfa `governance/nightly-floor` — `git cat-file -p origin/governance/nightly-floor:governance/nightly-floor.json` mostra `floor_count: 0` em 7 `computed_at` consecutivos.
2. **p95 de duracao das 7 nightlies <= 25min** (criterio R1 do ADR 0275:86) — medido do log do CT100 / summary.json.
3. **skipped NAO cresce** durante o burn-down vs a baseline de abertura do P04 (anti-mascaramento): o `skipped` da ultima nightly <= skipped da nightly de abertura. Se subir, o burn-down esta quarentenando em vez de consertar → reabrir.
4. **O scorecard canonico em main reflete o floor vivo** (resolve a divergencia 274 vs 295): `governance/sdd-scorecard.json` `full_suite_pass_rate.value` == floor da orfa na mesma data (depende do Gap 1a / P01 commit-back).
5. Nenhuma das 7 nightlies verdes usou `@group legacy-quarantine` novo em teste de produto sem docblock de razao + link de triage (auditavel via `git log` dos PRs do burn-down).

> **DoD NAO inclui flipar o gate a required** — isso e P13. P04 entrega as 7 noites verdes + p95; P13 consome.

## Passos (ordenados, concretos)
1. **Armar a baseline honesta do P04.** Registrar o floor de abertura (295) + skipped de abertura (2564) num doc de tracking. Sem isso o criterio "skipped nao cresce" e inauditavel.
2. **Confirmar P03 fechado (US-GOV-018/020 done, nao review).** Se o harness B3 (matrix sqlite→MySQL) ainda nao convergiu, os "1.413 skips que viram fails" entram como fails novos no meio do burn-down → caca-fantasma. NAO comecar B1/B2 antes de P03 estabilizar (dependencia DURA, ver §Dependencias).
3. **Re-triar o floor de 295 contra a ultima nightly** (Haiku batch por classe A-F via stacktrace JUnit, Sonnet revisa amostra 10%, concordancia >=90% — receita plano-mae:73). Separar: FIX barato / CONVERTER (RefreshDatabase→DatabaseTransactions, Business::first()→trait WithSeededTenant) / bug de produto real / quarentena-legitima-com-dono.
4. **Fan-out por modulo, RIGOROSAMENTE 1 modulo por agent (areas disjuntas):**
   - **B1 Financeiro ~172** (continuacao US-FIN-053) — 1 agent Sonnet.
   - **B2 NfeBrasil ~79** — 1 agent Sonnet.
   - **B4 tests/ raiz + trait `WithSeededTenant`** — PR isolado ANTES dos lotes (infra compartilhada).
   - Residual (~490 estimado: assertions stale + app-bugs) — sub-fan-out so apos B1/B2/B4, re-triado contra baseline limpo. Escalar Opus so em estado compartilhado/poisoning.
   - Regra: 1 PR = 1 intent <=300 linhas, conventional `Refs: SDD F2b burn-down B<n>`, PT-BR, nunca tocar arquivo de outra lane, nunca rodar suite local (CT100 only).
5. **Apos cada lote, Wagner re-roda o nightly** (gate humano — testes so no CT100). Re-derivar o floor de `origin/main`/orfa antes de afirmar progresso (numeros driftam em 1 dia).
6. **Quando o floor bater 0 (nao-quarentenado), iniciar a janela das 7 noites.** Re-quarentena expressa permitida pre-R1 se flakiness aparecer (plano-mae:105), mas conta como reset da janela se for teste de produto.
7. **Em paralelo (P01/Gap 1a):** garantir que o scorecard em main recebe o commit-back do floor medido (resolve 274 vs 295) — pre-req do DoD item 4.
8. **Ao fechar 7 verdes + p95<=25min:** entregar a P13 o pacote (7 `computed_at` com floor 0 + p95 + evidencia linkada). P04 NAO clica branch protection.

## Arquivos a tocar (lista real)
- **Codigo/testes de produto e teste (burn-down):** `Modules/Financeiro/**` + seus testes (B1); `Modules/NfeBrasil/**` + testes (B2); `tests/` raiz + um trait `WithSeededTenant` (B4). 1 PR por modulo, areas disjuntas.
- **Nao tocar (so ler/disparar):** `scripts/tests/ct100-fullsuite.sh`, `scripts/tests/floor-compute.mjs`, `scripts/governance/sdd-scorecard.mjs` — sao infra do floor; mexer aqui e P01/P03, nao P04.
- **Doc de tracking:** `memory/requisitos/_Governanca/roadmap/P04-burn-down-ate-nightly-verde.md` (este) + um ledger de baseline (floor/skipped de abertura).
- **Possivel (se P01 nao cobrir):** o step de commit-back em `.github/workflows/sdd-scorecard.yml` — mas isso pertence a P01/Gap 1a; P04 so consome.

## Gate / counterfactual (COMO eu provo que o gate MORDE — qual diff deve dar exit 1; ou, se nao e gate, qual evidencia objetiva fecha)
P04 **nao instala um gate novo** — produz o estado (7 noites verdes) que torna o gate-mae (R1, `full_suite_pass_rate`) promovivel por P13. A evidencia objetiva que fecha:
- **Contrafactual do progresso (anti-mascaramento):** um diff que quarentena um teste de **produto** sem `@group legacy-quarantine` + docblock de razao + link de triage deve aparecer no review e ser rejeitado; e o criterio DoD-3 (`skipped` da ultima nightly > skipped de abertura) deve **reabrir** o P04 automaticamente. Prova: comparar `skipped` em dois `governance/nightly-floor.json` consecutivos da orfa — se subiu, o burn-down trapaceou.
- **Contrafactual do floor:** se um PR de burn-down "conserta" um arquivo mas ele volta a falhar na nightly seguinte, o floor (intersecao de 3 runs, `floor-compute.mjs:54-56`) NAO cai — so cai quando o arquivo passa em TODOS os 3 runs da janela. Isso ja morde por construcao (intersecao); o numero nao e gameavel por 1 run sortudo.
- **Fechamento:** `git cat-file -p origin/governance/nightly-floor:governance/nightly-floor.json` → `floor_count: 0` em 7 `computed_at` consecutivos + p95<=25min. Quando P13 flipar o gate, o counterfactual de P13 (PR-regressao que sobe o floor → exit 1) e que prova o dente — P04 so garante que ha 0 pra subir a partir de.

## Dependencias (e por que)
- **P03 (harness DB do nightly = US-GOV-018/020) — DURA.** Ambos `status: review` (SPEC.md:339, :393), nao `done`. O B3 (matrix sqlite→MySQL) gera "1.413 skips que viram fails novos" (plano-mae:80) se o harness nao convergiu — comecar B1/B2 antes seria caca-fantasma de env. P04 NAO comeca o fan-out antes de P03 estabilizar.
- **P01 (armar floor / commit-back do scorecard — Gap 1a do BLUEPRINT).** Sem o commit-back, o floor canonico em main fica stale (274 vs 295 vivo) → o DoD-4 (scorecard reflete o floor vivo) nao fecha. P04 mede progresso pela orfa, mas o "verde" oficial precisa do JSON canonico atualizado.
- **P02.** Dependencia declarada pela orquestracao; provavel pcov/coverage ou anti-grandfather (Gap 1b/2 do blueprint) — P04 nao toca esses, mas a sequencia das ondas os coloca antes do burn-down final. (Nao encontrei P02 como doc no repo; tratar a dependencia como ordenacao de onda, nao como artefato.)
- **Destrava P13** (promocao de 1 gate SDD a required com counterfactual): P13 so e honesto se houver 0 floor pra defender. P04 entrega o "0 + 7 noites".

## Esforco (recalibrado ADR 0106)
- **Codavel (IA-pair, 10x + margem 2x):** o burn-down em si — re-triagem batch + fan-out 1 modulo/agent. B1 (172) + B2 (79) + B4 (trait) + residual sub-fan-out. Estimativa **~3-4 dev-days codaveis** distribuidos em ate 5 Sonnet paralelos (areas disjuntas), Opus so em estado compartilhado. A maior parte e mecanica (CONVERTER/FIX/quarentena-com-razao), perfil ideal pra IA-pair.
- **Relogio do mundo real (NAO acelera com IA):** a janela R1 = **7 nightlies verdes consecutivas** = no minimo **7 noites de calendario** (1 nightly/noite, 02:00 BRT), mais re-rodadas que Wagner dispara apos cada lote (gate humano CT100). Se um lote quebra a sequencia, reinicia. Realista: **2-3 semanas de relogio** entre abrir o burn-down e fechar 7 verdes, mesmo com o codigo pronto rapido. O p95<=25min tambem so se mede ao longo das noites.
- **Humano-limitado:** Wagner dispara o re-run do nightly (testes so no CT100) e confirma cada batch. Nao paralelizavel pela IA.

## Kill-criteria / risco (quando parar ou reabrir)
- **REABRIR se `skipped` cresceu** vs baseline de abertura (DoD-3): sinal de que o floor zerou por quarentena de teste de produto, nao por conserto. O "verde" e falso.
- **PARAR o fan-out se P03 nao convergiu:** se o harness (US-GOV-018/020) ainda gera fails de env (Base table not found, FK-off, TLS cert), B1/B2 estao cacando fantasma — voltar pra P03.
- **REABRIR se a janela de 7 noites quebra** por flakiness pos-quarentena (executionOrder random→default expoe ordem-dependencia): re-quarentena expressa permitida pre-R1, mas conta como reset.
- **Risco de divergencia silenciosa (274 vs 295):** se P01 nao landar o commit-back, o time pode declarar "verde" lendo o JSON stale em main enquanto a orfa mostra floor>0. Mitigacao: DoD sempre le da orfa (`git cat-file ... origin/governance/nightly-floor`), nunca so do scorecard em main.
- **Risco Tier 0:** rodar suite local (viola ADR 0101/0062) produz numero falso; testes so no CT100. Nao instalar pcov/sqlite-coverage na fonte errada (Hostinger).
- **Kill total:** se apos 3 semanas o floor nao desce de forma sustentavel (re-quarentena perpetua), escalar pra Wagner — pode indicar que parte do "490 residual" sao bugs de produto reais que merecem virar US propria, nao burn-down de teste.
