---
status: proposal
title: Frescor do espelho Cowork — o SLA passa a medir o que NUNCA desceu, e a desqualificar comparação contra bundle velho
proposed_by: Claude Code (a pedido de [W])
proposed_at: 2026-08-27
relates_to:
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0325-import-prototipo-designsync-pull-direto
---

# PROPOSAL — o SLA do espelho mede o eixo errado, e o certo custa uma chamada

> **Status:** `proposal`. ADR é Tier 0 — **só [W] cunha.** Esta proposta nasce de [W] apontando
> o defeito real em 2026-08-27: *"eu sei que já tem modificações que ainda não foram baixadas,
> então dá para saber que as máquinas estão quebradas"*. Estava certo, e o número prova.

## 1 · O problema, medido

O `--sla` do `cowork-mirror-freshness` responde hoje:

```
última rodada 2026-08-26T22:07Z: 0 sync · 1 stale · 247 unchecked · mediu 1/248
```

**247 de 248 arquivos do espelho nunca foram comparados com o vivo.** Qualquer modificação neles
é invisível — inclusive no `jana-merge.jsx`, que é âncora declarada de 3 telas, e no
`chat-jana.css`, de onde saiu a medição que fundamentou um ajuste de DS nesta mesma sessão
([#6356](https://github.com/wagnerra23/oimpresso.com/pull/6356)).

A causa não é o script. É que o `--compare` precisa de um `get_file` **por arquivo** (auth
interativa, ADR 0315 — o CI não alcança), então ninguém roda os 248. O instrumento é honesto:
diz `rc=1` e admite o `unchecked`. Mas **um instrumento honesto que ninguém aciona é
indistinguível, na prática, de um instrumento quebrado.**

### 1.1 · Dois eixos que estavam colapsados num só

| eixo | pergunta | quem responde hoje | estado |
|---|---|---|---|
| **MODIFICADO** | o arquivo mudou desde que desceu? | `--compare` | **1 de 248** |
| **NOVO** | existe no vivo e nunca desceu? | `--live-only` | roda, mas o SLA audita a IDADE do registro, não o objeto |

O `--sla-live-only` chegou a dizer `✓ 2 live-only` enquanto havia **4** — e os 2 que exibia
(`CLAUDE.md`, `github.md`) sequer podem pousar em `cowork/` por R1 do `ssot-guard`. Corrigido em
[#6352](https://github.com/wagnerra23/oimpresso.com/pull/6352); o eixo continua sem gatilho.

## 2 · A proposta — UMA peça

O `--sla` passa a reportar o **eixo NOVO** com o denominador do bundle, e a usar esse resultado
para **desqualificar** conclusões sobre o eixo MODIFICADO:

> `há N arquivos no vivo fora do bundle (emitido em <data>) — qualquer comparação contra ele é
> inconclusiva sobre o vivo`

Custo: **uma** chamada `DesignSync.list_files`, comparada contra a lista do
`sync/bundle.manifest.json`. Sem `get_file` por arquivo.

**Não é régua nova.** O `--live-only` e o `liveOnlyVerdict` já existem no mesmo módulo; a mudança
é o `--sla` passar a consumi-los e a declarar a inconclusividade — hoje ele cala sobre isso.

## 3 · O que foi TESTADO — e o que o teste REPROVOU

A proposta original tinha **duas** peças. A segunda foi testada e caiu.

### 3.1 · Testado e aprovado: o manifesto é comparável

`sha256` e `bytes` de **255/255** arquivos no manifesto. Comparado contra o espelho local:

```
BATE (espelho == bundle): 255 · DIFERE: 0 · AUSENTE: 0
```

Hash cru bate direto, sem normalização especial. Uma chamada daria 255 hashes onde hoje se
medem 1.

### 3.2 · Testado e REPROVADO: usar isso como veredito de frescor

O mesmo teste que aprovou a mecânica **reprovou o uso**. `255/255 sync` é o **verde falso** que a
lápide [§5 2026-08-25](../../licoes-rejeitadas.md) já havia nomeado — *"aplicar o bundle NÃO
sincroniza o espelho com o vivo; repõe o que já está lá"*. O espelho está fiel **ao bundle de
2026-08-24**, enquanto o vivo tem pelo menos 4 arquivos que o bundle não conhece.

Um medidor assim seria **pior que o atual**: o `--compare` de hoje diz `247 unchecked` e admite a
ignorância; o proposto diria `255 sync` e calaria.

**Por isso a proposta ficou com uma peça só.** O eixo NOVO deixa de ser "detector de arquivo
novo" e passa a ser **o que invalida qualquer leitura do eixo MODIFICADO** enquanto houver
pendência.

## 4 · O que esta proposta NÃO resolve — e é o principal

Ela **detecta melhor**; não conserta a origem. A causa raiz é de processo:

- o bundle é emitido por `gerar-payload-partes.mjs`, que roda **do lado que tem os arquivos em
  disco** (`:9-12`) — não do consumidor, por desenho;
- **não há automação**: varredura no repo mostra que os únicos invocadores são testes de CI e
  documentação — nenhum cron, hook ou workflow;
- **não há gatilho**: o passo não é o fim de nenhum ciclo declarado.

Enquanto emitir o bundle depender de alguém lembrar, a cegueira volta. A alavanca real é
**tornar a emissão o fim do ciclo de design** — e isso é decisão de processo, não de código.

## 5 · Alternativas descartadas

| alternativa | por que caiu |
|---|---|
| Rodar `--compare` completo (248 `get_file`) | custo proibitivo; é a razão de ninguém rodar hoje |
| Comparar contra o manifesto como veredito | **testado**, §3.2 — verde falso, lápide §5 2026-08-25 |
| Gate novo de frescor | duplicaria régua consolidada (§5 2026-07-09); o dono é o `cowork-mirror-freshness` |
| CI medir frescor | impossível por construção — auth interativa (ADR 0315) |

## 6 · Limites honestos desta proposta

- **O `--sla` continua sem poder rodar no CI.** Fica acionável por qualquer sessão logada, a uma
  chamada — não vira gate.
- **`list_files` não traz data nem hash.** Detecta ausência, nunca modificação. O eixo MODIFICADO
  segue sem oráculo barato, e esta proposta não finge o contrário.
- **O teste de hash foi feito contra o bundle**, não contra o vivo. Prova que a mecânica de
  comparação funciona; não prova nada sobre frescor.
- Nada aqui promove check a required nem altera enforcement.

## 7 · Decisão pedida a [W]

1. Aceitar a peça do §2 (eixo NOVO no `--sla` + desqualificação declarada)?
2. Aceitar o §4 como decisão de processo separada — **emitir o bundle vira o fim do ciclo de
   design** —, e se sim, onde ela mora (o dono do tema é `memory/reference/FLUXO-DESIGN.md`)?
