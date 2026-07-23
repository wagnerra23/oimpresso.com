---
title: "RUNBOOK — Validação pós-deploy oimpresso"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Validação pós-deploy oimpresso

> **Use sempre que mergear ≥1 PR que toca `Modules/<X>/Console/Commands/`, `Http/Controllers/`, migrations, Models com FK pra UltimatePOS core (users/business/contacts) ou novo módulo nWidart.**
>
> Pest unit + CI matrix MySQL **não pegam** bugs cross-cutting de schema UltimatePOS (legacy) nem race conditions de autoload Laravel pós-`git pull`. Esta validação 3-camadas pega.

## Origem

Runbook nasceu da sessão 2026-05-10 — Wagner pediu "use o browser para conferir e testar" pós-25 PRs autonomous merge. Validação descobriu 1 bug real em prod (`arquivos:audit-log` usava `users.name` que não existe em UltimatePOS schema — PR #482).

## Quando NÃO usar

- PR só toca tests, docs ou `memory/*` (sem code prod)
- PR só toca CI workflow YAML (testar via gh actions)
- PR é puro hotfix `<5 linhas` em código já validado

## Pré-requisitos

- SSH key Hostinger ativa (`~/.ssh/id_ed25519_oimpresso`) — auto-mem `reference_hostinger_ssh_credenciais.md`
- Wagner logado no browser oimpresso.com (sessão ativa biz=1 WR2)
- Chrome MCP extension conectado (`mcp__Claude_in_Chrome__*` tools disponíveis)

## Receita 3 camadas

### Camada 1 — HTTP curl (rápido, 30 segundos)

Confirma rotas registradas:

```bash
# Warm-up (Hostinger flaky)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

# Test rotas novas — esperar 200 ou 302 (redirect-to-login = middleware auth OK)
curl -s -o /dev/null -w "rota: %{http_code}\n" --max-time 30 https://oimpresso.com/<modulo>/<rota>

# 404 = rota NÃO registrada → autoload Laravel desatualizado, ir Camada 2
# 500 = erro código → ir Camada 3
# 302 = OK (sem cookie redireciona pra login)
# 200 = OK (rota pública ou cookie ativo)
```

### Camada 2 — SSH Hostinger (autoload + commands)

Se Camada 1 deu 404, regenerar autoload + caches:

```bash
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd ~/domains/oimpresso.com/public_html && \
     git log --oneline -3 && \
     composer dump-autoload --optimize 2>&1 | tail -2 && \
     php artisan route:clear && \
     php artisan config:clear && \
     php artisan view:clear'
```

> ⚠️ **NUNCA** rodar `composer install --no-dev` — Faker é usado em prod (auto-mem `reference_composer_install_obrigatorio_pos_deploy.md`).
> `dump-autoload` (~5s) é suficiente quando composer.json não mudou. `install` (~60s) só se composer.lock mudou.

Validar bindings Laravel:

```bash
# Lista commands novos
php artisan list <prefix>:  # ex: arquivos:, comvis:, vestuario:

# Lista rotas registradas
php artisan route:list 2>&1 | grep -iE "<modulo>"

# Migrations status
php artisan migrate:status 2>&1 | grep -iE "<modulo|tabela>"
```

### Camada 3 — Smoke test commands read-only em prod

**Use commands sem efeito colateral primeiro** (`--dry-run`, `list`, `status`, `health-check`):

```bash
# Pattern: testar 1 command read-only por módulo
ssh ... 'cd ... && php artisan <comando>:health-check --business=1 2>&1 | head -20'
```

Se erro `QueryException Column not found` ou `Class not found`:
- Provavelmente bug schema UltimatePOS (users sem `name`, transactions sem campo, etc)
- Documentar bug + criar PR fix com COALESCE em cascata + fallback graceful
- Pattern: `COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a, b)), ''), c, d)` resiste a NULL/whitespace/coluna ausente

### Camada 4 — Browser MCP (validação visual UX)

Após Camadas 1-3 OK, validar UX real:

```python
# Chrome MCP (já carregado em sessions com tools deferidas)
mcp__Claude_in_Chrome__navigate(url="https://oimpresso.com/manage-modules", tabId=...)
mcp__Claude_in_Chrome__computer(action="screenshot", tabId=...)
```

Verificar:
- ✅ Module aparece em `/manage-modules` com botão "Instalar"
- ✅ Descrição do `module.json` renderiza correctly
- ✅ Sidebar topnav inclui novo item após Install

## UltimatePOS schema gotchas (lições 2026-05-10)

| Tabela | Coluna **não existe** | Use isso em vez |
|--------|------------------------|------------------|
| `users` | `name` | `CONCAT_WS(' ', first_name, last_name)` ou `username` |
| `business` | `name_short` | `name` (raiz) |
| `transactions` | `customer_name` | JOIN com `contacts.name` via `contact_id` |
| `contacts` | `customer_name` | `name` (raiz) ou `supplier_business_name` |

Sempre validar via `Schema::getColumnListing('<table>')` antes de query JOIN nova.

## URL Modules nWidart — convenção UltimatePOS

```
✅ /<modulo>/install               — convenção UltimatePOS
✅ /<modulo>/api/<endpoint>        — endpoints internos
❌ /admin/<modulo>/install         — não funciona (não é convenção UPos)
```

`Routes/web.php` middleware stack típico:
```php
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone',
                   'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('<modulo>')->group(function() {
        // ...
    });
```

## Quando criar PR fix pós-validação

Se Camada 3 detectar bug:
1. **Fix branch:** `claude/fix-<module>-<issue>` ou `claude/all-frentes-prN-fix-...`
2. Editar APENAS o file com bug — escopo curto
3. Commit conventional: `fix(<modulo>): <descrição curta> — bug detectado prod via validação RUNBOOK`
4. Body do commit/PR: incluir output do erro original + razão do schema gotcha
5. PR via REST direto se GraphQL rate-limit (`gh api -X POST repos/.../pulls`)
6. Após merge: rerun Camada 3 pra confirmar fix

## Histórico de bugs detectados via este RUNBOOK

| Data | Bug | PR Fix | Lição |
|------|-----|--------|-------|
| 2026-05-10 | `arquivos:audit-log` `u.name` não existe em UltimatePOS users | [#482](https://github.com/wagnerra23/oimpresso.com/pull/482) | Validar `Schema::getColumnListing()` antes de SELECT JOIN cross-tabela |

---

**Owner:** Felipe (validação) + Claude (correção autônoma sob autorização Wagner)
**Última atualização:** 2026-05-10 — origem sessão massiva 28 PRs
**Refs:** ADR 0123 (Modules/Arquivos backbone), ADR 0093 (multi-tenant Tier 0), ADR 0061 (zero auto-mem privada — git canônico)
