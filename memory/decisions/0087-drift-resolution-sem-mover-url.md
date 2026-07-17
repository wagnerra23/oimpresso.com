---
slug: 0087-drift-resolution-sem-mover-url
number: 87
title: "Drift resolution sem mover URL — pattern de migration safe"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-06"
module: null
quarter: 2026-Q2
tags: [governance, refactor, drift-resolution, module-charter, migration-pattern]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0080-trust-tiers-operacional-audit-findings
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0088-module-rename-php-only
pii: false
review_triggers:
  - "PR-3+ planejado mover URLs/permissions/Pages — pattern desta ADR continua válido pra próximas drift resolutions?"
  - "Cross-module dep temporária causa confusão de blame em > 3 meses (lookup difícil) — virar regra estrita 'mover URL junto com controller' justifica?"
---

# ADR 0087 — Drift resolution sem mover URL — pattern de migration safe

## Contexto

Audit do MODULE-DRIFT-MIGRATION-PLAN v1.0.0 (2026-05-05) detectou 10 controllers em pastas erradas. O plano §3 sugeriu sequência incluindo:

> 4. Atualizar Routes/web.php de cada módulo afetado
> 6. Adicionar 301 redirects nas URLs antigas (Routes/web.php do módulo origem)

Implícito: **mover URL junto com controller** (e.g. `/copiloto/memoria` → `/kb/memoria` com 301).

Análise de blast radius pra cada drift controller revelou:

- `Mcp/SyncMemoryWebhookController` — webhook GitHub aponta pra `/api/mcp/sync-memory`. Mover URL exige Wagner atualizar settings GitHub manualmente + janela de overlap pra evitar dropar pushs.
- `Mcp/CcIngestController` — watchers Claude Code de cada dev (Wagner/Felipe/Maíra/Eliana) apontam pra `/api/cc/ingest`. Mover URL exige re-config de cada watcher.
- `MemoriaController` (LGPD) — Pages React `Pages/Copiloto/Memoria.tsx` faz `route('copiloto.memoria.*')`. Mover URL exige update da Page + route names.
- 6 outros — bookmarks de Wagner, links em emails históricos, refs em docs e specs.

Total: cada URL move = 1-3h adicional + risco humano (Wagner esquecer de atualizar webhook GitHub = MCP server quebra).

A decisão "mover URLs" não tem ROI claro vs "manter URLs" pra resolver drift. **O ponto do drift é controller na pasta errada**, não URL na rota errada.

## Decisão

**Drift resolution = mover só o controller físico. URL fica onde está.**

Implementação técnica:

### Caso 1 — Route group com `'namespace' => 'Modules\X\Http\Controllers\Y'`

Antes:
```php
Route::group([
    'namespace' => 'Modules\Copiloto\Http\Controllers\Mcp',
    'prefix' => 'api/mcp',
], function () {
    Route::post('/sync-memory', 'SyncMemoryWebhookController@handle');
});
```

