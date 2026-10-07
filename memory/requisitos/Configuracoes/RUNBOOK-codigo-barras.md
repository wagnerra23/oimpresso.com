---
slug: configuracoes-runbook-codigo-barras
title: "Configurações — Runbook da tela Código de barras"
type: runbook
module: Configuracoes
tela: Configuracoes/CodigoBarras/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Código de barras (`/barcodes`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/04`, tela 2 de 3
> **Fonte de design:** `prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx` → `Barras()` (rota `cfg-barras`).
> **Decisão D1 ([W] 2026-10-06):** Configurações com abas; cada aba mantém URL e permissão próprias.

## Estado final esperado

`GET /barcodes` (`BarcodeController::index`) responde Inertia `Configuracoes/CodigoBarras/Index` **quando a flag
`useV2ConfiguracoesCodigoBarras` está ligada** para o negócio; desligada, segue a Blade `barcode/index`. Cadastrar e
editar abrem um drawer; excluir e tornar padrão são ações da linha. Tudo grava pelos endpoints de sempre.

## 1. Objetivo

Trocar a Blade (DataTable + páginas `create`/`edit`) pela aba React, sem mudar o que é gravado nem a permissão.

## 2. Pré-condições

- Permissão: `barcode_settings.access` (todas as ações).
- Tabela `barcodes`: `business_id` **nullable** — `NULL` são os modelos globais que o `LabelsController` oferece a todos
  os negócios. A lista mostra só os do negócio; editar/excluir/tornar padrão só alcançam os do negócio desde o #8924.

## 3. Passo-a-passo

1. **#8924:** fecha o cross-tenant de `update`/`destroy`/`setDefault` (Tier 0).
2. **F1/F2 (este PR):** RUNBOOK + [`codigo-barras-parity.md`](./codigo-barras-parity.md) + `CodigoBarrasBaselineTest`.
3. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). Page + charter + casos.
4. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4. Tokens CSS

Só tokens do DS. Sem cor crua.

## 5. Estados visuais

Lista com a padrão marcada · vazio · busca sem resultado · drawer novo/editar (folha × rolo contínuo) · confirmação de
exclusão · padrão sem "Excluir" · aviso da última ação.

## 6. Responsividade

Tabela rola na horizontal abaixo de 768px; drawer ocupa a largura em telas estreitas.

## 7. Atalhos

`/` foca a busca · `n` abre "Nova configuração".

## 8. Component contract

Seções `data-contract`: `page-header`, `toolbar`, `etiquetas-table`, `vazio`, `etiqueta-form`, `confirm-excluir`.

## 9. DoD checklist

- [x] RUNBOOK + paridade
- [x] Pest baseline (tenant 98 × 99) — isolamento no #8924, o resto neste PR
- [ ] Ramo Inertia atrás da flag + Page + charter + casos (F3)
- [ ] Smoke biz=1 (F4) · cutover e remoção das Blades `barcode/*` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- **Unidade:** o protótipo pede tudo em **mm** e diz que converte na impressão. O banco e a impressão do legado
  (`LabelsController`) são em **polegada**. Converter muda o que é gravado e impresso — a tela nova segue em polegada até
  decisão [W]. Não há conversão inventada.
- "Imprimir prova" do protótipo: **não existe endpoint**. Fica fora.
- Rolo contínuo: o `store`/`update` gravam `is_continuous = 1` e forçam `stickers_in_one_sheet = 28`; o `update` também
  zera `paper_height`. Na folha, `stickers_in_one_sheet` e `paper_height` vêm do formulário.
- Cadastrar com "padrão" desmarca o padrão anterior do negócio. A padrão não pode ser excluída (`destroy` recusa).
- O protótipo mostra "Excluir" também na padrão; a tela nova esconde, como a Blade (botão desabilitado).
- Inertia v3 manda `X-Requested-With`: sem `! $request->inertia()` o ramo DataTable engole a visita.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
