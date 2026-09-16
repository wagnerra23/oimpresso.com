---
date: "2026-09-16"
time: "11:00 UTC"
slug: poda-worktrees-e-token-fora-do-disco
tldr: "Continuação do handoff de 22:30. O token MCP saiu do disco no repo principal — mas o caminho até lá não foi migrar arquivo: foi descobrir que `mergeado em main` não implica `disponível para a sessão`, porque cada worktree carrega a própria cópia de `.claude/` congelada na criação. Medido: 1 de 52 locais sabia ler o formato novo. Migrar teria quebrado o `brief-fetch` em 51. Desfecho: 28 worktrees podados com zero perda, principal movido de um branch 161 atrás para `main`, e só então a migração."
prs: [7388, 7389]
decided_by: [W]
related_adrs: [0057-tela-team-admin-regras-governanca-tokens-mcp]
next_steps:
  - "Decidir se o `cc-watcher` desligado + autostart `.off` é definitivo (vira ADR/lápide) ou contenção temporária até ele ganhar redação (vira task com o conserto nomeado)"
  - "Decidir os pares de token 10 vs 30 (Wagner) e 11 vs 21 (Maiara) — o uso indica clientes concorrentes, não sucessão; revogar o antigo derruba máquina viva"
  - "25 `settings.local.json` seguem com o token literal, em worktrees que rodam hooks anteriores ao #7383 — some conforme esses worktrees fecharem, não precisa de ação"
  - "`verificacao-assessoes-abertas-fe87a0` não removeu (Permission denied, handle preso) e `mystifying-bouman-58cf5b` ficou de fora por estado ambíguo — ambos são resíduo, não risco"
---

# 2026-09-16 11:00 UTC — O token saiu do disco, e o caminho até lá não era migrar arquivo

## TL;DR

