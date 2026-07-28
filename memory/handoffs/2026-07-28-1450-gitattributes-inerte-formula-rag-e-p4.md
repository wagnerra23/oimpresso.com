---
date: '2026-07-28'
time: '14:50'
slug: gitattributes-inerte-formula-rag-e-p4
tldr: '.gitattributes estava inerte há 14 meses (aspas viram pattern) — corrigido sem renormalizar nada. A fórmula de escrita pro RAG virou doc canônico derivado do indexador/chunker reais, e revelou que o RAG servia a versão ERRADA da pegadinha de junction. Quatro erros meus de medição (LC-08), o primeiro deles agora mecanizado pelo par P4 do block-instrumento-sem-porta-viva.'
decided_by: ['W']
cycle: CYCLE-06
prs: [4945, 4946, 4951, 4955, 4957]
next_steps:
  - 'P9 — excerpt de 400 chars é TODO o contexto do LLM; maior alavanca, não depende de nada'
  - 'P1 — trocar a FONTE do filtro (coluna tipada) no scopePorStatusAtivo; owner [F]'
  - 'P12/P11 — runbook manda adicionar mwart-gate (deletado) aos required = risco de deadlock de main'
  - 'P4-BRL — 21 valores BRL reais em 8 arquivos de memory/requisitos/ (decisão [W], não morto)'
related_adrs: ['0053-mcp-server-governanca-como-produto', '0256-knowledge-survival-meia-vida-catraca-sentinela']
---

# Handoff 2026-07-28 14:50 — `.gitattributes` inerte, a fórmula do RAG, e o P4

