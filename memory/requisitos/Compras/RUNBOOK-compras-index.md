---
slug: runbook-compras-index
title: "RUNBOOK — /compras (cockpit)"
type: runbook
module: Compras
page: /compras
component: resources/js/Pages/Compras/Index.tsx
status: scaffold
updated_at: 2026-05-21
version: 0.1
owner: wagner
---

# RUNBOOK — `/compras` (cockpit Inertia)

> **Status:** Wave 1 scaffold (2026-05-21). Page Inertia ainda não existe (Wave 4 cria).
> Este RUNBOOK descreve o **target** pós Wave 5, validado biz=1 + canary biz=4.

## 1. Quando esta tela quebra (sintomas)

- `/compras` retorna 500 → ver `storage/logs/laravel.log` + se `Modules/Compras/` está em `modules_statuses.json` com `true`
- `/compras` retorna 403 → user sem permission `compras.view` (Spatie role `admin#{biz}` ou role custom)
- Sidebar não mostra "Compras" → `compras_module` feature flag OFF no business OR `DataController::modifyAdminMenu` não executou (middleware `AdminSidebarMenu` ausente)
- KPI cards vazios → `TransactionUtil::getListPurchases($business_id)` retornou `[]` (filter por business correto?)
- Drawer não abre → conferir `Inertia::defer` props chegando — Network tab `?only[]=compra`
- "Importar XML" não lista DFe → conferir `nfe_dfe_recebidos.transaction_id IS NULL` + business_id match

## 2. Estrutura de arquivos

```
Modules/Compras/
├── module.json                                # nWidart manifest
├── composer.json                              # PSR-4 autoload
├── Config/config.php
├── Database/Migrations/                       # vazio Wave 1; Wave 6 adiciona transaction_id em nfe_dfe_recebidos
├── Http/Controllers/
│   ├── ComprasController.php                  # index/show/store/update (wrappers TransactionUtil)
│   ├── DataController.php                     # sidebar + permissions + superadmin_package
│   └── InstallController.php                  # install/uninstall/update
├── Providers/
│   ├── ComprasServiceProvider.php
│   └── RouteServiceProvider.php
├── Routes/web.php
├── Services/                                  # Wave 3: ComprasService; Wave 6: ImportarDfeComoCompraService
├── Jobs/                                      # Wave 6: ImportarDfeComoCompraJob
└── Tests/Feature/                             # ComprasIndexTest (Wave 1) + Wave 3+ multi-tenant + DFe

resources/
├── css/cowork-compras-bundle.css              # Wave 4: COPIAR INTEIRO compras-page.css
└── js/Pages/Compras/
    ├── Index.tsx                              # Wave 4: F1 pin literal compras-page.jsx
    ├── Index.charter.md                       # Wave 4: charter obrigatório
    └── components/
        └── GradeMatrixInput.tsx               # Wave 4.5: TanStack Table v8 headless
```

## 3. Comandos úteis

```bash
# Confirmar módulo registrado
php artisan module:list | grep Compras

# Roda Pest do módulo
php artisan test --filter=Compras

# Apenas o test smoke Wave 1
php artisan test Modules/Compras/Tests/Feature/ComprasIndexTest.php

# Reload autoload depois de novos arquivos
composer dump-autoload

# Limpar cache config (depois de mudança em modules_statuses.json)
php artisan config:clear

# Habilitar feature flag p/ business=4 (Larissa ROTA LIVRE) — exemplo Tinker
php artisan tinker --execute='
  $biz = \App\Business::find(4);
  $details = json_decode($biz->package_details ?? "{}", true);
  $details["compras_module"] = true;
  $biz->package_details = json_encode($details);
  $biz->save();
'
```

## 4. Smoke local (após Wave 4)

```bash
# 1. Build assets
npm run build

# 2. Servidor local (Herd ou php artisan serve)
php artisan serve --port=8000

# 3. Login + curl
curl -sv -b cookies.txt http://localhost:8000/compras 2>&1 | grep '^< HTTP'
# Esperado: < HTTP/1.1 200 OK

# 4. Confirmar Inertia component
curl -s -b cookies.txt -H "X-Inertia: true" -H "Accept: application/json" \
  http://localhost:8000/compras | jq '.component'
# Esperado: "Compras/Index"
```

## 5. Smoke prod (R1 PROTOCOLO WAGNER — evidência curl não narração)

```bash
# Status code literal
curl -sv https://oimpresso.com/compras 2>&1 | grep '^< HTTP'
# Esperado: < HTTP/2 200 (ou 302 se não logado)

# Regression adjacent — legacy /purchases ainda funcional (Wave 8 vira 301)
curl -sv https://oimpresso.com/purchases 2>&1 | grep '^< HTTP'

# Sells (vizinho mais comum)
curl -sv https://oimpresso.com/sells 2>&1 | grep '^< HTTP'

# Financeiro fluxo (vizinho de cockpit)
curl -sv https://oimpresso.com/financeiro/fluxo 2>&1 | grep '^< HTTP'
```

Chrome MCP screenshot **obrigatório** após deploy (hook `post-merge-ui-smoke-required.ps1`):

```
mcp__Claude_in_Chrome__navigate url=https://oimpresso.com/compras
mcp__Claude_in_Chrome__read_console_messages
mcp__Claude_in_Chrome__resize_window width=1280 height=800   # Larissa monitor
```

## 6. Rollback

- **Wave 1 (scaffold puro):** safe — desabilitar via `modules_statuses.json`:
  ```json
  "Compras": false
  ```
  + `php artisan cache:clear`. Rota `/compras` some sem afetar `/purchases` legacy.
- **Wave 8 (deprecação /purchases):** rollback = feature flag `compras_module` OFF no business. Blade legacy volta automaticamente.
- **Wave 9 (canary biz=4 Larissa):** rollback = feature flag OFF + WhatsApp Larissa avisando.

## 7. Tier 0 — invariantes que NUNCA podem quebrar

- ❌ Multi-tenant leak — `compras` mostrando dados de outro business (Pest `MultiTenantIsolationTest` em Wave 3)
- ❌ Observer Financeiro deixar de criar `fin_titulos` ao salvar Transaction type=purchase (Wave 3 Pest cobre)
- ❌ Listener legacy `PurchaseCreatedOrModified` não disparar (Wave 3 mantém event dispatch)
- ❌ Re-import duplicado de mesmo DFe criando 2 Transactions (Wave 6 UNIQUE + advisory lock)

## 8. Refs

- [SPEC.md](SPEC.md)
- [BRIEFING.md](BRIEFING.md)
- [Index.charter.md](../../../resources/js/Pages/Compras/Index.charter.md) — Wave 4
- [memory/sessions/2026-05-21-como-integrar-compras.md](../../sessions/2026-05-21-como-integrar-compras.md)
- [ADR proposta compras-modulo-greenfield-hibrido](../../decisions/proposals/compras-modulo-greenfield-hibrido.md)
