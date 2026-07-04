# RUNBOOK — Melhoria semanal da memória da Jana

> **Status:** ativo · **Owner:** Wagner · **Cadência:** 1×/semana (agendado em `~/.claude/scheduled-tasks/copiloto-memoria-semanal/`) · **Criado:** 2026-05-04 (1ª execução)

Playbook + histórico da rotina semanal de melhoria do pipeline de memória do agente IA. A rotina é autônoma (Claude Code agendado, Wagner não está presente) — toda decisão precisa estar escrita aqui ou na SKILL pra ser executada sem perguntar.

---

## 0. Mapa do pipeline (8 fases)

| # | Fase | Componente | Métrica |
|---|------|-----------|---------|
| 1 | Captura | `MemoryAutomataAgent` extrai fato de turno de chat | `facts_extracted_per_turn` |
| 2 | Classificação | Tipo (preferência/fato/contexto) + relevância 1-5 | `metadata.tipo`, `metadata.relevancia` |
| 3 | Persistência | `lembrar()` em `jana_memoria_facts` | `total_fatos` |
| 4 | Indexação | Scout → Meilisearch (full-text + embedder OpenAI) | `index_lag_seconds` |
| 5 | Recall | `MeilisearchDriver::buscar()` (hybrid + HyDE + Reranker) | `Recall@3`, `Precision@3`, `MRR` |
| 6 | Uso | `ChatJanaAgent` injeta no system prompt | `memoria_recall_chars`, `core_memory_used` |
| 7 | Evolução | `hits_count++` quando fato é usado em resposta | `hit_rate` |
| 8 | Esquecimento | Soft-delete LGPD + bloat reducer (hits=0 idade>30d) | `bloat_ratio` |

**Gate canônico (ADR 0049):** `Recall@3 ≥ 0.80` antes de evoluir pra próxima camada de memória.

**Baseline 2026-04-30 (ADR 0054):** Recall@3 = 0.125 · Precision@3 = 0.190 · MRR = 0.274 · p95 = 771 ms. Causa raiz: corpus subdimensionado + filtros ausentes.

---

## 1. Playbook de melhorias (ordem de impacto×esforço)

### Tier 1 — alto impacto, baixo risco

#### P1-A · Filtro `metadata_relevancia >= 3` no recall

**O quê:** descartar facts com relevância 1-2 antes do Meilisearch retornar.

**Pré-requisitos (validar antes de implementar):**
1. Coluna ou atributo Meilisearch `metadata_relevancia` deve estar **flat** (não dentro do JSON `metadata`).
   - Hoje: `Modules/Jana/Entities/MemoriaFato::toSearchableArray()` indexa `metadata_json` como string, **NÃO** expõe `metadata_relevancia` como filterable.
   - **Ação preliminar:** alterar `toSearchableArray()` pra adicionar `'metadata_relevancia' => $this->metadata['relevancia'] ?? null` + adicionar em `config/scout.php` `index-settings.jana_memoria_facts.filterableAttributes` o campo novo + reindexar (`php artisan scout:flush "Modules\\Jana\\Entities\\MemoriaFato" && php artisan scout:import "Modules\\Jana\\Entities\\MemoriaFato"`).
2. `MemoryAutomataAgent` precisa estar populando `metadata.relevancia` (1-5). Se não popula, P1-A vira no-op.
3. Baseline `Recall@3` medido no gabarito antes do filtro (se filtrar muitos facts borderline, pode regredir).

**Implementação:**
- `Modules/Jana/Services/Memoria/MeilisearchDriver.php::buscar()`
- No `$callback`, alterar `$params['filter']` pra incluir `metadata_relevancia >= 3`:
  ```php
  $params['filter'] = sprintf(
      'business_id = %d AND user_id = %d AND metadata_relevancia >= 3',
      $businessId,
      $userId
  );
  ```
- Tornar threshold configurável: `config('jana.memoria.relevancia_minima', 3)`.

**Esperado:** -30% candidatos retornados, +15-20pp Precision@3.

#### P1-B · `core_memory=true` injetado no system prompt antes do recall

**O quê:** facts com `core_memory=true` (hits ≥ 5) são injetados direto no system prompt, sem custo de retrieval.

