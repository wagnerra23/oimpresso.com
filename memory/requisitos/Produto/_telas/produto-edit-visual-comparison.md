---
id: requisitos-produto-telas-produto-edit-visual-comparison
slug: inventory-produto-edit-visual-comparison
title: "Produto — Comparativo visual da tela Editar"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
# canon_reference: o path abaixo foi removido em 2026-05-20, 1070e3759b7
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx _(removido em 2026-05-20, 1070e3759b7)_
blade_source: resources/views/product/edit.blade.php
inertia_target: resources/js/Pages/Produto/Edit.tsx
approved_by: pending_wagner_screenshot_approval
pattern_reuse: true
# blueprint_cowork: o path abaixo foi removido em 2026-05-20, 1070e3759b7
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/ _(removido em 2026-05-20, 1070e3759b7)_
---

# Comparativo visual — Editar produto (`/products/{id}/edit`)

> Form full-width derivada de Produto/Create + Produto/Index (ADR 0149)

## Resumo executivo

Blade legacy tem mesma estrutura `product.create` com defaultValues. MWART reusa estrutura Create.tsx 100% (mesma família visual AppShellV2, mesmos Cards, mesmas seções), inicializando `useForm` com `product` props recebidos do controller. Header diferencia: "Editar produto · {nome} · SKU mono".

## Tabela comparativa abreviada

| Aspecto | Blade | MWART (pattern reuse Create) |
|---|---|---|
| Estrutura | igual create.blade.php com `old()` | igual Create.tsx com `useForm({...product})` |
| Header diferencial | título "Edit Product" | "Editar produto · {nome}" + SKU mono small |
| Botões | "Update" | "Salvar alterações" + "Cancelar" |
| Type select | disabled (não muda type após criar) | mesmo — disabled |

## Pattern reuse

ADR 0149: Edit deriva de Create (form-secundário mesma entidade) — sem divergência.

## Gaps catalogados

| Gap | Plano |
|---|---|
| Variation editor (variable) | Wave 3 |
| Opening stock link | Wave 3 |

## Aprovação

⏳ Pendente Wagner screenshot approval.

## Rodada de medição — 2026-09-21 (PARCIAL: lado design medido, lado prod NÃO MEDIDO)

