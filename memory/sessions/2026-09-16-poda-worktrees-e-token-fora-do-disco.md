---
date: "2026-09-16"
topic: "O token MCP sai do disco no repo principal — e o caminho não era migrar arquivo, era descobrir que cada worktree carrega a própria cópia congelada de `.claude/`"
authors: [C]
prs: [7388, 7389]
outcomes:
  - "Token novo nasce com validade de 180 dias (#7388) — o ciclo semestral que a ADR 0057 linha 155 já decidia e que dependia de alguém lembrar; medido no dia, ninguém lembrava (14 tokens, zero revogados, 4 de 30/04 ativos sem uso)"
  - "`.gitignore` cobria `settings.local.json` por caminho EXATO — cópia com sufixo era commitável, e a rotação de ontem produziu uma (#7389); virou glob com bite-test e dois controles negativos"
  - "Bite-test do consumidor deu null nos 4 casos e quase virou `a migração está errada` — era consumidor errado, importado do repo principal que estava 161 commits atrás (rec n+19 da LC-08 + lápide §5 2026-09-16)"
  - "Medido 1 de 52 locais com o hook capaz de expandir `${ENV}` — aplicar a migração teria deixado 51 sessões sem `brief-fetch`"
  - "28 worktrees podados com zero perda; zero junctions medidas com controle positivo antes de confiar no zero; integridade do principal (vendor=113 / node_modules=707) imóvel em cada lote"
  - "Repo principal movido de `codex/prototipo-ssot-cleanup` (161 atrás) para `main` em dia, e só então o `settings.local.json` dele migrado e verificado com o consumidor real lendo o arquivo real"
---

# O token saiu do disco — mas só depois de descobrir por que ele não podia sair

## TL;DR

Pedido: migrar 52 `settings.local.json` pra `Bearer ${OIMPRESSO_MCP_TOKEN}`. Parecia um `sed`.
O bite-test rodado **antes de escrever** reprovou — e a leitura obvia era falsa: consumidor
errado, importado de um checkout 161 commits atras. O achado real: **`.claude/` de cada worktree
e uma copia congelada na criacao**, entao *mergeado em `main`* nao chega as sessoes. Eram **1 de
52** locais capazes. A saida foi podar 28 worktrees (zero perda, zero junctions medidas com
controle positivo) e mover o principal pra `main` — ai a migracao funcionou. Duas correcoes ao
meu proprio metodo no caminho: triagem que contava cache gerado como trabalho, e um `naopush=0`
que eu assumia em vez de medir.


## O pedido e o que ele virou

O pedido era migrar 52 `settings.local.json` de `Bearer <literal>` para `Bearer ${OIMPRESSO_MCP_TOKEN}` — fechar o vetor que a [LC-35](../LICOES_CODE.md) registrou ontem (segredo entra no transcript por leitura ampla). Parecia um `sed` com cuidado.

Não era. E o que o impedia não estava em lugar nenhum escrito.

## A ordem que salvou o trabalho

Montei o conteúdo migrado **em memória** e passei pelo `readAuthHeader` de produção **antes de escrever qualquer arquivo**. Deu `null` nos quatro casos — inclusive com a env presente, que é o caso que os 9 asserts do próprio hook afirmam funcionar.

Se eu tivesse escrito primeiro e testado depois, 52 arquivos teriam quebrado o `brief-fetch` de uma vez.

## O erro dentro do acerto

A leitura que eu quase publiquei foi *"o formato novo não é lido, a migração está errada"*. Falso. O `import` do bite-test apontava para `D:/oimpresso.com/.claude/hooks/` — **repo principal** — enquanto o `grep` cuja saída eu estava lendo era do **worktree**. O principal estava em `codex/prototipo-ssot-cleanup`, 161 commits atrás, sem a linha de expansão que o [#7383](https://github.com/wagnerra23/oimpresso.com/pull/7383) mergeara horas antes.

O que salvou foi banal e vale registrar como método: **os dois números não bateram** (o `grep` achava a linha, o teste dizia que ela não existia), e em vez de escolher um eu abri a função. Mesma sub-classe do `rec` n+15 do mesmo dia — lá a árvore errada era uma *branch*, aqui é outro *checkout* do mesmo repo.

## O que o erro destravou

> Cada worktree carrega a própria cópia de `.claude/`, **congelada no commit de criação**.
> Logo *"mergeado em `main`"* **não** implica *"disponível para a sessão"*.

Medido: **1 de 52** locais tinha o hook capaz. E o "1" nem era o meu worktree — descobri depois que ele não tem `settings.local.json`; era outro, que a poda levou. Ou seja: o número certo, herdado por uma razão que eu não tinha validado. Registrei isso também, porque acertar por engano não é acertar.

## A poda como conserto da causa

52 cópias do segredo não eram 52 problemas — eram o sintoma de 51 worktrees abandonados, cada um com uma cópia do token *e* uma cópia velha de cada hook, skill e gate.

**28 podados, zero perda.** O critério que sobreviveu: PR **MERGED** + nada não-commitado + nada não-pushado + branch sumiu do remoto.

A primeira triagem deu **1 de 67** e estava errada **por minha causa** — contava como trabalho o `?? prototipo-ui/cowork/_ds/` (cache gerado por hook) e os ` M .claude/...` (canon copiado na criação, byte-idêntico ao `origin/main`, conferido em 3 de 3). Corrigido: 30. Segundo furo, no meu próprio script: com o branch sumido do remoto eu **assumia** `naopush=0` sem medir — num repo que faz squash-merge, ancestralidade não responde "isto foi integrado?", e o oráculo certo é o estado do PR.

Sobre o footgun: `git worktree remove` com junction esvazia o alvo real do principal (dois incidentes em `proibicoes.md`). Varri os 67: **zero junctions** — e validei o detector com **controle positivo** antes de confiar no zero, porque "zero" também é o que um detector quebrado devolve. Integridade conferida antes e depois de cada lote.

## Um falso alarme meu, e como caiu

No meio da poda li `node_modules` com PowerShell (707) e depois com `ls` (703), e quase reportei perda de 4 entradas. Eram os dotfiles que o `ls` não conta. Re-medido com o **mesmo instrumento**: 707 nos dois. Comparação só vale em condições idênticas — inclusive quando o que muda é a ferramenta, não o objeto.

## O desfecho

`main` estava preso num worktree parado; liberado, o principal saiu do branch morto (PR #7224 já mergeado, remoto apagado) para `main` em dia. O hook novo veio junto. Aí sim a migração, verificada com o consumidor real lendo o arquivo real: expande com env, **rejeita fail-closed** sem env, com env vazia e com env torta.

## O que fica aberto

25 cópias com o literal, em worktrees com trabalho real que rodam hooks antigos — some conforme fecharem. Dois worktrees fora da poda (um com handle preso, um com estado ambíguo). Sobre o `cc-watcher` desligado com autostart `.off`, [W] decidiu no fechamento: é **contenção temporária**, não estado final. Isso mantém o daemon como capacidade desejada e move a dívida para o lugar certo — **redação na fronteira de ingest** (`cc-watcher` para `/api/cc/ingest`), que é exatamente o chokepoint que a LC-35 já tinha nomeado como o único onde a defesa faz sentido e onde existe corpus real pra medir falso-positivo. Enquanto ela não existir, o daemon fica parado. A task correspondente não pôde ser criada aqui: os tools MCP estão indisponíveis nesta sessão.
