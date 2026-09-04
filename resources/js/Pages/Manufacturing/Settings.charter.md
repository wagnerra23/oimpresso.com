---
page: /manufacturing/v2/settings
component: resources/js/Pages/Manufacturing/Settings.tsx
owner: wagner
status: draft
parent_module: Manufacturing
related_prototype: prototipo-ui/cowork/manufacturing-producao.jsx
related_us: [US-MANU-003]
runbook: memory/requisitos/Manufacturing/RUNBOOK-settings.md
casos: resources/js/Pages/Manufacturing/Settings.casos.md
alcance:
  rota: /manufacturing/v2/settings
  rota_nome: manufacturing.settings.v2.index
  permission: null # sem permissão Spatie granular própria — mesmo gate do Blade legado (só pacote)
  menu_hook: null  # alcançada pela aba "Configurações" das telas v2
  pacote: manufacturing_module
tier: B
charter_version: 1
---

# Page Charter — Manufacturing/Settings (DRAFT · PT-01 Formulário)

> Terceira onda da família Fabricação (handoff **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"** §4.7),
> ordem de custo crescente decidida por [M] 2026-09-02. Fonte visual:
> `prototipo-ui/cowork/manufacturing-producao.jsx::MfgConfig`.
>
> **Primeira tela da família que escreve** — e escreve no endpoint que JÁ EXISTIA
> (`SettingsController@store`, sem uma linha alterada). Rota aditiva, `/manufacturing/settings`
> (Blade) intocado.

## Mission

Deixar Wagner ajustar as 3 configurações do módulo sem sair do cockpit novo — e deixar claro,
na própria tela, o que cada trava faz na operação.

## Goals — Features (faz)

- Edita as 3 chaves reais de `business.manufacturing_settings`: `ref_no_prefix` ·
  `disable_editing_ingredient_qty` · `enable_updating_product_price`
- Botão "Atualizar" só habilita quando algo mudou (R-24) — e desabilita durante o envio
- Rodapé com a versão do módulo (`System::getProperty('manufacturing_version')`)
- Cartão "Integrações" com 3 ponteiros reais: Produtos (`/products`) · Compras (`/purchases`) ·
  Fila de produção (`/manufacturing/v2/production`)
- Mesma aba do módulo que as outras telas v2

## Non-Goals — Features (NÃO faz)

- ❌ **Não traz o cartão "Permissões (simulação)"** do protótipo — é ferramenta do próprio
  protótipo (chips que fingem permissão pra testar a tela), sem equivalente real na app.
- ❌ **Não cria endpoint de escrita novo.** Posta no `POST /manufacturing/settings` que já
  existe; `store()` não foi alterado.
- ❌ **Não escreve nada além das 3 chaves.** O controller lê só elas — campo espúrio no
  payload é ignorado, por construção.
- ❌ **Não salva sozinha.** Nenhum `onChange` dispara escrita; o disparo é o clique explícito.

## Automation Anti-hooks (o que agente nenhum pode fazer aqui)

- ❌ Alterar `SettingsController@store` pra "melhorar" o retorno. Ele devolve
  `redirect()->back()`, que o Inertia já segue corretamente — mexer nisso quebra também a
  tela Blade legada, que posta no MESMO endpoint.
- ❌ Trocar o disparo explícito por auto-save no `onChange`. Escrita silenciosa em config que
  governa produção (travar quantidade de ingrediente · propagar preço) é como se muda o
  comportamento do módulo inteiro sem ninguém perceber.
- ❌ Assumir que as 3 chaves existem. `ManufacturingUtil::getSettings()` devolve `[]` quando o
  business nunca salvou — o controller normaliza com `?? ''` / `! empty()`; não remover isso.
- ❌ Usar `<input type="checkbox">` nativo (ds/no-native-checkbox) — `Checkbox` canônico, com
  `id` + `htmlFor` no label (a11y: `label-has-associated-control`).

## UX Targets

- Cabe em 1280px; os dois cartões empilham em 1 coluna abaixo de ~700px (grid `auto-fit`)
- O texto de apoio de cada trava explica a consequência na operação, não repete o rótulo
- Estado "nada mudou" é visível: botão desabilitado, não um clique que não faz nada

## Refs

- Handoff normativo *PROTÓTIPO OFICIAL - FABRICAÇÃO V1* — §4.7 (cartões 1 e 3) · §16 (chaves reais)
- RUNBOOK (F1 PLAN): `memory/requisitos/Manufacturing/RUNBOOK-settings.md`
- Charters irmãos: `Recipes.charter.md` (Onda 1) · `Report.charter.md` (Onda 2)
