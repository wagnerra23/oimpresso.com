---
title: "RUNBOOK — Patrimonio/Index (Painel do Patrimônio)"
module: AssetManagement
tela: Patrimonio/Index
owner: W
status: ativo
last_validated: "2026-09-08"
preconditions:
  - "Modules/AssetManagement instalado no business (pacote assetmanagement_module)"
  - "Usuário com asset.view (ou superadmin)"
steps:
  - "Medir a âncora do dashboard() antes de editar — o sha muda a cada PR no controller"
  - "Conferir a coluna-fonte de cada KPI: sem fonte, renderiza — (C7)"
  - "Rodar a suíte Asset no CT 100 e ler assertions, não '0 failed'"
related_adrs:
  - 0394-endereco-de-ui-do-patrimonio-pages-patrimonio
  - 0104-processo-mwart-canonico-unico-caminho
  - 0182-pageheadertabs-canon-pattern-telas
  - 0093-multi-tenant-isolation-tier-0
---

# RUNBOOK — Patrimonio/Index (Painel do Patrimônio)

> **MWART F1 (PLAN)** da thread 07 do playbook SINCRONIZAR Patrimônio. Primeira tela React do
> módulo: ela cria o `_shared/PatrimonioSubNav.tsx` que as threads 08–12 vão importar.

## 1 · Âncoras (medidas em 2026-09-08, não herdadas)

| o quê | onde | medição |
|---|---|---|
| rota | `Modules/AssetManagement/Routes/web.php:21` | `Route::get('dashboard', …)` — **sem `->name()`** |
| controller | `AssetController::dashboard()` | `:436`–`:516` · arquivo 24.416 B · sha `c55d031fccfe` |
| fonte visual | `prototipo-ui/cowork/patrimonio-page.jsx` | aba "Painel": `painelData()` `:149`–`:194` · `PatPainel()` `:195`–`:245` |
| padrão | `PT-04 Dashboard` (golden `governance/Dashboard.tsx`) | `status: draft` — ver §7 |

> ⚠️ **A âncora do `07-painel.md` dizia `:467-:520` e sha `3eba5a4faae5`.** Remedida: o
> [PR #7018](https://github.com/wagnerra23/oimpresso.com/pull/7018) (fix do `orWhereNull` que
> escapava do tenant) inseriu 11 linhas de comentário e deslocou a faixa. O método real hoje
> começa em `:436`. Bytes coincidem (24.416) — **bytes iguais não provam conteúdo igual**.

## 2 · O que a tela mostra, e de onde vem cada número

O protótipo pede 4 KPIs, 3 blocos de análise e um "Resumo de hoje". A coluna **fonte** abaixo é
o que decide se o número renderiza ou vira `—` (C7 — número sem fonte não renderiza).

| bloco | fonte medida | veredito |
|---|---|---|
| **Patrimônio bruto** | `SUM(assets.quantity * assets.unit_price)` — as duas colunas existem (`decimal(22,4)`) | ✅ calcula |
| **Valor residual** | exigiria depreciação **calculada** | ❌ **`—`** · ver §3 |
| **Alocados X de Y** | X = `SUM(allocate) − SUM(revoke)` em `asset_transactions`; Y = `SUM(quantity)` dos `is_allocatable=1` | ✅ calcula |
| **Garantia vencida ou vencendo** | `asset_warranties.end_date` vs `CURDATE()` | ✅ calcula |
| **Patrimônio por categoria** | `SUM(quantity*unit_price)` agrupado por `categories.name` | ✅ calcula |
| **Situação da garantia** | 4 baldes: na garantia · vence em ≤30d · vencida · **sem registro** | ✅ calcula |
| **Manutenção em aberto** | `asset_maintenances` com `status NOT IN ('completed','cancelled')` | ✅ lista |
| ↳ **custo** da manutenção | **não há coluna de custo** em `asset_maintenances` | ❌ **`—`** · ver §3 |
| **Resumo de hoje** (prosa) | derivado dos números acima | ⚠️ parcial · ver §4 |

Colunas reais de `assets` (baseline `database/schema/mysql-schema.sql:674`): `id · business_id ·
asset_code · name · quantity · model · serial_no · category_id · location_id · purchase_date ·
purchase_type · unit_price · depreciation · is_allocatable · description · created_by · timestamps`.

## 3 · Os dois `—`, e por que não invento fórmula

**Valor residual.** A coluna `assets.depreciation` existe e é **gravada**, mas ninguém a calcula:
ela é validada como `['nullable','string']` (`StoreAssetRequest:67`), normalizada por `num_uf` e
relida **só** pelo `edit.blade.php:71`, para repopular o próprio formulário. Zero aritmética no
repo — nenhum `book_value`, nenhum valor residual. E a regra (linear ou SAC, com que fonte
contábil) é **decisão [W] em aberto** — RESÍDUO 6 do playbook, com dono declarado no
`SPEC.md:96` (`US-ASSET-W01`). Renderiza `—`. Recibo: `_saida-04.md §4`.

**Custo de manutenção.** `asset_maintenances` tem `id · business_id · asset_id · maitenance_id ·
status · priority · created_by · assigned_to · details · maintenance_note · timestamps`. **Não há
coluna de valor** — o `additional_cost` mora em `asset_warranties`, que é outra coisa (custo da
garantia). O protótipo mostra um "custo de manutenção no ano"; esse número é do mock. RESÍDUO 3
do playbook (*"custo de manutenção entra (não há coluna)?"*) é [W]. Renderiza `—`.

