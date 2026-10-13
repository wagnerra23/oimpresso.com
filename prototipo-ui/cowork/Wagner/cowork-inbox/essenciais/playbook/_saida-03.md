---
sessao: "_saida-03"
thread: "03 · Contratos dos 5 Index (derivados do alvo)"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/essenciais-03-contratos)"
base_lida: wagnerra23/oimpresso.com@main 79c3bfd419
---
# _saida-03

## Entregue: os 5 contratos, derivados dos alvos da A1 (#9214)
Cada contrato parte do `.alvo.json` da A1, não do `.tsx`. Entram só as seções do alvo que têm contraparte na
Page, e só o copy que aparece literalmente nos dois lados: a string foi conferida por máquina na
`base.assinatura` do alvo e no código da Page, dentro da faixa de linhas da âncora.

| contrato | Page | seções (âncora) | copy |
|---|---|---|---|
| `essenciais-documentos` | `Essentials/Documents/Index` | `cabecalho` · `tabela` | 3 + 4 |
| `essenciais-memorandos` | `Essentials/Documents/Index` (aba Memorandos) | `cabecalho` · `tabela` | 3 + 4 |
| `essenciais-lembretes` | `Essentials/Reminders/Index` | `toolbar-mes` · `grade-mes` | 2 + 0 |
| `essenciais-conhecimento` | `Essentials/Knowledge/Index` | `busca` · `navegacao` | 0 + 0 |
| `essenciais-tarefas` | `Essentials/Todo/Index` | `toolbar` · `filtros-periodo` · `tabela` | 3 + 4 + 11 |

O que ficou de fora do alvo, e por quê, está no `_nota` de cada contrato. Em resumo:
- `cabecalho` e `abas` do alvo são a casca do módulo no protótipo ("Essenciais" + abas do módulo). As Pages
  vivas não têm essa casca: cada tela é uma rota com o próprio h1.
- Lembretes: a `legenda` (origens Lembrete/Financeiro/Ponto) não existe na Page, que não filtra por origem.
- Conhecimento: o `artigo` é a rota Show (pela D1, painel lateral), não a Index.

## Pages: só âncoras (`nao_toca` relaxado, mesmo precedente do Estoque 02 / #9185)
O job "Contratos de tela" é required e exige `data-contract` na Page. Três das cinco telas já tinham as âncoras
(Lembretes, Tarefas e a tabela de Documentos) e não foram tocadas. Duas ganharam **um atributo cada**:
- `Documents/Index.tsx`: `data-contract="cabecalho"` no `<header>`;
- `Knowledge/Index.tsx`: `data-contract="navegacao"` na grade de livros.

Zero handler, dado, copy ou classe. O [W] autorizou seguir o precedente nesta sessão.

## RUNBOOKs criados (exigência do hook, fora do prefixo)
O hook `block-mwart-violation` bloqueia edição em Page sem RUNBOOK, e as duas telas não tinham. Foram criados
`memory/requisitos/Essentials/RUNBOOK-documents.md` e `RUNBOOK-knowledge.md`, curtos: estrutura, âncoras,
contratos e o que a tela não faz.

## Provas
- **Mordida:** antes das âncoras, `contrato-de-tela --contract` dava rc=1 em documentos, memorandos e
  conhecimento ("seção sem âncora data-contract"); lembretes e tarefas já davam rc=0. Depois, rc=0 nos cinco.
- Os modos do job, rodados localmente: os 88 contratos rc=0; `--map --check` rc=0 (as cinco telas
  `🟢 portado`); `--anti-tautologia` rc=0 sem aviso para os contratos novos; `--preflight origin/main` rc=0;
  `contrato-de-tela.test.mjs` e `contrato-de-tela-relevante.test.mjs` rc=0.
- **Correção feita pelo `--anti-tautologia`:** a primeira versão de `essenciais-conhecimento` levava o copy
  "Adicionar seção" na `navegacao`. O aviso apontou que ele não estava na fonte declarada. No protótipo o texto
  existe (`essenciais-extras.jsx`, `X.BaseConhecimento`), mas como ação do artigo aberto, e na tela é ação do card
  do livro: mesma string, seção diferente. Saiu do contrato, e a fonte passou a ser `essenciais-extras.jsx`.

## Fora deste PR
- As divergências "só no protótipo" da `_saida-00` (títulos h1, "categoria" × livro → seção → artigo, ícone de
  compartilhar, legenda de origens) ficam fora do contrato. Cada uma é decisão [W].
- Índice: a `03` diz `nao_toca: resources/js/Pages/`, mas o job required exige âncora na Page. Sugestão ao Cowork:
  registrar no índice a exceção "só atributo `data-contract`", como o Estoque 02 já fez na prática.
