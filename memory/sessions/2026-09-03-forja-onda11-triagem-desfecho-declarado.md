---
date: "2026-09-03"
slug: "forja-onda11-triagem-desfecho-declarado"
topic: "Forja — Onda 11 (Triagem): desfecho declarado sem criar tela"
tipo: "session"
tldr: "A Onda 11 do export da Forja pedia replicar a Triagem. Medido, as duas premissas do pedido caem — a fonte não monta a view, e Aprovações não contém a Triagem (filtros diferentes, e o slot `Proposta` já é o estado posterior). [W] decidiu fechar declarando: nenhuma tela criada, o motivo registrado nos donos existentes. PR #6683."
prs: [6683]
related_adrs:
  - "0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias"
  - "0374-emenda-0315-espelho-cowork-e-rota-prevista"
---

# Forja Onda 11 (Triagem) — o desfecho foi não construir, e isso levou a sessão inteira para provar

## O pedido e a armadilha que ele já sinalizava

O pedido trazia a Onda 11 do `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` (Triagem, aba `/forja`) com um
aviso raro e correto: *"ÓRFÃO DECLARADO… antes de replicar, resolver COM O [W] se esta aba deve
existir como destino próprio ou se ela já foi absorvida por Aprovações"*. E antecipava o desfecho:
*"se a resposta for 'absorvida', o desfecho correto pode ser NÃO criar tela"*.

**A medição refutou as duas opções do enunciado.** Não é "absorvida" e não é "destino próprio".

## O que foi medido (e a ordem importou)

| # | pergunta | resposta medida | fonte |
|---|---|---|---|
| 1 | O espelho tem `forja-triagem.jsx`? | **Não** — `git ls-files prototipo-ui/cowork/ \| grep forja` = 7 arquivos, sem triagem. `--sla` confirma 157 arquivos só no vivo | porta viva |
| 2 | A fonte monta a view? | **Não** — `FjTriagemView` é órfão no shell (o §8 do pacote e o `github.md` declaram) | pacote + Cowork |
| 3 | Foi absorvida por Aprovações? | **Não.** `McpTask::scopeTriage()` = `owner IS NULL OR priority IS NULL OR status='backlog'`; `ForjaAprovacoesService::fila():79` = `status='pending_approval'`. Nenhum é subconjunto do outro | código |
| 4 | Por que a absorção do protótipo não traduz? | O slot `Proposta` **já está ocupado**: é `pending_approval`, o estado **posterior** à triagem | `Aprovacoes/Index.tsx:38-39` |
| 5 | Ela ainda é aba? | **Não** — o topnav vivo (`Resources/menus/topnav.php`) tem 6 destinos e nenhum é Triagem (saiu na Onda 2, 09-02) | código |
| 6 | Então onde ela vive? | Landing `/forja` (`routes.php:267`) + destino de 4 redirects 301 (`/triage`, `/inbox`, `/burndown`, `/`) + alvo do botão primário `Novo issue` (`ForjaHub.tsx:141`) | código |

**A leitura que fecha:** Triagem é o **F0** (antes de entrar no backlog), Aprovações é o **gate de
decisão** (depois). Duas etapas do mesmo funil, não duas telas para a mesma coisa.

Isto é a lápide §5 2026-07-16 em estado puro — *"importar solução de outro sistema sem checar se o
problema existe no nosso"*. O protótipo compõe 6 views e dissolve a triagem numa linha da mesa de
Aprovações; aqui existe uma etapa de domínio que aquele modelo não tem, e o nome que ele usaria
(`Proposta`) já significa outra coisa. Traduzir a premissa, não copiar a solução.

## O que já estava escrito e ninguém tinha cruzado

A perda **já estava medida** em dois lugares canônicos, desde 01-02/09:

- `PARIDADE §9.7`: *"`Triagem` vira tipo `Proposta` em Aprovações, que abre **vazia** enquanto a
  Triagem tem 3 tickets vivos. Removê-los agora encurtaria a barra perdendo produto."*
- `Cockpit.casos.md` `UC-FORJA-02`: mesma frase, com a medição nos dois renders.

O que faltava não era medir de novo — era **conectar** isso à Onda 11 e fechar. Foi o que o PR fez.

## Duas armadilhas que quase custaram caro

1. **Numeração de onda colide entre dois programas.** O pacote de export tem Onda 6 = Aprovações e
   Onda 11 = Triagem; a `PARIDADE §11` tem Onda 3 = Aprovações e Onda 6 = Gantt; e existe ainda uma
   "Onda 11 = revogação de /project-mgmt" (#6617). O handoff de hoje 11:20 registra que **por dois
   dias seguidos** alguém inferiu "a onda X destrava Y" pelo título da onda e errou. Não repeti:
   confirmei a dependência medindo o PR (#6571, Aprovações, mergeado), não pelo número.
2. **Eu mesmo quase reportei um "vazamento de navegação" que não existe.** Cheguei a concluir que a
   Triagem ficara inalcançável (fora do topnav, só pela landing). Ao medir o `ForjaHub`, vi que o
   botão `Novo issue` vive no hub e portanto aparece nas 6 abas — ela é alcançável de qualquer
   lugar. O que existe é divergência de **rótulo** (lá o botão abre um compositor; aqui leva à
   lista). Corrigi antes de publicar. Medir o componente, não deduzir do topnav.

## Precisão devolvida ao pacote (sem inflar)

O §1 lista `forja-novo-issue` entre as superfícies "sem receptor no `main`". **Está correto** — o
compositor não existe aqui. O achado é mais fino, e é o que ajuda quem for construí-lo: o
**gatilho** já existe e aponta para outro lugar (`ForjaHub.tsx:141` → `/forja`). Registrei assim,
não como "o pacote errou" — que foi como formulei no meio da sessão, e estava impreciso.

## Entregue

PR [#6683](https://github.com/wagnerra23/oimpresso.com/pull/6683) — 110 linhas adicionadas, 2
removidas, **zero** `.tsx`/`.php`/CSS:

- `Cockpit.casos.md` — bloco de desfecho no `UC-FORJA-08`. Os UCs 08/09/10 seguem valendo sem
  alteração: a Triagem não mudou.
- `Cockpit.charter.md` — errata datada. Duas afirmações em presente tinham apodrecido (a lista de
  abas, de 06-16; e *"Novo issue é um no-op de navegação"*). Substituídas por fato datado — LC-10.
- `prototipo-ui/CODE_NOTES.devolutiva-cowork-onda11-triagem-2026-09-03.md` — devolutiva com a
  pergunta de design que sobra para o Cowork.

Não abri pedido de regeneração do bundle: **já existe** (#6671). Duplicar seria LC-19.

## Para quem herdar

Não há onda de re-skin pendente para a Triagem. Reabrir exige **uma das duas**: a fonte passar a
montar `FjTriagemView`, ou uma onda de **construção** que faça `ForjaAprovacoesService::fila()`
projetar também `McpTask::triage()` e flipe a landing `/forja` + os 4 redirects. A segunda é
decisão [W], não conserto de layout — e custa reescrever 4 UCs.
