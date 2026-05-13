# Aprendizados — Sessão massiva 2026-05-13 (70% → 91% em 17 PRs)

> **Tipo:** reference canônico (ADR 0061 — git canon, não auto-mem)
> **Sessão:** `nervous-mayer-3ff0da` · Wagner + Claude Opus 4.7
> **Duração:** ~6h
> **Resultado:** 17 PRs mergeados, +21pp maturidade global, 17 agents paralelos coordenados

## TL;DR — 8 lições críticas que NÃO devem regredir

1. **Sempre exigir `ls -la` no reporte de agent** (alucinação Write detectada 2026-05-13)
2. **Áreas isoladas obrigatórias em paralelização N agents** (zero overlap, parent consolida)
3. **Splittar Provider/Server/Kernel.php manualmente** quando múltiplos PRs editam
4. **Rebase pattern proven** quando 2º PR conflita com 1º já mergeado
5. **Multi-tenant Tier 0 IRREVOGÁVEL** em CADA prompt de agent (ADR 0093)
6. **PT-BR em prompts + comentários** sempre — preferência durável Wagner
7. **Tools MCP devem ter mock mode** pra testar sem custo OpenAI
8. **Custo IA tracking** em cada feature LLM (sustentabilidade ADR 0094 §4)

## Lições detalhadas

### 1. Agent pode alucinar Write/Edit — verificação `ls -la` é IRREVOGÁVEL

**Caso real:** `knowledge-architecture-expert` 1ª tentativa reportou criar artefato `AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md` mas `find -newer` mostrou que o arquivo NÃO existia no disk. Custo: 7min + 124k tokens perdidos.

**Mitigação aplicada nas demais 16+ agents:** prompt incluiu instrução irrevogável:

```
APÓS chamar Write, IMEDIATAMENTE rode `Bash: ls -la <path-EXATO>
&& wc -l <path>` pra CONFIRMAR. Se ls falhar, o Write falhou — refaça.
Inclua output dessa confirmação no reporte final como prova.
```

**Resultado:** zero alucinações nas Onda 1+2+3 (16 agents subsequentes).

### 2. Paralelização N agents — pré-requisitos validados em 17 spawns

Pattern documentado em `memory/how-trabalhar.md` §Paralelização N agents. **Pré-requisitos confirmados:**

1. **Áreas isoladas obrigatórias** — cada agent recebe pastas permitidas explícitas
2. **Zero git ops nos agents** — só `Write/Edit`. Parent consolida no final
3. **Prompt com regra "comparar e não duplicar"** — agent lê código existente antes de criar
4. **Restrições Tier 0 IRREVOGÁVEIS no prompt** — `business_id` global scope, Pest cross-tenant, PT-BR
5. **Pré-reqs Wagner sign-off** verificados antes do spawn

**Métrica:** 17/17 spawns sem conflito de arquivos (todos áreas isoladas validadas).

### 3. Splittar Provider/Server/Kernel.php — pattern manual

Quando múltiplos agents editam o mesmo arquivo compartilhado (`JanaServiceProvider.php`, `OimpressoMcpServer.php`, `app/Console/Kernel.php`):

**Solução proven (3 ondas, 17 PRs):**

```bash
# Pra cada PR consolidado:
git checkout -B claude/<bug> origin/main
git add <arquivos-próprios-do-agent>
git checkout origin/main -- <shared-files>  # reset pra base
# Aplica via Edit APENAS o chunk respectivo
git add <shared-files>
git commit + push + gh pr create
```

**`git add -p` interactive NÃO funciona em script** — usar Edit pra adicionar chunks específicos.

### 4. Rebase pattern proven — quando 2º PR conflita com 1º já mergeado

**Caso real:** Bug #4 (PR #757) conflitou com Bug #3 (PR #748) já mergeado — mesma região `Kernel.php`.

**Sequência aplicada (Onda 1, Onda 2, Onda 3 cada uma teve este pattern):**

```bash
git fetch origin main --quiet
git checkout -B claude/<branch>-rebased origin/main
git checkout <branch-original> -- <arquivos-específicos>
# Reaplicar manualmente chunk do shared file
git add + commit + push
gh pr close <PR-original> --comment "Replaced by rebased branch"
gh pr create  # nova PR
gh pr merge <new> --admin --squash --delete-branch
```

