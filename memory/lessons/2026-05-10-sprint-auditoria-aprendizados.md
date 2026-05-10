# Aprendizados Sprint Auditoria 2026-05-10

> Lições catalogadas durante a sessão Claude tarde-2 que entregou Sprint Auditoria
> inteiro (21h IA-pair planejado, 11 entregas, [ADR 0127](../decisions/0127-modules-auditoria-undo-activity-log.md))
> em sessão paralela. Documentação canônica para reuso futuro do time.
>
> Última atualização: 2026-05-10 ~14h BRT

---

## 1. GitHub GraphQL rate limit é 5000/h shared

### Observação

Sessão paralela intensa (8+ PRs criados num intervalo curto + outras sessões Claude do time trabalhando) **esgotou o rate limit GraphQL da API GitHub** em 5011/5000 chamadas. `gh pr create` retornou erro:

```
GraphQL: API rate limit already exceeded for user ID 61781989
```

`gh api rate_limit --jq .resources.graphql` retorna `{"limit":5000,"remaining":0,"reset":<unix>,"used":5275}`.

### Causa raiz

Quota GraphQL é **5000 requests/hora** e é **compartilhada entre todas as tools e agentes** do mesmo user GitHub. `gh pr create`, `gh pr view`, `gh pr list`, `gh pr merge`, `gh pr update-branch` e similares todos consomem dessa quota. Sessões paralelas Claude do time amplificam consumo.

### Mitigação prática

1. **Paralelizar push de branches** (usa REST API, quota separada de 5000/h tipicamente longe de saturar) mas **espaçar `gh pr create`** — cada PR consome ~3-5 calls GraphQL (validação branch + criar + verificar)
2. **Não fazer poll/retry em loop** quando rate limit. `ScheduleWakeup` ou pausar é melhor que sleep loop
3. **Verificar reset time** via `gh api rate_limit --jq .resources.graphql.reset` (Unix timestamp)
4. **Quando rate limit fechado:** branch já pushed, dar URL `https://github.com/wagnerra23/oimpresso.com/pull/new/<branch>` pro Wagner clicar — GitHub pré-popula PR title/body do commit message

### Quando atinge

- Sessão única Claude criando 5+ PRs em sequência rápida
- 2+ sessões Claude paralelas trabalhando simultaneamente
- Combinação de criação + view + list + merge

---

## 2. Sessões paralelas Claude mexem working tree simultaneamente

### Observação

Durante esta sessão, **outras sessões Claude do time fizeram `git checkout`/`git switch`** que reverteram meu working tree local enquanto eu editava. Por exemplo:

- Editei `app/Product.php` adicionando trait `LogsActivity`
- Outra sessão fez `git switch fix/modules-pest-yaml-syntax` no mesmo workspace
- Meu Edit foi revertido localmente
- **Mas** o `git commit` que fiz ANTES do switch da outra sessão capturou snapshot íntegro
- Push pra `claude/audit-002-product-vld-logsactivity` foi correto no remote

### Causa raiz

Worktrees compartilham o mesmo working tree filesystem. Múltiplas sessões Claude no mesmo worktree podem:
1. Fazer `git switch` que reseta arquivos
2. Editar mesmos arquivos simultaneamente
3. Comitar em branches inesperadas (commit cai na branch atual no momento, não na branch que eu acreditava estar)

### Mitigação prática

1. **Cada US/feature em branch própria** + commit/push imediato (não acumular working changes)
2. **Confirmar via `git show <commit-sha> --stat`** que o commit no remote contém os arquivos esperados — working tree pode estar revertido mas commit em remote OK
3. **Cuidado com `git status`** — pode mostrar arquivos modificados que NÃO são seus (deixe-os, não commit)
4. **`git add` específico por arquivo** (nunca `git add .` ou `git add -A`)
5. **Se precisa branch limpa:** `git switch -c claude/X origin/main` → arquivos commitados → push → próxima US: `git switch -c claude/Y origin/main` (não cumulativo)
6. **Worktrees isolados** (`.claude/worktrees/<nome>`) seriam ideais mas sessões paralelas no mesmo worktree são realidade

### Sintomas

- `git branch --show-current` retorna branch que você não sabe ter trocado
- Working tree de Edit anterior aparece "vazio" (revertido)
- Reflog mostra `checkout: moving from X to Y` que você não fez
- `git commit` cai numa branch diferente da esperada

---

## 3. Tool MCP `tasks-create` schema desatualizado vs backend

### Observação

Durante esta sessão, ao tentar criar US-AUDIT-001..010 no MCP via `tasks-create module:Auditoria`, retornou:

