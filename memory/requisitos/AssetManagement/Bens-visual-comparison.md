---
id: requisitos-asset-management-bens-visual-comparison
title: "Comparacao design x producao — Patrimonio/Bens (drawer de detalhe do bem)"
module: AssetManagement
tela: Patrimonio/Bens
owner: W
status: rascunho
# `inertia_target` + `last_updated` poem este doc sob o `visual-comparison-staleness.mjs`
# (mesma razao declarada no `Index-visual-comparison.md` deste modulo).
inertia_target: resources/js/Pages/Patrimonio/Bens.tsx
last_updated: "2026-09-30"
last_validated: "2026-09-30"
---

# Comparacao design x producao — `Patrimonio/Bens`, drawer de detalhe do bem

> **Escopo desta entrada:** so o **drawer de detalhe do bem** (leitura), entregue em
> [#8261](https://github.com/wagnerra23/oimpresso.com/pull/8261) pela decisao [W] de 2026-09-30
> (`prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/_saida-16b.md`). A LISTA de Bens
> nao foi comparada aqui.
>
> **Ancora computada** (`node scripts/design/ancora.mjs Patrimonio/Bens`):
> `prototipo-ui/cowork/Wagner/patrimonio-page.jsx`, `function BemDrawer` (aba `alocacoes`).
>
> **Frescor da fonte, medido em 2026-09-30:** `patrimonio-page.jsx` do espelho e **byte-identico**
> ao do Cowork vivo (projeto `019dcfd3-…`, `DesignSync.get_file`, `truncated:false`, 56.962 bytes,
> sha256 `23c34cb2d1f4c054…` nos dois lados). ⚠️ `patrimonio-page.css` **nao foi provado**: o
> `get_file` devolveu o arquivo inline (abaixo do piso de persistencia), e comparar exigiria
> transcrever — nao foi feito.

## Como foi medido

- **Protótipo:** espelho servido por `python -m http.server` na raiz do worktree (o shell resolve o
  DS em `../../design-system/` quando a URL contem `/prototipo-ui/cowork/`), rota `pat-bens`,
  bem `PAT-0006` (3 alocacoes, 1 revogada), aba Alocações.
- **Producao:** `https://oimpresso.com/asset/assets?bem=1` (cabecalho, "nao encontrado") e `?bem=2` (corpo, com o dado de teste), empresa 1.
- **Os dois lados:** tema dark, 1440×900, `getComputedStyle` + `getBoundingClientRect` relativos
  a borda do drawer. Producao medida so depois de o carregamento terminar e com **duas leituras
  iguais** (a primeira leitura pegou "Carregando bem…" e foi descartada).
- **Dado de teste (2026-09-30, autorizado por [W]):** a empresa 1 nao tinha nenhum bem. Foram
  criados, pela propria UI: a categoria de ativo `TESTE — medição`, o bem `TESTE — medição drawer
  (apagar)` (codigo 2026/0002, id 2, 2 un., atribuivel), a alocacao 2026/0001 de 2 un. e **duas
  devolucoes parciais** de 1 un. cada. **Ficam no banco** ate alguem apagar — nao se apaga dado de
  producao por conta propria.
- **Antes disso** so o cabecalho era medivel em producao (estado "Bem nao encontrado"); a tabela do
  cabecalho abaixo vem dessa fase.

## Cabecalho — medido nos dois lados

| Dimensao | Protótipo | Producao #8261 | Producao #8269 | Producao #8281 (atual) | Veredito |
|---|---|---|---|---|---|
| Padding | 16 / 18px | 16 / 16px | 16 / 18px | 16 / 18px | IGUAL |
| Titulo | 17px · 600 · x=19 | 16px · 600 · x=17 | 17px · x=19 | 17px · 600 · x=19 | IGUAL |
| Altura de linha do titulo | 22,1px | 24px | 25,5px | 22,1px | IGUAL |
| Subtitulo | 12,5px · x=19 | 14px · x=17 | 12,5px | 12,5px · x=19 | IGUAL |
| Altura de linha do subtitulo | 18,1px | — | 18,75px | 18,1px | IGUAL |
| Titulo ate subtitulo | 4px | 6px | 4px | 4px | IGUAL |
| Altura total do cabecalho | 76px | 83px | 81px | 77px | **DIVIDA A FECHAR** (+1px, origem nao medida) |

Os ajustes vieram de [#8269](https://github.com/wagnerra23/oimpresso.com/pull/8269) (espacamentos)
e [#8281](https://github.com/wagnerra23/oimpresso.com/pull/8281) (altura de linha). Medida final
com o deploy `a3ab9e0ac`.

## Corpo — medido nos dois lados (2026-09-30, deploy `a3ab9e0ac`, bem id 2)

| Dimensao | Protótipo | Producao | Veredito |
|---|---|---|---|
| Secao do corpo | x=13 · margem 10/12px · raio 12 · padding 14/18px · borda 1px | x=13 · margem 10/12px · raio 12 · padding 14/18px · borda 1px | IGUAL |
| Titulo da secao | 10,5px · 600 · caixa-alta · 8px abaixo | 10,5px · 600 · caixa-alta · 8px abaixo | IGUAL |
| Cartao da alocacao | x=32 · padding 10/13px · raio 8 | x=32 · padding 10/13px · raio 8 | IGUAL |
| Nome no cartao | 12,5px · 700 · x=14 · 12px do topo · altura 16px | 12,5px · 700 · x=14 · 13px do topo · altura 19px | **DIVIDA A FECHAR** (altura de linha) |
| Nome ate a linha seguinte | 5px | 4px | **DIVIDA A FECHAR** |
| Linhas 2 e 3 do cartao | 11px e 10,5px · 2px entre elas | 11px e 10,5px · 2px entre elas | IGUAL |
| Campos do Resumo | 3 col 208,3px · gap 10/16px · 10px entre linhas | 3 col 208,3px · gap 10/16px · 10px entre linhas | IGUAL |
| Rotulo/valor do campo | 10px caixa-alta, 2px abaixo / 12,5px | 10px caixa-alta, 2px abaixo / 12,5px | IGUAL |
| Abas: faixa | padding 0/18px · altura 30px | padding 0/18px · altura 38px | **DIVIDA A FECHAR** |
| Abas: cada aba | padding 0/14px · 12px · 600 · 0px entre abas | padding 8/12px · 14px · 500 · 2px entre abas | **DIVIDA A FECHAR** — vem do `SubNav` compartilhado do DS; mexer nele muda todas as telas que o usam |

**1 : N em producao, confirmado:** a alocacao 2026/0001 aparece com as **duas** devolucoes
(codigo, quantidade, data, autor e motivo de cada), selo "Devolvida" e "2 de 2 un.". A abertura pela
linha fez uma unica requisicao `GET /asset/assets?bem=2` (200), sem recarregar a lista.

**Observacao lateral, nao medida a fundo:** a alocacao foi feita com "Alocado de" `15:22` no
formulario e aparece como `18:22` na lista e no drawer (+3h). Pode ser fuso (UTC × BRT) ou o shift de
`format_date`; fica registrado para quem cuidar das datas do modulo.

## Forma que ainda diverge — DIVIDA A FECHAR

Pela regra vigente (protótipo manda na forma, sem divergencia aceita), cada item abaixo e divida,
nao escolha. Os motivos de ainda nao estarem na tela estao no charter (`Bens.charter.md`, Non-Goals):

- **Abas:** protótipo 6 (Resumo · Garantia · Alocações · Manutenção · Depreciação · Histórico);
  producao 2 (Resumo · Alocações).
- **Placar da aba Alocações** (quantidade · alocada · livre): ausente. Agregado de quantidade sobre
  o `Alocado` ainda nao auditado (residuo Tier 0 do indice).
- **Botoes** (Revogar por alocacao · Alocar recurso · Enviar pra manutencao · Editar bem): ausentes.
  Escrita, de outras threads (17/18).
- **Breadcrumb e selo de garantia** acima do titulo: ausentes.
- **Descricao** como secao propria no Resumo (no protótipo e uma `DrawerSection` separada, junto de
  "Onde este bem aparece"): na producao fica dentro da secao Identificacao.
- **Cores** nao foram comparadas nesta rodada.

## Falta

- Provar o frescor de `patrimonio-page.css` pela rota de bundle (sem transcrever).
- Apagar o dado de teste da empresa 1 (categoria, bem, alocacao e as 2 devolucoes) — decisao [W].
