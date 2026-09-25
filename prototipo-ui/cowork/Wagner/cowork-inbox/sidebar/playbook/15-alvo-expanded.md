---
sessao: "15"
titulo: MÁQUINA · alvo.mjs mede os 3 modos (hoje só rail)
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (f3611e548698 · _saida-07/08 lidos 2026-09-25 13:51 UTC)
onda: 2 — nasce do retorno da 07/08
---

# 15 · Alvo do sidebar nos 3 modos

## Por quê
`_saida-07` §"Não feito" 1: o `alvo.mjs` mede com viewport fixo 1280×900 e browser limpo; como o auto-rail é `(max-width: 1280px)` **inclusivo**, o alvo versionado `cockpit--sidebar.alvo.json` só tem o **rail**. Cabeçalho de grupo, item ativo e sub-telas **não existem** nele — a `comparacao` das 08 · 09 · 10 não tem o que comparar (`_saida-08` §"Não feito" 1).

## Abertura
Sessão limpa · `/onda sidebar --thread 15`. **Não escreve `Sidebar.tsx`** → pode correr em paralelo com a 09 (Lei 1).

## Faz
1. Flag de largura **ou** de modo no `alvo.mjs` (ex.: `--viewport 1440x900` e/ou `--sb-mode expanded|rail|hidden`, gravando `oimpresso.sb.mode` no vivo e `oimpresso.sidebar.mode` no protótipo antes da navegação). O que o README de targets já prever manda.
2. `secao-check` reproduz as mesmas flags (ele reexecuta a medição).
3. Regerar `cockpit--sidebar.alvo.json` com os 3 modos (um bloco por modo ou 3 alvos — o que o schema aceitar sem quebrar `jana--index`).

## Prova
- `secao-check --todos --servir-espelho` → `jana--index` continua **10 conforme** (não regrediu) e `cockpit--sidebar` ganha as seções do expanded.
- bite-test: `--injetar-falha` no `.sb-group-h` do expanded vira divergência.

## Fechar
`_saida-15.md` e PARE.
