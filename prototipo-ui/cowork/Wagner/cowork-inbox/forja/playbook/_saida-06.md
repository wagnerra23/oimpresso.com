---
sessao: "_saida-06"
thread: "06 · Alvo de toque 24×24 nas telas da Forja (D3)"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main (origin/main de 2026-10-07)
---
# _saida-06

## Entregue
Um bloco no fim de `resources/css/cowork-forja-bundle.css`. Nenhum `.tsx` mudou.

```css
.fj-pin, .fj-star{ width:24px; height:24px; }      /* eram 22×22 */
.fj-group-toggle{ min-height:24px; }                /* era 23,75 de altura */
.fj-search input{ min-height:24px; padding:0; }     /* era 19,5 de altura */
.fj-search{ padding-top:3.75px; padding-bottom:3.75px; }  /* a caixa segue com 33,5px */
```

O glifo não cresce; cresce a caixa do botão. O bloco fica separado, no fim, porque acima é a cópia
do CSS do protótipo, e o protótipo (`forja-page.css`) ainda tem 22px. A mesma correção cabe no
build do Cowork.

## Como foi medido
Produção, empresa WR2 (biz=1), só leitura, navegador embutido, viewport 1280×900. A sonda conta,
dentro de `main.main-body` (o conteúdo da tela, sem a sidebar do shell), todo elemento clicável
visível (`button`, `a[href]`, `[role=button|tab|checkbox|switch|menuitem]`, `input`, `select`,
`summary`, `[tabindex]`) com largura ou altura menor que 24px. Links inline em texto ficam de fora
(exceção do próprio 2.5.8). Cada número foi lido depois de duas contagens iguais de nós.

Caso de sanidade, antes de qualquer número: um botão de 20×20 injetado é contado (0 → 1) e,
removido, a contagem volta. Repetido com o bloco aplicado: continua contado.

### Antes (CSS de produção)
| tela | clicáveis | abaixo de 24×24 | quais |
|---|---|---|---|
| Aprovações (`/forja/aprovacoes`) | 10 | 0 | — |
| Trabalho · Lista (`/forja/trabalho`) | 1.051 | 1.010 | `fj-pin` 500 × 22×22 · `fj-star` 500 × 22×22 · `fj-group-toggle` 9 × 23,75 de altura · busca 1 × 19,5 de altura |
| Trabalho · Quadro | 530 | 501 | `fj-star` 500 · busca 1 |
| Gantt (`/forja/roadmap-gantt`) | 16 | 0 | — |
| Cockpit (`/forja`) | 12 | 0 | — |

O "81 de 118" do export de 2026-09-03 foi medido no protótipo; em produção hoje o problema está
concentrado no Trabalho.

### Depois (o bloco acima injetado na página de produção, mesma sonda)
| tela | abaixo de 24×24 |
|---|---|
| Trabalho · Lista | **0** de 1.051 |
| Trabalho · Quadro | **0** de 530 |

Geometria conferida no mesmo passo: caixa da busca 33,5 → 33,5; linha da lista 34 → 34;
cabeçalho de grupo 34,75 → 35.

## O que não foi medido
- O "depois" é o bloco injetado na página de produção, não o CSS deployado. Depois do merge e do
  deploy, a mesma sonda tem de ser repetida em produção.
- As barras do Gantt (`wx-gantt`) são `div` arrastáveis sem papel nem `tabindex`. A sonda não as
  vê, e o 2.5.8 sobre elas fica sem medida.
- Telas da Forja sem âncora (Scorecard, Sessões CC, Tarefas, Equipe e as de `ads/Admin`) não
  entram nesta thread.
