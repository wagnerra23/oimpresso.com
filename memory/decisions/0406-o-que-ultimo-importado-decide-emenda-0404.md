---
slug: 0406-o-que-ultimo-importado-decide-emenda-0404
number: 406
title: "O que 'último importado' decide, e o que ele não decide (emenda aditiva à 0404)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-16"
module: governance
tags: [design, cowork, importacao, espelho, ssot, precedencia]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0404-ultimo-importado-e-autoridade-do-espelho
  - 0405-espelhos-cowork-independentes-por-conta
  - 0398-espelho-cowork-recebe-a-arvore-da-conta
  - 0379-bundle-design-transacao-manifesto-delta-staging
pii: false
---

# ADR 0406 — o que "último importado" decide, e o que ele não decide

> **Emenda ADITIVA à [ADR 0404](0404-ultimo-importado-e-autoridade-do-espelho.md).** Nenhuma
> cláusula dela é revogada: a precedência do último importado continua inteira, e continuam
> valendo os gates, a procedência, o anti-replay e a regra de delta da
> [ADR 0379](0379-bundle-design-transacao-manifesto-delta-staging.md). O que esta emenda faz é
> **remover a dupla interpretação** — dizer, para cada pergunta que uma importação levanta, qual
> é a resposta única e quem a decide.

## Decisão do dono

Em 2026-09-16, [W] determinou: **"deve prevalecer apenas os ultimos arquivos importados"**, e
pediu que o protocolo fosse resolvido **"para que não de dupla interpretação"**.

## O problema — a mesma pergunta tem respostas opostas no repo (medido 2026-09-16)

A 0404 fixou a precedência em prosa. Os mecanismos que executam a importação não foram
reconciliados com ela, e nenhum deles a cita (`rg "0404"` em `scripts/` devolve zero fora de um
número de versão homônimo). Medido nesta data:

| pergunta | respostas que convivem hoje | onde |
|---|---|---|
| arquivo do espelho que **não veio** no pacote: fica ou sai? | "o apply **não apaga** — relato, não poda" × "o SSOT é ESPELHO do último handoff, **não união**" (`/PURGE`) × poda só quando `mirrorScope === 'tree'` | [`aplicar-payload.mjs:390-392`](../../scripts/design-sync/aplicar-payload.mjs) · [`importar-bundle.mjs:34-35`](../../scripts/design/importar-bundle.mjs) · [`bundle-transaction.mjs:344`](../../scripts/design-sync/bundle-transaction.mjs) |
| pacote cujo conteúdo bate com um **blob antigo** do git | "este ZIP esta ATRAS do espelho. Aplicar REVERTE esses arquivos" — veredito tirado do histórico do repo | [`receber-handoff.mjs:234,376`](../../scripts/design-sync/receber-handoff.mjs) |
| pacote que **reduz** o arquivo | "PERDE N LINHAS — confira se o espelho não está À FRENTE do vivo" | [`aplicar-payload.mjs:353-366`](../../scripts/design-sync/aplicar-payload.mjs) |
| o que **é** conteúdo do espelho | três listas de extensão diferentes: uma com `json`/`php`/imagens/fontes · uma que para em `md` · uma com dez extensões, sem imagem nem fonte | `bundle-contract.mjs:15` · `aplicar-payload.mjs:56` · `importar-bundle.mjs:98` |
| quem vence entre Cowork e git | "Cowork-autoritativo / repo é snapshot read-only" × "divergiu do git → o git vence" — no **mesmo arquivo** | [`PROCESSO_MEMORIA_CC.md:131`](../reference/prototipo-ui/PROCESSO_MEMORIA_CC.md) × `:271` |
| versão antiga mais completa | "onde o pacote trazia versão mais pobre do mesmo arquivo … prevaleceu a do repo" | [ADR 0398 §Consequências](0398-espelho-cowork-recebe-a-arvore-da-conta.md) |

A última linha já foi tratada pela 0404 como fato histórico. As demais não: são instruções em
tempo presente, e um operador que leia qualquer uma delas isoladamente decide **contra** a 0404
sem perceber.

## Decisão

**D1 — conteúdo: o pacote vence byte a byte.** Para todo arquivo que existe nos dois lados, o
espelho passa a ter exatamente os bytes do pacote. Não se funde, não se preserva trecho antigo,
não se escolhe "a versão mais completa", não se restaura do histórico. Arquivo maior no git não
é argumento; é sinal de que alguém escreveu deste lado, o que é proibido.

**D2 — ausência: quem decide é o ESCOPO DECLARADO do pacote, nunca o operador.** "Prevalece
apenas o último importado" é uma regra **por arquivo**; o conjunto sobre o qual ela age vem do
escopo que o próprio pacote declara:

