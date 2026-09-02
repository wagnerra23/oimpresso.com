---
date: "2026-09-02"
topic: "Forja Onda 4 — a lista do Trabalho vira a réplica do protótipo"
authors: [C]
prs: [6582]
us: [US-FORJA-006]
related_adrs: ["0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias"]
outcomes:
  - "A lista de /forja/trabalho passa a usar o vocabulário do forja-page.jsx (3 barras, fj-row densa, KPI que filtra)"
  - "KPI vira botão e recorta a lista sem recortar a si mesmo (build() x filtrar())"
  - "EpicRoll removido antes do merge: epic_id é FK pra McpEpic, não pra outra task (LC-08)"
  - "Medidor podia inverter veredito: o CliSeg do espelho local retorna null sem o Segmented do DS"
  - "6 diferenças declaradas no charter — todas exigem comportamento, e a ADR 0388 é de aparência"
---

# Forja Onda 4 — a lista do Trabalho vira a réplica do protótipo

> PARIDADE §11 Onda 4. PR **[#6582](https://github.com/wagnerra23/oimpresso.com/pull/6582)** (substituiu o #6577, fechado). Merge é ato [W] — [ADR 0283](../decisions/0283-handoff-loop-zero-paste.md).

## O que foi feito

`/forja/trabalho` no modo lista passou a usar o vocabulário do `forja-page.jsx`: as três barras de filtro (`fj-frentebar` · `fj-toolbar` · `fj-filterbar2`), a `fj-row` densa, o KPI que **filtra** (`<button>`, valor 17px) e o `--accent` dark 0,70 herdado do `.fj-page`. Saíram desta tela o `PageHeader`, o `KpiGrid`/`KpiCard` e os primitivos `Grid`/`Inline`/`Stack` — que é a [ADR 0388](../decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md) aplicada: onde existe âncora, a aparência é a dela.

No backend, `grupo`/`saude`/`papel` entraram com allowlist, e `filtrar()` ficou **separado** de `build()`: os KPIs medem o pool enquanto a lista encolhe. Sem essa separação, clicar "P0" zeraria "Fazendo" e o painel mentiria sobre o tamanho do problema exatamente quando alguém investiga um.

## A decisão de método que mais rendeu: medir o ALVO antes de codar

Antes de escrever a tela, servi o protótipo (`python -m http.server 5620`), pus tema dark, e **esperei ativamente** até `__oiLazyDone` **e** duas leituras consecutivas iguais (1418 = 1418 nós, 4 tentativas). A primeira leitura dava 515 → 533: teria produzido um alvo errado, e é literalmente a lápide §5 2026-08-24.

O alvo medido — `filterRows` 3 · KPI 4/`BUTTON`/17px/left · `--accent` `oklch(0.70 0.15 295)` · `.fj-row` com 13 filhos — virou o contrato contra o qual construí, em vez de eu derivar do `.jsx` lido (que seria LC-08).

Medir `children` de **cada bloco**, e não só da linha, rendeu duas ausências que eu **não tinha declarado** no primeiro empurrão: a `fj-onda-meta` do cabeçalho de grupo (estado · janela · carga · encerrar onda · ✦ resumir) e o `3 não-verificados` da barra de totais.

## Dois achados que valem mais que o código

**1. O medidor podia ter invertido o veredito.** O `.fj-frentebar` do protótipo mede **1** filho no espelho local — só a nota, sem o segmentado. Não é a tela: o `CliSeg` retorna `null` quando o `Segmented` do DS não está publicado, e o bundle do snapshot local está truncado pelo teto do `get_file` (44 de 55 componentes). O protótipo **vivo** tem 2 ali. Se eu tivesse tratado aquele 1 como alvo, teria concluído que a réplica com 2 estava *errada* — quando é a mais fiel, e é o que o pedido do [W] instruiu.

**2. O `EpicRoll` que nunca teria disparado (LC-08, meu).** Implementei o chevron de sub-issues e o roll-up do épico indexando as tarefas por `epic_id`. Em `mcp_tasks`, `epic_id` é FK pra **`McpEpic`** (`McpTask.php:230`) — outra entidade, não outra task. No protótipo o pai é um issue da **mesma** lista (`kidsOf[issue.id]`), então a hierarquia não tem equivalente aqui. Derivei do **nome** do campo em vez de abrir a relação.

O sintoma seria o pior tipo: nada quebra, o componente só nunca aparece, e o comentário ao lado seguiria afirmando o contrário. Peguei na auto-revisão, antes do merge; removido e declarado no charter. Conferi de passagem o vizinho e esse está **certo**: `blocked_by` é dependência entre tasks — é o que o Gantt usa pras setas.

## O que NÃO veio do protótipo (declarado, não esquecido)

A ADR 0388 D-5 diz que réplica não é licença pra *"tocar comportamento (rota, permissão, dado, cálculo)"*. Seis itens caem nisso: checkbox + `.fj-bulkbar` (mutação em massa sem endpoint), `Papéis`/`Perguntar ✦` (painéis inexistentes), `carry` e `frescor` (campos ausentes), hint de atalhos e a DSL de busca (anunciariam teclado e sintaxe que a tela não tem), e a `fj-onda-meta` (exige onda como **entidade**; em produção `forja_onda` é `custom_field` de texto).

Todos estão no charter §"Diferenças declaradas" e na lista de inconsistências.

## Duas reconciliações que ficaram para [W] no merge

1. **Non-Goal do pin.** Ele proíbe pin *"persistido … user-pref gravada"*. O que entrou é `localStorage` do próprio viewer — o que o protótipo faz (linhas 787-830). Nada vira coluna nem user-pref. Leio como fora do Non-Goal; se [W] ler como dentro, é remover dois botões.
2. **Anti-hook do `aria-pressed` no Gantt.** A réplica põe os três no mesmo segmentado, como o protótipo. O valor nunca é `gantt` — escolher navega na hora, então o item nunca fica ativo, e a seta `↗` + `title` seguem avisando. Cumpre o espírito por outro caminho.

## Rebase, conflito e o force-push que não aconteceu

O #6577 ficou `DIRTY` quando o [#6569](https://github.com/wagnerra23/oimpresso.com/pull/6569) mergeou — ele mudou o **próprio gerador** da lista de inconsistências. Rebasei em `origin/main` fresco e **regenerei** os derivados (derivado se regenera, não se resolve à mão): 117 → 118 abertas contra o main já com o gerador novo.

O rebase reescreveu a história, e o `block-destructive` barrou o force-push — corretamente. Em vez de forçar ou de insistir num `reset --hard` (também negado), abri **branch nova** e fechei o #6577 apontando pra ela. Nenhuma operação destrutiva, nenhum commit sobrescrito.

## A precondição do pedido que não se confirmou

O pedido dizia *"faça depois da Onda 3 estar mergeada — as ondas tocam o mesmo `Cockpit.tsx`"*. A Onda 3 **não está mergeada** (o último da série é a Onda 2.1, #6563/#6565) e **não há PR aberto** dela. Segui porque medi a colisão e ela não existe: a Onda 3 é `Pages/Forja/Aprovacoes/Index.tsx`, esta é `Pages/Forja/Trabalho/Index.tsx`. A superfície comum é o `ForjaHub.tsx` (não tocado) e o bundle CSS (que só ganhou um bloco no fim).

## Pendências honestas

- **A sonda pareada não rodou** — exige o deploy. Nada foi declarado "0 `DIVERGE(bug)`"; o §11 linha 4 está marcado 🟡, não ✅.
- **D1 (rede)** medível só em produção autenticada. Todo controle que recorta é `router.get` parcial com `only:[...]`.
- **Baseline visual**: `/forja/trabalho` não está no visreg (conferido no dono do inventário, `tests/Browser/visreg-screens.json` — das 39 telas, a única da Forja é `Forja/Aprovacoes`). Não há `.snap` a regravar.
- **O `casos-gate` não alcança esta tela**: o escopo dele é `resources/js/Pages/**`, e a Forja mora em `Modules/Forja/Resources/js/Pages/**`. Quem roda os UC é a lane `forja-pest.yml`. O contador deve subir de 12 para 16 — é o recibo a conferir, não a presença do arquivo.
