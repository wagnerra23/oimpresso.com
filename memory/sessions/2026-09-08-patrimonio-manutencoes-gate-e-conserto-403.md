---
date: "2026-09-08"
hour: "17:49 BRT"
topic: "Patrimônio/Manutenções — o gate de dependência barrou a tela, e o defeito de autorização que não dependia dela saiu e foi a produção"
authors: ["C"]
outcomes:
  - "Tela Manutenções NÃO migrada: `Pages/Patrimonio/_shared/**` tinha 0 arquivos no main, medido em 3 fontes independentes (árvore, os 4 PRs abertos cruzados POR ARQUIVO, e o próprio 06-ui-bloqueada.md)"
  - "PR #7034 (merged 17:11Z): dois defeitos no mesmo `if` em 6 sítios — o `&&` insatisfazível por construção (permissões `is_radio` mutuamente exclusivas) e o `|| subscription` que anulava o gate"
  - "Bite-test + mutação (CT 100, MySQL real): matriz de 3 variantes × 3 cenários. Com o controller ORIGINAL o MORDE devolve 200 — defeito material, não teórico. Suíte 72 → 75 passed, 0 failed"
  - "Verificado EM PRODUÇÃO por sondas idênticas prod×main (8 / 6 / 0 com rc=1), não pela declaração do deploy — com a ressalva de que o smoke HTTP não exercita o gate"
  - "PR #7036 (merged): errata do `_saida-06` — afirmou em presente `não mergeado` e apodreceu no instante do merge (LC-10)"
  - "Ledger: LC-08 149 → 150 e LC-10 6 → 7, os dois recibos de erros MEUS, consertados no mesmo ciclo"
  - "Fundação chegou 31min depois pelo #7035 (Bens, outra sessão): Manutenções destravada; resta a decisão de produto do custo (item 3 do §6)"
---

# 2026-09-08 · Patrimônio/Manutenções — o gate barrou a tela, o defeito saiu

## O que foi pedido e o que aconteceu

Pedido: migrar **uma** tela do Patrimônio (Manutenções) de Blade para Inertia/React, com uma
dependência declarada — *"a tela Bens funda `Pages/Patrimonio/_shared/**`; se ainda não existir
no main, **PARE e reporte**"*.

O gate bateu. E o que tornou a sessão útil mesmo assim foi separar o que **dependia** da fundação
(a tela) do que **não dependia** (o defeito de autorização que o prompt trazia como *"vivo, medido
e ainda não corrigido"*).

## 1 · A parada, medida em 3 fontes

Claim de ausência não se escreve de uma fonte só. `git ls-tree -r --full-tree` no `origin/main`
fresco deu **0** arquivos em `Pages/Patrimonio/`; os **4** PRs abertos foram cruzados **por
arquivo** (`gh pr view --json files`), não por título — nenhum fundava `_shared`; e o dono do
inventário, o `06-ui-bloqueada.md`, já dizia *"Nada dos 46 arquivos existe. Destravado ≠ começado"*.

Nada de `.tsx`, charter, casos ou RUNBOOK foi escrito: o formato de props/subnav sai do `_shared`,
e ancorar artefato em fundação inexistente é o retrabalho que o gate previne.

## 2 · O conserto — e por que ele virou PR próprio

O prompt mandava **decidir com [W]** se o conserto entrava na tela ou virava PR separado. Com a
tela barrada, a alternativa *"entra nesta tela"* deixou de existir: a decisão ficou forçada, e
levá-la como menu teria entregado zero. Virou PR próprio — que é o que `1 PR = 1 intent` já pedia.

Os dois defeitos, reproduzidos e confirmados 1:1 contra o `_saida-04.md §1`:

- **(a)** o `&&` exigia as duas permissões, que o `DataController` declara `is_radio` com o mesmo
  `radio_input_name` — **mutuamente exclusivas na UI de papéis**. Insatisfazível por construção, e
  barrava justamente o perfil para o qual o filtro de escopo do mesmo método foi escrito;
- **(b)** o `|| subscription` colapsava tudo em *"o módulo está assinado"* — razão pela qual (a)
  nunca apareceu em produção, e razão pela qual consertar só o `&&` seria **inerte no ar**.

## 3 · A prova, e o que ela NÃO prova

A matriz de 3 variantes × 3 cenários mostrou que **cada cenário morde numa direção diferente**: o
MORDE discrimina (b) (200 → 403); os dois CN discriminam o **conserto pela metade** (falham com
`&&`). E (a) **não é observável isoladamente por HTTP** — com a assinatura falsa, original e
consertado devolvem 403 igual. Isso é propriedade do sistema, não limitação do teste, e ficou
escrito no docblock.

Em produção, a verificação foi por **sondas idênticas nos dois lados** (prod e main: 8 / 6 / 0 com
`rc=1`), não pela declaração do deploy. E com a ressalva registrada: o `302` do smoke vem do
middleware `auth`, que roda **antes** do controller — o smoke prova que a app subiu e o roteamento
não regrediu, **não** o 403.

## 4 · Dois erros meus, e o que eles ensinam

**LC-08 (→150).** Escrevi que *"o cenário que prova (a) é o `CN view_own`"*. A minha própria
medição, na mesma sessão, refutou. O conserto foi trocar a afirmação pela **matriz**, que é
executável por quem rodar o mutante — não por outra afirmação.

**LC-10 (→7).** O `_saida-06` afirmou em presente *"não mergeado"*. Apodreceu no instante do merge.
O agravante desta instância: foi escrita **horas antes** do evento que a tornaria falsa, **pelo autor
do próprio evento** — previsível no ato da escrita, e ainda assim escrita em presente.

Uma terceira, pega antes de virar conclusão: sonda em prod devolveu `0` nos **dois** greps — o que é
impossível se o arquivo existe. O padrão é que estava errado; quem revelou foi o **controle positivo**.

## 5 · Pegadinhas de ambiente que custaram tempo real

`.gitattributes` (`* text=auto eol=lf`) fazendo arquivo novo nascer LF enquanto o controller legado
tem CRLF **dentro do blob**; `io.open` em modo texto reescrevendo o arquivo inteiro (diff 446/371 em
vez de 81/6, diagnosticado com `--ignore-cr-at-eol`); heredoc colapsando `\\` → `\`; `MSYS` mangling
no revspec com ponto; a lane `modules-pest` sem `synchronize`; e a mesma lane rodando **SQLite**, onde
o teste **skipa** — logo verde de CI ali não prova a guarda.

O checkout do CT 100 é **compartilhado e estava sujo** (5 arquivos de terceiros). Nada de `pull`,
`stash` ou `checkout` global: só os 2 arquivos confirmados limpos, restaurados ao sair, com **0
fixtures órfãos**.

## 6 · Onde isso deixa a frente

O [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035) (Bens) mergeou 31 minutos depois do
meu e fundou o `_shared`. **Manutenções está destravada.** O que resta é decisão de produto: o
**custo** (item 3 do §6) — e como Non-Goal de charter é campo que só [W] preenche, a tela não pode
nascer sem essa resposta sem inventar escopo.
