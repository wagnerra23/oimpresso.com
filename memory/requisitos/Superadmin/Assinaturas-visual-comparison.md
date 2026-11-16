---
id: requisitos-superadmin-assinaturas-visual-comparison
title: "Comparação design × produção — superadmin/Assinaturas/Index"
module: Superadmin
tela: superadmin/Assinaturas/Index
owner: W
status: rascunho
last_validated: "2026-11-16"
---

# Comparação design × produção — `superadmin/Assinaturas/Index` (Assinaturas)

> **Âncora computada** (`node scripts/design/ancora.mjs superadmin/Assinaturas --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/Wagner/superadmin-page.jsx`, declarada em `related_prototype` do charter.
> No shell do protótipo a vista é a rota `sa-assinaturas` (`app.jsx`; override em
> `governance/design/targets/roles/`).
>
> Medido em **2026-11-16** pela sonda canônica (`design-diff.mjs --probe`), a mesma nos dois lados,
> e comparado por `design-diff.mjs --compare prod.json design.json --check --contrato governance/design/contracts/superadmin-assinaturas.contract.json`.
> Snapshots e veredito em [`governance/design/targets/medidas/superadmin--Assinaturas--Index/`](../../../governance/design/targets/medidas/superadmin--Assinaturas--Index/) (`prod.json` sha `f7d32eb9162e`,
> `design.json` sha `5b216008b197`).

## Como foi medido

- **Produção:** `https://oimpresso.com/superadmin/superadmin-subscription`, sessão de superadmin já aberta no Chrome do [W] (só leitura;
  nenhum cookie ou senha manuseado). A medida antiga desta pasta era a **página 403** (`staging` sem
  usuário superadmin); esta é a tela.
- **Protótipo:** espelho `prototipo-ui/cowork/Wagner/oimpresso.com.html` servido localmente, no **mesmo Chrome**.
- **Tema:** claro nos dois lados — é o que a preferência `oi.theme` do navegador do [W] usa hoje. A
  produção não troca de tema pela classe `dark` no `<html>` (medido), então forçar escuro mediria claro × escuro.
- **Transporte:** o JSON de produção saiu da página por `postMessage` para uma ponte local que gravou em
  disco; o sha256 calculado na página bate com o do arquivo. Nada foi transcrito.
- **Dado pessoal e valor:** o texto visível de produção foi redigido **antes** de sair da página — a
  `assinatura` guarda só a copy do contrato que casou; dígitos, e-mails e valores em `label`/`texto`/`rotulo`
  viraram `#`/`[email]`/`[redigido]`.

## D0 — identidade da vista: **OK** (13 × 13 copies do contrato)

Os dois lados são a mesma tela pelo contrato `governance/design/contracts/superadmin-assinaturas.contract.json`.

## Dimensões medidas

| Dim | Campo | Produção | Protótipo | Veredito |
|---|---|---|---|---|
| D2 | layout | ok | ok | IGUAL |
| D4 | título font-size | 24px | 22px | DÍVIDA A FECHAR |
| D6 | cor | ok | ok | IGUAL |
| D8 | kpi align | ok | ok | IGUAL |
| D2 | nº de colunas | 7 | 8 | DÍVIDA A FECHAR |
| D2 | table-layout | auto | fixed | DÍVIDA A FECHAR |
| D8 | col0.align | left | center | DÍVIDA A FECHAR |
| D2 | col0.blocos de texto | 2 | 0 | DADO (não é forma) |
| D4 | col1.mono | sem mono | mono | DÍVIDA A FECHAR |
| D2 | col2.blocos de texto | 1 | 2 | DADO (não é forma) |
| D6 | col2.pílula | presente | ausente | A CLASSIFICAR (fonte) |
| D2 | col3.blocos de texto | 2 | 1 | DADO (não é forma) |
| D6 | col3.pílula | ausente | presente | DÍVIDA A FECHAR |
| D4 | col4.mono | sem mono | mono | DÍVIDA A FECHAR |
| D6 | col4.cor | herda a da linha | cor própria | DÍVIDA A FECHAR |
| D8 | col4.align | right | left | DÍVIDA A FECHAR |
| D2 | col4.blocos de texto | 1 | 2 | DADO (não é forma) |
| D4 | col5.mono | sem mono | mono | DÍVIDA A FECHAR |
| D8 | col5.align | left | right | DÍVIDA A FECHAR |
| D2 | col5.blocos de texto | 2 | 1 | DADO (não é forma) |
| D4 | col6.mono | sem mono | mono | DÍVIDA A FECHAR |
| D6 | col6.cor | herda a da linha | cor própria | DÍVIDA A FECHAR |
| D8 | col6.alcancavel | BUTTON | sem elemento clicavel | A CLASSIFICAR (fonte) |
| D8 | col6.align | right | left | DÍVIDA A FECHAR |
| D2 | col6.blocos de texto | 1 | 2 | DADO (não é forma) |

- **Cor do título (lida do JSON, a linha D6 agregada não a acusa):** produção `oklch(0.137 0.036 258.5)` × protótipo `oklch(0.22 0.01 80)`. Mesmo tema (claro) nos dois lados. Entra como dívida junto do tamanho do título.

Vereditos pela regra do [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
(eixo FORMA: o protótipo manda — UI-0029): **DÍVIDA A FECHAR** = a produção converge;
**DADO (não é forma)** = nº de blocos de texto por célula depende do registro, não do layout;
**A CLASSIFICAR (fonte)** = o instrumento acusa diferença de origem do conteúdo, a decidir por humano.

## O que esta medida NÃO cobre (declarado, não suposto)

- **Regiões de contrato (D9):** `superadmin.assinaturas.filtros`, `superadmin.assinaturas.kpis`, `superadmin.assinaturas.paginacao`, `superadmin.assinaturas.tabela` existem na produção; o protótipo não carrega `data-contract`, então D9 sai SEM-DADO por construção — não é divergência.
- **KPI e botão primário:** os papéis padrão da sonda não casaram (`kpi` = 0 na produção × 0 no protótipo; `primary` = não casou na produção). Medir exige override de papel em `governance/design/targets/roles/superadmin--Assinaturas--Index.json`.
- **Shell (sidebar, topo):** 28 campos `SHELL a classificar` — iguais nas 6 telas do Superadmin, são do AppShell e não desta tela.
- **Saúde do lado produção:** o `design-diff` acusa `tokens.prod NÃO MEDI` porque procura `--accent`… em `:root`. Na produção eles vivem em `.cockpit` (medido: `--accent`, `--pos`, `--neg`, `--warn`, `--text-dim`, `--border` resolvem lá; `--sunken` não existe). Nenhuma folha de estilo veio vazia. É limite do instrumento, não render quebrado.
- **D1 (rede):** medido em Negócios — digitar na busca dispara uma XHR `/superadmin/business?q=…`, sem recarregar o documento.
- **D3 (ícones) e D5 (rodapé/somatórios):** não medidos.

## Dívida desta tela

- D4 · título font-size: produção `24px` → protótipo `22px`.
- D2 · nº de colunas: produção `7` → protótipo `8`.
- D2 · table-layout: produção `auto` → protótipo `fixed`.
- D8 · col0.align: produção `left` → protótipo `center`.
- D4 · col1.mono: produção `sem mono` → protótipo `mono`.
- D6 · col3.pílula: produção `ausente` → protótipo `presente`.
- D4 · col4.mono: produção `sem mono` → protótipo `mono`.
- D6 · col4.cor: produção `herda a da linha` → protótipo `cor própria`.
- D8 · col4.align: produção `right` → protótipo `left`.
- D4 · col5.mono: produção `sem mono` → protótipo `mono`.
- D8 · col5.align: produção `left` → protótipo `right`.
- D4 · col6.mono: produção `sem mono` → protótipo `mono`.
- D6 · col6.cor: produção `herda a da linha` → protótipo `cor própria`.
- D8 · col6.align: produção `right` → protótipo `left`.
- Cor do título (ver acima).