**Métrica:** 4 rebases bem-sucedidos (Bug #4 + H4 + H5 + H6).

### 5. Multi-tenant Tier 0 — clausula em CADA prompt de agent

```
Multi-tenant Tier 0 (ADR 0093) IRREVOGÁVEL — queries DB devem respeitar
business_id global scope. Tabelas repo-wide (mcp_tasks, mcp_handoff_*,
mcp_weekly_digests) NÃO precisam de business_id, mas DOCUMENTE a decisão
na migration.
```

**Resultado:** zero violations Tier 0 detectadas no audit pós-sessão.

### 6. PT-BR — preferência durável Wagner

**Convenção:** comentários, prompts sistema, output, READMEs em PT-BR. Código/identificadores em inglês.

**Métrica:** 17/17 PRs respeitaram convenção.

### 7. Tools MCP com mock mode (sustentabilidade)

**Pattern H4 RAGAS:** `RagasJudgeService::enableMock($scores)` permite rodar Pest local sem chave OpenAI:

```php
RAGAS_FORCE_MOCK=true php artisan test --filter=Ragas
```

Cobre 11/11 tests local + skipped graciosamente em CI sem chave. Reusar em qualquer tool MCP que chama LLM.

### 8. Custo IA tracking por feature

Cada tool LLM adicionada hoje tem custo declarado:

| Feature | Modelo | Custo/call | Frequência | Custo mês |
|---|---|---|---|---|
| `kb-answer` (G3) | gpt-4o-mini | R$ 0.003 | sob demanda | ~R$ 3 |
| `handoff-fetch-summarized` (G4) | gpt-4o-mini | R$ 0.004/handoff | retomada | ~R$ 1 |
| `handoff-diff` (H3) | gpt-4o-mini | R$ 0.003 | retomada | ~R$ 1 |
| `jana:weekly-digest` (H6) | gpt-4o-mini | R$ 0.005 | semanal cron | R$ 0.26/ano |
| RAGAS gate (H4) | gpt-4o-mini judge | $0.02/run | weekly+manual | R$ 0.40 |

**Total acréscimo IA hoje:** ~R$ 5/mês max. Cumpre Princípio 4 ADR 0094 (loop fechado por métrica).

## Outras decisões durables (ADRs aceitas)

- **ADR 0144 accepted** — TaskRegistry DB canon + SPEC.md template (resolve Bug #2 MCP sync)
- **Provider/Server registro pattern documentado** — Page 1 (15 essenciais) vs Page 2 (knowledge cluster)

## 3 surpresas globais validadas (oimpresso > mercado mundial)

Mantidas após análise completa:

1. **Constituição v2 (ADR 0094)** — única no mundo com governance KB formal append-only
2. **Daily Brief 6×/dia automático pré-consumível por LLM** — único no mercado (Asana/Range/Reflect fazem 1× pra humano)
3. **`whats-active` cross-session detection** — LangGraph/AutoGen/CrewAI não têm equivalente

## Pegadinhas técnicas descobertas

- **Junction `vendor/` worktree** quebra autoload — `composer dump-autoload` regenera classmap
- **Carbon ^3** `diffInDays()` retorna float não int — usar `(int) abs(...)`
- **SQLite test schema** migration `enum()` incompatível — pattern `Schema::create string('type', 40)` em `beforeEach`
- **Eloquent `$model->_flag = true`** vira coluna SQL UPDATE — usar singleton estático per-request (FSM lição)
- **`!!binary` em YAML ADR** quebra parsing — fallback primeira linha `#` do body
- **PR `(#707)` parentético** NÃO casa regex auto-close — `#` não é `[A-Z]` (Bug #1 corrigido)

## Refs

- [Handoff curto piloto](handoffs/2026-05-13-0900-mcp-bugs-5prs-3auditorias-onda2-spawned.md) — 80 linhas (vs mediana 142)
- [COMPARATIVO-MCP-ESTADO-DA-ARTE](requisitos/Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) — 62% MCP/PM
- [AUDITORIA-KNOWLEDGE-ARCHITECTURE](requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) — 73% PKM/RAG
- [AUDITORIA-SESSION-HANDOFF](requisitos/Jana/AUDITORIA-SESSION-HANDOFF-2026-05-13.md) — 74% Handoff
- [MIGRACAO-AUTO-MEM](requisitos/Jana/MIGRACAO-AUTO-MEM-2026-05-13.md) — 51 docs canon
- ADRs: 0061 zero auto-mem · 0093 Multi-tenant Tier 0 · 0094 Constituição v2 · 0095 Skills Tiers · 0130 Handoff append-only · 0131 Tiering memória · 0144 DB canon

---

**Score final 2026-05-13:** ~91% maturidade global vs estado-da-arte 2026.

**Próximos passos:** auditoria gap analysis 91% → 100% (agent `maturity-gap-expert` em background).