> Cinco PRs, todos MERGED: [#4945](https://github.com/wagnerra23/oimpresso.com/pull/4945) · [#4946](https://github.com/wagnerra23/oimpresso.com/pull/4946) · [#4951](https://github.com/wagnerra23/oimpresso.com/pull/4951) · [#4955](https://github.com/wagnerra23/oimpresso.com/pull/4955) · [#4957](https://github.com/wagnerra23/oimpresso.com/pull/4957).
> Session log: [2026-07-28-gitattributes-inerte-e-formula-do-rag.md](../sessions/2026-07-28-gitattributes-inerte-e-formula-do-rag.md).

## Estado em que o próximo pega

`.gitattributes` corrigido — `git check-attr text eol -- <arquivo>` devolve `auto`/`lf`. **Arquivos novos passam a nascer LF; os 1.456 CRLF existentes ficam intocados.** Renormalizá-los seria ~180k linhas e é decisão separada, **não** feita aqui.

`memory/reference/como-escrever-doc-para-o-rag.md` é o doc canônico de como escrever para ser recuperável. **Leia antes de escrever doc que a IA precise achar.**

O hook `block-instrumento-sem-porta-viva.mjs` ganhou o par **P4** e está vivo em `main` (selftest 47/47, já rodado no CI). Ele **bloqueia** contagem com pathspec cru quando o número diverge de `:(glob)`.

## O que NÃO é o que parece (leia antes de agir)

**Não há chunking no RAG hoje.** O `DocumentChunker` só é instanciado dentro de `aplicarContextualRetrieval()`, que retorna cedo com `JANA_CONTEXTUAL_RETRIEVAL=false` — o estado em prod. O documento é indexado **inteiro**, e o que chega ao modelo são os **primeiros ~400 chars** (`extrairExcerpt(content_md, 400)`).

**O descasamento de `status` é do FALLBACK, não do caminho vivo.** Medido no banco: híbrido (primário, `docs_pipeline=true`) tem **1.958 de 2.015 visíveis (97,2%)** porque lê a **coluna tipada**; o FULLTEXT lê `metadata->status` cru e descarta 285. Dos 57 fora no híbrido, **47 são `deprecated`/`rascunho`/`superseded` — corretos**. Quem escreve **não** precisa se preocupar com `status` hoje.

**`memory/requisitos/<Mod>/` indexa só 9 nomes exatos** — `RUNBOOK-criar-modulo.md` não casa `RUNBOOK`. Medido: **481 de 744** ficam fora, incl. 125 `RUNBOOK-*`.

## Números — e a armadilha que os produziu errados 2×

Use **`:(glob)`** ao medir cobertura de glob que vive em código. Sem ele, o `*` do pathspec **atravessa `/`** e o número sai maior:

```bash
git ls-files ':(glob)memory/requisitos/*/*.md' | wc -l   # 744  (o que o indexador vê)
git ls-files 'memory/requisitos/*/*.md' | wc -l          # 1046 (conta subpasta) — o hook barra
```

E **confira em que branch está o diretório**: `git ls-files` lista o índice **daquele** worktree. O repo principal `D:\oimpresso.com` estava **248 commits atrás**, numa branch de outra sessão — foi o segundo erro do dia.

## Fila deixada, por dependência (não por severidade)

O adversário de consolidação matou 8 de 16 achados. Sobreviveram, nesta ordem:

1. **P9 — o excerpt de 400 chars.** Maior alavanca; não depende de nada. Degrada **todas** as respostas, inclusive as 1.958 que funcionam.
2. **P1 — trocar a fonte do filtro** em `scopePorStatusAtivo` (`metadata->status` → coluna tipada, que já existe ao lado). **Não** normalizar documento em massa. Owner **[F]** (matriz §3).
3. **P12/P11 — contradições perigosas.** `Infra/RUNBOOK-branch-protection.md` manda adicionar `mwart-gate` aos required contexts; o workflow foi **deletado** (ADR 0271) — reintroduzir = deadlock de `main`.
4. **P2 — allowlist por família**, nunca "os 481". Não antes de 1–3.

**Discordância registrada:** o adversário matou o item dos **21 valores BRL em 8 arquivos** de `memory/requisitos/` alegando que o hook já fecha forward. O hook fecha o **futuro**; os arquivos existentes continuam lá e ficam recuperáveis por busca se a indexação avançar. Deixei como **decisão do [W]**, não como morto.

## Estado MCP no momento do fechamento

⚠️ **O servidor MCP estava indisponível** (`cycles-active` e `my-work` retornaram *"Server unavailable"*). Usei o fallback do `how-trabalhar.md`, mas pelo **oráculo certo** — consulta direta ao banco via container `oimpresso-mcp` no CT 100 (`u906587222_oimpresso`), não por leitura de arquivo.

- **Cycle:** `CYCLE-06` (2026-07-21 → 2026-08-03) em **`planning`** — nenhum cycle com `status=active`.
- **Tasks de wagner:** 6 em `review` (US-KB-002, US-KB-001, US-TR-309, US-PROD-025, US-PROD-027, US-INFRA-023) + 2 `blocked` (US-NFE-043, US-NFE-044).
- **ADRs tocadas em 7d:** 0354 (TeamMcp Pest required), 0353 (máquina de evolução de réguas), e re-toques em 0001/0002/0003.
- **Docs indexados:** 2.016.
- **Handoff anterior:** [2026-07-28 14:40](2026-07-28-1440-regra-do-dono-vira-teste-e-o-momento-de-ler-o-charter.md).

## Registro de aprendizado

**LC-08 22 → 23**, e o gate saiu de 3/9 para **4/9**. O P4 é o primeiro par cuja segunda perna é **medição** em vez de condição — o hook roda as duas formas do glob e só morde se divergirem.

Corte do [W] que produziu isso, textual: *"arrumar a maquina sempre vai ser melhor que fazer mão"*. Eu havia concluído que a classe era não-mecanizável; era desistência disfarçada de constatação. **Placar honesto: 1 de 4 erros virou máquina** — os outros três seguem sem mecanismo próprio.

Duas vezes nesta sessão uma máquina do projeto barrou o autor: o `block-instrumento-sem-porta-viva` (P4, no comando do próprio incidente) e o `memory-schema` (no frontmatter deste handoff, `decided_by: 'C'` fora do enum). Nos dois casos a mensagem trouxe o motivo e o conserto — que é o padrão que se quer.
