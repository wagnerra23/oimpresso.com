---
name: migrar-modulo
description: Use ao mover, renomear, ou extrair controller/módulo Laravel modular existente em `Modules/<X>/` — qualquer `git mv Modules/X Modules/Y`, `git mv Modules/X/Http/Controllers/A.php Modules/Y/...`, refactor de drift detectado em SCOPE.md, ou pedido explícito "renomear módulo", "mover controller", "resolver drift". Carrega contraste entre ADR 0087 (drift sem URL move) vs ADR 0088 (rename PHP-only) + matriz de 8 dimensões satélites + Cascade Review §10.4 + comando GUARDA. Substitui leitura repetida do `MODULE-DRIFT-MIGRATION-PLAN.md`.
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
---

# Migrar módulo / mover controller — pattern safe

## Quando ativa

- `git mv Modules/X Modules/Y` (rename de módulo inteiro)
- `git mv Modules/X/Http/Controllers/A.php Modules/Y/...` (move single controller)
- Pedido: "renomear módulo", "mover controller", "resolver drift", "executar Fase 3.7-3.10"
- SCOPE.md tem `drift_alerts[]` não vazio
- `MODULE-DRIFT-MIGRATION-PLAN.md` referenciado

**NÃO ativa** pra criação de módulo novo — usa skill `criar-modulo` então.

## Princípio canônico

> **Mudança com blast radius vira estágios opt-in.** O plano canônico declara estado-alvo; cada PR faz erratum justificando se desvia. Nunca mover URL/permission/Pages/log/config/lang/DB junto com o code refactor a menos que cada item tenha decisão explícita.

Ver [ADR 0087](../../memory/decisions/0087-drift-resolution-sem-mover-url.md) + [ADR 0088](../../memory/decisions/0088-module-rename-php-only.md).

## 2 sub-patterns (decidir qual aplicar)

### Pattern A — Drift resolution (mover só 1 controller pro módulo dono)

Trigger: SCOPE.md de Modules/X tem `drift_alerts[]` apontando controller que pertence a Modules/Y.

| O que muda | O que NÃO muda |
|---|---|
| `git mv` arquivo do controller | URL pública |
| Namespace do controller | Pages React |
| `use` import na Routes/web.php | route names |
| Tuple `[Class::class, 'method']` ou `'namespace'` prefix do route group | Webhook GitHub / watchers |
| SCOPE.md origem (drift_alerts) + destino (contains[]) | Permissions |

ADR 0087 detalha. Aplicado em PR-1 da Fase 3.7 (commit `850ac349`) — 9 controllers movidos.

### Pattern B — Module rename (renomear pasta inteira)

Trigger: `will_rename_to:` em SCOPE.md OU pedido explícito de rename de módulo.

