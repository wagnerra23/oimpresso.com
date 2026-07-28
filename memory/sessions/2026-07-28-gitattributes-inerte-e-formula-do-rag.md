---
date: '2026-07-28'
hour: '14:50'
topic: gitattributes inerte por 14 meses, a fórmula de escrita pro RAG, e o P4 que mecaniza o erro de medição
authors: ['C', 'W']
outcomes:
  - '.gitattributes: `text=auto eol=lf` volta a valer — sem renormalizar nenhum arquivo'
  - 'memory/reference/como-escrever-doc-para-o-rag.md — regras derivadas do indexador/chunker reais'
  - 'gotcha de junction corrigido: o RAG servia a orientação ERRADA sobre comando destrutivo'
  - 'rag-status-vocab-check.mjs — detector do descasamento schema × retrieval (FP medido = 0)'
  - 'hook P4: bloqueia contagem com pathspec cru quando o número MUDA (LC-08 gate 3/9 → 4/9)'
prs: [4945, 4946, 4951, 4955, 4957]
related_adrs: ['0053-mcp-server-governanca-como-produto', '0256-knowledge-survival-meia-vida-catraca-sentinela']
---

# Sessão 2026-07-28 — `.gitattributes` inerte, a fórmula do RAG, e o P4

Começou com uma pergunta de uma linha e terminou com uma máquina que bloqueia o próprio autor. Cinco PRs, todos mergeados.

## O que motivou

[W] notou que a linha 1 do `.gitattributes` estava inteira entre aspas: `"* text=auto eol=lf"`. Em gitattributes, aspas delimitam um *pattern* com espaços — então o git lia aquilo como um nome de arquivo literal com **zero atributos**. A diretiva era inerte.

O pedido incluía a trava certa: **confirmar por medição antes de mudar**, e avaliar o impacto de corrigir em vez de aplicar direto.

## O que a medição mostrou

`git check-attr text eol` devolvia `unspecified` para todos os arquivos. Um controle-negativo em três repositórios descartáveis isolou a causa: **são as aspas** — o CR à direita e o espaço não são a variável.

Não era "nunca teve política". O commit `339fceb760` (**2025-05-16**, "Converte para LF") substituiu um `* text=auto` que **funcionava** pela versão quebrada, e levou junto `linguist-vendored` e `export-ignore`. Ficou inerte **14 meses**, num commit cujo título anuncia o oposto.

**A premissa de risco da tarefa não se confirmou.** Corrigir não renormaliza nada: o git não converte arquivo já commitado com CRLF (*"When the file has been committed with CRLF, no conversion is done"*). Testado em repo descartável — adicionar a linha, `touch`, re-salvar, clone fresco: `git status` limpo nos quatro. Só `git add --renormalize` explícito mudaria os 1.456 arquivos CRLF. Confirmado no repo real: após o fix, apenas o próprio `.gitattributes` aparece modificado.

`linguist-vendored` e `export-ignore` **não** foram restaurados: medido que o primeiro marcaria 45 arquivos js/css **próprios** como código de terceiro (a premissa do upstream UltimatePOS não vale mais), e o repo não usa `git archive`.

## A fórmula do RAG, derivada da máquina

[W] pediu para colocar o conhecimento no RAG e "achar a fórmula de fazer com responsabilidade". As regras saíram do código real, não de opinião:

- o **PATH** decide `type` e `module` — o frontmatter não é consultado para isso;
- `memory/requisitos/<Mod>/` indexa só **9 nomes exatos**; `memory/reference/**` é recursivo;
- arquivos `_*` e `README` são pulados;
- o frontmatter vira **coluna indexada** (`status`, `authority`, `lifecycle`, `module`, `tags`);
- **não há chunking hoje** — o `DocumentChunker` só roda dentro de `aplicarContextualRetrieval()`, que retorna cedo com a flag off. O documento é indexado inteiro e o que chega ao modelo são os **primeiros ~400 chars**.

## O defeito que a investigação revelou

O RAG servia a versão **errada** de uma pegadinha destrutiva. `memory/reference/gotcha-worktree-junction-vendor-rm.md` estava indexado afirmando que o `--force` era a causa e recomendando *"remove SEM `--force` (preferível)"* — exatamente o caminho que apagou o `node_modules` real em 2026-07-14. A versão corrigida vivia em `memory/requisitos/Infra/PEGADINHA-*`, **fora do índice** por causa da allowlist.

Duas causas somadas: tema duplicado em dois docs que drifaram, e o correto num path sem cobertura. Corrigido no doc que está indexado, apontando para o dono detalhado em vez de restatear.

## Quatro erros meus de medição

Todos da mesma classe (**LC-08**), nenhum pego por CI ou releitura — todos por revisão adversarial:

| # | erro | como apareceu |
|---|---|---|
| 1 | `git ls-files 'a/*/*.md'` — pathspec faz `*` atravessar `/` | publiquei 1.011/743/144 |
| 2 | medi no repo principal, **248 commits atrás** | corrigi para 715/455 — ainda errado |
| 3 | generalizei o `scopePorStatusAtivo` para "a busca" | 285 invisíveis eram do **fallback**; o híbrido tem 97,2% visível |
| 4 | escrevi regra sobre um chunker que **não executa** | Regra 4 descrevia comportamento inexistente |

O número certo, na terceira tentativa: **744 / 481 / 125**. O único que sobreviveu às três foi o `125`.

Os erros 1, 3 e 4 chegaram a `main` e foram corrigidos com **errata visível**, não rewrite — o doc ensina "medir um caminho e concluir sobre o sistema é erro", e apagar tiraria a prova de que a regra vale para quem a escreveu.

## O corte do [W] que mudou o resultado

Eu havia escrito que *"os gates pegam forma, o adversário pega premissa"* — e tratei como limite. [W] cortou: **"arrumar a máquina sempre vai ser melhor que fazer na mão"**.

Estava certo. O erro do pathspec parecia não-mecanizável porque eu só tinha considerado a forma **sintática** — grep no comando — que é justamente a que o §5 já matou quatro vezes. A saída não era heurística melhor; era **parar de adivinhar e medir**: o hook roda `git ls-files <pat>` e `git ls-files :(glob)<pat>` e compara. Se os números batem, fica calado.

**FP = 0 por construção**, não por calibragem.

## Fecho

A prova final foi acidental e é a melhor que havia: ao verificar o estado em `main`, rodei o comando errado por reflexo e **o hook me bloqueou**, com o delta medido na mensagem (1046 vs 744, delta 302).

**Placar honesto: 1 de 4 erros virou máquina.** O do diretório stale está coberto pela mensagem do P4, mas sem detecção própria. Os outros dois seguem sem mecanismo — não sei mecanizá-los sem cair no guard sintático banido, e não vou inflar o placar.