## 4 · "Resumo de hoje" — o que entra e o que fica de fora

O protótipo escreve duas frases. A **primeira** é derivável (bens, unidades, bruto) menos o valor
residual, que vira `—`. A **segunda** cita `HP Latex`, `VS-640` e um custo por cabeça de
impressão: isso é **cenário do mock**, não copy de contrato — não há fonte no banco para nenhum
dos três. O painel renderiza a parte derivável e **omite** a parte de cenário, em vez de imitá-la
com texto plausível. Substituto plausível é a forma mais duradoura de mentira.

## 5 · SubNav — 7 abas, 5 com rota

`_shared/PatrimonioSubNav.tsx` segue o padrão do `FinanceiroSubNav` (ADR 0313): lista de abas
**canônica no frontend** (a do protótipo), com o `shell.menu` servindo de **gate de permissão** e
fonte do `primary`. Sem entry do módulo no menu → `return null` (multi-tenant Tier 0).

| aba | rota | origem |
|---|---|---|
| Painel | `/asset/dashboard` | shell.menu `dashboard` |
| Bens | `/asset/assets` | shell.menu `assets` (lá rotulado "Ativos" — ver §6) |
| Alocações | `/asset/allocation` | shell.menu `allocation` |
| Manutenções | `/asset/asset-maintenance` | shell.menu `asset-maintenance` |
| Configurações | `/asset/settings` | shell.menu `settings` |
| **Garantias** | **não existe** | bloqueada por `D-GARANTIAS` |
| **Auditoria** | **não existe** | bloqueada por `D-AUDITORIA` |

As duas sem rota entram como `extraOverflowItems` no `⋯ Mais`, **inertes**, com `title`
explicando o bloqueio. Não invento rota. Alternativa considerada e rejeitada: estender
`PageHeaderGhost` com `disabled`/`title` — o tipo não os tem, e `PageHeaderTabs.tsx` é shared por
4 módulos e está fora do prefixo desta thread. `PageHeaderOverflowItem` **já** tem `title`.

Também fora: `revocation` ("Devoluções"), que existe no shell mas o protótipo funde em Alocações.
Fundir a **rota** é decisão [W]; a thread 09 funde a **tela**. Fica no overflow, navegável.

**Grupo/hue:** `estoque`. Medido, não herdado — `Sidebar.tsx:243` lista `'Gestão de ativos'` na
whitelist do grupo `estoque`, e o `DataController` do módulo não declara `group`, então o
`findGroupKey` resolve por label. (A ADR 0180 e o comentário do `DataController` dizem `operar`,
que é **alias legacy v2** → `producao`. Divergência registrada no `_saida-07.md`.)

## 6 · Vocabulário — três em disputa, e qual vence

| termo | `pt/lang.php` | protótipo | shell.menu |
|---|---|---|---|
| a entidade, no plural | **`assets` → "Bens"** | "Bens" (54×) | "Ativos" |
| a entidade, no singular | `asset` → "Ativo" | "bem" | — |
| o módulo | `asset_management` → "Gestão de ativos" | "Patrimônio" | "Gestão de ativos" |

A tela usa o `pt/lang.php`, que para o plural **já diz "Bens"** — coincidindo com o protótipo. A
divergência real é o `shell.menu` ("Ativos") e o próprio `lang.php` internamente ("recurso" em
`view_asset`, `add_asset`, `asset_name`). **Não unifico** — é decisão de produto, registrada no
`_saida-07.md`.

## 7 · Riscos e travas

- **Tier 0 multi-tenant (ADR 0093):** `asset_warranties` **não tem `business_id`** — toda consulta
  de garantia entra por `join` com `assets` filtrando `assets.business_id`. As duas consultas do
  ramo não-admin do `dashboard()` hoje filtram só `receiver`; ao serem reescritas para alimentar
  as props, nascem com `business_id`. Declarado no PR.
- **`Inertia::defer`** (RUNBOOK canônico em `_DesignSystem/RUNBOOK-inertia-defer-pattern.md`): as
  6 agregações são deferidas; só `is_admin`, permissões e o carimbo de hora são eager.
- **`Util::num_uf`:** o painel é **somente leitura** — não grava valor, então o vetor do incidente
  de 2026-06-05 não se aplica aqui. Ele passa a se aplicar na thread 08 (formulário de Bens).
- **Golden do PT-04 é `draft`:** o `criar-tela.mjs` avisa que esta tela não fecha o
  "ciclo-completo" até o golden virar `live`. Não bloqueia a entrega; fica declarado.
- **`dashboard.blade.php` fica órfão** quando o `dashboard()` passa a devolver Inertia. Não
  deletado aqui: deleção de view é escopo de limpeza, e o arquivo não está no prefixo.

## 8 · Verificar

```bash
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan test --filter=Asset"
npm run casos:report
node scripts/governance/anchor-lint.mjs --check
```

Ler **assertions**, nunca "0 failed" — teste que pula sai com exit 0. Tenant de teste é o
fictício **98** (ADR 0358); `biz=4` é proibido.
