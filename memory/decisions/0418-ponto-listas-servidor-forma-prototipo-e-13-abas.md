---
slug: 0418-ponto-listas-servidor-forma-prototipo-e-13-abas
number: 418
title: "Ponto — listas seguem paginadas no servidor com a forma do protótipo (W11) e a navegação vira as 13 abas do protótipo (W9)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-28"
module: pontowr2
tags: [ponto, forma, prototipo, navegacao, paginacao, playbook]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0413-ponto-fechamento-competencia-conformidade-relatorios-legais
  - 0182-pageheadertabs-canon-pattern-telas
pii: false
---

# ADR 0418 — Ponto: listas no servidor com a forma do protótipo (W11) e 13 abas (W9)

## Contexto

O playbook do Ponto (`prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/00-INDICE.md` §6)
deixava abertas duas decisões de [W] que travavam a passada de FORMA das telas:

- **W11** — as listas viram `DataGrid` no cliente (como o protótipo, que pagina com `usePagina`)
  ou seguem `LengthAwarePaginator` no servidor? O índice registra que ela **trava 35 PRs de forma**
  (Vaga 3: ALVO + forma das 17 telas restantes).
- **W9** — navegação: as 13 abas de área do protótipo ou o `PontoSubNav` atual (5 abas + ⋯,
  padrão da [ADR 0182](0182-pageheadertabs-canon-pattern-telas.md))?

Em 2026-09-28, depois do smoke em produção de `Ponto/Escalas/Index`, [W] comparou a tela viva com o
protótipo e decidiu: *"o protótipo está correto, mas a produção é muito inferior"*. As duas
perguntas foram feitas em seguida, com as opções e as consequências de cada uma.

## Decisão

1. **W11 — servidor + forma do protótipo.** As listas do Ponto **seguem paginadas no servidor**
   (`LengthAwarePaginator`, 20 por página, partial reload `only: [...]`), e a **forma** vem do
   protótipo: colunas, ordem, sub-linhas, pills, estados e textos. Não há `DataGrid` no cliente
   nem mudança de controller/props por causa da forma.
2. **W9 — 13 abas do protótipo.** A navegação do módulo Ponto passa a ser a barra de abas de área
   do protótipo (13 abas), no lugar do `PontoSubNav` com 5 abas + ⋯. Vale para o Ponto; não muda a
   ADR 0182 para os outros módulos.

## Consequências

- A Vaga 3 do playbook (forma das telas restantes) deixa de estar travada por W11: cada tela mede
  o ALVO (`governance/design/targets/ponto--<pasta>--<page>.alvo.json`) e aplica a forma sem mexer
  no backend de paginação.
- A troca da navegação (W9) é **um PR próprio**, no componente de navegação do Ponto, porque muda
  o cabeçalho de todas as telas do módulo de uma vez — não entra misturada no PR de forma de uma tela.
- A ADR 0182 segue valendo fora do Ponto. No Ponto, a divergência é resolvida pela
  [UI-0029](../requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)
  (protótipo soberano no eixo FORMA) com esta decisão explícita de [W].
- O pedido de edição do `00-INDICE.md` do Cowork fica em
  `prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/_DECISOES-W-2026-09-28.md` — o Code não
  edita o índice do espelho.

## Fonte

- [W] 2026-09-28, nesta sessão: respostas às perguntas W11 e W9, logo após o smoke em produção do
  PR [#8079](https://github.com/wagnerra23/oimpresso.com/pull/8079).
