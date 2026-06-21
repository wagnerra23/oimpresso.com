---
date: 2026-06-13
topic: "Rotina semanal de memória da Jana — BLOCKED (report-only, Wagner ausente)"
type: session
---

# Rotina semanal de memória da Jana — 2026-06-13 (BLOCKED · report-only)

> **Status:** ⛔ BLOCKED — nenhuma melhoria aplicada. Run autônomo (Wagner ausente).
> **Cadência:** scheduled-task `copiloto-memoria-semanal`. **2ª execução** da rotina (1ª foi setup-only em 2026-05-04).
> **Resultado:** análise + medição de baseline + verificação de pré-requisitos. **Sem commit/push** (ver "Por que não apliquei").

---

## TL;DR

A rotina não pôde aplicar a próxima melhoria do playbook (P1-A) nesta sessão por **5 bloqueios convergentes** (abaixo). Em vez de forçar, fiz o que era seguro e útil: medi o baseline no único caminho sancionado (CT 100), **verifiquei o estado real de cada pré-requisito contra `origin/main`** e descobri que **o RUNBOOK está 40 dias defasado** vs um pipeline que já evoluiu muito. A ação recomendada para a próxima rodada **não é** "aplicar P1-A" — é **reconciliar o RUNBOOK com a realidade** primeiro, a partir de um checkout fresco de `origin/main` e num ambiente que rode Pest (CT 100).

---

## Medição de baseline (CT 100 staging)

`php` não está no PATH local (proibição Tier 0: testes/eval só no CT 100). Medição rodada no container `oimpresso-staging` via Tailscale:

```
total=5 · hit_rate=1.0 · bloat_ratio=0 · core=0
```

⚠️ **Caveat forte:** é o corpus **staging anonimizado** (n=5, dogfooding), **não** o dataset `oimpresso` local/prod que ancorou o baseline de 2026-04-30 (ADR 0054). Com n=5 os números são ruído — servem só pra confirmar saúde de schema, **não** pra comparar com a série histórica. Confirmam: tabela `jana_memoria_facts` existe e responde; colunas `hits_count`/`core_memory` queryáveis; `core=0` (nenhum fato auto-promovido a core — consistente com a pendência de `$casts` aberta).

---

## Estado verificado dos pré-requisitos (contra `origin/main`, não o working tree stale)

| Pré-req | Item | Estado | Evidência (`origin/main`) |
|---|---|---|---|
| **P1-A #1** | `metadata_relevancia` **flat** + filterable | ❌ UNMET | `MemoriaFato::toSearchableArray()` indexa só `metadata_json` (JSON string); `config/scout.php` index-settings ainda é placeholder comentado; `MeilisearchDriver::buscar()` filtra só `business_id [AND user_id]` (sem relevância) |
| **P1-A #3** | baseline Recall@3 no gabarito | ⚠️ requer CT 100 + gabarito | comando agora existe como `jana:ragas:eval` (RAGAS, não Recall@3 simples) |
| **P1-B (HitTracker)** | `incrementarHits()` plugado no fluxo | ✅ **MET agora** | `LaravelAiSdkDriver.php:244` e `:392` chamam `HitTrackerService` (era pendência aberta em 2026-05-04) |
| **P1-B / Pendência #1** | `$casts` com `hits_count`/`core_memory`/`ultimo_hit_em` | ❌ STILL OPEN (🔴 P1, 40d) | `MemoriaFato::$casts` = `metadata`,`valid_from`,`valid_until` apenas |

**Leitura:** P1-A continua bloqueado na própria etapa de preparação (indexação flat — é mudança de código + reindex, não um filtro de 1 linha). P1-B teve seu prereq de wiring **destravado**, mas a pendência de `$casts` (pequena e segura) segue aberta há 40 dias.

---

## Sinal mais importante: o RUNBOOK está defasado vs o pipeline real

O RUNBOOK (`memory/requisitos/Jana/RUNBOOK-MEMORIA-SEMANAL.md`, última atualização **2026-05-04**) descreve um playbook Tier 1/2/3 que pressupõe um pipeline mais cru. Mas `origin/main` hoje já tem:

- `Modules/Jana/Console/Commands/JanaRagasEvalCommand.php` — **gate RAGAS** (faithfulness/relevancy/precision/recall) sobre Brief Diário + kb-answer
- Stack de reranking completa: `BgeReranker`, `RrfReranker`, `LlmRerankerAdapter`, `NullReranker`
- `GabaritoEvaluator`, `RetrievalTelemetry*` (OTel spans), `RagasEvalCITest`, `HallucinationEvalTest`
- O hook SessionStart lista **"#2 P0 — RAGAS gate em CI"** como pendente na "rotina de fechar o loop do IA-OS"

