---
date: "2026-09-23"
time: "14:30 BRT"
slug: omission-assinatura-funcao-c3-c4
tldr: "O --omission do contrato-de-tela acusava toda mudança de assinatura de função (âncora ^[-] anulava o C1 do #7691). Conserto + um teste por família + invariante de SYMBOL_RES armado no dono (#7788), e lápide §5 + rec LC-30 (#7792). Os dois mergeados."
decided_by: [W]
cycle: null
prs: [7788, 7792]
next_steps:
  - "Os 3 outros exemplos do erro: VariacoesTab, Painel e Acervo já estão em main. Nada a fazer — o gate só olha PR novo."
  - "Janela 09-15..09-22 do docblock (2 acusados de 21) NÃO foi recontada com o C3 — errata diz isso; recontar se alguém for citar a taxa"
  - "PR #7784 (FinanceiroConciliacao) continua aberto; com o #7788 em main, o commit vazio 2e6d5c12c deixou de ser necessário"
related_adrs: [0344-two-strikes-cobre-processo]
---

# `--omission` acusava mudança de assinatura de função — C3 + C4

## O que foi feito (tudo mergeado em `main`)

| PR | O quê |
|---|---|
| [#7788](https://github.com/wagnerra23/oimpresso.com/pull/7788) | `scripts/contrato-de-tela.mjs`: família `function` de `SYMBOL_RES` passa de `^[-]` para `^[-+]` (C3). Teste por família do C1 (`export`, `function`, `route()`, `Route::`), com mutação por família. **C4**: cada família declara `amostra` e `checkSymbolRes` exige que ela case `-` e `+` com o mesmo símbolo; roda antes de todo `--omission` e no modo novo `--check-symbol-res`. Errata no docblock e no workflow: o `VariacoesTab` da medição do #7691 era este falso-positivo. |
| [#7792](https://github.com/wagnerra23/oimpresso.com/pull/7792) | Lápide §5 2026-09-23 em `licoes-rejeitadas.md` (+ §5 regenerado) e `rec` na LC-30, que passou de 5 para 6. Fechamento passou pelo `ciclo-adversary` antes do canon (ACCEPT com 6 correções, todas aplicadas). |

## Medição (reproduzível)

Squash commits de `origin/main`, `git diff c~1 c -- resources/js/Pages Modules`, gatilho = regex do `detect` lida do YAML. 1.500 commits (08-24..09-23): 199 disparam; 43 acusações em 11 commits → 37 em 7. As 6 que somem são mudança de assinatura, conferidas no diff. Reproduzido de forma independente pelo `ciclo-adversary`.

## Caveats

- A lane `contrato-de-tela` é **advisory**: o C4 protege este dono, não a classe LC-30. O `Gate:` da LC-30 não mudou.
- Lição de transporte repetida na sessão (LC-26): 3 escritas por heredoc/`python -c` colapsaram barra ou comeram espaço; o que funcionou foi Edit/Write direto ou script em arquivo.

## Estado MCP no momento do fechamento

`cycles-active` e `my-work` devolveram `ECONNREFUSED` (servidor MCP inacessível às 14:30 BRT). Estado de cycle/tasks **não medido** nesta sessão.
