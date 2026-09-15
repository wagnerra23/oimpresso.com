---
date: "2026-09-15"
time: "20:11 UTC"
slug: rec-suficiencia-e-dedupe-lc08
tldr: "Continuação do handoff das 18:41. Registrei a afirmação que eu mesmo publiquei em canon e a medição derrubou (o matcher era necessário e NÃO suficiente), e deduplicei a LC-08 — o bloco tinha 161 linhas de recibo para 81 únicas, 80 reprises de resolução de conflito. O ciclo-adversary deu REJECT na 1ª redação e achou que eu ia escrever o contador errado, que é a própria classe do registro."
prs: [7348, 7349]
decided_by: [W]
related_adrs: [0344-two-strikes-cobre-processo]
next_steps:
  - "Mergear o #7349 quando o PHP / Pest (Unit) fechar — 82 pass, 0 fail, guarda armada para não mergear com vermelho"
  - "Chip task_65bfe89f (matcher + ehMencao do P5) segue rodando em sessão própria"
---

## Estado MCP no momento do fechamento

Tools MCP do servidor oimpresso **indisponíveis** (não conectado) — fallback canônico de
[`how-trabalhar.md`](../how-trabalhar.md) §Fallback: git + `gh` + as máquinas locais.

Medido em `origin/main` pós-merge, não em branch:

```
--reconcile           rc=0   123/123 recibos · 0 pendurados
sec5-derive --check   rc=0   193 limites, 0 perdidos
maquinas-inventario   rc=0   592 máquinas, 0 faltando · 0 ghost
```

PRs da sessão: **#7322 · #7327 · #7331 · #7336 · #7348 mergeados**; **#7349** aberto,
82 pass / 0 fail / 1 pendente (`PHP / Pest (Unit)`), merge armado com guarda.

## O que aconteceu

Dois atos, encadeados por uma medição que [W] pediu.

**1. O rec da afirmação derrubada ([#7348](https://github.com/wagnerra23/oimpresso.com/pull/7348)).**
No `rec` da LC-13 mergeado às 18:38 eu escrevera que faltava medir *"se o harness emite
`PreToolUse` para o tool `Monitor`"*. Medido: **o pré-requisito estava satisfeito** (sonda
temporária registrou `DISPAROU tool_name=Monitor`, com controle positivo `Glob`; payload traz
`tool_input.command`) — **e o candidato mesmo assim não pegava o caso**. O bite-test com o
comando real deu `exit=0` **inclusive no payload de Bash, que deveria morder**: o `ehMencao`
casa `echo`/`printf` antes do match e na mesma linha, e desliga o P5. Classe **LC-08**
(afirmar sobre SUFICIÊNCIA medindo metade da fonte), não LC-15.

**2. O dedupe da LC-08 ([#7349](https://github.com/wagnerra23/oimpresso.com/pull/7349)),
autorizado por [W].** O bloco tinha **161 linhas `- **rec**` e 81 ÚNICAS** — 80 reprises
byte-idênticas, com assinatura de resolução de conflito que guardou os dois lados, 3×.
Removidas só as repetições, mantendo a primeira ocorrência: **0 únicas perdidas, 0 ganhas**,
linhas não-`rec` intactas, ordem preservada. O ato ficou registrado em comentário dentro do
bloco — num arquivo append-only, 80 linhas sumindo sem explicação é pior que a duplicata.

## O rito derrubou a 1ª redação, de novo

`ciclo-adversary` rodado antes do canon: **REJECT**, e os 5 achados de fato conferiram na
reverificação. O mais duro: **eu ia escrever "160 → 161"** — contando a string sem perguntar se
cada hit era ocorrência distinta, que é **a própria classe que o rec registra**. Também caíram
três erros de mecanismo meus (o `echo` posterior **não** desliga; em outra linha **não**
desliga; e `echo "use o jq"` é protegido pelo **regex**, não pelo `ehMencao`) e o "sub-eixo
novo" — §5 2026-07-22 já o nomeia com a palavra *matcher*.

## Os três eventos de CI exigiram respostas diferentes

| evento | verdade medida |
|---|---|
| #7348 advisory | dívida herdada do #7347 — **não era minha**; dono no #7351 |
| #7349 conflito | retrato de um **SHA já superado** — nada a fazer |
| #7349 advisory | **agora era meu** — branch atrasada, sem o conserto que já existia no main |

Tratar os três igual teria consertado escopo alheio, refeito um merge e ignorado o que era real.

## Conflito resolvido sem repetir a dívida

O conflito do #7348 era **1 recibo de cada lado** — a configuração exata que gerou as 80
reprises. Resolvido por união: `161 + 161 → 162 linhas / 82 únicas`, faltam 0, sobram 0, e a
duplicata herdada **não aumentou**. Depois do squash-merge, o #7349 divergiu e foi reconciliado
pelo dedupe determinístico, que **não teve nada a remover**.

## Persistência

- **git canônico:** 5 PRs mergeados; o #7349 aberto com guarda de merge.
- **MCP:** webhook propaga `memory/**`.
- **Chips:** `task_adfa9577` virou o **#7345** (mergeado); `task_65bfe89f` segue rodando.

## Próximos passos pra retomar

```bash
node .claude/hooks/licoes-code-two-strikes.mjs --reconcile
```

Deve dar `123/123 · 0 pendurados`. Se o #7349 já tiver mergeado, a LC-08 estará em
**82 linhas / 82 únicas / excedente 0**.

## Lições catalogadas

Além da registrada no #7348: grepei `✗` num log de CI e tratei como falha **sem checar o rc** —
o caso negativo de um teste imprime exatamente isso (`design-return-check.test.mjs` sai rc=0).
E sem o **controle na base limpa** eu teria "consertado" três falhas que não eram minhas.
Nenhuma das duas abriu entrada nova: ambas já têm casa no §5.
