---
date: "2026-09-16"
time: "15:15 UTC"
slug: motivo-da-nao-medicao-divida-paga
tldr: "Fecha a divida que o handoff das 14:15 deixou aberta — os dois caminhos de nao-medicao do C1 davam a MESMA mensagem, e a de um deles era falsa. Quem pagou foi a sessao irma, com o desenho dela. Este handoff existe porque o anterior e append-only e diz 'divida nao paga' — sem esta linha, o ponteiro apodrece."
prs: [7429]
decided_by: [W]
related_adrs: [0130-handoff-append-only-mcp-first, 0317-adr-0216-governanca-drift-framework]
next_steps:
  - "NADA pendente do eixo do limiar. A cadeia #7392 -> #7399 -> #7409 -> #7413 -> #7424 -> #7429 fechou: limiar medido, refutado, trocado por predicado, fail-open consertado, motivo da nao-medicao distinguido."
  - "SEGUE ABERTO, e nao e bug: os 8 achados que o C1 passou a expor no modo texto. Sao linhas de doc que dizem 'removido' sobre conteudo vivo em `prototipo-ui/cowork/Wagner`. A redacao certa e de quem escreveu cada uma — lista com doc/proporcao/dispersao no handoff das 14:15."
  - "PROMOCAO A REQUIRED do `mudou_de_casa` segue NAO recomendada. O argumento agora e o contador `nao_resolvidos` — 117 pares em 35 linhas, 8 sem medicao alguma."
  - "ACHADO SOLTO, de outro assunto: a lane `crons de governanca vivos? (watchdog G6 · ADR 0317)` estava VERMELHA no #7429. Conferido por correspondencia exata contra os 47 required — NAO e required, e o PR nao tocava cron. Nao investiguei."
  - "Rodar o checklist MCP-first ao abrir sessao nova. NAO pode ser rodado aqui — servidor `oimpresso` recusa o header de auth (HTTP 401)."
---

# A dívida do handoff anterior está paga — e não fui eu que paguei

## Por que este handoff existe

O das 14:15 fechou dizendo, com todas as letras: *"DIVIDA MINHA, nao paga (...) Fica como follow-up para o proximo toque no arquivo"*. O follow-up aconteceu **28 minutos depois**. Handoff é append-only, então a única forma de o próximo leitor não herdar um ponteiro podre é esta linha nova.

## O que era a dívida

Ao consertar o fail-open no #7424, criei um **segundo** caminho de `null` em `auditMudouDeCasa` e reaproveitei a mensagem do primeiro. Resultado medido: os dois imprimiam `sem base pra comparar`, e no caso novo isso é **falso** — a base existe; o que falta é o índice de blobs. E os dois se consertam de formas diferentes (buscar a base do diff × buscar a ref `origin/main` no checkout).

## Quem pagou

A sessão irmã, com o desenho que ela já tinha proposto no #7426 (fechado em favor do meu #7424). Eu avisei que o campo tinha se perdido e disse *"se tu pegares primeiro, melhor ainda, é teu desenho"* — ela pegou. Quando a [W] me mandou pegar, o **[#7429](https://github.com/wagnerra23/oimpresso.com/pull/7429) já estava aberto**; medi o remoto antes de criar a branch e parei.

Ela foi além do desenho original em um ponto que importa: o fallback virou `'motivo nao registrado'` em vez de reaproveitar uma das duas mensagens — o que impediria a mesma imprecisão de voltar em escala menor.

Verificado no arquivo de `origin/main`, não no retorno do merge:

| caminho | `medido` | motivo |
|---|---|---|
| sem base | `false` | `` sem base de diff (`git merge-base origin/main HEAD` vazio) `` |
| sem índice | `false` | `` indice de blobs vazio (`git ls-tree -r origin/main` sem saida — a ref existe neste checkout?) `` |

## O near-miss desta rodada

Ao conferir os dois caminhos, minha 1ª mutação **não aplicou** — o #7429 reestruturou o `if (!base)` de linha única para bloco, e meu `replace` não casou. A saída veio vazia e eu ia registrar *"o caso sem base não imprime nada"*. Peguei porque o vazio pareceu errado, refiz mirando o `const base` e **conferi que aplicou** antes de ler o resultado.

**Terceira vez no mesmo dia** que uma mutação silenciosa quase produz um resultado falso (as outras duas estão no #7413 e no handoff das 14:15). Nas três, o que salvou não foi o teste — foi conferir que a mutação alterou o arquivo. §5 2026-08-01.

## Estado MCP no momento do fechamento

⚠️ **NÃO consultado** — servidor `oimpresso` recusa o header de auth (`HTTP 401`); `laravel-boost` fecha a conexão. `cycles-active` / `my-work` / `sessions-recent` **não rodados**: ausência de medição, não "nada a reportar" (LC-33). Fallback: `ls memory/handoffs/2026-09-16-*` (3, este é o 4º) · `git log --diff-filter=A -- memory/decisions/` (0 ADRs novas hoje) · tip de main `1d80b9336d2`.
