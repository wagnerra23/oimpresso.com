---
title: "RUNBOOK MWART — Repair/Settings (configurações da folha de OS + etiqueta)"
module: "Repair"
tela: "Repair/Settings"
owner: "W"
status: "rascunho"
last_validated: "2026-09-04"
preconditions:
  - "Módulo Repair habilitado na assinatura do business (repair_module) — Camada 1 do sidebar"
  - "Usuário com permissão repair.create OU superadmin"
  - "Nenhuma migration: os dois conjuntos são JSON em business.repair_settings e business.repair_jobsheet_settings"
steps:
  - "F1 PLAN — este documento (medição do Blade legado + decisão de recorte)"
  - "F2 BASELINE — Pest fixando o contrato de gravação dos DOIS endpoints antes de tocar UI"
  - "F3 CODE — RepairSettingsController@index passa a Inertia::render + Pages/Repair/Settings/Index.tsx"
  - "F4 QA — smoke autenticado 1280px + prova de que cada aba grava no endpoint certo"
  - "F5 CUTOVER — Blade legado deixa de ser renderizado"
related_adrs:
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0093-multi-tenant-isolation-tier-0"
  - "0121-oimpresso-modular-especializado-por-vertical"
---

# RUNBOOK MWART — Repair/Settings (configurações da folha de OS + etiqueta)

> **Tela:** `/repair/repair-settings` · **Componente alvo:** `resources/js/Pages/Repair/Settings/Index.tsx` (a criar)
> **Origem:** Onda 1 do pacote de export do Repair — `prototipo-ui/design-docs/cowork-inbox/REPAIR-ONDAS-2026-09-04.md`, aterrissado no PR #6773 (link omitido de propósito: o arquivo só entra no `main` quando aquele PR mergear) · **Data:** 2026-09-04
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · ADR ARQ-0002 local (portal público — **fora** desta onda)