> ⚠️ **ERRATA 2026-09-21, do próprio autor — o achado 4 abaixo CADUCOU, e antes do merge
> que o publicou.** Ele afirma que `Produto/Edit` e `Create` **não estão** em `anchored` no
> `application-report.json`. Isso era verdade na base em que medi (18/09), e **deixou de ser**
> quando o #7579 regenerou o report: `generatedAt` **2026-09-21T10:43:26Z**, commitado
> **11:09:44Z** — o PR que publicou o achado (#7584) mergeou **11:23:15Z**, 14 minutos depois.
> Medido agora: `design-diff-lote --dry --tela Produto/Edit` **seleciona** a tela, fonte
> `produto-blade-forms.jsx`, frescor verificado 2026-09-21.
>
> **Causa do erro:** rebasear a branch atualiza o código, **não as conclusões** — eu não
> re-rodei a medição no instante de publicar (§5 2026-09-05, emenda).
>
> **O que do achado 4 PERMANECE verdadeiro, e foi confirmado pelo próprio evento:**
> (a) estar em `anchored` **não** é estar medível — o lote reporta **`executáveis: 0/1`** para
> as duas telas (rota parametrizada `{id}` e/ou sem rota derivável no shell), e **1/1 sem
> contrato D0**; (b) quem reescreveu o report foi um **handoff de bundle** (#7579), não a
> correção de charter — exatamente o mecanismo que o achado descreve.
>
> O texto do achado 4 fica abaixo **como foi publicado**; esta errata é que vale.

> Registro do que foi **medido por sonda**, e do que **não foi**. Nada aqui foi concluído por
> screenshot ou por leitura de código apresentada como equivalência (LC-06).
> O conteúdo de 2026-05-15 acima fica **intacto** — é o retrato daquele dia.

### Como reproduzir

```
node scripts/design/protocolo.config.mjs --selftest
node scripts/design/ancora.mjs Produto/Edit
node scripts/design/design-diff.mjs --probe          # sonda canônica, idêntica nos 2 lados
node scripts/design/design-diff.mjs --canario <snap.json>
```

A âncora vigente **não é restateada aqui** — quem responde é o `ancora.mjs` (o
`canon_reference`/`blueprint_cowork` do frontmatter apontam pro diretório removido em
2026-05-20, `1070e3759b7`, e seguem como lápide, não como ponteiro vivo).

### Vereditos por dimensão

| Dimensão | Veredito | Base da medição |
|---|---|---|
| Sonda válida (canário) | **OK — a régua morde** | `--canario` acusou `title.fontPx 11 → 22` |
| Tema | **IGUAL** (`dark` nos dois lados) | `theme` do snapshot; tema do [W] |
| D0 identidade da view | **NÃO MEDI** | sem `governance/design/contracts/produto-edit.contract.json`; a âncora serve **4 views** (`FormProduto`, `Historico`, `Precos`, `Massa`) |
| D2/D4/D6/D8 (prod × design) | **NÃO MEDI** | só o lado design foi capturado — ver §Bloqueio |
| D1 rede (partial-reload) | **NÃO MEDI** | depende do lado vivo |
| KPI / tabela / filtros | **NÃO SE APLICA** | a sonda é orientada a lista; esta tela é formulário |

Lado design capturado (modo **edit**, não create): `title` 11px/700 · `primary`
`oklch(0.7 0.15 295)` · 4 seções — *Editar produto · Configurações · Fiscal · Impostos e preços*.

### Achados medidos (obstáculos estruturais, não opinião)

1. **A tela React não é alcançável por URL direta.** `ProductController@edit` só devolve
   `Inertia::render('Produto/Edit')` sob `if (request()->header('X-Inertia'))`; a visita direta cai
   na **Blade legada**. Medido no staging (checkout em dia): sem o header, sem `data-page`, DOM
   AdminLTE; com o header, **409** (asset version), porque a SPA só inicializa em `/`.
   O mesmo gate existe em `Index`, `Create`, `Show` e `SellingPrices` do mesmo controller.
   **Consequência:** smoke, screenshot ou lote que navegue por URL mede a Blade — e o veredito
   sai plausível, que é o pior tipo de erro.
2. **O protótipo não alcança o modo edit pela navegação.** `key={route + ":" + tick}`
   (`app.jsx:951`) remonta ao trocar de rota e `useState(view === "form" ? null : ...)` zera o
   alvo; clicar *Editar* na lista abre **"Novo produto"**. Medido instrumentando o componente:
   `FormProduto` recebeu `produto: null` nas 2 renderizações. Para medir o modo edit foi preciso
   montar o componente com o produto.
3. **O espelho não renderiza sozinho.** 3 deps do DS respondiam 404 (`colors_and_type.css`,
   `cockpit_domains.css`, `_ds_bundle.js`) — as mesmas que o `--preview-ds` repunha antes de ser
   aposentado (#7224). Sem elas o próprio protótipo imprime *"A grade do DS não carregou"*, e
   qualquer medição de cor/tipografia seria contra um render degradado.
4. **Esta tela e a `Create` estão fora da fila de medição automática.** Nenhuma casa em
   `anchored` no `application-report.json`, que é o insumo de seleção do `design-diff-lote`.
   O report foi gerado em 2026-09-18T15:59Z e a âncora foi corrigida no #7545 (commit
   `4e04cfdb92`, 17:12Z) — e ele só é reescrito por `bundle-transaction.mjs`, isto é, na
   **aplicação de um bundle**. Corrigir o charter não põe a tela na fila.

### Divergências candidatas — HIPÓTESE, não achado

Vieram de **leitura de código com varredura contada**, não da sonda nos dois lados. Não entram
na tabela §Gaps catalogados enquanto não forem medidas no DOM da tela viva.

```
for t in ncm cest cfop origem subUnits locs Bipar; do
  printf "%-10s tsx=%s proto=%s
" "$t"     "$(grep -ciE "$t" resources/js/Pages/Produto/Edit.tsx)"     "$(grep -ciE "$t" prototipo-ui/cowork/Wagner/produto-blade-forms.jsx)"; done
```

| Item | `Edit.tsx` | protótipo | Observação |
|---|---|---|---|
| NCM / CEST / CFOP / Origem | 0 / 0 / 0 / 0 | 7 / 4 / 5 / 3 | seção **Fiscal** inteira; num ERP com NfeBrasil, pesa |
| Locais do negócio | 0 | 7 | — |
| Sub-unidades | 0 | 1 | — |
| Bipar (código de barras) | 0 | 1 | — |
| Ações de rodapé | 2 (`Cancelar`, `Salvar alterações`) | 5 (inclui 3 *Salvar e adicionar…*) | — |
| Agrupamento | 3 cards (Identificação · Preço & Imposto · Estoque) | 4 seções | — |

Descartado componente importado: os `import` do `Edit.tsx` são só primitivos de UI
(`AppShellV2`, `Input`, `Button`, `Label`, `Textarea`, `Card`, `Select`) — nenhum traz esses
campos. O `Edit.casos.md` tem **0** menções fiscais, e §Gaps catalogados lista apenas
*Variation editor* e *Opening stock link*: o bloco Fiscal **não está catalogado em lugar nenhum**.

### Bloqueio — por que o lado prod ficou NÃO MEDIDO

Duas tentativas de ler `staging.oimpresso.com` pelo browser foram recusadas pelo classificador
de permissões (`Credential Materialization` no redirect com token; `Production Reads` no proxy
com login server-side). Não houve contorno. Para fechar, o caminho é autorização [W] de leitura
do staging pelo browser — entrando por `/` e navegando client-side até a tela, para que o
request carregue `X-Inertia` e o `.tsx` chegue ao DOM.

### Por que o `date:` do frontmatter NÃO foi bumpado

`visual-comparison-staleness --json` reporta este doc com `gapDays: 45` (`doorDate` 2026-05-15
declarado × `codeDate` 2026-06-29). Bumpar a data zeraria esse sinal e faria o instrumento
dizer "em dia" enquanto **metade das dimensões segue NÃO MEDIDA**. O gap é real e fica visível
de propósito; quem fechar a comparação atualiza a data com o veredito completo.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto. |
| 2026-09-21 | [C] | Rodada de medição parcial: lado design medido por sonda, lado prod NÃO MEDIDO (bloqueio de permissão); 4 achados estruturais; divergências candidatas do bloco Fiscal. |
| 2026-09-21 | [C] | ERRATA no achado 4: a tela passou a constar em `anchored` (report regenerado pelo #7579 às 10:43Z, 14min antes do merge do #7584); permanece `executáveis: 0/1` e sem contrato D0. |
