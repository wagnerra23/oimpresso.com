---
date: "2026-09-21"
time: "14:48 UTC"
slug: guard-destrutivo-tres-mudancas-medidas-e-o-lint-cego-a-tests
tldr: "O php -l do ci.yml nao alcancava tests/ (523 arquivos fora de todo check de PR) e o guard do rm tinha isencao ordem-dependente. 5 PRs mergeados, todos com FP medido ANTES e bite-test por mutacao. O trabalho que mais rendeu foi adversarial entre duas sessoes: das 4 divergencias, a irma acertou 4 e eu 0 — sempre o mesmo erro, afirmar sobre a propriedade ou o ambiente errado."
prs: [7602, 7604, 7613, 7617, 7625]
decided_by: [W]
related_adrs: [0344-two-strikes-cobre-processo]
next_steps:
  - "Medir o atrito diario do #7613: 559 e custo NO PASSADO; o sinal novo aparece como pedido de aprovacao repetido, nao como vermelho de CI"
  - "Decidir se merge-com-checks-verdes vira pre-aprovacao no PROTOCOLO (R10 ja preve escopo, falta a linha)"
---

# Guard destrutivo: 3 mudanças medidas, e o lint que não via `tests/`

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO** em COPI.
- `decisions-search "block-destructive guard rm hook heredoc"` → `0224` (block×advisory — slug com
  ponto, por isso fora do frontmatter), `0271`, `0283`, `0080`.
- Handoffs irmãos de hoje: `0800`, `0844`, `0850`, `1139`, `1242` — este é o 6º.
- PRs desta sessão: **#7602, #7604, #7613, #7617, #7625 — todos `MERGED`**.

## O que aconteceu

Começou estreito: um achado colateral do #7574 dizia que o step `PHP syntax lint` do `ci.yml`
cobria `app` e `Modules`, mas **não** `tests/`. Medido: **523 arquivos `.php`** fora de todo check
de PR, e o `phpunit.xml` não inclui `tests/Browser` — então erro de sintaxe lá passava e só
quebrava no `schedule` semanal, semanas depois. FP medido no CT 100 contra a árvore do HEAD
exportada por `git archive` (não o checkout do container, que está noutro commit): **523/523
limpos**, incluindo as 23 fixtures de `governance-fixtures/`. Bite-test no CI real: fixture
inválida → step `failure`; árvore limpa → `success` (#7602).

O resto da sessão foi **auditoria adversarial** de três mudanças no `block-destructive.mjs`,
feitas por uma sessão irmã. A dinâmica valeu mais que qualquer um dos diffs: cada uma media o
número da outra antes de aceitar, e foi isso que pegou os erros — nenhum gate pegou nenhum.

- **#7604** — a isenção do `rm` casava o literal `-rf`, então `rm -f /tmp/x` bloqueava enquanto
  `rm -rf /tmp/x` passava. Eu achei que era inversão simples; a medição mostrou que era
  **ordem-dependente**: `-rf` e `-Rf` passam, `-fr` e `-fR` bloqueiam — o mesmo comando para o SO,
  dois vereditos. Escala fechada em 10 pontos com a regra exata: a isenção casava o **par ordenado
  `(r|R)(f|F)`**.
- **#7613** — abre a categoria para o `rm(1)` **sem flag**, com `ehToolRm` isentando `git rm` e
  irmãos. APERTOU **559**; AFROUXOU 32 (os `git rm`, que eram FP).
- **#7625** — o `statements()` fatiava em `\n` sem saber de heredoc, então **prosa virava
  statement** e o hook impedia escrever sobre o próprio hook. Consertado no fatiador, com a
  distinção que importa: heredoc **não** é inerte por construção (`bash <<EOF` executa), então a
  isenção é decidida pelo comando que consome o stdin.
- **#7617** — o ledger: rec da LC-08, rec da LC-09 e a lápide das 2 propostas minhas refutadas.

## Artefatos gerados

| PR | arquivos | o que trava |
|---|---|---|
| #7602 | `.github/workflows/ci.yml` (+11/−2) | `find app Modules tests`; bite-test no CI |
| #7613 | `block-destructive.mjs` + `.test.mjs` | 190 asserts; 4 mutantes |
| #7617 | `LICOES_CODE.md`, `licoes-rejeitadas.md`, `proibicoes.md` (+78/−0) | §5 derivado por `sec5-derive --write` |
| #7625 | `block-destructive.mjs` + `.test.mjs` | **205** asserts (era 188); 3 mutantes |

## Persistência

- **git:** 5 PRs em `main` (`c79289d18`, `e60b5fcf7`, `f2e061f4e`, `5e7ed62da`, `432b6f968`).
- **ledger:** LC-08 → **112x**, LC-09 → **3x** (derivados, não escritos à mão).
- **chip entregue por sessão separada:** `task_4688c778` → **#7607**, que trocou a data fixa do
  checkout do CT 100 pelo comando que a mede, sem tocar em nenhum fato datado.

## Lições catalogadas

Todas já registradas no #7617 — nenhuma classe nova.

1. **Os 3 números que errei eram propriedade do objeto errado** (LC-08): posição medida no comando
   bruto quando o hook avalia o **statement fatiado** (75,9% → 91,1%); whitelist procurada em
   qualquer lugar do comando em vez do **alvo resolvido** (13,7% → 0%); partição que não fechava
   por misturar dois universos (514+46+4 = 564 contra 559). **A soma que não fecha foi o detector
   mais barato da sessão** — 3 vezes.
2. **Recomendei tirar o `/i` alegando semântica POSIX** (LC-09). Em MSYS/NTFS a forma maiúscula
   **executa** (`command -v` resolve; `uname -s` = `MINGW64_NT-10.0-26200`). Aplicada, teria
   **aberto** um guardrail Tier-0. Corolário: o assert que o comentário chamava de "herança
   decorativa do porte .ps1" protege caso real.
3. **`gh pr checks --watch` saiu `rc=1` com "no checks reported"** — os workflows ainda não tinham
   nascido (0 runs; minutos depois, 69). Código de saída sobre **ausência de medição** lido como
   veredito. Quase virou "o PR está vermelho".
4. **O Auto-fix reporta QUE falhou, não DE QUEM é a falha.** O PHPStan do #7625 caiu no step
   `Install PHP deps` (análise `skipped`), e o próprio workflow imprime *"NÃO é regressão do seu
   PR, e o baseline NÃO deve ser regenerado"*. Tratar o evento como "conserte o que quebrou" levaria
   direto ao erro que o aviso antecipa.

## Próximos passos pra retomar

```bash
gh pr view 7613 --json body --jq '.body' | grep -A6 "custo NO PASSADO"
```

O único item em aberto é **decisão [W]**: o #7613 aperta o guard em 559 comandos medidos no
passado, e o padrão *cria-sonda-e-remove-na-mesma-linha* é recorrente. Quantas vezes por dia uma
sessão vai parar é o que decide se o aperto compensa — e isso aparece como **pedido de aprovação
repetido**, nunca como vermelho de CI.

## Pointers detalhados

- Corpo do **#7613** — escala de 13 pontos, `ehToolRm`, os 64 lidos um a um.
- Corpo do **#7625** — os 7 controles negativos de executor e as 14 bordas com alvo fora da whitelist.
- **#7617** — a lápide §5 2026-09-21 (o `/i` do rm) com o limite das 2 propostas refutadas.
- `.claude/hooks/block-destructive.mjs` §FLAG-SET e §HEREDOC — as medições datadas no código.