```
❌ Sem 'module' canônico, é obrigatório passar 'project' (key existente em mcp_jira_projects).
```

Mesmo após:
- Adicionar `AUDIT` em `mcp_jira_projects` via migration aplicada (#441)
- Confirmar via mysql que `id=25, key='AUDIT', name='Auditoria', status='active'` existe
- Tentar `module:AUDIT` em vez de `module:Auditoria`

### Causa raiz

[ADR 0125](../decisions/0126-mcp-jira-projects-modulos-verticais.md) (mergeado hoje 2026-05-10) renomeou parâmetro do tool MCP de `module` → `project`. Backend MCP foi atualizado para exigir `project` mas o **schema JSON do tool client ainda só aceita `module`** com `additionalProperties: false`. Tool client está numa versão de transição não-sincronizada com backend.

### Workaround

**SPEC.md no git é fonte canônica das US** (US-AUDIT-001..010 documentadas em `memory/requisitos/Auditoria/SPEC.md` mergeado em main via #432). Tool MCP é cache temporariamente cego. Sprint não está bloqueado — quem pega Sprint F1 pode codar lendo direto da SPEC.

### Fix definitivo (~30min, trabalho separado)

Investigar onde tool MCP é definido (provavelmente `Modules/Jana/Ai/Mcp/Tools/` ou pacote `laravel/mcp`) e:

- **Opção A:** atualizar schema do tool pra aceitar `project` (`additionalProperties: false` permite só keys listadas)
- **Opção B:** adicionar shim no backend `module → project` mapping durante deprecation window

### Lição genérica

Renomear parâmetros de tool MCP exige sync coordenado entre **schema JSON do tool client** + **validação no backend**. Mudança não-atômica deixa tool quebrado pra todos os devs do time até fix propagar.

---

## 4. Padrão Pest do projeto: skip-graceful em sqlite memory CI

### Observação

`phpunit.xml` força `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` em ambiente testing. **Schema UltimatePOS não é migrado em sqlite memory** (100+ migrations, alguns features mysql-only).

Tests integration que dependem de dados reais (Business, User, Contact, Transaction etc) **não funcionam em CI sqlite**. Padrão estabelecido do projeto:

```php
beforeEach(function () {
    try {
        $this->business = \App\Business::first();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode local com DB_CONNECTION=mysql (dev) ou aguarde CI integration job.');
    }
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB.');
    }
    // ...
});
```

### Quando aplicar skip-graceful

- Test depende de dados seed UltimatePOS (Business, User, Contact, Product, Transaction, etc)
- Test usa mysql-specific features (`SHOW INDEX`, `JSON_EXTRACT`, ENUMs nativos, triggers)
- Test depende de migrations que falham em sqlite (campos `JSON`, `ENUM`, FK ON DELETE CASCADE complexa)

### Quando rodar real

**Pré-merge:** rodar `vendor/bin/pest tests/Feature/<X>/` com `.env.testing` apontando mysql dev:

```env
# .env.testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oimpresso_dev
DB_USERNAME=root
DB_PASSWORD=
```

Wagner regra (`feedback_tenancy_changes_require_pest_local`): mudanças tenancy/Model exigem Pest local antes de PR.

### Referência canônica do padrão

`tests/Feature/Modules/Financeiro/TransactionObserverIntegrationTest.php` — padrão limpo de skip-graceful que copiei nos 6 testes Pest entregues no Sprint Auditoria (TransactionLogsActivityTest, ProductStockLogsActivityTest, ContactPiiLogsActivityTest, SellLinePaymentLogsActivityTest, RevertServiceTest, CauserKindResolverTest).

---

## Aplicabilidade futura

Estas 4 lições são **regras-de-ouro pra próximas sessões Claude paralelas + Sprint AI-pair intensos**:

1. **Não saturar GraphQL rate limit:** espaçar `gh pr create`, push em paralelo
2. **Branches isoladas + commit/push imediato:** working tree é volátil em sessões paralelas
3. **Tool MCP pode estar quebrado:** SPEC.md em git é sempre fonte canônica fallback
4. **Pest test pattern:** skip-graceful em sqlite + validação real em mysql dev pré-merge

## Refs

- [ADR 0127](../decisions/0127-modules-auditoria-undo-activity-log.md) — Modules/Auditoria UI + undo (Sprint mãe)
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 §3 charter
- [ADR 0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — Gate F1.5+F3 visual MWART
- [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART canônico
- `memory/regras-time.md` — feedback_tenancy_changes_require_pest_local
