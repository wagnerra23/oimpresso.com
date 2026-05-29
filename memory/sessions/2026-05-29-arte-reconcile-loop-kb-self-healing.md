---
name: Estado-da-arte — Reconcile loop / self-healing da knowledge base + índice
slug: arte-reconcile-loop-kb-self-healing
data: 2026-05-29
autor: estado-da-arte (Claude Opus 4.8)
tema: arquitetura de sincronização + frescura + auto-cura de KB/índice de busca
status: dossier (não-normativo — insumo pra ADR)
related: [0053, 0061, 0067, 0093, 0216, FRESHNESS-PIPELINE]
---

# Reconcile loop / self-healing da KB — SOTA 2026 vs oimpresso

> Problema observado (2026-05-29): o time "descobre" que coisas se perderam/ficaram stale só
> na hora de operar — embedder Meilisearch perdido 2×, INDEX.md com contagens stale,
> `modulos/INDEX.md` parado desde abr, container MCP 1302 commits atrás, eval golden-set
> perdida, SPEC uncommitted na prod. As peças de detecção existem; falta um **loop de
> reconciliação declarativo único** que garanta `git == DB == índice == settings == embedder == código deployado`
> e cure/alerte sozinho.

---

## 1. Como os melhores fazem (SOTA 2026)

A pesquisa convergiu pra um único paradigma vindo de **GitOps** e adaptado a KB/RAG:
**estado desejado declarado em git → controller reconcilia continuamente o estado vivo
contra ele → detecta drift → cura (self-heal) ou alerta.** Os 4 sub-campos abaixo são
facetas do mesmo princípio.

| Player / fonte | Mecanismo concreto (não buzzword) | Por que é referência |
|---|---|---|
| **ArgoCD / Flux CD** (GitOps) | Controller mantém cache watch-backed do estado vivo; faz *semantic diff* contra manifests renderizados do git em loop contínuo. `selfHeal: true` re-aplica em ~5s. Flux 2.8 cancela health-check em andamento e dispara reconcile assim que um fix entra no git. Server-Side Apply dá ownership de campos ao API server (menos "conflict storms"). | Define o paradigma. "Drift detection como produto", reconcile contínuo, self-heal opt-in. É o que resolve "nunca se perder" no mundo infra. |
| **Meilisearch settings-as-code** (v1.6→1.14) | `embedders` é um *index setting* declarativo (source/model/dimensions/documentTemplate). Mudar o setting faz o Meilisearch **re-embedar os docs existentes** (task async). 1.14: embedder distinto em index-time vs query-time; controle de erro por-embedder (doc sem embedding não derruba o batch). | É exatamente a stack do oimpresso. Settings versionáveis = índice reproduzível a partir de config. |
| **Cognee `memify`** (KB self-improving) | Pipeline de pós-processamento que roda *após* a ingestão: poda nós por baixa frequência de acesso, reforça/re-pondera edges por sinal de uso (respostas avaliadas realimentam pesos), deriva fatos novos, re-otimiza embeddings. Memória como estrutura que evolve, não arquivo. ⚠️ Doc oficial é vago no "como" — é mais *framework pra construir* self-heal do que caixa-preta pronta. | Mostra a fronteira: KB que se mantém sozinha por feedback de uso. Inspiração, não receita copiável. |
| **Drift-Adapter** (arXiv 2509.23471, set/2025) | Upgrade de modelo de embedding **sem re-embedar tudo**: aprende uma matriz de transformação linear que mapeia o espaço do modelo velho → novo (espaços compartilham geometria). Serve híbrido durante transição (velho-transformado + novo-fresco), near-zero-downtime. | Resolve o calcanhar de Aquiles do "embedder mudou": migração de modelo barata e reproduzível, sem reindex total. |
| **RAG observability 2026** (FutureAGI, RisingWave, apxml) | Tendência: batch re-index → **streaming re-index** (só o doc que mudou re-embeda na hora). 3 sinais de drift monitorados: (a) avg top-k similarity caindo, (b) eval pass-rate de Faithfulness caindo, (c) distribuição de versões de doc citadas (docs velhos dominando = índice stale). SLO de freshness + snapshots de índice versionados. | Define **o que medir** pra saber que o índice degradou *antes* do operador descobrir na mão. |

**Síntese SOTA:** o melhor sistema (a) declara TODO o estado desejado em git (docs + settings + embedder + schema + versão de código), (b) tem **um** reconcile loop que compara desejado×vivo em todas as camadas, (c) cura o que é seguro curar e alerta o resto com idempotência, (d) trata embedder como artefato versionado com migração barata, (e) mede drift por sinal de qualidade (eval), não só por idade.

---

## 2. NOTA 0-100 do oimpresso por dimensão

Avaliado contra o código real (`McpSyncMemoryCommand`, `FreshnessCheckCommand` +
`StalenessDetectorService`, `IndexRegenCommand`, `MeilisearchIndexSetupCommand` +
`config copiloto.meilisearch_indexes`, `ChannelsReconcilerCommand`, `DetectDriftCommand`
/ Governance Drift Framework ADR 0216, `deploy.yml`, `INFRA-ACESSO-CANON.md`).

