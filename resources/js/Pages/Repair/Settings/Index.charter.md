---
page: /repair/repair-settings
component: resources/js/Pages/Repair/Settings/Index.tsx
related_prototype: "n/a (herda PT-02 Form; segue o Padrão de Tela) — repair-page.jsx se declara no cabeçalho 'importado dos blades ... settings', ou seja é porte REVERSO do Blade que esta tela substitui; ancorar aqui seria ancorar a tela nela mesma (§5 2026-08-28)"
owner: wagner
status: draft
last_validated: "2026-09-05"
parent_module: Repair
related_us: [US-REPA-003]
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

Contrato executável em [`Index.casos.md`](./Index.casos.md) — UC-RSET-01 a UC-RSET-08, cada um citado por ≥1 teste em [`RepairSettingsContratoTest.php`](../../../../../Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php). Veredito por UC vem do manifesto (`scripts/casos-test-results.json`, gate G-7), não da prosa: em 2026-09-05 são **6 `pass` e 2 `skip`** (os dois de coexistência de flag — razão na tabela de pendências).

---

## Pest GUARD

Todos em `Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php`, tenant **98** (ADR 0358), lane **Verticais · Pest (MySQL)** (`verticais-pest.yml`, allowlist).

⚠️ **Corrigido em 2026-09-05 (F4 QA).** Esta linha dizia lane *Modules Pest*, e isso descrevia um gate que não existia: aquela lane roda em **sqlite**, onde os 8 UCs pulam no primeiro guard do `beforeEach` (o schema de `business` exige MySQL) e o job sai `success` assim mesmo — falso-verde (LC-13), verificado no run `33938642020`. A lane com MySQL real (`verticais-pest`) roda uma allowlist explícita e este arquivo não estava nela. O contrato **não era exercido por lane nenhuma** até o PR desta medição, que o incluiu na allowlist.

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

1. Smoke real autenticado, dark, **1280px**, com screenshot no PR (R1). **Segue aberta em 2026-09-05, com a razão medida:** o staging responde 200 mas **não tem assets buildados** (`public/build/manifest.json` ausente) e portanto não renderiza Inertia; o container não tem `node`/`npm` no PATH; e seu checkout está **432 commits atrás** (`c1abe9548`) com trabalho não-commitado de outra sessão. Buildar ali retrataria código de agosto + os arquivos desta onda — não seria a tela nem de produção nem do `main`.
2. Flag `MWART_REPAIR_SETTINGS_INDEX` ligada por [W] após o smoke — o cutover é decisão dele.
3. ~~Confirmar em render real se a aba de impressão do Blade legado está quebrada.~~ **RESOLVIDO em 2026-09-05 — a premissa era falsa.** O partial renderiza (9143 bytes, checkbox presente, zero warning sobre a variável): ele define `$contact_custom_fields` e `$custom_labels` na **própria linha 4**, a partir de `$jobsheet_pdf_settings`, que o `compact()` passa. A migração **não** conserta erro vivo aqui, e não há mudança de comportamento a declarar por este motivo. Detalhe e provas em [`Index.casos.md`](./Index.casos.md) §RESOLVIDO.
4. **Novo, descoberto no F4 QA:** semear `system.repair_version` no `pest-mysql-setup` para destravar UC-RSET-07/08 no CI. Sem essa linha, `ModuleUtil::getTaxonomyData` faz `exit` dentro de `index()` — com o guard atual isso vira skip visível; sem ele, matava a suíte inteira sem output. É PR próprio: aquele seed é compartilhado por 16 lanes.
5. **Novo, descoberto no F4 QA — header no componente antigo.** O `.tsx` importa `@/Components/shared/PageHeader`; o canon desde [ADR 0189](../../../../../memory/decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) / [0190](../../../../../memory/decisions/0190-primary-button-roxo-universal-295.md) é `@/Components/PageHeader`, e o gate `PageHeader · ratchet` (advisory) marca esta tela como **adotante nova do header antigo**. A tela seguiu o módulo — **todas** as 6 Pages irmãs do Repair usam o antigo (medido: `Dashboard/Index`, `DeviceModels/{Index,Create,Edit}`, `Repair/Index`, `JobSheet/{Create,Edit,AddParts}`) —, e o próprio charter mandava imitá-las. **Não corrigido aqui, de propósito:** a API difere (`leading`/`subtitle` no canon × `icon`/`description` no antigo), logo a troca **muda o render**, e a pendência 1 acima diz que hoje não há como ver o resultado. Trocar o header às cegas é a classe LC-06 (declarar UI sem medir). Fica como dívida datada, junto com o smoke.
