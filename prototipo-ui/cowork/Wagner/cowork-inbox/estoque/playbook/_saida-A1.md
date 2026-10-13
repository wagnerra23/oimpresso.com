---
sessao: "_saida-A1"
thread: "A1 · ALVO estoque--ajustes--index · estoque--transferencias--index"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/estoque-a1-alvos-medidos)"
base_lida: wagnerra23/oimpresso.com@main 2cbc057103
---
# _saida-A1

## Entregue: os 2 alvos, medidos pela máquina
Os dois foram gravados por `scripts/design-sync/alvo.mjs --alvo` contra o espelho
`prototipo-ui/cowork/Wagner/`, servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`)
em `http://127.0.0.1:5561/`. Tema dark (default do espelho, conferido no `base.theme` dos dois),
viewport 1280×900, browser limpo. Nada foi escrito à mão nos `.alvo.json`. Os `.secoes.json` levam
seletores colhidos com `alvo:mapa`, e o resultado está registrado em `_mapa` dentro de cada um.

As rotas são as que a 00 fixou (`_saida-00`, mapa rota↔Page): `est-ajustes` = `StockAdjustment/Index`
e `est-transferencias` = `StockTransfer/Index`. O `app.jsx:849` manda as duas para `window.EstoquePage`.
A rota fixa a aba e as colunas padrão sem ler o storage (`usarAbaEst` · `usarCols` fixo).

| slug | seções | comando |
|---|---|---|
| `estoque--ajustes--index` | 7 (cabecalho · abas · subabas · periodo · toolbar · tabela · rodape) | `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5561/ --tela estoque--ajustes--index --rota est-ajustes --secoes governance/design/targets/estoque--ajustes--index.secoes.json --quieto-ms 2000` |
| `estoque--transferencias--index` | 7 (as mesmas) | idem, com `--tela estoque--transferencias--index --rota est-transferencias` e as seções do próprio slug |

Saídas: `seções medidas: 7 · ausentes no DOM: 0` nos dois, rc=0.

## Antes e depois de medir
- **Sanidade:** `alvo.mjs --selftest --browser` → `27/27 ok` (inclui 2 runs byte-idênticos e
  `--injetar-falha` mudando o JSON).
- **Estável:** a medida espera `__oiLazyDone` e duas leituras iguais de `querySelectorAll('*').length`
  (dentro do `alvo.mjs`), mais `--quieto-ms 2000`.
- **Determinismo:** cada tela foi re-medida com `--saida` num arquivo temporário; `cmp` deu
  byte-idêntico nas duas.
- **A-LOTE:** um alvo por tela, hashes diferentes —
  `9da365f2…` (ajustes) × `df82d27b…` (transferências). Por seção, 5 de 7 coincidem (cabecalho, abas,
  periodo, toolbar, rodape): é a mesma casca da `EstoquePage` nas duas abas. `subabas` (3 × 5 itens) e
  `tabela` diferem, e a `base.assinatura` de cada um traz as linhas da própria aba (AJ-0142… ×
  TRF-0088…). Não é a mesma tela medida duas vezes.

## `secao:check`
`node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`:
- `estoque--ajustes--index`: `✓ 7 seção(ões) conforme`, rc=0
- `estoque--transferencias--index`: `✓ 7 seção(ões) conforme`, rc=0

## Fora deste PR
- A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: o prefixo
  da thread é `governance/design/targets/estoque--*`.
- Os Create (ajuste/transferência) seguem sem alvo, como o índice diz (§4).
- As divergências "só no protótipo" da `_saida-00` (rótulos de filtro, "moveu"/"só reserva",
  "Finalizada", colunas Itens/id, paginação) entram no alvo como o protótipo as desenha. Decidir cada
  uma é [W]; a 02 deriva os contratos deste alvo.
