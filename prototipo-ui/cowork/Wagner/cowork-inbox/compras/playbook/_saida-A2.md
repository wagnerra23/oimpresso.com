---
sessao: "_saida-A2"
thread: "A2 · ALVO compras--pedidos--index · compras--requisicoes--index · compras--devolucoes--index"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/compras-a2-alvos)"
base_lida: wagnerra23/oimpresso.com@main 67d1f7ce80
---
# _saida-A2

## 1 · Feito
Os 3 alvos, gravados por `scripts/design-sync/alvo.mjs --alvo` contra o espelho
`prototipo-ui/cowork/Wagner/`, servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`)
em `http://127.0.0.1:5562/`. Mesmo molde da A1 do Estoque: tema dark (conferido no `base.theme` dos 3),
viewport 1280×900, browser limpo, `--quieto-ms 2000`. Nada escrito à mão nos `.alvo.json`; os
seletores dos `.secoes.json` vieram do `alvo:mapa` e estão registrados em `_mapa`.

Rotas: `app.jsx:844` manda toda rota `cmp-*` para `window.ComprasExtrasPage` (`compras-extras.jsx`).

| slug | rota | seções |
|---|---|---|
| `compras--pedidos--index` | `cmp-pedidos` | 6 · cabecalho · abas · kpis · toolbar · tabela · rodape |
| `compras--requisicoes--index` | `cmp-requisicoes` | 7 · as 6 + `nota` (`p.cu-nota` no topo do widget) |
| `compras--devolucoes--index` | `cmp-devolucoes` | 7 · as 6 + `aviso` (Alert info "Devolução de compra") |

Comando (por tela): `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5562/ --tela <slug> --rota <rota> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`
→ `ausentes no DOM: 0` nos 3.

## 2 · Provas
- **Sanidade:** `alvo.mjs --selftest --browser` → `27/27 ok`.
- **Determinismo:** re-medição de cada tela com `--saida` → `cmp` byte-idêntico nos 3.
- **A-LOTE:** sha256 distintos (`570acfb1…` · `a88df8f3…` · `ba167229…`). Por seção, `toolbar`, `tabela` e
  `rodape` diferem entre as 3. `cabecalho`, `abas` e `kpis` saem iguais: a casca da `ComprasExtrasPage`
  é a mesma, e o alvo mede estrutura e estilo, não texto (os rótulos dos KPIs mudam por tela e não entram).
- **Aviso:** o seletor `nav.ds-tabbar + div` (o Alert não tem classe) resolve o Alert — o texto
  "Devolução de compra / Sai do estoque e vira crédito…" está no alvo.
- `node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho` → `conforme`, rc=0, nos 3.

## 3 · Não feito, e por quê
- README "Alvos exportados" de `governance/design/targets/` não atualizado (fora do prefixo, como na A1).
- Threads 06/07/08 (Pedidos, Requisições, Devoluções → Inertia) não começam aqui; elas dependem desta.
- A aba ativa do `ds-tabbar` não diferencia as 3 telas no alvo (mesmo motivo do texto).
  Se a 06–08 precisar disso, o contrato tem que medir `aria-selected`, não este alvo.

## 4 · Pedido literal
> "pega a próxima thread do playbook de Compras" — [W] 2026-10-13. O placar (`scripts/qa/placar.mjs --indice … --proximo`) apontou **A2**.

## 5 · Descobertas
- As threads 03 e 04 do índice seguem ⛔ na tabela §2, mas estão feitas desde 2026-09-24
  (#7918, #7915, `_saida-03`/`_saida-04` presentes); o placar as lê como `bloqueada`. Corrigir no índice **do Cowork**.

## 6 · Prefixo tocado
`governance/design/targets/compras--{pedidos,requisicoes,devolucoes}--index.{secoes,alvo}.json` + este recibo (arquivo novo).
