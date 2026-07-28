---
name: Gotcha — aspas em .gitattributes transformam a linha inteira em pattern e a diretiva fica inerte
description: Uma linha `"* text=auto eol=lf"` inteira entre aspas é lida pelo git como um pattern com espaços e NENHUM atributo — a política de fim de linha some sem erro nenhum. O oráculo é `git check-attr`, nunca ler o arquivo. Ficou inerte por 14 meses no oimpresso.
type: reference
authority: canonical
lifecycle: ativo
updated_at: '2026-07-28'
---

# Gotcha — aspas em `.gitattributes` deixam a diretiva inerte

## O sintoma: nenhum

Não há erro, warning ou saída de diagnóstico. O arquivo `.gitattributes` parece correto na leitura humana, o git aceita sem reclamar, e a política declarada simplesmente **não vale**. É uma falha silenciosa: só aparece se alguém for medir.

No oimpresso a linha era exatamente esta:

```
"* text=auto eol=lf"
```

Parecia declarar normalização de fim de linha para todo o repositório. Não declarava nada.

## A causa: aspas delimitam o PATTERN, não a linha

Em `.gitattributes` o formato é `<pattern> <attr1> <attr2> ...`. As aspas duplas existem para permitir **pattern com espaço no nome** — por exemplo `"pasta com espaço/*" text`.

Quando a linha inteira vem entre aspas, o git lê:

- **pattern** = `* text=auto eol=lf` (um nome de arquivo literal, com espaços)
- **atributos** = nenhum

Como nenhum arquivo do repositório se chama `* text=auto eol=lf`, a regra nunca casa com nada. O efeito é idêntico a não ter linha alguma.

Controle-negativo que isola a causa (três repositórios descartáveis, mesmo conteúdo, só as aspas mudando):

| conteúdo da linha | `git check-attr text eol -- f.txt` |
|---|---|
| `"* text=auto eol=lf" ` + CRLF | `unspecified` / `unspecified` |
| `* text=auto eol=lf` | **`auto` / `lf`** |
| `"* text=auto eol=lf"` (sem espaço, sem CR) | `unspecified` / `unspecified` |

O CR no fim da linha e o espaço extra **não** são a variável — só as aspas.

## O oráculo: `git check-attr`, nunca a leitura do arquivo

Ler `.gitattributes` e concluir que a política está ativa é derivar da fonte errada. O git resolve atributos com precedência entre múltiplos arquivos `.gitattributes` (raiz, subdiretórios, `.git/info/attributes`), e a única resposta confiável é a do próprio git:

```bash
git check-attr text eol -- <arquivo>     # o que vale para este arquivo
git check-attr --all -- <arquivo>        # tudo que vale para ele
git ls-files --eol                       # estado real: i/<índice> w/<worktree>
```

A mesma disciplina vale para qualquer arquivo de configuração declarativa: pergunte à ferramenta o que ela resolveu, não ao arquivo o que ele diz.

## Corrigir NÃO dispara renormalização em massa

A suposição intuitiva — "consertar vai reescrever milhares de arquivos no próximo checkout" — é falsa, e isso está documentado no manual do git:

> *When the file has been committed with CRLF, no conversion is done.*

Medido em repositório descartável, com arquivos CRLF já commitados e a linha corrigida:

| ação | resultado |
|---|---|
| adicionar a linha corrigida | `git status` limpo, zero arquivos modificados |
| `touch` nos arquivos | limpo |
| clone fresco | worktree recebe CRLF preservado, status limpo |
| editar 1 linha mantendo CRLF | diff de 1 linha, arquivo continua CRLF |
| `git add --renormalize` | **só aqui** os arquivos ficam modificados |

Ou seja: o efeito de corrigir é que **arquivos novos passam a nascer LF**. Os que já estão no índice com CRLF ficam intocados até que alguém os edite de propósito. Renormalização em massa exige `--renormalize` explícito e é decisão separada.

Confirmado no repositório real do oimpresso: após a correção, `git status` acusou **apenas o próprio `.gitattributes`** como modificado.

## O que aconteceu no oimpresso

O commit `339fceb760` ("Converte para LF", 2025-05-16) **substituiu uma diretiva que funcionava** por esta versão quebrada:

```diff
-* text=auto                      ← funcionava
-*.css linguist-vendored
-*.scss linguist-vendored
-*.js linguist-vendored
-CHANGELOG.md export-ignore
+"* text=auto eol=lf"             ← inerte
```

Ficou assim por 14 meses. Um commit cujo título anuncia a conversão para LF foi exatamente o que a desligou — e levou junto as diretivas `linguist-vendored` e `export-ignore`.

Estado do repositório quando a falha foi detectada em 2026-07-28 (re-rodar `git ls-files --eol` antes de citar estes números): 13.586 arquivos LF, 1.456 CRLF, 4 mixed. Índice e worktree idênticos, porque `core.autocrlf` local está em `false` e nenhuma conversão acontecia em ponta nenhuma.

## A lição generalizável

Uma diretiva de configuração que **parece** ativa e não está é pior que a ausência dela: o time confia na proteção declarada e ninguém procura o problema que ela deveria evitar. Sempre que uma política for declarada em arquivo, o registro do que a torna real é a **saída da ferramenta que a consome**, com data — não o texto do arquivo.

## Refs

- `git help attributes` — seção *Effects*, sobre `text`, `eol` e o comportamento com arquivos já commitados em CRLF
- [`memory/proibicoes.md`](../proibicoes.md) §Ambiente — as lições irmãs de BOM em PowerShell 5.1 e de mojibake em branch protection, todas da mesma família: o que foi escrito não é o que a ferramenta leu