| # | Dimensão | Nota | Justificativa (honesta) |
|---|---|---:|---|
| a | **Fonte-de-verdade** | **92** | Git canônico (ADR 0061), zero auto-mem, hook bloqueador. Settings de índice agora em git (`meilisearch_indexes`). Top de mercado. Tira 8 porque `modulos/INDEX.md` e contagens do `INDEX.md` ainda têm curadoria semi-manual que driftou. |
| b | **Sync automation (git→DB→índice)** | **80** | Webhook GitHub + cron 5min + checksum `git_sha` + `withoutSyncingToSearch` + redaction PII. Sólido. Mas é **push de conteúdo**, não reconcile: se o índice perder settings, o sync não reconstrói; é batch, não streaming por-doc-mudado. |
| c | **Drift detection** | **78** | Forte e plural: `freshness-check` (4 níveis + drift A `updated_at>indexed_at` + drift B SHA git↔DB), `index-regen --check` (Tier 0 + links + contagens), Governance Drift Framework (ADR 0216) com DriftCheckers idempotentes. Detecta drift de **conteúdo/estrutura**; NÃO detecta drift de **settings de índice** (embedder sumiu) nem de **versão de código deployado**. |
| d | **Self-healing / reconcile** | **45** | Existe o *padrão* maduro (`ChannelsReconcilerCommand`: desired×live, auto-fix, dry-run, idempotente) mas **só no domínio WhatsApp**. Na KB: `freshness-check --reindex` dispara re-embed (cura parcial), `index-regen --fix` reescreve contagens. Mas NÃO há um reconcile que, ao ver embedder `{}`, re-aplique sozinho; settings driftam sem cura. Sem entrypoint único. |
| e | **Index/embedder-as-code** | **70** | `meilisearch-setup` + config com model+dimensions+documentTemplate versionados — exatamente SOTA Meilisearch. Tira 30 porque: (1) é aplicação manual/cron, não reconciliação contínua que **detecta** o drift de settings; (2) sem migração de modelo (nada estilo Drift-Adapter — trocar embedder = reindex total na unha); (3) o gate `meilisearch-setup` não tem `--check` que falhe se o índice vivo ≠ config. |
| f | **Deploy drift** | **20** | Pior dimensão. `deploy.yml` é `workflow_dispatch` (manual) e **só cobre Hostinger** — o **container MCP CT 100 não está em nenhum workflow**. Deploy do MCP = SSH manual (`git pull` + composer + `octane:reload`). Resultado real: 1302 commits atrás + `composer install` quebrado por Dockerfile. Código deployado ≠ git é invisível até quebrar. |
| g | **Observabilidade de drift** | **58** | Tem alertas idempotentes (`mcp_alertas_eventos`), exit codes p/ cron, `health-check` daily 5 SQL checks, logs estruturados, métricas-alvo (FRESH+WARM≥80%). Mas mede **idade/presença**, não **qualidade**: não há SLO de freshness publicado, nem o sinal SOTA "eval pass-rate / top-k similarity caindo" como gate vivo (a eval golden-set FULLTEXT×hybrid se PERDEU justamente porque não era gate rodando). Sem "reconcile lag" como métrica. |

### Nota global: **63 / 100**

Leitura: **fundação de fonte-de-verdade e detecção é forte (top de mercado em a/b/c)**, mas
**cura/reconcile é parcial e fragmentado (d), e o estado "código deployado" é praticamente
cego (f)**. O oimpresso tem MAIS peças prontas que a média do mercado — o gap não é capability,
é **orquestração**: as peças não falam entre si sob um contrato único de "estado desejado".
Notavelmente, o padrão de reconcile que falta **já existe e está provado** no `ChannelsReconcilerCommand`.

---

## 3. Como deveria ser — o reconcile loop único

### Desenho: `jana:reconcile` (1 comando, N reconcilers, 1 contrato)

Espelha ArgoCD/Flux e **generaliza o padrão `ChannelsReconcilerCommand` que já funciona**.
Cada faceta vira um *Reconciler* que implementa `desired()`, `observed()`, `diff()`,
`heal()` (idempotente, com `--dry-run`), `alert()`. O comando orquestra, agrega e expõe
exit code + JSON.

```
php artisan jana:reconcile [--check] [--heal] [--dry-run] [--only=settings,index,deploy] [--json]

  --check  : CI-friendly. exit 1 se QUALQUER reconciler reporta drift. NÃO cura. (gate de PR)
  --heal   : cura o que é seguro curar (idempotente); alerta o resto. (cron diário/horário)
  default  : reporta desired×observed×drift por faceta, sem mexer.
```

Reconcilers (estado desejado em git → cura):

