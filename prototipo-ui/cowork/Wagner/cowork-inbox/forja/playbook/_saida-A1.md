---
sessao: "_saida-A1"
thread: "A1 · ALVO das 4 telas com âncora: aprovacoes · trabalho · roadmap-gantt · cockpit"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
entregue_em: "PR desta thread (claude/forja-a1-alvos)"
base_lida: wagnerra23/oimpresso.com@main 03f5d9d8227e
---
# _saida-A1

## Entregue: 3 de 4 alvos, medidos pela máquina
Os 3 foram gravados por `scripts/design-sync/alvo.mjs --alvo` contra o espelho `prototipo-ui/cowork/Wagner/`, servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`) em `http://127.0.0.1:5561/`. Tema dark (default do espelho), viewport 1280×900, browser limpo. Nada foi escrito à mão nos `.alvo.json`. Os `.secoes.json` levam seletores colhidos com `alvo:mapa`, e o resultado está registrado em `_mapa` dentro de cada um.

O espelho roteia por `oimpresso.route` (`app.jsx:901`): `projects` (ou `teammcp`) monta `window.ForjaPage` (`forja-page.jsx`). Dentro dela a view é `oimpresso.forja.view`, que vale `hoje` por default (`forja-page.jsx:9`), e a sub-visão do Trabalho é `oimpresso.forja.trabvis`, que vale `lista` por default (`forja-page.jsx:41`).

| slug | seções | comando |
|---|---|---|
| `forja--aprovacoes--index` | 4 (cabecalho · vivo · mesa · equipe) | `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5561/ --tela forja--aprovacoes--index --rota projects --secoes governance/design/targets/forja--aprovacoes--index.secoes.json --quieto-ms 2000` |
| `forja--trabalho--index` | 6 (visao · kpis · toolbar · filtros · lista · totais) | idem, com `--tela forja--trabalho--index --clicar ".fj-viewtabs > .fj-navgroup:first-child > button:nth-of-type(2)"` e as seções do próprio slug |
| `forja--cockpit` | 5 (header · titulo · acoes · abas · novo) | idem ao primeiro, com `--tela forja--cockpit` e as seções do próprio slug |

Aprovações e Cockpit são medidos no mesmo estado (view `hoje`). O Cockpit pega o header/shell `.os-page-h`, e Aprovações pega o corpo `.ap-page` (`window.ForjaAprovacoes`, `forja-aprova.jsx`).

Saídas: `alvo gravado … seções medidas: 4 · ausentes no DOM: 0` · `… 6 · 0` · `… 5 · 0`, todos com rc=0. O determinismo foi conferido re-medindo Trabalho e Aprovações com `--saida` para um arquivo temporário, e `cmp` deu byte-idêntico nos dois.

## `secao:check`
`node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`:
- `forja--aprovacoes--index`: `✓ 4 seção(ões) conforme`, rc=0
- `forja--trabalho--index`: `✓ 6 seção(ões) conforme`, rc=0
- `forja--cockpit`: `✓ 5 seção(ões) conforme`, rc=0
- `forja--roadmap-gantt`: `NÃO MEDI: alvo inexistente`, rc=2

## Não medido: `forja--roadmap-gantt` (exit 2)
O Gantt é a sub-visão `trabvis=gantt` dentro da view `trabalho`. Para chegar a ele são precisos **dois** cliques (aba Trabalho e depois o segmento Gantt da `.fj-frentebar`), ou então gravar `oimpresso.forja.view`/`oimpresso.forja.trabvis` no localStorage. O `alvo.mjs` aceita **um** `--clicar` (o `val()` lê só a 1ª ocorrência) e só grava `oimpresso.route` e o modo da sidebar no localStorage. A tentativa com um clique direto no segmento saiu `NÃO MEDI: --clicar … não casou nenhum elemento`, rc=2, porque a `.fj-frentebar` não existe na view `hoje`. Por regra da thread, o `alvo.mjs` não foi editado. Para destravar, o `alvo.mjs` precisaria de clique em cadeia ou de chave de localStorage arbitrária, e isso fica para outra thread. A D1 (âncora `forja-gantt.jsx` × `forja-page.jsx`) segue aberta: as duas âncoras renderizam o mesmo `FjGanttView` dentro da `ForjaPage`.

## Slugs
São os previstos no `00-INDICE.md`. A única diferença é que o Gantt não ganhou arquivo.

## Fora deste PR
A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada, porque o prefixo da thread é `governance/design/targets/forja--*`.
