---
date: "2026-08-16"
time: "05:40 BRT"
slug: guia-b9-manual-orfaos-e-a-ancora-que-quebraria-na-tela
tldr: "Continuação do handoff das 20:35 — os 4 PRs que ele não cobre. Construí o --orfaos (fecha detectar→consertar, −265 links mortos no piloto) e seu RUNBOOK; então [W] perguntou 'ainda não sei como usar isso, alguém fez um manual?'. O manual existia — o erro foi meu: escrevi num diretório técnico que a tela /documentacao não lê. B9 entra no GUIA, que é o dono. E medindo o consumidor peguei que minha própria âncora quebraria ali: o controller extrai código por regex, não slug."
prs: [5818, 5820, 5823, 5822]
decided_by: [W]
next_steps:
  - "Os `####` do GUIA (B6.1, B7.1, B8.1-8.4) não recebem âncora: o indexador do DocumentacaoController casa `<h([23])>` e para em h3. Nada quebrado — apenas não são linkáveis. Conserto é uma linha no regex, mas mexe em como TODA âncora é gerada; medir antes."
  - "O `--orfaos` fechou o elo detectar→consertar, mas quem PAGA a dívida ainda é decisão humana por escopo. O piloto foi −265; o restante do corpus não foi varrido. Rodar por diretório, conferindo `git diff --numstat` a cada leva, é o ciclo que a B9 descreve."
  - "MCP inalcançável a sessão INTEIRA (token ausente/inválido em settings.local.json) — 2ª sessão seguida. Operei em fallback filesystem o tempo todo. Nenhum snapshot de cycles-active/my-work existe neste handoff porque não havia como obtê-lo, não porque pulei."
---

# O manual do `--orfaos`, e a âncora que quebraria exatamente na tela

Continuação do [handoff das 20:35](2026-08-15-2035-jana-espelho-defasado-ciclo-e-9-prs.md), que fecha em **#5816**. Os 4 PRs abaixo ficaram fora dele.

## Estado MCP no momento do fechamento

**Não obtido — MCP indisponível.** O hook de SessionStart reportou `FALLBACK ATIVADO — token Authorization ausente/inválido em settings.local.json`, e nenhuma tool `mcp__oimpresso__*` esteve acessível. Fallback filesystem conforme [how-trabalhar §Fallback](../how-trabalhar.md). Registro a ausência em vez de omitir: `cycles-active`, `my-work` e `decisions-search` **não foram consultados porque não havia canal**, e isso é diferente de terem sido pulados.

## O que aconteceu

**#5818 — `doc-auto-relink --orfaos`.** O repo detectava link morto (`deadlink-gate`) e não tinha como consertar em lote: a catraca acusava e o pagamento era à mão. O `--orfaos` fecha esse elo. No piloto, **−265 links**. Ele é idempotente e declara **6 recusas** — casos em que se nega a agir e **conta o que não fez**, em vez de silenciar.

**#5820 — o RUNBOOK técnico.** 8 casos de uso virando teste, suíte **19→27**.

**#5823 — o relógio da Jana.** `BriefDiarioAgentTest` falhava **1h por dia** — janela de saudação cruzando a fronteira. Congelado.

**#5822 — e aqui o pedido de [W]:** *"Ainda não sei como usar isso. Alguém fez algum manual?"*

O manual existia e tinha até tela: `oimpresso.com/documentacao`, servida pelo `DocumentacaoController` a partir de `memory/GUIA-DO-SISTEMA.md`. **O erro foi meu** — escrevi o RUNBOOK em `memory/requisitos/Infra/`, junto com outros 148, um diretório técnico que a tela **não lê**. Medido: o GUIA citava `deadlink` 2×, nenhuma explicando como **pagar** a dívida. Escrever documentação onde o caminho humano não passa é o mesmo que não escrever — só que com a aparência de feito.

Entrou a **B9 "Manutenção — a catraca acusou, e agora?"** no dono existente, sem doc novo (§5 2026-07-23 mata mapa/índice paralelo): o que é uma catraca, o ciclo de 4 passos (VER → APLICAR por escopo → CONFERIR com `git diff --numstat` → TRAVAR com `--write-baseline`), as 3 perguntas que sempre aparecem, e a tabela do que nunca é automático. Mais 1 linha na tabela "uma pergunta, um dono", que tinha o **detectar** e não o **consertar**.

## Lições catalogadas

**LC-22 pego ao vivo, e é o achado.** O `DocumentacaoController` **não usa slug do título** — extrai o código com `preg_match('/^([AB]\d{1,2})\./u')`. Logo `### B9. Manutenção — …` vira `id="b9"`, não o slug longo. Meu link funcionaria **no GitHub** (onde eu olharia) e quebraria **exatamente na tela** que era o ponto do PR. A mesma medição revelou que o único link interno preexistente estava quebrado desde sempre: `#b7-como-especificar-…` → `#b7`. Doc que a máquina lê é código com cara de doc: valida-se **rodando o consumidor**, não relendo o texto.

**Um susto que quase virou afirmação falsa.** Ao conferir a B9 no `main` pós-merge, busquei `^## B9` e voltou **vazio** — como se a seção não tivesse entrado. Não era o conteúdo: o heading é **h3**, e eu pedira exatamente dois `#`. Reabri com `^#{1,6} +[AB][0-9]{1,2}\.` e estava lá, junto com as outras 30. **Grep estreito devolve vazio, e vazio parece ausência** (§5 2026-07-28/07-31). O que segurou foi não declarar nada em cima daquele vazio.

**A máquina funcionou contra mim, e estava certa.** Ao levantar o estado da sessão rodei `git log --since=…`; o hook `block-instrumento-sem-porta-viva` **barrou**: clone raso, aquela data devolveria o piso do clone, não a história. Consertei o instrumento (API do GitHub como oráculo) em vez de contornar com a escape valve. No mesmo fechamento, o `block-memory-drift` barrou Edit **e** Write neste handoff e o `block-destructive` barrou um `rm -f` — os três sobre rascunho não-commitado, ou seja **falso-positivo por construção**. Ainda assim a resposta certa foi o caminho que o próprio hook sanciona (arquivo novo, nome novo), nunca o override Tier 0, que é do [W].

**Um `rc` que li errado, na minha própria verificação.** Rodei `node validate.mjs … | tail` e li `rc=0` como "passou": era o `$?` do **`tail`**. O `rc` real do node era **2** (deps ausentes) e, depois de instalá-las, **1** — o gate mordeu de verdade e pegou `tldr` acima de 500 chars na primeira versão deste próprio arquivo. §5 2026-08-13 na veia, cometido enquanto eu escrevia o handoff que o cataloga.

## Persistência

- **git** — 4 PRs squash-merged no `main`, o último `ce32be44`.
- **MCP** — sem propagação: canal indisponível a sessão inteira (ver acima).
- **BRIEFING** — não aplicável: nenhum PR alterou capacidade de módulo de negócio.

## Próximos passos pra retomar

```
Abrir oimpresso.com/documentacao e procurar B9 no trilho lateral.
O ciclo de manutenção está lá; o RUNBOOK técnico (#5820) é o aprofundamento que ela aponta.
```

## Pointers detalhados

- Handoff anterior desta sessão: [2026-08-15 20:35](2026-08-15-2035-jana-espelho-defasado-ciclo-e-9-prs.md)
- Manual humano: `memory/GUIA-DO-SISTEMA.md` §B9 · rota `/documentacao`
- RUNBOOK técnico do `--orfaos`: `memory/requisitos/Infra/` (via #5820)