| Reconciler | Desired (git) | Observed (vivo) | Heal |
|---|---|---|---|
| `ContentReconciler` | `memory/**` + `git_sha` HEAD | `mcp_memory_documents` | re-sync + re-embed do doc divergente (consolida `sync-memory` + `freshness --reindex`) |
| `SettingsReconciler` | `config meilisearch_indexes` | `GET /indexes/{uid}/settings` vivo | **detecta** embedder `{}`/divergente e **re-aplica** (hoje só aplica cego); falha `--check` se ≠ |
| `IndexReconciler` | Tier 0 + links + contagens | `memory/INDEX.md` + `modulos/INDEX.md` | reescreve contagens, regenera `modulos/INDEX.md` (consolida `index-regen`) |
| `EvalReconciler` | golden-set + threshold (recall/Faithfulness) | resultado da eval rodada | falha `--check` se pass-rate < threshold (re-instaura a eval perdida como gate) |
| `DeployReconciler` | `origin/main` HEAD | SHA deployado no CT 100 (`git rev-parse` via tailscale ssh) | alerta drift > N commits; opcional dispara deploy |

### Rankeado impacto × esforço (ADR 0106: estimativa IA-pair 10x + margem 2x)

| Ação | Impacto | Esforço IA-pair | Pré-req? | Consolida/Evolui |
|---|---|---|---|---|
| **SettingsReconciler** (detect+heal embedder drift) | **alto** — mata o bug recorrente nº3 (embedder perdido 2×) que degrada recall silenciosamente | ~2-3h | nenhum (config + command já existem) | **EVOLUI** `meilisearch-setup`: add `--check` que compara settings vivos × config |
| **DeployReconciler** (SHA CT 100 × origin/main) | **alto** — drift de deploy é cego hoje (1302 commits); P1 operacional | ~3-4h | acesso tailscale ssh (já existe) | **NOVO** reconciler |
| **`jana:reconcile` orquestrador** | **alto** — entrypoint único + contrato; transforma peças soltas em loop | ~4-6h | os reconcilers individuais | **CONSOLIDA** freshness+index-regen+settings sob 1 contrato |
| **EvalReconciler** como gate | **médio-alto** — impede "conclusão de eval perdida"; mede qualidade não só idade | ~4-6h | golden-set versionado + threshold acordado | **EVOLUI** RAGAS gate existente (`jana-ragas-gate.yml`) |
| **`modulos/INDEX.md` no IndexReconciler** | **médio** — stale desde abr | ~1-2h | `module:specs` existente | **CONSOLIDA** em `index-regen` |
| Streaming re-index (só doc mudado) | **médio** | ~6-8h | ContentReconciler | EVOLUI sync batch |
| Drift-Adapter (migração de embedder sem reindex total) | **baixo (hoje)** | ~1-2 dias | só vale quando trocar modelo | NOVO (não fazer agora) |

**Princípio de consolidação:** NÃO criar 5 comandos novos. `jana:reconcile` **chama** a lógica
que já existe (extrair de `freshness-check`, `index-regen`, `meilisearch-setup` para services
reusáveis) sob o contrato Reconciler. O `ChannelsReconcilerCommand` é o template de referência —
copiar a forma (stats, dry-run, idempotência, summary, exit code), não reinventar.

---

## 4. Surpresa estratégica

**O oimpresso já tem o reconcile loop de classe-A — só não percebeu que tem.**

O `ChannelsReconcilerCommand` (WhatsApp, 2026-05-13) é, ponto por ponto, um mini-ArgoCD:
desired (DB) × observed (daemon) × semantic diff (tabela de transições) × auto-fix idempotente
× dry-run × summary × exit code. E o **Governance Drift Framework (ADR 0216)** já generalizou
"N DriftCheckers sob `governance:audit` + auto-PR de cura" — que é *literalmente* o padrão
de reconcile aplicado a governança de código.

A surpresa: **o gap da KB não é técnico nem de maturidade — é de transferência de padrão entre
domínios.** O time construiu o reconcile loop duas vezes (WhatsApp, Governance) sem nomear o
padrão, então quando o problema apareceu na KB (embedder perdido, deploy drift) ninguém aplicou
a solução que já estava no repo ao lado. A ação de maior alavancagem não é inventar arquitetura
nova — é **nomear o padrão Reconciler como primitivo de 1ª classe** (talvez ADR) e
*portá-lo* pra KB. Custo de ignorar isso: vai-se reconstruir o mesmo loop uma 4ª vez no próximo
domínio que sangrar.

---

## Recomendação final

**Comece pelo `SettingsReconciler` (evoluir `jana:meilisearch-setup` com `--check`).**
Alto-impacto (mata o bug recorrente nº3 — embedder perdido que degrada recall em silêncio),
baixo-esforço (~2-3h IA-pair), **sem pré-req bloqueante** (config + command já existem), e é a
primeira peça concreta do `jana:reconcile`.

**Próxima ação hoje:** adicionar flag `--check` ao `MeilisearchIndexSetupCommand` que faz
`GET /indexes/{uid}/settings` em cada índice, compara o bloco `embedders`+`filterableAttributes`
vivo contra `config copiloto.meilisearch_indexes`, e retorna exit 1 (+ alerta idempotente em
`mcp_alertas_eventos` tipo `index_settings_drift`) se divergir. Plugar no cron diário e como
gate de PR. Em seguida, extrair `freshness-check` + `index-regen` + esse `--check` para um
orquestrador `jana:reconcile` espelhando o `ChannelsReconcilerCommand`.
