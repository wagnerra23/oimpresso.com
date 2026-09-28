---
sessao: "32"
titulo: "ALVO ponto--dashboard--index — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: e4289e688
thread: 32-alvo-painel.md
veredito: "entregue — alvo medido (6 seções, 0 ausentes), duas medidas byte-idênticas; nenhum PARAR SE disparou; destrava a 13."
---

# _saída 32 · ALVO do Painel

Entregue no mesmo PR das threads 33 e 34 (um PR para as três, pedido da sessão gerente).

## O que saiu

| arquivo | origem |
|---|---|
| `governance/design/targets/ponto--dashboard--index.secoes.json` | a semente da thread, conferida com `alvo:mapa`. Os 6 seletores ficaram como vieram. |
| `governance/design/targets/ponto--dashboard--index.alvo.json` | saída do `alvo:medir`, nunca editado à mão |
| linha na tabela "Alvos exportados" do `README.md` da pasta | — |

## Como foi medido

- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`), o mesmo servidor que o `secao-check` usa no CI, na porta 5550.
- `cowork-mirror-freshness --preview-ds` antes de medir: exit 0.
- Pré-requisito conferido em `origin/main` e4289e688: `cli-pagehead.jsx` e `modulo-padrao.css` têm a `.cli-ph`.
- `npm run alvo:mapa -- http://127.0.0.1:5550/ --rota ponto --raiz .ponto-root` retornou `div.cli-ph` (p + header) · `nav.ds-tabbar.jm-tabs` · `div.pt-body`. Dentro do `.pt-body`: nota · `.pt-kpis` · `.pt-cols-2` (fila + atividade) · `.pt-legal`.
- Os 4 `data-contract` (`painel-nota-fechamento`, `painel-kpis`, `painel-fila-aprovacoes`, `painel-atividade`) resolvem 1 nó cada no DOM.
- `npm run alvo:medir -- http://127.0.0.1:5550/ --tela ponto--dashboard--index --rota ponto --secoes governance/design/targets/ponto--dashboard--index.secoes.json --quieto-ms 2000`, rodado duas vezes. Os dois arquivos são **byte-idênticos** (`cmp` sem saída), sha256 `a3199ec3de84a7e6…`.
- Resultado: 6 seções medidas, 0 ausentes, `nos_totais` 657, viewport 1280×900, tema dark (padrão do `alvo.mjs`).

## Provas de efeito

- `secao-check --todos --servir-espelho`: `ponto--dashboard--index — 6 seção(ões) conforme`.
- `pedido.mjs --tela Ponto/Dashboard/Index --secao kpis` sai **0** (antes, sem o alvo, saía 2 NÃO MEDI). O controle negativo `--tela Ponto/Colaboradores/Index` continua saindo 2.

## PARAR SE

Nenhum disparou: o `header` saiu presente, todos os seletores resolveram e as duas medidas bateram. Não precisei de `--aguardar-sumir`.

## Fora do alvo, de propósito

`presenca_agora` (PresenceStrip) e `serie_7dias` existem só na produção. A 13 os preserva como guarda; o alvo não os cobra.
