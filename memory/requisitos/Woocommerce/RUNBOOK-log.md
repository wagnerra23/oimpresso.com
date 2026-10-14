---
slug: woocommerce-runbook-log
title: "Woocommerce — Runbook da tela Log de sincronização"
type: runbook
module: Woocommerce
tela: Woocommerce/Log/Index
owner: W
status: ativo
last_validated: "2026-10-14"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Log de sincronização do WooCommerce (`/woocommerce/view-sync-log`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `woocommerce/playbook/02`.
> **Fonte de design:** alvo `governance/design/targets/woocommerce--log--index.alvo.json` (thread A1), medido sobre
> `prototipo-ui/cowork/Wagner/integra-extras.jsx` → `WooCommercePage`, aba Log de sincronização.

## Estado final esperado

`GET /woocommerce/view-sync-log` (`WoocommerceController::viewSyncLog`) responde Inertia `Woocommerce/Log/Index`
**quando a flag `useV2WoocommercePainel` está ligada** (a mesma do painel). Desligada, ou com `?classico=1`, segue a
Blade `woocommerce::woocommerce.sync_log`, cuja tabela DataTables continua buscando o mesmo endpoint por AJAX.

## 1. Objetivo

Listar as execuções de sincronização (data, tipo, operação, quem sincronizou, registros) e os erros de cada uma, no
desenho do alvo da A1.

## 2. Pré-condições

- Acesso ao módulo (`ensureModulo`). Os tipos visíveis seguem a permissão: `woocommerce.syc_categories` → categorias ·
  `woocommerce.sync_products` → produtos · `woocommerce.sync_orders` → pedidos; `superadmin` vê todos.
- Lane `acessos-pest.yml` (MySQL) passa a rodar `Modules/Woocommerce/Tests/Feature/Log*Test.php`.

## 3. O que a Blade faz hoje (lido em `viewSyncLog` / `getLogDetails` / `WoocommerceSyncLogRepository`)

- Pedido AJAX → DataTables sobre `paraDatatable($business_id, $tipos, $superadmin)`: só logs do negócio da sessão,
  filtrados pelos tipos permitidos. **Sem nenhuma das 3 permissões e sem superadmin, a lista de tipos fica vazia e o
  filtro não se aplica** (vê todos os tipos) — comportamento atual, preservado.
- Linha com `details` abre os erros por AJAX em `getLogDetails` (partial `log_details`): produto não encontrado,
  quantidade insuficiente, pedido sem cliente.

## 4. Passo-a-passo

1. F2 — `LogBaselineTest` trava o AJAX de hoje: só logs do negócio da sessão e só os tipos permitidos.
2. F3 — a regra dos tipos sai do `viewSyncLog()` para `tiposDeLogPermitidos()`, usada pelo DataTables e pela tela
   React: uma fonte só.
3. F3 — ramo Inertia **antes** do `request()->ajax()`: o Inertia manda `X-Requested-With` em toda visita, então o
   ramo React se decide por `X-Inertia` (ou pedido não-AJAX); o DataTables da Blade não manda `X-Inertia` e continua.
4. F3 — `Woocommerce/Log/Index.tsx` + charter + casos + `LogContratoTest`.
5. F4 — smoke biz=1 com a flag ligada. F5 — cutover, decisão [W].

## 5–8. Tokens · estados · responsividade

Herdados do PT-01 e do `AppShellV2`. Estados: carregando (prop deferida), sem execução, linha com erros (expande).
25 execuções por página, mais recentes primeiro.

## 9. DoD checklist

- [ ] `LogBaselineTest` e `LogContratoTest` verdes na lane `acessos-pest.yml`, com assertions > 0.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. Pegadinhas e divergências do protótipo

- **`X-Requested-With`**: ver passo 3. Testar o ramo React sem esse header passaria verde e quebraria no browser
  (§5 2026-09-08).
- **Situação por execução** ("Com erro" / "Sincronizado") do protótipo vem de `details`: a tela marca com erro quando
  há erro listado, como o ícone de expandir da Blade.
- **Busca por quem sincronizou** existe no DataTables (servidor); a tela nova filtra na página.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
