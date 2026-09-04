---
page: /repair/repair-settings
component: resources/js/Pages/Repair/Settings/Index.tsx
related_prototype: "n/a (herda PT-01; segue o Padrão de Tela) — repair-page.jsx se declara no cabeçalho 'importado dos blades ... settings', ou seja é porte REVERSO do Blade que esta tela substitui; ancorar aqui seria ancorar a tela nela mesma (§5 2026-08-28)"
owner: wagner
status: draft
last_validated: "2026-09-04"
parent_module: Repair
parent_capterra: memory/requisitos/Repair/CAPTERRA-FICHA.md
related_adrs: [104, 93, 358]
tier: B
charter_version: 1
---

# Page Charter — /repair/repair-settings

> **Origem:** Onda 1 do pacote de export do Repair. F1 PLAN em [`RUNBOOK-repair-settings.md`](../../../../../memory/requisitos/Repair/RUNBOOK-repair-settings.md).
> **Coexistência:** flag MWART `repair_settings_index` (default OFF → Blade legado intacto).

---

## Mission

Dar ao admin do negócio um único lugar para definir **os padrões da folha de OS** e **o que sai impresso** na folha e na etiqueta — sem duplicar os cadastros que já têm tela própria.

---

## Goals — Features (faz)

- Editar os padrões da folha: prefixo, status padrão, produto padrão (leitura), etiqueta e tipo de código de barras, 4 textos longos (problema relatado, condição, configuração, termos) e checklist padrão.
- Editar os 5 rótulos de campo personalizado — vazio significa coluna oculta na listagem de folhas.
- Editar a impressão: 3 rótulos de cliente, largura/altura da etiqueta e as 17 chaves `show_*`.
- Apontar para as telas próprias de **Status de OS** e **Modelos de dispositivo**.

---

## Non-Goals (não faz) — cada item vira Pest GUARD

- **Não grava os dois conjuntos no mesmo endpoint.** São colunas disjuntas: `store()` escreve `business.repair_settings`, `updateJobsheetSettings()` escreve `business.repair_jobsheet_settings`. Mandar o segundo para o primeiro salva sem erro e não persiste. → **UC-RSET-02**, **UC-RSET-06**
- **Não reimplementa Status nem Modelos de dispositivo.** As duas abas do Blade legado já têm Page viva; esta tela linka, não duplica.
- **Não cria tabela nem migration.** Tudo é JSON em `business`.
- **Não decide permissão por conta própria.** O gate do Controller (`superadmin` OU `repair_module` + `repair.create`) é a autoridade; a UI só reflete. → **UC-RSET-04**
- **Não grava nada em GET.** Escrita só por submit explícito.
- **Não toca o portal público** (`/repair-status`) nem a taxonomia de dispositivos — ondas próprias.

---

## Automation Anti-hooks — o que um agente NÃO pode fazer aqui

- ❌ **Não "simplificar" para um formulário só.** A separação em dois `<form>` não é estética: é o contrato dos dois endpoints. Unificar reintroduz a tela inerte.
- ❌ **Não enviar submit parcial.** Os dois métodos substituem o JSON inteiro (`$request->only` + `json_encode`); chave ausente **some do banco**. Cada form envia seu conjunto completo. → **UC-RSET-03**
- ❌ **Não ler `job_sheet_custom_field_2`/`_4` condicionando ao campo 1.** Foi o defeito do Blade legado (`repair_settings_tab.blade.php:113` e `:125`), que somado ao contrato destrutivo apagava os dois campos.
- ❌ **Não usar `<SelectItem>` cru** em opção vinda de dados (status, barcode). É `SafeSelectItem` — valor vazio derruba a árvore React inteira (§5 2026-06-29).
- ❌ **Não introduzir vocabulário automotivo** (`placa`, `vehicle`, `km`, `mecanico`, `box`, `elevador`) — `repair-shared-vocab.yml` reprova.
- ❌ **Não usar cor crua.** Tokens do DS, como as 6 Pages irmãs do módulo.

---

## Casos

Contrato executável em [`Index.casos.md`](./Index.casos.md) — UC-RSET-01 a UC-RSET-06, cada um citado por ≥1 teste em [`RepairSettingsContratoTest.php`](../../../../../Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php).

---

## Pest GUARD

Todos em `Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php`, lane **Modules Pest** (dispara em `Modules/Repair/**` e `resources/js/Pages/Repair/**`), tenant **98** (ADR 0358).

| GUARD | prova |
|---|---|
| UC-RSET-01 | `store()` grava as chaves da folha em `repair_settings` |
| UC-RSET-02 | `store()` **não** toca `repair_jobsheet_settings` |
| UC-RSET-03 | submit parcial apaga chave ausente (contrato destrutivo declarado) |
| UC-RSET-04 | sem permissão, os dois endpoints negam e nada é gravado |
| UC-RSET-05 | gravar no tenant não altera nenhum outro business (Tier 0) |
| UC-RSET-06 | `updateJobsheetSettings()` grava a etiqueta e **não** toca `repair_settings` |

---

## Pendências antes de `status: live`

1. Smoke real autenticado em prod, dark, **1280px**, com screenshot no PR (R1).
2. Flag `MWART_REPAIR_SETTINGS_INDEX` ligada por [W] após o smoke — o cutover é decisão dele.
3. Confirmar em render real se a aba de impressão do Blade legado está quebrada hoje (`$contact_custom_fields` indefinida); se estiver, declarar a mudança de comportamento.
