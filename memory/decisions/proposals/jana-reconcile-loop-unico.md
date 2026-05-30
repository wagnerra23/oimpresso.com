---
slug: jana-reconcile-loop-unico
title: "jana:reconcile — loop de reconciliação único (git == índice == MCP == settings == deploy) consolidando o padrão Reconciler já provado"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-30"
module: governance
quarter: 2026-Q2
tags: [governance, reconcile, self-healing, drift, gitops, idempotente, index, freshness, mcp, claude-design, anti-poluicao]
related:
  - 0216-governance-drift-framework-driftchecker-plugavel
  - 0220-charters-freshness-checker-adapter
  - 0236-governanca-evolucao-doc-design
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0053-mcp-server-governanca-como-produto
authors: [W, C]
---

# Proposta — `jana:reconcile`: loop de reconciliação único (anti-poluição de memória)

> **Origem:** Wagner 2026-05-30, ao ver que o `_INDEX-LIFECYCLE` registrava **5 de 11** colisões: *"então o index está errado? pode estar poluindo com memórias ruins?"*. Sim — e a causa-raiz não é capability, é **orquestração**: índices mantidos à mão driftam do git, e o MCP + o Claude Design consomem o drift como verdade.
> **Número alvo no aceite:** `0237` (verificado livre 2026-05-30 pelo novo `AdrNumberCollisionTest` — reservar ao aceitar).
> **Design-base:** dossiê estado-da-arte [`2026-05-29-arte-reconcile-loop-kb-self-healing`](../../sessions/2026-05-29-arte-reconcile-loop-kb-self-healing.md) (nota oimpresso **63/100**; gap = orquestração, não capability). Esta ADR formaliza + decide; o dossiê tem a pesquisa profunda (não duplicar aqui — DRY é justamente o princípio).

## Contexto — a poluição, medida (não hipótese)

O drift entre **git (verdade) ↔ índice ↔ MCP ↔ settings ↔ código deployado** é descoberto só na hora de operar, nunca antes. Evidência desta sessão:

- `_INDEX-LIFECYCLE.numbering_collisions`: **5 de 11** colisões registradas (45% de drift) — pego só quando o `AdrNumberCollisionTest` rodou pela 1ª vez.
- Docs de design citando ADR **morta**: `0039` (superseded) em **31 docs**, `0009` em 7, `0008` em 6 — risco do Claude Design reconstruir o padrão velho.
- `INDEX-DESIGN-MEMORIAS §6`: ~13 docs declarados stale pelo próprio índice, **1** refrescado.
- Dossiê: embedder Meilisearch perdido 2×, `modulos/INDEX.md` parado desde abril, container MCP 1302 commits atrás, eval golden-set perdida.

**As peças de detecção existem, mas fragmentadas** — cada uma resolve 1 faceta, nenhuma sob contrato único:

| Peça existente | Faceta que cobre |
|---|---|
| `governance:audit` + N DriftCheckers ([ADR 0216](../0216-governance-drift-framework-driftchecker-plugavel.md)) + o novo `DesignDocsFreshnessChecker` | drift de código/doc |
| `freshness-check` + `StalenessDetectorService` | git→DB (`updated_at>indexed_at`, SHA git↔DB) |
| `index-regen --check` | contagens + links + Tier 0 do `INDEX.md` |
| `meilisearch-setup` | settings/embedder do índice |
| `DeployDriftChecker` | SHA deployado vs `main` |
| **`ChannelsReconcilerCommand`** (WhatsApp) | **o padrão Reconciler classe-A — já provado em prod** |

**A surpresa do dossiê:** o oimpresso JÁ construiu o reconcile loop **duas vezes** (WhatsApp `ChannelsReconcilerCommand` + Governance Drift Framework ADR 0216) **sem nomear o padrão**. Quando a poluição apareceu na KB/índice, ninguém aplicou a solução que estava no repo ao lado. Não é arquitetura nova — é **transferência de padrão entre domínios**.

## Decisão

### 1. Nomear o primitivo `Reconciler` (1ª classe)
Cada faceta do estado vira um **Reconciler** com contrato único (espelha o `ChannelsReconcilerCommand` provado):
- `desired()` — estado desejado, derivado do **git** (fonte da verdade, [ADR 0061](../0061-conhecimento-canonico-git-mcp-zero-automem.md)).
- `observed()` — estado vivo (índice / DB / settings / SHA deployado).
- `diff()` — drift semântico desired × observed.
- `heal(dryRun)` — cura **idempotente** o que é seguro curar (append-only — nunca rebaixa/deleta).
- `alert()` — alerta idempotente (`mcp_alertas_eventos`) o que não é seguro curar.

