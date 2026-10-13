---
sessao: "A1"
titulo: "ALVO cliente--index — saída da thread"
autor: "[CL]"
data: 2026-10-13
base: origin/main a47deb137d
thread: A1-alvos.md
veredito: "entregue — 1 alvo medido (7 seções, 0 ausentes), byte-idêntico na re-medição; secao-check conforme."
---
# _saida-A1 · Alvo da lista de Clientes

## Premissa conferida antes de medir
- Nenhum `cliente--index.*` em `governance/design/targets/` no `main`: o único alvo de Cliente lá é
  `cliente--grupos--index` (thread A2).
- A rota existe no protótipo: `clientes` = `Cliente/Index` (`_saida-00`, `window.CliRotas`, `clientes-page.jsx`).
- A lista pula o esqueleto de 520 ms quando abre na rota de boot (fix da `_saida-00`). O `alvo.mjs` grava a rota
  no `localStorage` antes de carregar, então a medida abre na rota de boot.

## O que saiu
| tela | slug | rota no protótipo | seções |
|---|---|---|---|
| Clientes (lista) | `cliente--index` | `clientes` → `clientes-page.jsx` | 7 · cabecalho · abas · kpis · toolbar · tabela · linha · paginacao |

Arquivos: `governance/design/targets/cliente--index.{secoes,alvo}.json`. O `.alvo.json` é saída do `alvo.mjs`, não
editado à mão. Os seletores vieram do `alvo:mapa --rota clientes` e estão em `_mapa` no `.secoes.json`.

## Como foi medido
- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`) em `http://127.0.0.1:5561/`.
- **Sanidade antes:** `alvo.mjs --selftest --browser` → `27/27 ok`.
- `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5561/ --tela cliente--index --rota clientes --secoes
  governance/design/targets/cliente--index.secoes.json --quieto-ms 2000` → `seções medidas: 7 · ausentes no DOM: 0`,
  rc=0.
- Viewport 1280×900, tema escuro (`base.theme = dark`), `nos_totais` 1312.
- **Não é o esqueleto:** a `base.assinatura` traz a lista real ("15 cadastrados · 13 ativos", os KPIs, as colunas), e a
  seção `linha` tem 11 filhos (células de dado). A linha de esqueleto do protótipo tem 1 célula (`colSpan={11}`).
- **Determinismo:** re-medida com `--saida` num arquivo temporário; `cmp` byte-idêntico. sha256 (16 primeiros)
  `e1dd2e6471f97792`.
- `node scripts/qa/secao-check.mjs --tela cliente--index --servir-espelho`: **conforme**, 7 seções, rc=0.

## `tableRow`
A ficha pede `tableRow` em `__DD_ROLES` nos dois lados. Isso é do `design-diff`, não do `alvo.mjs` (no alvo,
`base.roles.tabela` sai `null`). A linha da tabela está coberta aqui pela seção `linha` (1ª `tbody > tr.cli-row`),
como na `_saida-A2` e na `_saida-A1` do Crm. A thread 02 (DIVERGE do título + linha da tabela) é quem roda o
`design-diff` nos dois lados.

## Pendente (não inventado)
1. A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: está fora do prefixo.
2. O drawer do cliente (760) fica fora do alvo: só existe depois de clicar numa linha. Se o contrato da 01 quiser o
   drawer, é outro alvo com `--clicar`.
3. O alvo mede o protótipo. O que o contrato faz com cada seção (o que tem contraparte na Page) é da thread 01.

## Placar
entregue 1 de 1 alvo · 7 de 7 seções medidas · ausentes 0.
