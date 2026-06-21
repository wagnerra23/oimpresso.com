---
date: 2026-06-20
topic: "como-integrar — T6 drift de nomenclatura Copiloto→Jana nas skills"
type: session
---

# como-integrar — T6 drift de nomenclatura Copiloto→Jana nas skills

**Data:** 2026-06-20 · **Agent:** como-integrar · **Task:** T6 (mecânica, baixo risco)
**Veredito:** drift REAL confirmado e mapeado. Correção mecânica de path/namespace. NÃO é feature.

---

## Fase 1 — INVENTÁRIO

Verdade do código real (grep dentro do projeto, não inventei):

| O que procurei | Onde achei | Status |
|---|---|---|
| Módulo real (path) | `Modules/Jana/Services/Memoria/` existe; `Modules/Copiloto/` retorna 0 ocorrências de path real | renomeado Copiloto→Jana |
| Namespace real | `Modules\Jana\Entities\MemoriaFato`, `Modules\Jana\Services\ContextSnapshotService` | `Modules\Jana\` |
| Tabela memória facts | `MemoriaFato::$table = 'jana_memoria_facts'` (entity); rename via ADR 0092 | tabela CORRENTE = `jana_memoria_facts` |
| Tabela métricas | `jana_memoria_metricas` (rename ADR 0092) | tabela CORRENTE = `jana_*` |
| VIEW legacy `copiloto_*` | criada na migration de rename, **drop planejado 2026-06-05** (já passou) | view legacy provavelmente já dropada |
| `ScopeByBusiness` | `Modules/Jana/Scopes/ScopeByBusiness.php` (real) | drift na skill multi-tenant |
| Comando métricas (signature) | `$signature = 'copiloto:metrics:apurar'` — NÃO renomeado | `copiloto:metrics:apurar` é CORRETO |
| Comando gabarito (signature) | `$signature = 'copiloto:eval'` — NÃO existe `copiloto:gabarito:avaliar` | prefixo `copiloto:` correto; subnome errado |
| Pasta requisitos Copiloto | `memory/requisitos/Copiloto/` AINDA EXISTE (RUNBOOK + RETRIEVAL antigos) | path Copiloto LEGÍTIMO p/ requisitos |
| ADR rename | ADR 0092 (rename tabela), ADR 0088 (rename módulo PHP-only) | histórico — não tocar |

**Conclusão Fase 1:** drift é AUSENTE de correção (ninguém arrumou as skills ainda) → corrige do zero. As skills `jana-arch` e `jana-recall-flow` já foram parcialmente migradas no front-matter `name`/tabela mas mantêm `Modules/Copiloto/` no corpo — drift INCONSISTENTE, exatamente o alvo de T6.

---

## Fase 2 — PEGADINHAS APLICÁVEIS

| # | Pegadinha | Aplica a este caso |
|---|---|---|
| 1 | **NÃO trocar referências legítimas a Copiloto** | CRÍTICO. 4 classes de menção legítima: (a) `$signature` de comandos `copiloto:metrics:apurar`/`copiloto:eval` — comando real NÃO renomeado; (b) ADRs 0088/0092 que documentam o rename; (c) nome de arquivo de MIGRATION (`create_copiloto_memoria_facts_table.php` é histórico, append-only); (d) `memory/requisitos/Copiloto/` que AINDA EXISTE no fs; (e) exemplos pedagógicos em `migrar-modulo` e `audit-constituicao` que usam Copiloto→Jana COMO EXEMPLO do processo. |
| 7 | Append-only / imutabilidade | Migration file name e ADRs históricas são imutáveis. Não renomear. |
| 4 | MWART | NÃO aplica — não toca `.tsx` Page Inertia, só `.md` de skill. |
| Tier-0 multi-tenant | NÃO aplica diretamente — mas a skill `multi-tenant-patterns` cita `ScopeByBusiness` com path errado; corrigir path melhora a fonte canônica do próprio Tier-0. |
| Charter | NÃO aplica — skills não têm `.charter.md`. |
| Runtime CT100 vs Hostinger | NÃO aplica — edição de doc, sem deploy. |

Observação separada (NÃO é T6, não tocar agora): linha `php artisan copiloto:gabarito:avaliar` na `jana-recall-flow:139` cita comando inexistente (real é `copiloto:eval`). Drift de SIGNATURE, fora do escopo de drift de PATH. Registrar como follow-up.

---

## Fase 3 — PONTO DE PLUGUE (arquivo:linha exato)

### A) DRIFT REAL DE PATH/NAMESPACE — TROCAR `Modules/Copiloto/` → `Modules/Jana/`

**`jana-recall-flow/SKILL.md`** (epicentro — ~16 ocorrências de path):
- L3 (description): `Modules/Copiloto/Services/Memoria/` → `Modules/Jana/Services/Memoria/`
- L16: `Modules/Copiloto/Services/Memoria/`
- L17: `(Modules/Copiloto/Services/ContextSnapshotService.php)`
- L18: `Modules/Copiloto/Services/Mcp/IndexarMemoryGitParaDb.php`
- L37: `Modules/Copiloto/Services/Memoria/NegativeCacheService.php`
- L38: `Modules/Copiloto/Services/Memoria/HydeQueryExpander.php`
- L39: `Modules/Copiloto/Services/Memoria/MeilisearchDriver.php`
- L40: `Modules/Copiloto/Services/Memoria/LlmReranker.php`
- L43: `Modules/Copiloto/Services/Mcp/IndexarMemoryGitParaDb.php` + `Modules/Copiloto/Console/Commands/McpSyncMemoryCommand.php`
- L47: `Modules/Copiloto/Services/ContextSnapshotService.php`
- L79: `Modules/Copiloto/Services/Mcp/IndexarMemoryGitParaDb.php`
- L86: `Modules/Copiloto/Services/Mcp/QuotaEnforcer.php`
- L111: `Modules/Copiloto/Services/Metricas/MetricasApurador.php` + `Modules/Copiloto/Console/Commands/ApurarMetricasCommand.php`
- L120: `Modules/Copiloto/Services/Ai/LaravelAiSdkDriver.php`
- L133: `php -l Modules/Copiloto/Services/Memoria/MeilisearchDriver.php`
- L136: `vendor/bin/pest Modules/Copiloto/Tests/` → `Modules/Jana/Tests/`
- L19, L41, L118 (tabela `copiloto_memoria_facts`/`copiloto_memoria_metricas`): ⚠️ ver seção B abaixo
- L12, L135 (texto "Copiloto"/"suite Copiloto" como NOME do módulo): ⚠️ ver seção C

**`jana-arch/SKILL.md`** (drift inconsistente — já meio migrado):
- L3 (description): `Modules/Copiloto/`
- L46: `Modules/Copiloto/` (bloco de árvore de arquivos)
- (L12, L24, L38 já usam `jana_memoria_metricas` — coerência já parcial)

**`multi-tenant-patterns/SKILL.md`**:
- L35: `Modules/Copiloto/Scopes/ScopeByBusiness.php` → `Modules/Jana/Scopes/ScopeByBusiness.php` (path real confirmado)

### B) ⚠️ TABELA — decisão por nome CORRENTE (jana_*) mas NÃO migration filename

- `jana-recall-flow:19,41,118` citam tabela `copiloto_memoria_facts`/`copiloto_memoria_metricas`. Tabela CORRENTE (ADR 0092) = `jana_memoria_facts`/`jana_memoria_metricas`. A entity confirma `$table='jana_memoria_facts'`. **Trocar para `jana_*`** — mas é drift de TABELA, não de path. Manter no escopo só se quiser consistência total; se T6 é estritamente "path do módulo", deixar como follow-up e marcar. RECOMENDAÇÃO: incluir (mesma natureza de drift, mesma ADR de rename).

### C) NÃO TOCAR — menções legítimas a "Copiloto"

| Local | Por quê legítimo |
|---|---|
| `jana-recall-flow:139` `copiloto:gabarito:avaliar` | é comando artisan (prefixo `copiloto:` real). Subnome errado é OUTRO bug (follow-up). |
| `jana-arch:139`-style `copiloto:metrics:apurar` (L26 jana-arch) | `$signature` real = `copiloto:metrics:apurar`. NÃO renomear. |
| `jana-arch:34,52,53` `ChatCopilotoAgent` | classe real? confirmar via grep antes (provável `ChatJanaAgent` agora). Se classe foi renomeada, é drift; se não, manter. ⚠️ exige grep extra. |
| `migrar-modulo:60,62,83,84,93,94` | EXEMPLO pedagógico do rename Copiloto→Jana. NÃO tocar — é a documentação do processo. |
| `audit-constituicao/prompts/01-auto-mem.md:15,41` | EXEMPLO de "STALE" usando Copiloto→Jana. NÃO tocar — é didático. |
| `jana-brief-concierge:31,236` `memory/requisitos/Copiloto/RUNBOOK-...` | pasta EXISTE no fs. Path legítimo. NÃO tocar. |
| migration filenames `create_copiloto_*` | append-only, histórico. NÃO renomear. |
| ADR 0092 (rename tabela) e textos que citam "rename copiloto→jana" | histórico. NÃO tocar. |

### D) ⚠️ Casos cinza — exigem grep de confirmação antes de decidir (sub-tarefa)
- `ChatCopilotoAgent` (jana-arch L34,52,53; ads-route L79) — confirmar nome de classe REAL via `grep -rn "class ChatCopilotoAgent\|class ChatJanaAgent" Modules/Jana`.
- `FabCopiloto` (cockpit-runbook EXAMPLES L18,25) — confirmar se componente React foi renomeado.
- `oimpresso-stack:77,87` "Copiloto" / "Cliente Copiloto" — nome de produto/módulo no diagrama; provável drift mas é texto descritivo, baixa prioridade.
- `sidebar-menu-arch:195` "Copiloto" no menu — confirmar label real do menu IA.

---

## Fase 4 — CHECKLIST PRÉ-CÓDIGO

### Antes de Edit
- [ ] RUNBOOK: ausente (correção de doc, não precisa)
- [ ] Feature flag: não
- [ ] Migration: não
- [ ] ADR nova: não — só corrige drift apontando p/ realidade já estabelecida por ADR 0088/0092

### Pegadinhas a respeitar
- [ ] NÃO trocar `$signature` `copiloto:metrics:apurar`/`copiloto:eval` (comando real não renomeado)
- [ ] NÃO trocar `memory/requisitos/Copiloto/` (pasta existe)
- [ ] NÃO trocar exemplos pedagógicos em `migrar-modulo` e `audit-constituicao`
- [ ] NÃO trocar migration filenames nem ADRs (append-only)
- [ ] Confirmar `ChatCopilotoAgent`/`FabCopiloto` via grep ANTES de trocar (casos cinza)

### Pontos de plugue (ordem)
- [ ] `jana-recall-flow/SKILL.md` — ~16 paths `Modules/Copiloto/`→`Modules/Jana/` + 3 tabelas `copiloto_*`→`jana_*`
- [ ] `jana-arch/SKILL.md` — L3, L46 path; confirmar `ChatCopilotoAgent`
- [ ] `multi-tenant-patterns/SKILL.md` — L35 `ScopeByBusiness` path

### Validação final (grep=0)
- [ ] `grep -rn "Modules/Copiloto" .claude/skills/jana-recall-flow .claude/skills/jana-arch .claude/skills/multi-tenant-patterns` → 0
- [ ] Confirmar que ocorrências RESTANTES de "Copiloto" em todo `.claude/skills` são SÓ as legítimas (signature, pasta requisitos, exemplos, nome de produto)

### Estimativa (IA-pair, ADR 0106)
- ~15-25 min (3 arquivos, edição cirúrgica, sem teste de código)
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
