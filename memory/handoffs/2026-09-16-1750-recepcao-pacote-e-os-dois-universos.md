---
date: "2026-09-16"
time: "17:50 UTC"
slug: recepcao-pacote-e-os-dois-universos
tldr: "O pacote v2 do handoff 20 veio CONFORME e não havia nada a promover — o bundleId regerado era idêntico ao ativo. Os 36 do cowork-inbox desceram por decisão [W], e a medição do por que eles eram invisíveis achou um defeito real: o espelho tem dois universos (frescor × continência) e o liveOnly usava o errado, acusando 327 arquivos versionados como nunca-descidos."
prs: [7414, 7422, 7437]
decided_by: [W]
related_adrs: [0398-espelho-cowork-recebe-a-arvore-da-conta, 0390-bundle-design-build-only, 0374-emenda-0315-espelho-cowork-e-rota-prevista]
next_steps:
  - "Decisão (a) pendente de [W]: o exportPlan recusa .md (decisão [W] 11/09, pinada em teste) enquanto a ADR 0398 D2 (13/09) diz que .md é conteúdo do espelho — a 0398 não menciona aquele mecanismo. Reconciliar libera relaxar o galho .md do liveOnlyDetalhado."
  - "Upstream no Cowork (CODE_NOTES de hoje): restaurar o conteúdo perdido dos 17, NÃO verbatim (o achado D1 morreu em 08/09); corrigir os 8 paths que não existem em árvore nenhuma; dizer o que causou a perda."
  - "D-RECEPCAO-FALHA e D-QUEM-REGENERA seguem abertas de direito — o receber-handoff já responde as duas de fato (falha fechada; nunca commita pacote). Ratificar fecha sem código."
---

# Recepção do pacote 16/09, os 36 do cowork-inbox, e os dois universos do espelho

## Estado MCP no momento do fechamento

- `cycles-active` — **nenhum cycle ATIVO** em COPI.
- `my-work` — **sem tasks ativas** pra `@wr23`.
- `sessions-recent limit:3` — os 3 últimos indexados são de **2026-08-22** (arte/estado-da-arte); o índice MCP está atrás dos session logs de hoje.
- `whats-active hours:3` — 4 sessões, 2 em worktrees irmãos (`oimpresso-erp-lista-853e70`, `sleepy-pike-8779ac`/branch base); **nenhum Edit/Write na janela** nos meus paths.
- `decisions-search "espelho cowork bundle manifesto frescor"` — 0324 (frescor do espelho, SLA+ledger) e 0149 no topo; nada aceito hoje que colida com o que mexi.

## O que aconteceu

[W] entregou o ZIP do handoff 20. Segui a **rota canônica do painel** (`receber-handoff.mjs --zip --conta w`), não a receita manual que o pedido trazia — aquela foi substituída por máquina em 2026-09-10, por decisão [W] no dia. O pacote veio **`CONFORME`** (o de 07/09 saía `FORA-DO-CONTRATO`: primeira vez que a recepção não precisou descartar o `sync/`), e o gerador canônico fechou um bundle com **`bundleId` idêntico ao ativo desde 14/09** — delta `+0 ~0 -0 =278`. **Nada a promover.** Não rodei `--apply`: com delta zero ele não escreveria conteúdo e só carimbaria hora nova em 3 arquivos de `state/`, produzindo um commit que diz "importado" sobre importação que não houve.

Os 281 do pacote × os 278 do gerador eram **5 arquivos, todos `_ds/**`** — 3 `woff2` que o grafo não alcança (a sessão irmã provou depois que são **byte-idênticos** ao `-400`, quatro nomes um arquivo) e 2 resolvidos por regra, espelho vencendo.

[W] então mandou trazer os **36 de `cowork-inbox/`**. Eu havia levantado que 9 dos 17 divergentes tinham o espelho à frente; ao medir descobri que os 17 têm **um commit só** — foram importados, nunca editados aqui —, o que inverte o argumento: a [ADR 0398 D1](../decisions/0398-espelho-cowork-recebe-a-arvore-da-conta.md) manda espelhar. Desceram os 36, com as 510 linhas substituídas registradas no corpo do PR e recuperáveis em `ba8e812d687`.