Tudo abaixo foi medido contra `origin/main` no tip `dea7d2c4ef` (2026-09-04 18:08 -0300). O que não foi medido está declarado como tal.

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/settings/index.blade.php` + 2 partials (preservado) |
| Controller | `RepairSettingsController@index` → `view('repair::settings.index')` (linha 78) |
| Inertia Page | **não existe** — `resources/js/Pages/Repair/Settings/` ausente |
| Rotas | `Route::resource('/repair-settings')->only('index','store')` (`web.php:18`) **+** `POST /repair/update-repair-jobsheet-settings` (`web.php:40`) |
| Persistência | 2 colunas JSON em `business` — **sem tabela, sem migration** |
| Cliente piloto canary | biz=1 (ROTA LIVRE biz=4 **não** usa Repair) |

## Decisões F1 PLAN

### 1. O recorte: 2 de 5 abas — as outras 3 NÃO entram

O Blade legado é um **hub de 5 abas**, não uma tela de formulário:

| # | aba (id no Blade) | destino nesta onda |
|---|---|---|
| 1 | `repair_status_tab` | ❌ **já migrada** — `Pages/Repair/Status/Index.tsx` (viva) |
| 2 | `repair_device_tab` (taxonomia de dispositivos) | ❌ fora do escopo — sem Page hoje, onda própria |
| 3 | `repair_device_models_tab` | ❌ **já migrada** — `Pages/Repair/DeviceModels/Index.tsx` (viva) |
| 4 | `repair_settings_tab` | ✅ **entra** — endpoint `store()` |
| 5 | `jobsheet_settings_tab` | ✅ **entra** — endpoint `updateJobsheetSettings()` |

Migrar "a tela de settings" inteira **duplicaria duas telas que já existem e estão vivas**. A Page nova cobre as abas 4 e 5 e **aponta** para as Pages existentes de Status e Modelos em vez de reimplementá-las.

### 2. São DOIS endpoints de escrita, não um

O pacote de export afirma que a rota tem "só `index`+`store`" e manda submeter tudo em `store()`. **Está errado**, e obedecer produziria tela inerte:

| endpoint | método | coluna | conteúdo |
|---|---|---|---|
| `POST /repair/repair-settings` | `store()` | `business.repair_settings` | prefixo da folha, status padrão, produto padrão, barcode (id + tipo), 4 textos longos, campos personalizados 1..5, checklist |
| `POST /repair/update-repair-jobsheet-settings` | `updateJobsheetSettings()` | `business.repair_jobsheet_settings` | 3 rótulos + largura/altura da etiqueta, `contact_custom_fields[]`, **17 chaves `show_*`** |

Uma Page que mandasse a aba 5 para o `store()` **salvaria sem erro e não persistiria nada** — classe LC-30 (correção que passa no CI e é inerte no runtime).

### 3. Escrita é destrutiva por construção — submit parcial APAGA

Os dois métodos fazem `$request->only([...])` e depois `Business::update([<coluna> => json_encode($input)])`. **Substituem o JSON inteiro.** Chave ausente no POST **some do banco**.

Consequência dura para a Page: cada formulário tem de enviar **o conjunto completo** do seu endpoint a cada submit. Campo que a UI não renderizar é campo que o próximo salvamento apaga. Isso vira GUARD de Pest, não comentário.

### 4. Dois defeitos do Blade legado, medidos — a migração muda o que o usuário vê

**(a) Campos personalizados 2 e 4 somem quando o 1 está vazio.** Em `repair_settings_tab.blade.php:113` e `:125` a *condição* lê a chave errada:

```php
{!! Form::text('job_sheet_custom_field_2', !empty($repair_settings['job_sheet_custom_field_1']) ? $repair_settings['job_sheet_custom_field_2'] : '', ...) !!}
```

O valor usa a chave certa; **o guard usa a do campo 1**. Com o campo 1 vazio, os campos 2 e 4 renderizam em branco mesmo tendo valor gravado — e, somado à decisão 3, **o próximo submit apaga os dois**. É perda de dado silenciosa na tela legada.

**(b) A aba 5 dereferencia variável que o controller não passa.** `jobsheet_settings_tab.blade.php:56` faz `in_array('custom_field1', $contact_custom_fields)`, mas o `compact()` do `index()` (linha 78) entrega 9 variáveis e **`$contact_custom_fields` não é uma delas** — nem `$custom_labels`. Varredura contada no repo inteiro: os únicos sites `.php` fora de views são o *array de nomes* no próprio controller (`:136`), o `InvoiceLayoutController`, o cast em `app/InvoiceLayout.php`, o `TransactionUtil` e migrations — **nenhum `View::share`**.

⚠️ **Não afirmo que a aba 5 dá 500 hoje** — não renderizei. Afirmo o que está provado estaticamente. **Verificar em F2** (render autenticado da aba no CT 100/staging) antes de decidir se a migração *conserta* um erro vivo ou apenas evita reintroduzi-lo.

Se (a) e (b) se confirmarem em runtime, o comportamento pós-migração **difere** do legado — e isso é melhoria, mas tem de aparecer no PR como mudança declarada, nunca como efeito colateral silencioso.

### 5. Sem migration, sem rota nova

Tudo é JSON em `business`. A Page reusa as duas rotas que já existem. **Nada de tabela `repair_settings`.**

### 6. Permissão é espelhada, não reescrita

Os três métodos usam o mesmo gate: `superadmin` OU (`repair_module` na assinatura **e** `repair.create`). A Page **não** reimplementa a regra — o Controller segue sendo a autoridade, e a UI só reflete o que ele já decidiu (botão desabilitado, nunca rota aberta).

## F2 BASELINE

Antes de tocar UI, Pest fixando o contrato de gravação **dos dois** endpoints:

1. `store()` grava as 14 chaves em `business.repair_settings` e **só** nelas.
2. `updateJobsheetSettings()` grava rótulos + `contact_custom_fields` + as 17 `show_*` em `business.repair_jobsheet_settings` e **só** nelas.
3. **Submit parcial apaga** — o teste afirma o comportamento destrutivo atual, para que a Page não o descubra em produção.
4. 403 sem `repair.create` nos dois endpoints.
5. Isolamento: gravar no tenant A não altera o JSON do tenant B.

Tenant de teste = **98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)). biz=4 é proibido; biz=1 fica só para smoke manual.

## F3 CODE

- `RepairSettingsController@index` ganha branch `Inertia::render('Repair/Settings/Index', [...])` com as **9 props reais** do `compact()` atual: `barcode_settings` · `repair_settings` · `default_product_name` · `barcode_types` · `repair_statuses` · `brands` · `devices` · `module_category_data` · `jobsheet_pdf_settings`.
- Props que a aba 5 exige e hoje **não existem**: `contact_custom_fields` e `custom_labels` — o branch Inertia **passa as duas** (derivadas de `session('business.custom_labels')`, como o resto do app já faz). É o conserto do defeito (b).
- `Pages/Repair/Settings/Index.tsx` — `AppShellV2` + `PageHeader` + `@/Components/ui/*`. Dois formulários independentes, um por endpoint, cada um enviando **seu conjunto completo**.
- Nenhum hex cru. Tokens do DS, como as 6 Pages irmãs do módulo já fazem.
- Vocabulário **shared** (`repair-shared-vocab.yml`): nada de `placa|vehicle|km|mecanico|box|elevador`.
- `store()` e `updateJobsheetSettings()` **não são tocados** — o contrato de gravação fica intacto nesta onda.

## F4 QA

- Pest do F2 verde na lane MySQL.
- Prova por aba: submeter a aba 4 **não** altera `repair_jobsheet_settings`, e vice-versa.
- Prova de não-apagamento: salvar a aba 4 preserva as 14 chaves, inclusive campos 2 e 4 com o campo 1 vazio (o defeito (a) não sobrevive à migração).
- Smoke autenticado em prod, dark, **1280px** (monitor da Larissa), com screenshot no PR — R1.
- `casos.md` com ≥1 UC citado por teste, no MESMO PR (`casos-gate` G-2).

## F5 CUTOVER

Blade legado preservado. Coexistência **opt-in por flag MWART**, como as 6 telas irmãs do módulo já fazem: `config('mwart.repair_settings_index')`, env `MWART_REPAIR_SETTINGS_INDEX` (+ `_BIZ` para whitelist), **default OFF**.

> ⚠️ **Emenda no F3 (2026-09-04, mesmo PR que implementou):** a primeira redação deste RUNBOOK dizia *"sem flag por business nesta onda — o cutover é a troca do `return`"*. Está **corrigido aqui, não apagado**. Trocar o `return` faria o cutover acontecer no **deploy**, e o F5 exige smoke real (R1) + decisão [W] — sem flag, não há como mergear o código sem já ter mudado a tela de quem estava usando. A flag devolve o cutover a quem decide.

O cutover é [W] ligando a flag depois de ver o screenshot.

## Riscos

| # | risco | mitigação |
|---|---|---|
| 1 | Submit parcial apagar configuração do cliente | GUARD de Pest no F2 **antes** da UI; a Page envia conjunto completo por endpoint |
| 2 | Mandar a aba 5 pro endpoint errado (tela inerte) | teste por aba provando qual coluna mudou |
| 3 | Reimplementar Status/DeviceModels e duplicar tela viva | decisão F1 §1 — a Page aponta, não reimplementa |
| 4 | A aba 5 estar quebrada hoje e a migração "consertar" em silêncio | verificar em F2 com render real; se confirmado, declarar como mudança de comportamento no PR |
| 5 | PR estourar 300 linhas | onda dividida: este RUNBOOK (F1) é PR próprio; F2+F3 vêm depois |

## Aprovação

- **F1 PLAN:** este documento — autorizado por [W] em 2026-09-04 ("pode tocar tudo" / "autorizado").
- **F5 CUTOVER:** exige smoke real em prod com screenshot (R1) e segue sendo decisão [W].
