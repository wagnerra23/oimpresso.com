---
roadmap_item: P06
slug: materializar-g7-g8-historia-brief
onda: 3
status: executed
executed_at: "2026-07-12"
depende_de: [P01]
destrava: []
related_adrs: [275, 226, 279]
esforco_estimado: "0.2d codável (zero código novo) + 1 deploy real + 2 ciclos de cron (relógio: ~1-2 dias pra brief mostrar a linha)"
---
# P06 · Materializar G7+G8 (migrate prod + linha SDD no brief)

> **✅ EXECUTADO 2026-07-12** — tabela `mcp_sdd_scorecard_history` aplicada em prod, linha SDD no brief, e agora o **cron diário 07:10 BRT roda no CT 100** (host-cron `ct100-sdd-scorecard-snapshot.sh`: node no host gera o JSON → `docker exec oimpresso-mcp php artisan governance:sdd-scorecard-snapshot --input=...`; o container conecta na prod DB, a imagem não tem node). **Decisão Wagner 2026-07-01:** não-Hostinger (`schedule:run` não roda no shared hosting; ADR 0062). Provado ponta-a-ponta 2026-07-12: row fresca `2026-07-12 · composta 64.1 (k=6)`, contra o máx anterior `2026-07-01 · composta 50.0` (11 dias stale). RUNBOOK: [RUNBOOK-ct100-sdd-scorecard-snapshot](../../Infra/RUNBOOK-ct100-sdd-scorecard-snapshot.md). Residual opcional de hardening (fora do escopo do cron): alarme 3 camadas (wrapper→`mcp_alertas`, check `sdd_snapshot_freshness` no `jana:health-check`, stale-guard no `SddBriefLineService`).

## Problema (o que está quebrado, em 2-3 frases)
O código de G7 (snapshot diário do scorecard SDD) e G8 (linha SDD no Daily Brief) está
100% mergeado em `main`, com testes, schedule e wiring — mas a migration
`mcp_sdd_scorecard_history` **nunca foi aplicada em produção**. Resultado: a tabela não
existe em prod → 0 rows → `SddBriefLineService::line()` e `DashboardController::buildSddPayload()`
ambos retornam `null` por guarda `Schema::hasTable(...)` → a linha SDD **nunca aparece no brief**
e o card SDD do dashboard **fica em empty-state perene**. Entrega anunciada como "leitura sem
esforço" é ilusória: o pipeline existe mas a primeira gota d'água nunca caiu.

## Causa-raiz (evidência VERIFICADA — file:line reais que confirmei)
Verificado no repo canônico `D:\oimpresso.com` (worktree atual `frosty-greider-83ab2f` NÃO
contém estes arquivos — todos confirmados na árvore principal):

- **Migration existe, é idempotente, nunca aplicada em prod:**
  `Modules/Governance/Database/Migrations/2026_06_12_100000_create_mcp_sdd_scorecard_history_table.php:19`
  guarda `if (Schema::hasTable('mcp_sdd_scorecard_history')) return;` — cria `snapshot_date` (unique),
  `payload` (json), `composta` (decimal nullable). Cross-tenant intencional (sem `business_id`).
- **Snapshot command pronto + schedule registrado:**
  `app/Console/Kernel.php:327` → `$schedule->command('governance:sdd-scorecard-snapshot')->dailyAt('07:10')->timezone('America/Sao_Paulo')->onOneServer()->environments(['live'])`.
  O command (`Modules/Governance/Console/Commands/SddScorecardSnapshotCommand.php:35`) **falha cedo
  com `self::FAILURE`** se a tabela não existir (`Schema::hasTable(...)` → `$this->error('rode php artisan migrate primeiro')`).
- **Brief wiring confirmado (linha diverge do que a evidência dizia):**
  `Modules/Brief/Console/Commands/GenerateBriefCommand.php:53` chama
  `app(SddBriefLineService::class)->inject($content)` (a evidência citou ":53" como o wiring;
  o `use` está na linha 12, a chamada na 53 — confirmado).