**Pré-requisitos:**
1. Auto-promote logic implementado (Phase 4 ADR 0054 — fact com hits ≥ 5 vira core).
2. `HitTrackerService` (Modules/Jana/Services/Memoria/HitTrackerService.php) precisa estar conectado no fluxo de resposta.
3. `Modules/Jana/Entities/MemoriaFato.php` precisa ter `hits_count`/`core_memory`/`ultimo_hit_em` em `$casts` (HOJE NÃO TEM — bug detectado 2026-05-04, ver pendências).

**Implementação:** `Modules/Jana/Agents/ChatJanaAgent.php` (ou agente equivalente), adicionar query `MemoriaFato::doUser($biz, $user)->where('core_memory', true)->get()` antes do recall e prepend ao system prompt.

**Esperado:** -10-15% tokens em prompts repetidos + Recall efetivo dos top facts garantido (sem depender de scoring).

#### P1-C · Flatten `metadata_*` em searchable + filterable

**O quê:** expor `metadata.tipo`, `metadata.relevancia`, `metadata.fonte`, `metadata.expira_em` como atributos top-level no Meilisearch.

**Implementação:** atualizar `toSearchableArray()` + `config/scout.php` index-settings + reindexar.

**Esperado:** destrava P1-A e diversos filtros futuros (escopo temporal, escopo por tipo).

### Tier 2 — médio impacto, requer experimento

#### P2-A · A/B `semantic_ratio` 0.7 vs 0.55 vs 0.3

**O quê:** parâmetro do hybrid search Meilisearch (1.0 = 100% semantic, 0.0 = 100% full-text).

**Pré-requisitos:** gabarito de 50 perguntas registrado em `jana_memoria_gabarito` + comando `copiloto:eval` funcional.

**Procedimento:**
- Hoje: `COPILOTO_MEMORIA_SEMANTIC_RATIO=0.7` (default no driver).
- Mudar pra 0.55 → rodar eval → registrar Recall@3.
- Mudar pra 0.3 → rodar eval → registrar Recall@3.
- Manter o melhor.

#### P2-B · HyDE habilitado em prod

**O quê:** `HydeQueryExpander` já existe (services prontos `3d060fec`), só não está habilitado.

**Procedimento:** `COPILOTO_HYDE_ENABLED=true` no `.env` Hostinger + measure 1 semana.

#### P2-C · Reranker LLM-as-judge habilitado

**O quê:** `LlmReranker` reordena candidatos via gpt-4o-mini.

**Procedimento:** `COPILOTO_RERANKER_ENABLED=true` no `.env` Hostinger + measure 1 semana.

**Custo:** +1 chamada LLM por busca (~50 tokens). Avaliar latência p95.

### Tier 3 — alto risco, exige ADR antes

#### P3-A · Migrar pra Mem0RestDriver (ADR 0036 sprint 8+)
#### P3-B · Conversation summarizer (ADR 0047 — não implementado)
#### P3-C · Profile distiller (ADR 0047 — não implementado)
#### P3-D · Temporal validity bi-temporal (ADR 0074)

---

## 2. Histórico de execuções

| Data | Melhoria | Recall@3 antes | Recall@3 depois | hit_rate antes | hit_rate depois | Decisão | Notas |
|------|----------|----------------|-----------------|----------------|-----------------|---------|-------|
| 2026-05-04 | _(setup da rotina — nenhuma melhoria aplicada)_ | — | — | — | — | ⚙️ infra | 1ª execução: SKILL apontava pra `Modules/Jana/` (renomeado pra `Modules/Jana/`) + `CURRENT.md` removido (ADR 0070) + ADR 0062 ≠ 8 fases. Setup do RUNBOOK + SKILL atualizada + permissões pré-aprovadas. Bug detectado: `MemoriaFato.$casts` em Jana não tem `hits_count`/`core_memory`/`ultimo_hit_em` (migration aplicou colunas mas entity não cast). |
| 2026-07-04 | _(reconciliação com o canon — nenhum código aplicado)_ | n/d¹ | n/d¹ | 1.0² | 1.0² | 📋 reconcile | 2ª execução. RUNBOOK estava 4741 commits stale (última edição 2026-05-04). **Descoberto:** Tier 2 (HyDE/reranker/semantic_ratio/RRF/decay) **já construído** no `MeilisearchDriver` + suite de eval nova (`jana:recall-eval`, `jana:ragas-eval`, `jana:drift-sentinel`, `apurar-metricas`). **P1-A e P1-C/P1-A.0 seguem NÃO aplicados** (verificado em `origin/main`: `buscar()` só filtra `business_id`+`user_id`; `toSearchableArray()` não expõe `metadata_relevancia`; `config/scout.php` index-settings vazio). Baseline de recall **não medível autonomamente** (¹staging = 5 fatos anonimizados biz=1; prod não-mutável; `jana:recall-eval` mira `mcp_memory_documents`, índice diferente) → regra #5 bloqueia P1-A. ²hit_rate/bloat medidos em staging biz=1 (5 fatos, todos com `metadata.relevancia`; core_memory=0). Fila de código pronta registrada em §7. |

