# 2026-07-04 — Rotina semanal de memória da Jana (2ª execução)

> Task agendada `copiloto-memoria-semanal` (desassistida). Owner: Wagner. Cadência 1×/semana.

## TL;DR

RUNBOOK estava **4741 commits stale** (última edição 2026-05-04). Esta rodada **reconciliou o playbook com o canon** em vez de aplicar código às cegas — decisão justificada abaixo. Nenhum código de retrieval tocado. Fila de código pronta (cast fix + P1-A.0) registrada com diffs + bloqueadores.

## Baseline medido (CT 100 · `oimpresso-staging` · biz=1)

```
COLS = id,business_id,user_id,fato,metadata,valid_from,valid_until,event_valid_from,
       event_valid_until,supersedes_id,hits_count,ultimo_hit_em,core_memory,...
total=5 | hit_rate=1.0 (5/5) | bloat_ratio=0 | core_memory=0
withRelevancia=5   (dist. incl. inativos: rel=4→3, rel=3→1, blank→10)
distinct_businesses=…
```

⚠️ **Staging é clone anonimizado (5 fatos biz=1)** — `Recall@3`/`Precision@3` **não são medíveis autonomamente** aqui. Prod não é mutável pela rotina; `jana:recall-eval` mira `mcp_memory_documents` (índice de conhecimento canônico), **não** `jana_memoria_facts` (fatos de negócio). Logo a regra RUNBOOK #5 ("sem baseline, não aplicar") **bloqueia** qualquer melhoria que mude o retrieval (P1-A).

## Estado real do canon (verificado em `origin/main` @ `10c41d6a07`)

| Item playbook | Status real | Evidência |
|---|---|---|
| **Tier 2** — HyDE / reranker / `semantic_ratio` / RRF / decay | ✅ **já construído** | `MeilisearchDriver::buscar()` injeta `HydeQueryExpander`, `Reranker` (rrf/llm/null via config), `semantic_ratio` configurável (0.7) |
| Suite de eval | ✅ existe (não no RUNBOOK) | `jana:recall-eval` (golden set `tests/eval/recall-golden.yaml`), `jana:ragas-eval`, `jana:ragas-real-eval`, `jana:drift-sentinel`, `apurar-metricas` |
| **P1-A** (filtro `metadata_relevancia >= 3`) | ❌ **não aplicado** | `buscar()` filtra só `business_id`+`user_id` |
| **P1-C / P1-A.0** (flatten `metadata_relevancia` + filterable) | ❌ **não aplicado** | `toSearchableArray()` manda `metadata_json` opaco; `config/scout.php` `index-settings` vazio |
| **Pendência #1** — `MemoriaFato.$casts` sem `hits_count`/`core_memory`/`ultimo_hit_em` | ❌ **ainda aberta** (colunas existem no DB) | `$casts` só tem metadata/datas/supersedes_id |

## O que foi feito nesta rodada

1. **Reconciliação do RUNBOOK** (`memory/requisitos/Jana/RUNBOOK-MEMORIA-SEMANAL.md`):
   - Linha de histórico 2026-07-04 (📋 reconcile).
   - Pendência #1 reconfirmada + marcada com diff pronto.
   - Nova **§7 — Fila de melhorias de código prontas** (7.A cast fix, 7.B P1-A.0) com diffs + bloqueador operacional do reindex.
   - Footer atualizado.
2. **Baseline registrado** (staging biz=1, acima).

## Por que NÃO apliquei código nesta rodada (decisão autônoma, registrada)

Três condições de segurança falharam para um PR de código desassistido no subsistema de memória:
1. **Sem baseline de `Recall@3` medível** (regra #5) → bloqueia P1-A.
2. **Testes unitários relevantes são sqlite-only** (`HitTrackerServiceTest`: 9 skipped no MySQL do CT 100) → sem validação limpa autônoma.
3. **Subsistema sob desenvolvimento ativo** — HEAD do staging = `#3621 "loop IA-OS #3 fechado — recall-eval real migra live→staging [CC]"`. Tier 2 já construído; o "próximo passo" do RUNBOOK estava obsoleto. Landar às cegas arrisca colisão.

A reconciliação do playbook **é** uma correção da Fase 7 (Evolução/governança): um playbook stale desorienta a própria rotina. Zero risco de código, não precisa de baseline, e é o passo em §step 4 da SKILL.

## Próxima ação recomendada (1 frase)

Numa **sessão assistida**, aplicar **§7.A (cast fix)** primeiro — worktree fresco off `origin/main` + Pest no CT 100 (novo teste que exercita o cast Eloquent) + PR sem merge; deixar §7.B (P1-A.0) para depois com auditoria dos `filterableAttributes` de prod antes de qualquer reindex.

## Comandos-chave (reprodutíveis)

- Baseline: `tailscale ssh root@ct100-mcp "… | base64 -d | docker exec -i oimpresso-staging php artisan tinker"` (script `jana_baseline.php`).
- Suite: `tailscale ssh root@ct100-mcp 'docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test Modules/Jana/Tests/Feature/HitTrackerServiceTest.php'` → 9 skipped (sqlite-only).

## Decisão do Wagner (mesmo dia, presente): APOSENTAR a rotina

Perguntado se a tarefa `copiloto-memoria-semanal` ainda é válida perante o SDD, o Wagner respondeu **"1" = aposentar**. Verificado em `origin/main` que o SDD absorveu o loop:
- `jana:drift-sentinel` (canary semanal, `app/Console/Kernel.php`), `jana:recall-eval --mode=real` (Recall@3 semanal + gate CI `jana-recall-eval.yml`), `jana:ragas-real-eval` (RAGAS semanal, ADR 0318).
- Scorecard unificado `governance/sdd-scorecard.json` stream **MEM** (read-path ADR 0270) — `recall_eval_violations`, `ragas_real_uptime`, drift.
- Rodar a rotina manual reproduzia o anti-padrão "roadmap paralelo a canon" ([proibicoes §Ideias descartadas](../proibicoes.md)).

**Executado:**
1. Scheduled-task `copiloto-memoria-semanal` → `enabled=false` (via MCP scheduled-tasks); descrição marcada como APOSENTADA.
2. RUNBOOK-MEMORIA-SEMANAL → banner 🪦 superseded no topo apontando pros crons/gates/scorecard do SDD; corpo preservado como histórico.
3. Resíduo registrado (não perdido): higiene dos `jana_memoria_facts` por-business (hit_rate/bloat/core_memory, Fase 7/8) + cast fix §7 — candidato a virar **métrica MEM no scorecard**, não cron paralelo.

**NÃO tocado:** crons/gates/scorecard do SDD (já funcionam).

## PR

`chore/jana-memoria-semanal-2026-07-04` — docs-only (RUNBOOK banner superseded + este session log) + desativação da scheduled-task (fora do repo). Sem código, sem Pest necessário. Não-merge autônomo (R10).