Ou seja: vários itens do playbook (P2-B HyDE, P2-C Reranker, eval) **já foram construídos ou superados** por trabalho posterior não refletido no RUNBOOK. Aplicar P1-A "porque é o próximo da lista" arriscaria trabalhar sobre premissas mortas.

---

## Por que não apliquei nenhuma melhoria (5 bloqueios)

1. **Checkout stale** — sessão em `feat/governance-ds-rollout-ledger`, **67 commits atrás** de `origin/main`. O guard de base-freshness (incidente 2026-05-31) proíbe produzir/validar canon a partir deste tree. Trabalho real exige `git worktree add -b <branch> <path> origin/main`.
2. **Pest impossível aqui** — `php` fora do PATH local + Tier 0 IRREVOGÁVEL "testes só no CT 100". RUNBOOK regra #1: nunca commitar código sem rodar Pest.
3. **P1-A bloqueado** na etapa de prep (indexação flat de `metadata_relevancia` — verificado UNMET). É exatamente o caso "P1-A.0 preparação" previsto na SKILL.
4. **Wagner ausente** (run agendado) → R10 proíbe merge autônomo; gates de governança (14 required + enforce_admins) impedem push direto em `main`.
5. **RUNBOOK 40d stale** → a ação honesta é reconciliar, não codar.

Conforme regra de segurança #3 da SKILL ("se bloqueado: parar, deixar arquivos como estão, criar session log BLOCKED, Wagner triagia"), **não houve commit, não houve push, o RUNBOOK tracked NÃO foi editado** (pra não poluir a branch errada). A linha de histórico pronta pra aplicar está abaixo.

---

## Para a PRÓXIMA rodada (a partir de checkout fresco `origin/main` + ambiente CT 100)

**Ação recomendada nº 1 (substitui "aplicar P1-A"):** *Reconciliar o RUNBOOK §1/§6 com o pipeline atual* — marcar como ✅/superado o que já existe (eval `jana:ragas:eval`, reranker stack, telemetry), e repriorizar o playbook. Sem isso, toda rodada futura parte de um mapa errado.

**Ação recomendada nº 2 (quick win seguro, depois da nº 1):** fechar a **Pendência #1** — adicionar 3 entradas em `MemoriaFato::$casts`:
```php
'hits_count'    => 'integer',
'core_memory'   => 'boolean',
'ultimo_hit_em' => 'datetime',
```
Mudança mínima, destrava confiabilidade de `core_memory` (hoje `core=0`). Exige: worktree off `origin/main` → editar entity → Pest no CT 100 → PR → Wagner merge (R10).

**Ação recomendada nº 3:** só então atacar **P1-A.0** (indexação flat de `metadata_relevancia` em `toSearchableArray()` + `config/scout.php` + reindex), e medir Recall@3 real no gabarito via CT 100 **antes** de ligar o filtro `>= 3`.

### Linha pronta pra `RUNBOOK §2 Histórico` (aplicar na próxima rodada limpa)

```
| 2026-06-13 | _(análise/BLOCKED — nenhuma melhoria aplicada)_ | — | — | (staging n=5: 1.0) | — | ⛔ blocked | 2ª execução. Bloqueada por: checkout 67-commits stale + Pest só CT 100 + P1-A prereq flat-index UNMET + Wagner ausente (R10). Achado-chave: RUNBOOK 40d defasado vs pipeline (já tem jana:ragas:eval + reranker stack + telemetry). HitTracker prereq de P1-B agora MET (LaravelAiSdkDriver:244,392); pendência #1 ($casts) segue aberta. Próx. ação = reconciliar RUNBOOK, não aplicar P1-A. Ver session 2026-06-13-memoria-semanal-BLOCKED.md |
```

---

## Metadados do run

- **Caminho de medição:** `tailscale ssh root@ct100-mcp` → `docker exec -i oimpresso-staging php artisan tinker` (stdin pipe; CT 100 já autenticado, sem re-auth).
- **Arquivos lidos (canon):** `origin/main:memory/requisitos/Jana/RUNBOOK-MEMORIA-SEMANAL.md`, `MemoriaFato.php`, `config/scout.php`, `MeilisearchDriver.php`, `JanaRagasEvalCommand.php`.
- **Mutações:** nenhuma (só este session log, untracked). Sem git ops.