Investigar **por que os 19 novos eram invisíveis** virou o achado do dia. O `liveOnly` classificava **327** paths versionados de `cowork-inbox/` como *"existe no vivo e NUNCA desceu"*, porque o universo do manifesto filtrava por extensão de build e a subárvore inteira (378 arquivos, 87% `.md`) ficava fora. Conserto: **dois universos com nome** — `frescor` (o que pode ser comparado) e `conteudo` (o que o espelho tem), um por pergunta. Falso-ausentes → **0**.

## Artefatos gerados

| artefato | onde | tamanho |
|---|---|---|
| Recibos `_saida-01` + `_saida-02` + pedido + patch de índice | `prototipo-ui/cowork/Wagner/cowork-inbox/recepcao-pacote/playbook/` | 5 arquivos, +363 ln |
| Os 36 do `cowork-inbox` (19 novos + 17 espelhados) | `prototipo-ui/cowork/Wagner/cowork-inbox/` | 36 arquivos, +1855/−542 |
| Devolutiva ao Cowork | `memory/reference/prototipo-ui/CODE_NOTES.errata-patrimonio-perdida-no-handoff-20-2026-09-16.md` | +145 ln |
| Os dois universos + bite-test de 5 asserts | `scripts/governance/cowork-mirror-freshness{,.test}.mjs` | +68/−10 |
| Lápide §5 + 3 recs no ledger | `memory/licoes-rejeitadas.md` · `memory/LICOES_CODE.md` | LC-08 → 94 · LC-26 → 15 |

## Persistência

- **git** — 3 PRs mergeados: [#7414](https://github.com/wagnerra23/oimpresso.com/pull/7414) (recibo do ciclo), [#7422](https://github.com/wagnerra23/oimpresso.com/pull/7422) (os 36 + devolutiva), [#7437](https://github.com/wagnerra23/oimpresso.com/pull/7437) (`547419f3bdfd`, os dois universos). Branch do #7437 deletada (404 conferido no servidor, não na ref local).
- **MCP** — nada a atualizar: sem cycle ativo e sem task minha. Este handoff propaga pelo webhook.
- **BRIEFING** — não se aplica: nenhuma capacidade de módulo mudou (tudo governança + design-memory).

## Próximos passos pra retomar

```
node scripts/design/protocolo.config.mjs && node scripts/governance/cowork-mirror-freshness.mjs --sla-live-only
```

Os 3 itens abertos estão no `next_steps` do frontmatter — o primeiro (a decisão **(a)**) é o que destrava o resto.

## Lições catalogadas

Três `- **rec**` no ledger, e as duas piores são de formato, não de técnica:

1. **LC-08** — recomendei ao [W] remover um galho alegando premissa vencida; o `exportPlan` **ainda recusa `.md`** hoje, e [W] respondeu *"merge"* em cima da minha premissa falsa. Só não virou código porque sondei antes do primeiro `Edit`.
2. **LC-08** — li a errata `[CL]` do espelho como edição local e publiquei isso em PR body. O que me pegou foi **um contador**: copiei 21 arquivos onde esperava 19.
3. **LC-26** — escrevi o emoji como par de surrogate *dentro do texto que registrava esta própria classe*, e como o script abria o destino com `io.open(p,'w')` o **ledger foi truncado a 0 bytes** (518.610 → 0). Recuperado por `git checkout HEAD --`, zero perda. As duas lições eram canon antes de eu começar.

O que funcionou como defesa, três vezes: **o número que não bate**. O 21≠19, o 327→38 que não zerava, e o `assert` da âncora. Nenhuma foi revisão de código.

## Pointers detalhados

- Session log: [`memory/sessions/2026-09-16-recepcao-pacote-e-dois-universos.md`](../sessions/2026-09-16-recepcao-pacote-e-dois-universos.md)
- Lápide §5 (emenda da 2026-08-25): `memory/licoes-rejeitadas.md`, cabeçalho *"consertei o predicado de um instrumento alinhando-o ao dono de OUTRA pergunta"*
- Recibos do ciclo: `cowork-inbox/recepcao-pacote/playbook/_saida-01.md` e `_saida-02.md`