---

## 3. Regras invioláveis

1. **Nunca commitar sem rodar a suite Pest** (`php artisan test tests/Feature/Modules/Jana/ --stop-on-failure`).
2. **Nunca aplicar 2 melhorias na mesma semana** — impossibilita medir causalidade.
3. **Se Recall@3 regredir após uma melhoria:** rollback imediato + linha 🔴 no histórico + ADR de post-mortem.
4. **`business_id` isolado em toda query** — multi-tenant inviolável (UltimatePOS).
5. **Antes de cada melhoria:** medir baseline. Sem baseline = não aplicar.
6. **Pré-requisitos do Tier 1:** validar **todos** antes de implementar. Se algum falhar, criar issue/task e pular pra próxima melhoria do Tier.

---

## 4. Comandos canônicos

### Medir baseline (sem `copiloto:eval`)
```powershell
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='oimpresso'; php artisan tinker --execute="
`$total = DB::table('jana_memoria_facts')->where('business_id',1)->whereNull('deleted_at')->count();
`$comHits = DB::table('jana_memoria_facts')->where('business_id',1)->whereNull('deleted_at')->where('hits_count','>',0)->count();
`$hitRate = `$total > 0 ? round(`$comHits/`$total, 2) : 0;
`$semHits = DB::table('jana_memoria_facts')->where('business_id',1)->whereNull('deleted_at')->where('hits_count',0)->count();
`$bloatRatio = `$total > 0 ? round(`$semHits/`$total, 2) : 0;
echo \"total=`$total | hit_rate=`$hitRate | bloat_ratio=`$bloatRatio | core_memory=\".DB::table('jana_memoria_facts')->where('business_id',1)->where('core_memory',true)->count();
"
```

### Eval com gabarito (quando comando existir)
```powershell
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='oimpresso'; php artisan copiloto:eval --business=1 --persist
```

### Suite de testes
```powershell
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='oimpresso'; php artisan test tests/Feature/Modules/Jana/ --stop-on-failure
```

### Reindexar Meilisearch (após mudar `toSearchableArray`)
```powershell
php artisan scout:flush "Modules\\Jana\\Entities\\MemoriaFato"
php artisan scout:import "Modules\\Jana\\Entities\\MemoriaFato"
```

---

## 5. Referências

- ADR 0049 — Camadas de memória do agente, fase por fase, medir antes de evoluir
- ADR 0050 — 8 métricas obrigatórias + tabela `jana_memoria_metricas`
- ADR 0051 — Schema próprio + adapter + OTel GenAI
- ADR 0054 — Pacote enterprise busca memória + evolução
- ADR 0072 — Maturação memória vs OpenClaw/Mem0/Letta/Zep/A-Mem (mai/2026)
- ADR 0074 — Temporal validity bi-temporal (P1)

---

## 6. Pendências detectadas (não-bloqueantes pra rotina)

| # | Item | Prioridade | Ação |
|---|------|-----------|------|
| 1 | `MemoriaFato.$casts` (Modules/Jana) sem `hits_count`/`core_memory`/`ultimo_hit_em` — **reconfirmado 2026-07-04 em `origin/main`** (colunas existem no DB; casts ausentes) | 🔴 P1 | Adicionar 3 entradas no `$casts` → **ver §7 (diff pronto)**. Consumidores são todos DB-layer (`HitTrackerService`/`CleanupMemoriaCommand` usam `where()`), então o cast é aditivo e seguro. |
| 2 | Comentário em `MemoriaFato.php` ainda referencia `Modules/Jana/Database/seeders/MeilisearchIndexSetup.php` (path antigo) | 🟡 P3 | Atualizar pra `Modules/Jana/...` ou remover seeder se obsoleto |
| 3 | `config/scout.php` `index-settings.jana_memoria_facts` está vazio — filterableAttributes setados manualmente no servidor (drift de governança) | 🟠 P2 | Declarar no `config/scout.php` os filterableAttributes (`business_id`, `user_id`, `valid_until`, `metadata_relevancia` futuro) — Scout aplica no startup |
| 4 | ADR canônica do roadmap 8 fases é a **0049**, não 0062 (que é separação runtime) — SKILL apontava pro número errado | ✅ corrigido 2026-05-04 | — |

---

## 7. Fila de melhorias de código — prontas pra aplicar (registrada 2026-07-04)

Duas mudanças **verificadas como necessárias em `origin/main`**, com diff pronto. Não aplicadas nesta rodada porque exigem worktree fresco + validação Pest no CT 100 (regra #1) e a rotina roda desassistida — a próxima rodada (ou sessão assistida) aplica **uma** por vez (regra #2). Ordem recomendada: **A antes de B** (A não depende de baseline de recall; B é a fundação do filtro P1-A).

### 7.A · Cast fix (pendência #1) — **recomendada como próxima aplicação**

**Por que é segura sem baseline de recall:** é casting app-layer, ortogonal ao retrieval (não muda `buscar()`). Consumidores são todos DB-layer (verificado). Destrava a Fase 7 (hits/core_memory) corretamente em PHP.

`Modules/Jana/Entities/MemoriaFato.php` — adicionar ao `$casts`:
```php
'hits_count' => 'integer',
'core_memory' => 'boolean',
'ultimo_hit_em' => 'datetime',
```
**Validação:** teste Pest que instancia o Eloquent e afirma `is_bool($f->core_memory)` + `$f->ultimo_hit_em instanceof \Illuminate\Support\Carbon` (o `HitTrackerServiceTest` atual lê via `DB::table()->value()`, **não** exercita o cast — é sqlite-only, 9 skipped no MySQL). Rodar no CT 100 / CI.

### 7.B · P1-A.0 (prep do P1-C) — flatten `metadata_relevancia`

`Modules/Jana/Entities/MemoriaFato.php::toSearchableArray()` — adicionar `'metadata_relevancia' => $this->metadata['relevancia'] ?? null`.
`config/scout.php` → `meilisearch.index-settings.jana_memoria_facts.filterableAttributes`.

> ⚠️ **BLOQUEADOR OPERACIONAL antes do reindex:** o índice Meilisearch de prod hoje tem os `filterableAttributes` setados **manualmente no servidor** (pendência #3, drift). O `buscar()` **depende** de `business_id` + `user_id` filtráveis. Declarar um set no config + `scout:sync-index-settings`/reindex pode **derrubar `user_id`** e quebrar recall multi-tenant em prod. **Auditar os `filterableAttributes` atuais do índice prod ANTES** e incluir `business_id`, `user_id`, `valid_until` no set declarado. Reindex de prod = passo operacional gated pelo Wagner (não autônomo).

Só **depois** de 7.B aplicado + reindexado + baseline de recall medível é que **P1-A** (filtro `metadata_relevancia >= 3` em `buscar()`) pode ser aplicado sob a regra #5.

---

> **Última atualização:** 2026-07-04 — 2ª execução: reconciliação com o canon (RUNBOOK estava 4741 commits stale). Tier 2 marcado como já-construído; fila de código pronta (§7 — cast fix + P1-A.0) registrada com diffs + bloqueadores. Nenhum código aplicado (baseline de recall não medível autonomamente + validação Pest exige worktree/CT 100).
> **2026-05-04** — 1ª execução da rotina (setup-only, melhoria não aplicada).