O token MCP saiu do disco no repo principal. O caminho **nao** foi migrar arquivo: o bite-test
do consumidor, rodado ANTES de escrever, deu `null` nos 4 casos — e a leitura obvia (*"a migracao
esta errada"*) era falsa. Era **consumidor errado**, importado de um checkout 161 commits atras.
Isso destravou o que de fato bloqueava: **cada worktree carrega a propria copia de `.claude/`,
congelada na criacao** — logo *mergeado em `main`* nao implica *disponivel para a sessao*.
Medido, **1 de 52** locais sabia ler o formato novo; migrar teria deixado 51 sem `brief-fetch`.
Desfecho: 28 worktrees podados com zero perda, principal movido de branch morto para `main` em
dia, e so entao a migracao — verificada com o consumidor real lendo o arquivo real.


> Continuação direta do [handoff de 2026-09-15 22:30](2026-09-15-2230-mcp-desligado-type-e-rotacao-token.md).
> Aquele fechou com a capacidade entregue (o `Authorization` passa a aceitar `Bearer ${ENV}`, [#7383](https://github.com/wagnerra23/oimpresso.com/pull/7383)) e a migração **não feita**. Este conta por que ela não podia ser feita naquele momento, e o que foi preciso antes.

## O que foi para o main

| PR | o quê |
|---|---|
| [#7388](https://github.com/wagnerra23/oimpresso.com/pull/7388) | Token MCP novo **nasce com validade de 180 dias** — o "ciclo de troca semestral" que a ADR 0057 linha 155 já tinha decidido e que dependia de alguém lembrar. Perpétuo agora se justifica (`--sem-validade`). |
| [#7389](https://github.com/wagnerra23/oimpresso.com/pull/7389) | O `.gitignore` cobria `/.claude/settings.local.json` por **caminho exato** — qualquer cópia com sufixo (`.bak`, `.off`, `.TOKEN-ANTIGO.off`) era commitável, e a rotação de ontem produziu uma. Virou glob + negação do `.example`, com bite-test e dois controles negativos. |

Dois defeitos pegos dentro do #7388 antes de virarem comportamento: a saída do comando ia imprimir **"nunca"** para um token de 180 dias (lia a variável local, não o valor gravado — LC-15), e três asserts do `Wave23ScorecardRotateTest` mediam `expires_at->toBeNull()` como **proxy de forma** para "o rotate não tocou este token" (LC-11); iam quebrar sem nada errado ter acontecido.

## O achado que reorganizou a noite

A migração dos `settings.local.json` parecia um `sed` com cuidado. Antes de escrever, rodei o bite-test do consumidor real. **Deu `null` nos quatro casos, inclusive com a env presente** — o oposto do que os 9 asserts do próprio hook afirmam.

Quase publiquei *"o formato novo não é lido"*. Era **consumidor errado**: o `import` apontava para o repo principal e o `grep` que eu lia era do worktree. O principal estava em `codex/prototipo-ssot-cleanup`, **161 commits atrás**, sem a linha de expansão. Registrado como `rec` n+19 na LC-08 e lápide §5 2026-09-16 — mesma sub-classe do n+15 ("medi a ÁRVORE ERRADA"), agora no eixo *checkout*, não *branch*.

O que isso destravou é maior que o erro:

> **Cada worktree carrega a própria cópia de `.claude/`, congelada no commit de criação.**
> Logo *"mergeado em `main`"* **não** implica *"disponível para a sessão"*.

Medido no dia: **1 de 52** locais com `settings.local.json` tinha o hook capaz de expandir `${ENV}`. Aplicar a migração teria deixado 51 sessões sem `brief-fetch`. O bloqueador nunca foi o token — era a população de cópias velhas do consumidor.

## A poda, e por que ela era o caminho

52 arquivos com o token em claro não eram 52 problemas: eram o sintoma de **51 worktrees abandonados**, cada um carregando uma cópia do segredo *e* uma cópia velha de todo hook.

**28 worktrees podados, zero perda de trabalho.** Critério: PR **MERGED** + nada não-commitado + nada não-pushado + branch sumiu do remoto.

⚠️ **O footgun documentado em [`proibicoes.md`](../proibicoes.md) §Ambiente foi medido, não presumido.** `git worktree remove` com junction `vendor/`/`node_modules/` esvazia o alvo **real** do repo principal — dois incidentes catalogados (318MB → 0 em 2026-05-11; `node_modules` em 2026-07-14). Varri os 67 diretórios: **zero junctions**. E validei o detector com **controle positivo** antes de confiar no zero — criei uma junction de teste e conferi que ele a acusa, e que uma pasta real não dispara. Controle de integridade do principal antes e depois de cada lote: `vendor=113` · `node_modules=707`, imóveis.

A primeira triagem deu **PODE=1 de 67** e o número estava errado por minha causa — contava como "trabalho" duas coisas que não são: o `?? prototipo-ui/cowork/_ds/` (cache gerado por hook) e os ` M .claude/...` (canon copiado na criação do worktree, **byte-idêntico ao `origin/main`**, medido em 3 de 3). Corrigido, virou 30. Segundo furo do meu próprio script: quando o branch sumia do remoto eu *assumia* `naopush=0` sem medir — fechado trocando o oráculo para o **estado do PR**, que é o que separa trabalho integrado de trabalho perdido num repo que faz squash-merge.

## O desfecho

Com `main` livre (o branch estava preso num worktree parado), o principal saiu de `codex/prototipo-ssot-cleanup` para **`main` em dia**. O hook novo veio junto. Só então a migração:

```
forma no arquivo : REFERENCIA (token fora do disco)
sem env          : rejeitado (fail-closed OK)
com env          : expandiu certo
```

Verificado com **o consumidor real lendo o arquivo real** — não com o texto revisado no olho.

## Estado MCP no momento do fechamento

⚠️ **O checklist MCP-first da [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) NÃO pôde ser rodado, e isto é declaração, não omissão:** `cycles-active`, `my-work`, `sessions-recent` e `decisions-search` estão **indisponíveis nesta sessão**. Medido: `ToolSearch` por tools `oimpresso` devolve zero. A causa não é config quebrada — o `.mcp.json` está bem formado (`type=http` + `Authorization` por `${ENV}`, os dois consertos de ontem presentes). É que **esta sessão nasceu antes** do conserto, e servidor MCP se registra no início da sessão. Uma sessão nova os terá.

O `brief-fetch` que aparece no SessionStart veio do **hook** (`brief-fetch-curl.mjs`), não do tool MCP — por isso funcionou enquanto os tools não existem.

Registro também o que quase virou achado falso: ia reportar que o `laravel-boost` tem o mesmo defeito de `type` ausente que travou o `oimpresso`. **Falso** — ele é **stdio** (`command`), e ali `type` ausente é o default correto. O `CONNECTION_CLOSED` dele é falha de conexão, de outra natureza.

## Números do fim

| | início | fim |
|---|---|---|
| worktrees registrados | 106 | 77 |
| diretórios em `.claude/worktrees` | 67 | 41 |
| cópias do token em claro | 52 | 25 |
| repo principal | `codex/…`, 161 atrás | `main`, em dia |
| `Authorization` do principal | literal | `Bearer ${OIMPRESSO_MCP_TOKEN}` |