- **Guarda que zera a saída:** `SddBriefLineService::line()`
  (`Modules/Governance/Services/SddBriefLineService.php:64`) retorna `null` se `! Schema::hasTable('mcp_sdd_scorecard_history')`,
  e `:74` retorna `null` se não houver rows.
- **Segundo consumidor morto pela mesma causa:** `Modules/Governance/Http/Controllers/DashboardController.php:154`
  `buildSddPayload()` → `:156` retorna `null` se a tabela não existe → card SDD em empty-state.
- **Deploy roda migrate automaticamente:** `.github/workflows/deploy.yml:270`
  `php artisan migrate --force` (sob `if: skip_migrate != 'true'`). Ou seja: o mecanismo de aterrissagem
  da migration **já existe no pipeline** — falta apenas um deploy que NÃO pule o migrate ter rodado
  desde 2026-06-12, OU a tabela ter sido absorvida por outro deploy posterior sem que ninguém validasse 1 row.

## Estado atual no repo (o que achei ao verificar agora)
- **Código G7+G8: TODO mergeado.** PRs `#2617` (snapshot 1/2), `#2628` (Pest + card 2/2), `#2630`/
  commits `d14f543625`/`e83b57097c`/`d8f48e0276` (linha SDD no brief + kill-switch `GOVERNANCE_SDD_BRIEF_LINE`).
  Não há código novo a escrever — é puramente operacional (aplicar migration em prod + validar).
- **DIVERGÊNCIA CRÍTICA com a evidência e com o session log adversarial — reportada:**
  A evidência afirma *"Composta não calcula enquanto houver not_yet_measured"* e *"Depende de P01
  (composta precisa do floor pra sair de not_yet_measured)"*. **Isso está tecnicamente ERRADO.**
  Por ADR 0275 §4 (`memory/decisions/0275-...md:67`): *"v1 = média simples das métricas **armadas**"*,
  e em `SddScorecardSnapshotCommand::summarize()` (`:144-151`) uma métrica só entra em `$scores` se
  `$armed === true && is_numeric($target)` — métricas `not_yet_measured` são **puladas** (`continue` em `:137`),
  não zeram a composta. O baseline versionado `governance/sdd-scorecard-baseline.json` JÁ tem **2 métricas
  armadas** (`ghost_count` armed:true valid_measurements:3 `:54-55`; `front_door_coverage` armed:true
  valid_measurements:3 `:64-65`), ambas vindas de `knowledge-drift.mjs` (NÃO do floor do nightly).
  → **Logo: a composta v1 sai NÃO-NULA com k=2 no instante em que a migration for aplicada e o primeiro
  snapshot rodar, SEM depender de P01.** P01 (reconectar o read-side do floor de `full_suite_pass_rate`,
  `scripts/governance/sdd-scorecard.mjs:117`) apenas ARMA uma 3ª métrica (k=2→k=3) e melhora a composta —
  não a destrava.
- O session log adversarial (`memory/sessions/2026-06-21-sdd-avaliacao-adversarial.md:31` e `:47`) repete
  o mesmo erro ("composta não calcula"). Provável confusão: o read-side do floor (P01) afeta SÓ
  `full_suite_pass_rate`; ghost_count e front_door_coverage não passam por aquele caminho.
- **`depende_de: [P01]` mantido no frontmatter por respeito ao roadmap dado, MAS rebaixado a soft-dep**
  nas seções abaixo: P06 pode entregar valor (linha SDD viva + card vivo, composta k=2) sem P01.
  Se Wagner quiser segurar P06 até P01 pra mostrar composta k=3 já na estreia, é decisão dele — mas
  não é um bloqueio técnico. Recomendo soltar P06 antes.
- Diretório `memory/requisitos/_Governanca/roadmap/` **não existia** — criado por este arquivo.

