---
slug: 0411-snapshot-de-pixel-fora-do-passo-3-da-0409
number: 411
title: "Snapshot de pixel do VRT fica fora do passo 3 da ADR 0409 — referência de regressão, regenerada só com decisão [W]"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-23"
module: governance
tags: [governanca, visual, vrt, snapshot, baseline, ci]
supersedes: []
supersedes_partially:
  - 0409-zero-baseline-de-tolerancia-conformidade-absoluta
related:
  - 0290-fidelity-lock-v0-recusado
  - 0408-medicao-de-paridade-agendada-advisory-emenda-0290
  - 0410-ratificacao-zero-baseline-no-funil-design
pii: false
---

# ADR 0411 — Snapshot de pixel do VRT fica fora do passo 3 da ADR 0409

## Contexto

O plano de migração da [ADR 0409](0409-zero-baseline-de-tolerancia-conformidade-absoluta.md)
tem um passo 3, *Visual*: substituir a autoridade de snapshot pela comparação entre
protótipo vivo e aplicação na mesma execução, e manter as capturas só como evidência.

A [proposta de 2026-09-21](proposals/2026-09-21-vrt-snapshot-vs-0409-reconciliacao.md) mediu
que esse passo colide com duas decisões vigentes:

- a [ADR 0408](0408-medicao-de-paridade-agendada-advisory-emenda-0290.md), aceita no mesmo
  dia, permite a comparação protótipo × aplicação **só como medição advisory**. Bloquear merge
  por ela continua recusado pela [ADR 0290](0290-fidelity-lock-v0-recusado.md), cujo critério
  de reabertura é um check hermético (sem render, sem login, sem CDN), que não existe;
- o substituto cobria, na medição daquele dia, 23 das 68 telas ancoradas, e 58 não tinham o
  contrato que prova a identidade da view renderizada.

A proposta deixou uma pergunta que só [W] respondia: o passo 3 vale para os `.snap` do
`visual-regression` com a mesma força que a 0409 vale para arquivos de tolerância? A
[ADR 0410](0410-ratificacao-zero-baseline-no-funil-design.md), que ratificou a 0409 no funil
de design, não tratou dos snapshots de pixel. Enquanto a pergunta ficasse aberta, a 0409 §3 e
a 0408 apontavam para lados opostos.

## Decisão

**O passo 3 do plano de migração da ADR 0409 não se aplica aos snapshots de pixel do
`visual-regression`.** [W], 2026-09-23.

1. O `.snap` não é arquivo de tolerância. Ele não lista erros conhecidos para perdoar; é a
   **referência de comparação** que pega regressão da aplicação contra ela mesma. A 0409 segue
   valendo integralmente para os arquivos de tolerância (PHPStan, lints, `grandfathered`).
2. Os snapshots continuam sendo a referência do `visual-regression`. Eles não são substituídos
   pela comparação protótipo × aplicação.
3. A comparação protótipo × aplicação continua existindo como está na 0408: medição
   **advisory**, agendada, que detecta desvio de intenção. Ela não bloqueia merge, e a 0290
   segue intacta.
4. **Regenerar snapshot é decisão de [W], nunca reflexo para fazer o check passar.** Esse é o
   princípio da 0409 que continua valendo para os snapshots: a foto não se atualiza só porque
   divergiu. Quando a intenção visual muda de propósito (Fundação aprovada, tela nova, mudança
   de tela aprovada), a regeneração acontece com o motivo declarado e com o diff à vista.

Esta ADR substitui a ADR 0409 **apenas** no passo 3 do plano de migração e no trecho
"Protótipo Bubble até produção" que trata o snapshot como evidência sem autoridade, e só no que
se refere aos `.snap` do `visual-regression`.

## Consequências

- A 0409 §3 e a 0408 deixam de apontar para lados opostos: a 0408 é o desenho final para a
  comparação com o protótipo, e esta ADR para os snapshots.
- O desenho (B) da proposta de 2026-09-21 — manter a regeneração atrás de porta [W] explícita —
  deixa de ser etapa e passa a ser o destino.
- **Ainda não implementado** (fica como trabalho, não como estado): o disparo de regeneração
  exigir um campo de motivo que vá para o corpo do PR, e o PR de regeneração trazer o resultado
  do `scripts/tests/snap-diff.mjs` (px alterados, Δmax, células), para a revisão ser sobre a
  mudança e não sobre a palavra "regeneradas". Até isso existir, a porta [W] é só humana.
- Caso concreto que esta decisão destrava: a DRE mudou de propósito no
  [#7767](https://github.com/wagnerra23/oimpresso.com/pull/7767) e diverge 6,6% da foto. O
  caminho é regenerar com aprovação [W] e o diff à vista, não substituir a foto pela comparação
  com o protótipo.
- A 0409 reserva o termo `baseline` para arquivos de tolerância. O `visual-regression` ainda
  chama os `.snap` de baseline; renomear fica fora desta decisão.