### 2. `jana:reconcile` — orquestrador único (CONSOLIDA, não reinventa)
```
php artisan jana:reconcile [--check] [--heal] [--dry-run] [--only=index,settings,...] [--json]

  --check : exit 1 se QUALQUER reconciler reporta drift. NÃO cura. (gate de PR / CI)
  --heal  : cura o seguro (idempotente); alerta o resto. (cron diário)
  default : reporta desired × observed × drift por faceta, sem mexer.
```
O orquestrador **chama a lógica que já existe** (extraída de `governance:audit`, `freshness-check`, `index-regen`, `meilisearch-setup` para services reusáveis). **NÃO cria 5 comandos novos.** O `ChannelsReconcilerCommand` é o template — copiar a forma (stats, dry-run, idempotência, summary, exit code).

### 3. Os reconcilers (mapa de consolidação)
| Reconciler | desired (git) | observed (vivo) | heal | consolida |
|---|---|---|---|---|
| **IndexReconciler** ⬅️ *cura a poluição desta sessão* | lifecycle real das ADRs + lista real de docs + Tier 0 + links | `_INDEX-LIFECYCLE` · `INDEX-DESIGN-MEMORIAS` · `INDEX.md` · `modulos/INDEX.md` | reescreve contagens/colisões/lista; regenera `modulos/INDEX.md` | `index-regen` + os testes de hoje |
| **SettingsReconciler** | `config meilisearch_indexes` | settings vivos do índice | re-aplica embedder `{}`/divergente | `meilisearch-setup` + flag `--check` |
| **ContentReconciler** | `memory/**` + `git_sha` HEAD | `mcp_memory_documents` | re-sync + re-embed do doc divergente | `sync-memory` + `freshness --reindex` |
| **DeployReconciler** | `origin/main` HEAD | SHA deployado CT 100 | alerta drift > N commits | `DeployDriftChecker` |
| **EvalReconciler** | golden-set + threshold | resultado da eval | exit 1 se pass-rate < threshold | RAGAS gate |

> `governance:audit` (DriftCheckers do ADR 0216, incl. o `DesignDocsFreshnessChecker` de hoje) é **uma família de reconcilers** sob o guarda-chuva — `jana:reconcile` o **chama**, não duplica.

### 4. Rollout incremental (dossiê: começar pequeno, não os 5 de uma vez)
1. **IndexReconciler (P0)** — cura exatamente a poluição que o Wagner viu (índices que driftam → memórias ruins servidas ao MCP + Claude Design). **Os 2 testes de hoje (`AdrNumberCollisionTest` + `DesignIndexSingleSourceTest`) já SÃO o `--check` dele** — falta só o `--heal` (reescrever a parte computável dos índices).
2. **SettingsReconciler** — `meilisearch-setup --check` (embedder perdido 2× degrada recall em silêncio).
3. **`jana:reconcile` orquestrador** — unifica sob 1 contrato + exit code + JSON; pluga no cron diário + gate de PR.
4. Deploy + Eval reconcilers.

## Invariantes (herdados ADR 0216 / 0230)
- **Idempotência:** rodar 2× = mesmo resultado; `heal` nunca rebaixa/deleta (append-only).
- **Rastreabilidade (RTM):** todo drift cita a fonte (git path / ADR / doc).
- **Cura segura só:** o que tem fonte-de-verdade clara cura sozinho; ambíguo **alerta** humano (R10 — sem commit/push automático sem aprovação).

## Consequências
- ✅ Índice nunca diverge em silêncio — `--check` no CI pega antes de mergear; `--heal` no cron cura.
- ✅ Mata a poluição **na origem** (MCP + Claude Design param de consumir drift como verdade).
- ✅ Nomeia o padrão que já existe → próximo domínio que sangrar reusa, não reconstrói a 4ª vez.
- ⚠️ Esforço de extração (mover lógica de commands → services reusáveis) — por isso o rollout incremental.
- ⚠️ `heal` que reescreve índice respeita append-only: índices são `type: index` (não ADR), então reescrever a parte computável é permitido; a parte curada à mão (prosa) nunca é tocada.

## Estado-da-arte (resumo — detalhe no dossiê §1)
ArgoCD/Flux (`selfHeal`, reconcile contínuo) · Meilisearch settings-as-code · Cognee `memify` · Drift-Adapter (arXiv 2509.23471) · RAG observability 2026 (streaming re-index + 3 sinais de drift). Síntese: declara TODO o estado em git → 1 controller reconcilia contínuo desired×vivo em todas as camadas → cura o seguro, alerta o resto, idempotente. O oimpresso tem **mais peças prontas que a média** — o gap é o contrato único.

## Próximo passo (ao aceitar)
Reservar `0237`. Implementar o **IndexReconciler** primeiro — os testes de colisão + índice-fonte-única de hoje já cobrem o `--check`; só falta o `--heal` (reescrever contagens/colisões/lista computável dos 4 índices).
