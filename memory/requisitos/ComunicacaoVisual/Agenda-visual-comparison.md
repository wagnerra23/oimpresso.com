---
id: requisitos-comunicacaovisual-agenda-visual-comparison
title: "Comparação design × produção — ComunicacaoVisual/Agenda"
module: ComunicacaoVisual
tela: ComunicacaoVisual/Agenda
owner: W
status: rascunho
last_validated: "2026-11-11"
---

# Comparação design × produção — `ComunicacaoVisual/Agenda`

> **Âncora computada** (`node scripts/design/ancora.mjs ComunicacaoVisual/Agenda`):
> `related_prototype` = `prototipo-ui/cowork/Wagner/producao-cv-campo.jsx` (último commit
> `b485f12263`, 2026-11-05). É o widget "Agenda de instalação" da tela **Produção** do
> Modules/Producao, não uma tela de agenda (auditoria 2026-11-05, A3).
>
> Primeiro registro desta tela. Lote 4 da auditoria do módulo (achados A7 e A9).

## Como foi medido (2026-11-11)

Mesmo arranjo do [`Index-visual-comparison.md`](Index-visual-comparison.md): produção de **biz=1**
no deploy `93cc0a40cf`, iframe de 1280×900, tema claro nos dois lados, sonda `design-diff --probe`
sha256 `358fd0f0…ebe20`.

- Produção: `/comunicacao-visual/agenda`. Snapshot `prod-agenda.json` sha256 `fd4341b1f7a0b244…`.
- Protótipo: `route=pcv-instalacao` (a vista "Instalação" da `ProducaoCvPage`, que renderiza o
  `PcvCampo.Instalacao` do arquivo âncora). Snapshot `design-agenda.json` sha256 `c7a86d0de510daed…`.

## Veredito: **comparação não vale como prova**

O `design-diff --compare` saiu rc=0 com D2/D4/D6/D8 "IGUAL", e esse resultado **não prova
nada**, por dois motivos medidos:

1. **D0 sem prova.** Não há contrato de tela para a Agenda em `governance/design/contracts/`,
   então nada confirma que os dois lados são a mesma tela.
2. **São telas diferentes.** O protótipo abre com `h1` "Produção", abas Painel/Quadro/Linha do
   tempo/…/Instalação, e a instalação aparece como **lista "próximos dias"** (data, cliente, OS,
   equipe, veículo). A produção é uma **grade semana × pessoa** com formulário ao lado. Nenhuma
   das regiões `data-contract` da produção (`cabecalho`, `semana`, `agenda`, `agendar`) existe no
   protótipo (D9: só um lado).

O que se pode afirmar: o título tem a mesma tipografia nos dois (22px/600, mesma cor). O resto é
de outra tela.

## Smoke funcional (DOM, biz=1)

| Medida | Valor |
|---|---|
| Regiões | `cabecalho`, `semana`, `agenda`, `agendar` presentes |
| Grade | 10 pessoas (linhas) × 8 colunas (equipe + 7 dias) |
| Formulário de agendar | visível (390px), alinhado ao topo da grade |
| Largura do card da grade | **543px**, com a tabela de **880px** dentro: rola na horizontal |
| Rolagem horizontal da página | não |

## Achados (registrados, não consertados aqui)

1. **A 1280px a semana não cabe.** O card da grade fica com 543px (a coluna do formulário
   ocupa 390px) e a tabela tem largura mínima de 880px (`Agenda.tsx:154`), então metade da semana
   fica escondida atrás da rolagem do card.
2. **Sem âncora de design própria.** A grade semana × equipe não tem protótipo; a âncora declarada
   é um widget de outra tela. Decisão [W] (auditoria A3): desenhar a Agenda no protótipo ou
   declarar `n/a`.
3. **Sem contrato de tela** em `governance/design/contracts/`, o que impede a D0.

## Não medido

- Tema escuro (a conta de biz=1 usa o claro; ver o doc do Index).
- Agendar e o alerta "Sem janela": escrita em produção, não executada. Coberto em Feature e no E2E do lote 4.
