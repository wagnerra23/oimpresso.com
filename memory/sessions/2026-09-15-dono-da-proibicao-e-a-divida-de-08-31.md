---
date: "2026-09-15"
topic: "Dono do inventário × dono da proibição, e a dívida de 08-31"
authors: [W, C]
prs: [7322, 7327, 7331]
outcomes:
  - "ciclo-adversary rodado ANTES do canon deu REJECT e derrubou o incremento que eu ia declarar — o agravante já era da lápide-mãe de 2026-08-13"
  - "Ele achou um 2º precedente que eu não tinha varrido: §5 2026-08-17, contra o MESMO arquivo — logo 3ª instância da classe, 2ª no mesmo artefato"
  - "O incremento que sobreviveu é de eixo: o gatilho da mãe dispara em LER; esta é a 1ª em que o ato é POSICIONAR"
  - "Recibo pendurado de 08-31 pago: 67 de 68 ocorrências com cabeçalho MM-DD (n+N) têm lápide na própria data, FP 1,5% — era dívida real, não typo"
  - "LC-13 do Monitor mudo por jq ausente: reincidência com o canon no main há 4h50, não ocorrência paralela"
  - "Duas correções de fato ao enunciado inicial, por medição: a premissa do #7224 era verdadeira sobre o movimento (R100) e o check que reprovou era advisory"
---
# 2026-09-15 — Dono do inventário × dono da proibição, e a dívida de 08-31

> Session log do trabalho. O **estado** de fechamento está no handoff
> [`2026-09-15-1841-ledger-dono-da-proibicao-divida-08-31.md`](../handoffs/2026-09-15-1841-ledger-dono-da-proibicao-divida-08-31.md) — não duplicar.

## TL;DR

Três PRs de ledger mergeados. O `ciclo-adversary`, rodado **antes** de escrever, deu **REJECT**:
o incremento que eu ia declarar já era da lápide-mãe, e ele achou um 2º precedente que eu não
tinha varrido — contra o **mesmo arquivo**. O que sobreviveu foi outro eixo: o gatilho da §5
dispara em **LER**, e esta é a 1ª instância em que o ato é **POSICIONAR**. Paguei também a
dívida do recibo pendurado de **08-31** (medido: 67 de 68 ocorrências têm lápide na própria
data ⇒ era dívida real, não typo) e registrei o **`Monitor` mudo** por `jq` ausente, que
cometi 4h50 depois de o canon estar no `main`. Fecho: `--reconcile` **120/120 · 0 pendurados**.

## O trabalho, na ordem

**1. Registrar a reincidência de LC-08 do [#7314](https://github.com/wagnerra23/oimpresso.com/pull/7314)** — mover um `.gitignore` para o lugar que o `cowork-ssot-guard` proíbe (R2), por ter consultado o dono do inventário e não o da proibição.

O `ciclo-adversary` rodou **antes** de qualquer escrita e deu **REJECT**. Reverifiquei os 5 achados de fato dele por medição independente; todos conferem:

| Achado | Verificação |
|---|---|
| o incremento que eu ia declarar já é da mãe | `licoes-rejeitadas.md:943` — *"Canon lido não é canon aplicado"* |
| existe 2º precedente, mais próximo | §5 2026-08-17, **mesmo arquivo** (`cowork/Wagner/.gitignore:26`) |
| *"a premissa era falsa"* é impreciso | `R100`, blob `1cfcd38e18c` idêntico — o movimento **foi** mecânico |
| cortar *"citei a lápide"* | a §5 está sempre no system prompt — frase trivial |
| o check era **advisory** | `design-memory` = 0 hits no baseline required |

O que sobreviveu: **o gatilho da mãe dispara em LER**; esta é a 1ª instância em que o ato é **POSICIONAR**.

**2. Pagar a dívida do recibo pendurado 08-31.** Medi antes de escolher o conserto, porque as três hipóteses (typo · recibo fabricado · dívida real) davam caminhos diferentes: das **68** ocorrências com cabeçalho `MM-DD (n+N)`, **67** têm lápide na própria data. FP de 1,5% ⇒ o alarme estava certo e **faltava a lápide**. Escrita retroativa, autodeclarada, com corpo reconstruído das fontes primárias re-medidas — não de memória.

**3. Registrar o `Monitor` mudo** que eu mesmo armei (`jq` ausente). Outra sessão já tinha registrado a classe **às 13:07Z**; cometi às 17:5x, com o header da lápide no system prompt. Reincidência, não ocorrência paralela. Sub-eixo próprio: meu `|| echo '[]'` converteu falha **permanente** em loop silencioso — pior que o do rec irmão, que ao menos terminava.

## Medições que mudaram uma decisão

- **`--stat` de rename não responde "por que foi permitido".** O `#7224` fez o rename **e criou a R2 no mesmo commit** (`git log -S "R2 item proibido" --reverse`), reescrevendo o guard `43/112`. Meu commit citava o `#7224` 3× sem abrir o guard que ele reescreveu.
- **A 2ª forma óbvia também é ilegal** — medido, não lido: duas cópias de sha256 idêntico disparam `R4 conteúdo duplicado: Felipe/.gitignore = Wagner/.gitignore`; controle negativo volta a 0.
- **`gh --jq` funciona** mesmo sem o binário: `0` falhas com controle positivo de `116`.
- **Não existe `PreToolUse` com matcher curinga** — o `"*"` só está em `SessionStart`/`Stop`/`UserPromptSubmit`; nenhuma rota acidental alcança o tool `Monitor`.

## Erros meus na sessão, contidos antes de virar afirmação

| Erro | Lápide que já existia |
|---|---|
| `/tmp` do Bash ≠ `/tmp` do Node no Windows | §5 2026-08-21 |
| `<ref>:<path>` devolve falso-vazio para path com ponto (MSYS) | §5 2026-08-23 |
| `&&` cortou a cadeia quando um `grep` deu 0 | §5 2026-07-31 |
| sonda de bytes de controle medindo a saída do `od`, não os bytes | a própria LC-08 |

Nenhuma abriu entrada nova — todas já tinham casa.

## O que mais vale guardar

O **teste de identidade pegou o `main` avançando durante a escrita**: o [#7316](https://github.com/wagnerra23/oimpresso.com/pull/7316) criou a regra *"lápide nova não escreve `nº N`"*, que me afetava. O diff contra a base não bateu e, em vez de forçar, fui ver por quê. Sem esse teste, eu teria escrito um contador à mão numa convenção que tinha acabado de mudar.

## PRs

[#7322](https://github.com/wagnerra23/oimpresso.com/pull/7322) · [#7327](https://github.com/wagnerra23/oimpresso.com/pull/7327) · [#7331](https://github.com/wagnerra23/oimpresso.com/pull/7331) — 33 linhas somadas, **zero deleções**, CI 113/113/112 pass e 0 fail.