Depois (controller movido pra `Modules\TeamMcp\Http\Controllers\Mcp\`):
```php
Route::group([
    'namespace' => 'Modules\TeamMcp\Http\Controllers\Mcp', // <-- só isso muda
    'prefix' => 'api/mcp',
], function () {
    Route::post('/sync-memory', 'SyncMemoryWebhookController@handle');
});
```

URL `/api/mcp/sync-memory` permanece. Webhook GitHub não nota.

### Caso 2 — Route com string controller `'Class@method'`

Antes:
```php
Route::get('/memoria', 'MemoriaController@index')->name('copiloto.memoria.index');
```

Depois (controller movido pra Modules\KB):
```php
Route::get('/memoria',
    [\Modules\KB\Http\Controllers\MemoriaController::class, 'index']
)->name('copiloto.memoria.index');
```

URL `/copiloto/memoria` e route name `copiloto.memoria.index` permanecem. Pages React não notam.

### Caso 3 — `use` import no topo do Routes/web.php

Antes:
```php
use Modules\ADS\Http\Controllers\Admin\ToolsController;
```

Depois (controller movido pra TeamMcp):
```php
use Modules\TeamMcp\Http\Controllers\Admin\ToolsController; // <-- só isso muda
```

`Route::get('/admin/tools', [ToolsController::class, 'index'])` continua intocada.

### O que SCOPE.md registra

**Origem** (Copiloto, ADS): `drift_alerts: []` zerado. Nota inline:
```yaml
drift_alerts: []
  # Fase 3.7 PR-1 (2026-05-06): N drift controllers movidos pros donos corretos.
  # URLs mantidas via tuple [Class::class, 'method'] / namespace prefix dos route groups.
```

**Destino** (KB, TeamMcp, ProjectMgmt): controller listado em `contains[]` com URL real:
```yaml
contains:
  - "MemoriaController — tela LGPD pessoal; URL /copiloto/memoria mantida"
```

## Justificativa

**Por que não mover URL.** Plano canônico tinha 2 racionais aglutinados: (a) controller no módulo dono correto, (b) URL coerente com módulo. (a) é o problema arquitetural real (drift de scope); (b) é cosmético + tem custo externo (webhook, watchers, bookmarks). Separar (a) e (b) permite resolver drift HOJE sem coordenação humana com sistemas externos.

**Por que cross-module dep é OK.** Após drift resolution, Modules/Copiloto/Http/routes.php tem refs a `Modules\KB\Http\Controllers\MemoriaController`. Aparenta ruim ("Copiloto chamando KB"), mas é EXATAMENTE o mesmo padrão que `app/routes/web.php` chamando controllers de qualquer módulo. URL é fachada pública; pertence a quem expôs primeiro. Controller é detalhe; pertence a quem é dono semanticamente.

**Por que tuple `[Class::class, 'method']` em vez de string `'Class@method'`.** String depende do `namespace` prefix do group. Tuple é FQCN explícito — funciona em qualquer group, claro pra IDE refactoring, e evita resolução implícita.

**Quando reabrir.** PR posterior (PR-3+) pode mover URL se Wagner decidir item a item — ver ADR 0088 (rename PHP-only) que estabelece a mesma estratégia pra rename de módulo.

## Cascade Review (cumprindo §10.4)

| Camada | Auditada | Resultado | Ação |
|---|---|---|---|
| L5 Module Charter | ✅ sim | SCOPE.md de 5 módulos atualizados (drift_alerts zerado nas origens, contains[] absorveu nos destinos) | OK |
| L7 Audit | ✅ sim | git mv preserva history (96-99% similarity); nenhum new actor | OK |
| L4 Identity Mesh | n/a | drift resolution não toca actors | n/a |
| Tests Pest | ✅ sim | imports atualizados (`use Modules\Copiloto\Http\Controllers\MemoriaController` → `use Modules\KB\Http\Controllers\MemoriaController`) | OK |
| Pages React | ✅ sim | NÃO tocadas — `route('copiloto.memoria.*')` continua válido (URL/name preservados) | OK |
| Webhook GitHub / Watchers | ✅ sim | URLs `/api/mcp/sync-memory` e `/api/cc/ingest` preservadas — config externa intocada | OK |

## Consequências

**Positivas:**

- **Drift resolution executada com zero break.** Wagner pode mergear sem coordenar com sistemas externos.
- **Plano canônico aprovado em estágios.** §1 (controller move) feito; §3-4 (URL move + 301) opcional posterior.
- **Pattern reusável.** Próximas drift resolutions (Fase 3.4-3.6 com 24 SCOPE.md restantes) seguem mesmo método.

**Negativas / Trade-offs:**

- **Cross-module dep temporária.** `Modules/Copiloto/Http/routes.php` referencia `Modules\KB\Http\Controllers\MemoriaController`. Lookup de blame inicial fica confuso pra dev novo. Mitigação: comment inline explica drift resolution e linka esta ADR.
- **URL semanticamente mismatch.** `/copiloto/memoria` chama controller de KB. Para o usuário final é transparente; para auditoria interna parece inconsistente. Mitigação: SCOPE.md de KB registra URL real.
- **PR posterior ainda existe.** Wagner pode querer mover URLs depois — esta ADR não fecha o tema, só fragmenta.

**Riscos mitigados:**

- Webhook GitHub quebrar durante migration (URL preservada = zero risco).
- Watchers Claude Code parando de ingest (URL preservada).
- Pages React quebrarem com `route('copiloto.memoria.index')` undefined (route name preservado).
- 30 `Inertia::render('Copiloto/...')` quebrando (Pages dir não tocada).

## Implementação

✅ **FEITO em PR-1 da Fase 3.7 (commit `850ac349`):**

1. 9 drift controllers movidos via `git mv` preservando history (96-99% similarity)
2. Namespaces atualizados nos 9 arquivos
3. Routes em Copiloto/Http/routes.php + ADS/Routes/web.php — `use` imports + tuple `[Class::class, 'method']` + `'namespace'` prefix swap
4. 5 SCOPE.md atualizados (Copiloto, ADS, KB, TeamMcp, ProjectMgmt)
5. Plano canônico v1.0.0 → v1.1.0 com erratum §1 (Memoria/Fontes não eram o que descrevia)
6. `bin/check-scope.php`: 0 drift / 29 módulos

⏸️ **Aplicável a (próximas sessões):**

- 24 SCOPE.md restantes (Fase 3.4) podem detectar drift novo — usar este pattern
- Fase 3.7 #6 (`Admin/GovernancaController` em Modules/Copiloto → Modules/Governance) — mesma estratégia em Fase 5

## Referências

- [MODULE-DRIFT-MIGRATION-PLAN v1.2.0](../governance/MODULE-DRIFT-MIGRATION-PLAN.md) §1, §3, erratum §1+§4
- [ADR 0080 — Trust Tiers + audit findings](0080-trust-tiers-operacional-audit-findings.md)
- [ADR 0086 — Fase 5 MVP Governance](0086-fase-5-mvp-governance-actiongate-warn.md)
- [ADR 0088 — Module rename PHP-only](0088-module-rename-php-only.md) (decisão pareada)
- [Session log 2026-05-06 PR-1](../sessions/2026-05-06-fase-3-7-pr1-drift-controllers.md)
- PR [oimpresso.com#97](https://github.com/wagnerra23/oimpresso.com/pull/97) commit `850ac349`