## Objetivo / DoD (critério de pronto OBJETIVO e checável)
1. `Schema::hasTable('mcp_sdd_scorecard_history')` retorna `true` em **produção** (tabela aplicada).
2. `SELECT COUNT(*) FROM mcp_sdd_scorecard_history` ≥ **1** em prod (snapshot rodou ao menos 1 vez).
3. A row tem `composta` **NÃO-NULA** (com 2 armadas no baseline, esperado k=2; payload `composta_k=2`).
4. O **brief real** (gerado pós-snapshot) contém a linha SDD na seção `## FLAGS` — formato
   `🟡 SDD: composta NN,N (Δ—) · 2/N vivas` (1º snapshot = sempre "muda", então aparece; ver
   `SddBriefLineService::line():84` — `$previous === null` ⇒ `$changed = true`).
5. O **card SDD do dashboard** (`/governance`) sai do empty-state e mostra composta + vivas.

## Passos (ordenados, concretos)
1. **Pré-flight em prod:** SSH Hostinger → `cd $RDIR && php artisan migrate:status | grep sdd_scorecard_history`
   pra confirmar o estado real (pending vs ran). NÃO assumir — a evidência diz 0 rows, mas a tabela pode
   ter sido criada e o snapshot simplesmente nunca ter rodado (ou o `environments(['live'])` não casar).
2. **Aplicar a migration:** disparar deploy normal com `skip_migrate` = false (default), OU rodar
   pontualmente via `extra_artisan` (`deploy.yml:272` aceita `inputs.extra_artisan`) com
   `migrate --force`. Verificar exit 0 e que o passo `Artisan migrate` do `deploy.yml:270` não foi pulado.
3. **Forçar o 1º snapshot na hora (não esperar 07:10):**
   `php artisan governance:sdd-scorecard-snapshot` em prod (com `node` disponível, lê live de
   `scripts/governance/sdd-scorecard.mjs`). Confirmar output `Snapshot SDD OK — <data> · composta NN,N (k=2) · 2/N vivas`.
   Se o ambiente prod não tiver `node`, usar `--input` com um JSON pré-gerado (o command suporta — `:94`).
4. **Validar a row:** `SELECT snapshot_date, composta, JSON_EXTRACT(payload,'$.composta_k'), JSON_EXTRACT(payload,'$.vivas') FROM mcp_sdd_scorecard_history ORDER BY snapshot_date DESC LIMIT 1`.
   Esperado: `composta` não-nulo, `composta_k = 2`, `vivas ≥ 2`.
5. **Gerar o brief e inspecionar a linha:** rodar o `GenerateBriefCommand` em prod (ou esperar o ciclo do
   brief) e confirmar via `mcp__oimpresso__brief-fetch` que a seção `## FLAGS` traz o bullet SDD.
   (kill-switch `GOVERNANCE_SDD_BRIEF_LINE` deve estar ON/ausente — default true, `SddBriefLineService::inject():39`.)
6. **Validar o card do dashboard:** browser MCP em `/governance` → screenshot mostrando card SDD
   com composta (não empty-state). Salvar evidência.
7. **Confirmar o cron diário:** após 24h, conferir 2ª row (`snapshot_date` do dia seguinte) pra provar
   que `dailyAt('07:10')` está disparando em prod (`onFailure` loga em `single` channel se falhar — `Kernel.php:333`).

## Arquivos a tocar (lista real)
- **Nenhum arquivo de produção a EDITAR** — código já mergeado. P06 é operação + validação.
- Possível toque (opcional, fora do caminho crítico): se o pré-flight revelar que `environments(['live'])`
  não casa com o ambiente prod (Hostinger), corrigir `app/Console/Kernel.php:332` — mas só após confirmar
  que é a causa (não inventar).
- Evidência a produzir (não é "tocar" código): screenshot do card + dump da query + brief com a linha.

## Gate / counterfactual (COMO provo que isto MORDE)
P06 **não é um gate de CI** — é uma entrega operacional com prova objetiva, então o counterfactual é
empírico, não um diff que dá exit 1:

- **Prova positiva (o que deve ser verdade ao fechar):** `SELECT COUNT(*) FROM mcp_sdd_scorecard_history ≥ 1`
  E a string `SDD: composta` aparece no markdown do brief real. Hoje, AMBOS falham (tabela inexistente).