| escopo declarado | o que acontece com o arquivo que o pacote não traz |
|---|---|
| árvore completa (`mirrorScope: tree`, ZIP de árvore completa, sync com `/PURGE`) | **sai** — a ausência é informação da origem |
| delta / lote parcial | **fica**, e é **relatado como resíduo não medido** — a ausência não é informação, é silêncio |

Delta não autoriza poda, e árvore completa não autoriza preservar. Em nenhum dos dois casos o
operador escolhe: ele lê o escopo. Um pacote parcial **não** promove o espelho a "conferido" —
o que ele não cobriu continua sem prova, e dizer o contrário é transformar ausência de medição
em aprovação.

**D3 — direção: coincidir com blob antigo do git NÃO é prova de que o pacote é velho.** O git
mostra que aquele conteúdo já esteve versionado; ele não distingue **replay de um export
antigo** de **espelho editado deste lado depois do import**. Os dois produzem o mesmo sinal, e a
0404 resolve o segundo a favor do pacote. O desempate é a **identidade do pacote** — bundleId,
sequência e estado-base da 0379 — nunca o histórico do repo. Com pacote mais novo que o estado
ativo, "o conteúdo já esteve versionado" lê-se: *alguém alterou o espelho deste lado*; o pacote
prevalece e o achado vai para a origem.

**D4 — recusar é legítimo por REGRA, nunca por riqueza.** Continuam recusando o lote: falha de
integridade (bytes/sha/grafo), procedência não vinculada, escopo fora do destino, sequência ou
estado-base que não fecham, e o **dono declarado** de um caminho — `_ds/**` e
`prototipo-ui/design-system/` seguem o projeto Design System, não o export de telas. Não recusa,
e não justifica reconciliar à mão: o arquivo ter ficado menor, ter perdido linhas, ou parecer
pior que o anterior. Isso é **achado**, vira comunicação para a origem, e o import segue.

**D5 — anotação do Code não mora no espelho.** Errata, comentário, correção ou nota escrita
dentro de `prototipo-ui/cowork/<dono>/` é apagada pelo próximo import, **sem aviso e sem
conflito** — é consequência direta de D1. O que o Code precisa registrar vai para
`memory/reference/prototipo-ui/`; o que precisa mudar no conteúdo vai para a origem.

**D6 — a precedência é por conta e por escopo.** Vale dentro de `prototipo-ui/cowork/<dono>/`,
um dono de cada vez ([ADR 0405](0405-espelhos-cowork-independentes-por-conta.md)). Não alcança
código de produção, DS canônico, nem documentação canônica do Code.

## O que procurar antes de aplicar (roteiro)

Isto é roteiro de leitura, **não gate** — não há check automatizado para nenhum dos seis itens.
Cada linha é uma pergunta com a fonte que a responde:

| # | procure por | onde se responde | veredito |
|---|---|---|---|
| 1 | **escopo do pacote** — árvore completa ou delta? | manifesto (`mirrorScope`, `mode`) ou a flag da rota ZIP | define D2; sem isto, poda e preservação são ambas defensáveis |
| 2 | **identidade e sequência** — este pacote é posterior ao estado ativo? | `scripts/design-sync/state/` (por conta) × manifesto | define D3; resolve "atrás" sem consultar o git |
| 3 | **divergência com o espelho** | relato do próprio import, arquivo a arquivo | D1: o pacote vence; anotar o que muda, não impedir |
| 4 | **arquivo do espelho com histórico local** — commit que não seja de import | `git log -- <path>` no espelho | se existir, alguém escreveu deste lado (D5): o conteúdo morre agora, e o registro precisa migrar para `memory/` |
| 5 | **caminho de dono alheio** no lote — `_ds/**`, `design-system/`, canon do Code | mapa de destino do import | D4: recusa por regra, antes de qualquer escrita |
| 6 | **texto que contradiga a 0404** ao encostar no assunto | prosa e comentários que digam "fundir", "restaurar", "o mais completo", "o git vence", "não apaga", "está atrás" | cada um precisa dizer **em que escopo** vale, ou vira instrução para decidir contra o protocolo |

## Limite de implementação

Esta emenda registra precedência e roteiro. Ela **não** altera mecanismo, não arma gate e não
afirma que os importadores já concordem entre si — as três listas de extensão da tabela acima
continuam divergentes, e reconciliá-las muda o que entra no espelho, o que é decisão própria com
PR próprio. O que foi feito neste ciclo é retirar, dos pontos medidos, o texto que instruía o
contrário.
