---
sessao: "_saida-A1"
thread: "A1 · ALVO produto--unificado--index"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/alvo-produto-unificado-index)"
base_lida: wagnerra23/oimpresso.com@main 8fe939db25
---
# _saida-A1

## Entregue: o alvo, medido pela máquina
`governance/design/targets/produto--unificado--index.{secoes,alvo}.json`, gravado por
`scripts/design-sync/alvo.mjs --alvo` contra o espelho `prototipo-ui/cowork/Wagner/`, servido por
`servirEstatico` (`scripts/design/render-proto-baseline.mjs`) em `http://127.0.0.1:5581/`. Tema dark
(default do espelho, conferido em `base.theme`), viewport 1280×900, browser limpo. Nada foi escrito à
mão no `.alvo.json`. O `.secoes.json` leva seletores colhidos com `alvo:mapa`, registrados em `_mapa`.

**Rota:** `produtos` (`app.jsx:811` → `window.ProdListPage`, `produtos-page.jsx`). A `_saida-00`
fixou que `produtos` é o porte reverso do `Produto/Unificado/Index` (`/products/unificado`), e o
charter do Unificado aponta o mesmo arquivo como `bundle_source` (`ancora.mjs Produto/Unificado/Index`).
A 00 está entregue, então a medida é posterior à mudança do protótipo, como a ficha pede.

| seção | seletor |
|---|---|
| `titulo` | `.main-body > .pd-page > .pd-head > .pd-head-l` |
| `acoes` | `.main-body > .pd-page > .pd-head > .pd-head-r` |
| `abas` | `.main-body > .pd-page > .pd-head > nav.ds-tabbar` |
| `kpis` | `.main-body > .pd-page > .pd-kpis` |
| `filtros` | `.main-body > .pd-page > .pd-fbar` |
| `tabela` | `.main-body > .pd-page > .pd-grid-card > .pd-scroll` |
| `rodape` | `.main-body > .pd-page > .pd-grid-card > .pd-rodape` |

Comando: `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5581/ --tela produto--unificado--index
--rota produtos --secoes governance/design/targets/produto--unificado--index.secoes.json --quieto-ms 2000`.
Saída: `seções medidas: 7 · ausentes no DOM: 0`.

## Lista real, não esqueleto
O `alvo:mapa` contou **25** `pd-tr` (linhas de produto) e 25 `pd-prod-n`, com 4 cartões de KPI e a
barra de filtros com 10 filhos. A `base.assinatura` traz o conteúdo da lista ("Produtos 36
cadastrados", contagens das abas, "36 registros", a 1ª linha `1001 Cartão de …`). `nos_totais` 1475.

## Antes e depois de medir
- **Sanidade:** `alvo.mjs --selftest --browser` → `27/27 ok`.
- **Estável:** o `alvo.mjs` espera `__oiLazyDone` e duas leituras iguais de `querySelectorAll('*').length`,
  mais `--quieto-ms 2000`.
- **Determinismo:** re-medido com `--saida` num arquivo temporário; `cmp` byte-idêntico, rc=0.
- **Hash distinto:** `936e8f99` (sha256 do `.alvo.json`), diferente dos 4 alvos de Produto já no main —
  `223d9487` cadastros · `7d969ec4` etiquetas · `6e445f8f` importação · `b12d5727` atualizar-preco.

## `secao:check`
`node scripts/qa/secao-check.mjs --tela produto--unificado--index --servir-espelho`:
`✓ produto--unificado--index — 7 seção(ões) conforme`, `secao-check: conforme`, rc=0.

## Fora deste PR
- `governance/design/targets/README.md` (tabela "Alvos exportados") não foi tocado: o prefixo da thread é
  `${ALVOS}/produto--unificado--index.*` (mesma pendência registrada na `_saida-A2`).
- Fora do alvo: os menus de ação por linha (`pd-menu`, abertos só por clique), os popovers de filtro e
  as abas além da default ("Todos").
- `produtos` × `prod-lista` (Blade) segue como ponto aberto da 00, decisão [W]; este alvo mede só o
  porte do Unificado.
- A `url` do alvo carrega a porta 5581; re-medir em outra porta muda só esse campo.