| O que muda (PHP-only) | O que NÃO muda (fachada legacy) |
|---|---|
| `git mv Modules/X Modules/Y` | URLs `/x/*` |
| `git mv X/Providers/XServiceProvider.php Y/Providers/YServiceProvider.php` | Permissions Spatie `x.*` |
| Namespaces `Modules\X\` → `Modules\Y\` (todos arquivos PHP/JSON/MD) | Config keys + env vars (`X_*`) |
| ServiceProvider class name + docblock | Log channel `x-ai` etc |
| `module.json` (name, alias, providers, descrição) | Pages React `Pages/X/` |
| `composer.json` (name, providers, autoload PSR-4) | Lang namespace `x::` |
| SCOPE.md (campo `module:`) | Tabelas DB `x_*` |

ADR 0088 detalha. Aplicado em PR-2 da Fase 3.7 (commit `8f7a5138`) — Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS.

**Por que PHP-only.** Blast radius de mudar fachada de uma vez é alto demais (5993 clientes ROTA LIVRE com permissions, watchers Claude Code, webhook GitHub, 30 `Inertia::render('Copiloto/...')`). Cada dimensão da fachada vira PR-3+ posterior, com ADR sub-decisão.

## Matriz blast radius — decidir cada item ANTES de mover

| Dimensão | Renomeia? | Custo se renomear |
|---|---|---|
| Namespace PHP `\X` → `\Y` | ✅ óbvio sim | composer dump-autoload pós-deploy |
| Pages React dir `Pages/X/` | ⚠️ default mantém | ~30 `Inertia::render` + ~30 Pages tsx git mv |
| URLs `/x/*` → `/y/*` | ⚠️ default mantém | bookmarks + watchers + webhook + 301 redirects + Pages route() calls |
| Permissions Spatie `x.*` | ⚠️ default mantém | DB backfill em prod (todos tenants) + double-grant durante transição |
| Log channel `x-ai` | ⚠️ default mantém | grep histórico precisa olhar 2 channels |
| Config keys + env `X_*` | ⚠️ default mantém | .env Hostinger update + restart |
| Lang namespace `x::` | ⚠️ default mantém | view rendering + ~N blade strings |
| Tabelas DB `x_*` | ⛔ default JAMAIS | migration + downtime + risco perda de dados |

**Default: renomeia só PHP. Cada outro item vira PR isolado se Wagner decidir vale o investimento.**

## Receita técnica (Pattern B passo-a-passo)

```bash
# 1. git mv pasta + ServiceProvider
git mv Modules/Copiloto Modules/Jana
git mv Modules/Jana/Providers/CopilotoServiceProvider.php Modules/Jana/Providers/JanaServiceProvider.php
```

```powershell
# 2. Bulk rename namespace via PowerShell (literal Replace, sem regex)
$root = "D:\oimpresso.com"  # adjust pra worktree atual
$files = Get-ChildItem -Path $root -Recurse -File -Include *.php,*.json,*.md,*.tsx,*.ts,*.js,*.blade.php |
    Where-Object { $_.FullName -notmatch '\\(node_modules|vendor|public\\build|storage\\framework)\\' }
$replacements = @(
    @{ Old = 'Modules\Copiloto\';   New = 'Modules\Jana\' },
    @{ Old = 'Modules\\Copiloto\\'; New = 'Modules\\Jana\\' }  # JSON-escaped variant
)
foreach ($file in $files) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    $original = $content
    foreach ($r in $replacements) { $content = $content.Replace($r.Old, $r.New) }
    if ($content -ne $original) { [System.IO.File]::WriteAllText($file.FullName, $content) }
}
```

```bash
# 3. Edit individual:
# - ServiceProvider class name + docblock + middleware array key
# - module.json name + alias + providers FQCN
# - composer.json name + providers FQCN + autoload psr-4
# - SCOPE.md campo module: + texto + histórico v1.x
# - Plano canonical bump + erratum

# 4. Validar
php bin/check-scope.php   # 0 drift / 29 módulos esperado

# 5. Commit + push + PR
```

## Erros frequentes (lições da Fase 3.7)

⚠️ **Plano canônico não é infalível.** O plano §1 v1.0.0 confundiu nomes de 2 controllers. SEMPRE leia conteúdo dos arquivos antes de mover, valida descrição do plano vs realidade. Se divergir, criar erratum antes de executar.

⚠️ **Edit precisa Read pós `git mv`.** Path mudou — re-Read no novo path antes de Edit. Senão Edit erra "File has not been read yet".

⚠️ **Perl/sed regex `\C` é metaclass.** Pra namespaces PHP (com `\` literal), usa PowerShell `[string]::Replace()` literal — não regex. Mais previsível, sem escape hell.

⚠️ **Tuple `[Class::class, 'method']` > string `'Class@method'`.** Tuple é FQCN explícito; string depende de `'namespace'` prefix do group. Em drift resolution (Pattern A), tuple zera ambiguidade.

⚠️ **Cross-module dep é OK em transição.** Após Pattern A, Modules/X/Http/routes.php referencia `Modules\Y\Http\Controllers\Z`. Aparenta esquisito, mas é EXATAMENTE o que `app/routes/web.php` faz. URL pertence a quem expôs primeiro; controller pertence a quem é dono semanticamente. Comment inline + ADR linka.

⚠️ **`composer dump-autoload` pós-deploy é OBRIGATÓRIO.** Esquecer = autoloading PSR-4 quebra após pasta renomeada. Incluir no checklist do PR description.

## Cascade Review §10.4 — checklist obrigatório

Antes de fechar PR, audit cada camada:

| Camada | Pergunta | Esperado |
|---|---|---|
| L5 Module Charter | SCOPE.md de origem e destino refletem o move? | drift_alerts zerado origem + contains[] absorve destino |
| L7 Audit | git mv preservou history? | 96-99% similarity |
| Plano canônico | bump version + erratum se divergiu? | sim |
| Composer autoload | PSR-4 atualizado nos composer.json? | sim |
| Tests Pest | imports atualizados? | sim |
| Pages React | toca dir? | default não — registra "fachada legacy mantida" |
| Permissions / DB | toca? | default não — registra "legacy mantida" |
| Webhook / watchers | URL preservada? | sim — caso contrário, plano de overlap + comunicação externa |
| GUARDA `bin/check-scope.php` | 0 drift detectado? | sim |

## Critérios de "PR pronto pra merge"

- [ ] git status mostra renames com R 96-99% similarity
- [ ] SCOPE.md das origens com `drift_alerts: []` (Pattern A) ou campo `module:` atualizado (Pattern B)
- [ ] SCOPE.md dos destinos com controllers em `contains[]`
- [ ] Plano canônico v.X.Y bumped com erratum
- [ ] Composer autoload PSR-4 atualizado nos composer.json (Pattern B)
- [ ] ADR sub-decisão criada se desviou do plano
- [ ] `php bin/check-scope.php`: 0 drift / 29 módulos
- [ ] PR description tem checklist pós-merge: `composer dump-autoload` + smoke URLs

## Substitui

- Leitura repetida de [`MODULE-DRIFT-MIGRATION-PLAN.md`](../../memory/governance/MODULE-DRIFT-MIGRATION-PLAN.md) (canônico, mas longo)
- Releitura dos ADRs 0087 + 0088 a cada operação
- Mental gymnastics da matriz blast radius

## Referências

- [ADR 0087 — Drift resolution sem mover URL](../../memory/decisions/0087-drift-resolution-sem-mover-url.md)
- [ADR 0088 — Module rename PHP-only](../../memory/decisions/0088-module-rename-php-only.md)
- [MODULE-DRIFT-MIGRATION-PLAN](../../memory/governance/MODULE-DRIFT-MIGRATION-PLAN.md) v1.2.0
- [Skill criar-modulo](../criar-modulo/SKILL.md) — para criação (não migração)
- PR exemplo: [oimpresso.com#97](https://github.com/wagnerra23/oimpresso.com/pull/97) Fase 3.7 PR-1+PR-2
