---
sessao: "34"
titulo: "ALVO ponto--aprovacoes--index — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: e4289e688
thread: 34-alvo-aprovacoes.md
veredito: "entregue — alvo medido (5 seções, 0 ausentes), duas medidas byte-idênticas; `aprovacoes-kpis-estado + div` é a barra, como previsto; destrava a 15 (que segue presa a W15)."
---

# _saída 34 · ALVO das Aprovações

Entregue no mesmo PR das threads 32 e 33.

## O que saiu

| arquivo | origem |
|---|---|
| `governance/design/targets/ponto--aprovacoes--index.secoes.json` | a semente da thread, conferida com `alvo:mapa`. Nenhum seletor corrigido. |
| `governance/design/targets/ponto--aprovacoes--index.alvo.json` | saída do `alvo:medir`, nunca editado à mão |
| linha na tabela "Alvos exportados" do `README.md` da pasta | — |

## Como foi medido

- Mesmo servidor, mesmo pré-requisito e mesmo `--preview-ds` (exit 0) da `_saida-32`.
- `npm run alvo:mapa -- http://127.0.0.1:5550/ --rota pt-aprovacoes --raiz '.ponto-root .pt-body'` retornou 4 filhos: `div.pt-kpis[aprovacoes-kpis-estado]` (6 botões de estado) · `div` com a barra Estado/Tipo/Prioridade · `div[aprovacoes-fila-de-aprovacoes]` · `div.pt-legal`.
- `[data-contract="aprovacoes-kpis-estado"] + div` resolve a barra de filtros, como a semente previa.
- `npm run alvo:medir -- http://127.0.0.1:5550/ --tela ponto--aprovacoes--index --rota pt-aprovacoes --secoes governance/design/targets/ponto--aprovacoes--index.secoes.json --quieto-ms 2000`, rodado duas vezes. Os dois arquivos são **byte-idênticos**, sha256 `19f07fd6bbd3699f…`.
- Resultado: 5 seções, 0 ausentes, `nos_totais` 671, viewport 1280×900, dark.

## Provas de efeito

- `secao-check --todos --servir-espelho`: `ponto--aprovacoes--index — 5 seção(ões) conforme`.
- `pedido.mjs --tela Ponto/Aprovacoes/Index --secao fila` sai **0**.

## W15

W15 continua aberta: o "motivo do lote" não tem endpoint (`aprovarEmLote` só recebe `ids`; `rejeitar` é um por vez, com `motivo required|max:500`). Ela trava a thread 15, não esta medição.

A barra de lote não entrou no alvo porque só existe com seleção. Se a 15 precisar dela depois de W15, a medida é um 2º alvo com `--clicar` no checkbox.

## PARAR SE

Nenhum disparou.