- **Counterfactual que prova que mordeu:** rodar `php artisan governance:sdd-scorecard-snapshot` ANTES
  da migration → o command retorna `self::FAILURE` com `'Tabela mcp_sdd_scorecard_history não existe'`
  (`SddScorecardSnapshotCommand.php:36`). Depois da migration → exit 0 + 1 row. A transição FAILURE→0+row
  é a mordida.
- **Regressão futura a vigiar:** se alguém dropar a tabela ou o cron parar >24h, o brief silenciosamente
  volta a omitir a linha (degrada gracioso — `inject()` é best-effort). Isso é invisível. **Recomendação
  (fora do escopo de P06, candidato a item próprio):** adicionar um check em `jana:health-check` que fale
  ALERT se `mcp_sdd_scorecard_history` tiver 0 rows OU `MAX(snapshot_date) < hoje-2d` em prod — fecha o
  buraco "entrega some sem ninguém notar" que é exatamente o que aconteceu aqui.

## Dependências (e por que)
- **P01 (read-side do floor) — SOFT, não hard.** Frontmatter mantém `depende_de: [P01]` por fidelidade ao
  roadmap, mas tecnicamente P06 entrega valor sem P01 (composta k=2 com ghost+door, já armados no baseline).
  P01 só eleva k=2→k=3 ao armar `full_suite_pass_rate`. **Por quê importa:** se P06 esperar P01, atrasa uma
  entrega já-pronta por uma dependência inexistente. Recomendo soltar P06 ANTES de P01.
- **ADR 0279 (transporte do floor CT100):** relacionada porque é a infra que P01 conserta; P06 não a toca.
- **Ambiente prod (Hostinger) com `node` no PATH:** se ausente, usar `--input` (o command já suporta).

## Esforço (recalibrado ADR 0106 — codável vs relógio real)
- **Codável com IA-pair:** ~0d. **Não há código a escrever** (tudo mergeado). No máximo 0.2d se o
  pré-flight revelar um ajuste de `environments(['live'])` no Kernel — improvável.
- **Operação humano-limitada (relógio do mundo real):**
  - Aplicar migration via deploy + rodar snapshot manual: ~30min síncronos (Wagner ou agente com SSH).
  - Validar brief: o brief é gerado em ciclo agendado; a linha SÓ aparece após (a) tabela criada,
    (b) ≥1 snapshot, (c) próxima geração de brief. **Relógio real: ~1 dia** pra ver a linha no brief
    natural (ou imediato se gerar o brief à mão pós-snapshot).
  - Provar o cron diário (passo 7): **+1 dia de relógio** pra ver a 2ª row aparecer sozinha às 07:10.
- **Total honesto:** trabalho ativo ~30-60min; janela de validação completa ~1-2 dias de relógio
  (esperar 2 ciclos de cron). Nada disso é "10x acelerável" — é deploy + espera de cron.

## Kill-criteria / risco (quando parar ou reabrir)
- **Reabrir / escalar se:** a migration aplicar mas o snapshot continuar falhando (ex.: `node` ausente em
  prod e `scripts/governance/sdd-scorecard.mjs` indisponível) → investigar antes de forçar `--input`
  estático (que viraria um número de fixture mentindo como prod — proibido).
- **Parar se:** o pré-flight (passo 1) revelar que a tabela JÁ existe com rows em prod e o problema real
  é o brief/card não renderizando — então a causa-raiz NÃO é a migration e este plano está errado;
  reabrir investigação no `inject()`/`buildSddPayload()`.
- **Risco de número enganoso:** com só 2 métricas armadas (ghost + door), a composta v1 é `k=2` — o card
  e a linha DEVEM imprimir o `k` junto (ADR 0275 §4 `:69` proíbe plotar trend cruzando mudança de k).
  Confirmar que o frontend/brief expõem o `k` (payload tem `composta_k`); se não expuserem, é mentira por
  omissão e P06 não fecha até corrigir.
- **Não promover nada a required** a partir de P06 — é entrega de leitura, não de governança (a governança
  required é P01→GT-G3, outro item).
