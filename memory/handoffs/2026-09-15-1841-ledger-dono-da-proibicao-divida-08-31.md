---
date: "2026-09-15"
time: "18:41 UTC"
slug: ledger-dono-da-proibicao-divida-08-31
tldr: "Três PRs de ledger mergeados: a emenda §5 que alarga o gatilho de LER para POSICIONAR (3ª instância inventário×proibição), a lápide de 08-31 que estava faltando (recibo pendurado zerado, 119/120 → 120/120 no main) e o rec da LC-13 do Monitor mudo por jq ausente. O ciclo passou pelo ciclo-adversary, que deu REJECT e derrubou o incremento que eu ia declarar."
prs: [7322, 7327, 7331]
decided_by: [W]
related_adrs: [0344-two-strikes-cobre-processo]
next_steps:
  - "Medir se o harness emite PreToolUse para o tool Monitor — pré-requisito para armar o matcher Bash|PowerShell|Monitor (two-strikes já disparou na LC-13)"
  - "Nada bloqueado: main verde, reconcile 120/120, sec5-derive 193 limites"
---

## Estado MCP no momento do fechamento

Tools MCP do servidor oimpresso **indisponíveis nesta sessão** (não conectado) — usado o fallback
canônico de [`how-trabalhar.md`](../how-trabalhar.md) §Fallback: git + `gh` + as máquinas locais.
O `brief-fetch` do SessionStart trouxe o brief #644 (cycle sem rótulo; 5 HITL pendentes [W]).

Rodado contra `origin/main` **pós-merge dos três PRs**, não contra branch:

```
--reconcile          rc=0   120/120 recibos resolvem · 0 pendurados
sec5-derive --check  rc=0   193 limites, 0 perdidos
deadlink-gate --check rc=0  793/793 grandfathered
```

## O que aconteceu

Pedido de [W]: registrar uma reincidência de **LC-08** medida e consertada no [#7314](https://github.com/wagnerra23/oimpresso.com/pull/7314) — mover um `.gitignore` para um lugar que o `cowork-ssot-guard` proíbe (R2), por ter consultado o dono do **inventário** e não o da **proibição**.

**O rito veio antes do canon.** O `ciclo-adversary` deu **REJECT** com 9 itens e derrubou o
incremento que eu ia declarar: *"o canon nomeava o guard e mesmo assim não foi aberto"* **já é da
lápide-mãe** (2026-08-13, *"Canon lido não é canon aplicado"*). Ele também achou um **2º precedente
que eu não tinha varrido** — §5 2026-08-17, *"consultei o dono do inventário e não o da regra"*,
contra o **mesmo arquivo**. Reverifiquei os 5 achados de fato dele por medição independente: todos
conferem. O incremento que sobreviveu é outro: **o gatilho da mãe dispara em LER**, e esta é a
primeira instância em que o ato é **POSICIONAR**.

Duas correções de fato entraram no registro por medição, contra o enunciado inicial:
- *"A premissa era FALSA"* é impreciso — `git show 4f51a9ec781 --find-renames` dá `R100` com blob
  idêntico: o movimento **foi** mecânico. Falsa era a **inferência** (mecânico ⇒ reversível), e a R2
  que a refuta **nasceu no mesmo commit**, que reescreveu o guard `43/112`.
- O agravante *"citei a lápide nesta sessão"* foi **cortado**: a §5 está sempre no system prompt,
  o que torna a frase trivial. Trocado por recibo duro — o commit da 1ª abordagem cita o `#7224`
  **3×**, inclusive o lema, sem abrir o guard que aquele PR reescreveu.

Depois [W] pediu a dívida do **recibo pendurado 08-31** em PR separado. Medi antes de consertar:
das **68** ocorrências com cabeçalho `MM-DD (n+N)`, **67** têm lápide na própria data — FP de 1,5%,
logo o alarme estava certo e **faltava a lápide**, não sobrava ponteiro torto. Escrita retroativa,
autodeclarada, com corpo reconstruído das fontes primárias re-medidas (ADR 0235 `:45`/`:47`/`:54`).

Por fim, [W] mandou registrar o **Monitor mudo** que eu mesmo armei — `jq` ausente, silêncio lido
como "nada falhou". Outra sessão já tinha registrado a classe **hoje**, às 13:07Z; eu cometi às
17:5x, com o header da lápide no meu system prompt. Reincidência, não ocorrência paralela.

## Artefatos gerados

| PR | Arquivo | Δ |
|---|---|---|
| [#7322](https://github.com/wagnerra23/oimpresso.com/pull/7322) | `licoes-rejeitadas.md` + `LICOES_CODE.md` + `proibicoes.md` | 14/0 · 1/0 · 6/0 |
| [#7327](https://github.com/wagnerra23/oimpresso.com/pull/7327) | `licoes-rejeitadas.md` + `proibicoes.md` | 12/0 · 6/0 |
| [#7331](https://github.com/wagnerra23/oimpresso.com/pull/7331) | `LICOES_CODE.md` | 1/0 |

**Zero deleções nos três** — append puro, com teste de identidade em cada um (remover o inserido
devolve o original byte a byte). CI: 113 · 113 · 112 pass, **0 fail**.

## Persistência

- **git canônico:** os três mergeados (18:04:53Z · 18:20:47Z · 18:38:53Z), conteúdo conferido no `main`.
- **MCP:** webhook propaga `memory/**` automaticamente.
- **BRIEFING:** não aplicável — nenhum `Modules/<X>` tocado.

## Próximos passos pra retomar

```bash
node .claude/hooks/licoes-code-two-strikes.mjs --reconcile
```

Deve seguir `120/120 · 0 pendurados`. O único item aberto é o **two-strikes da LC-13**: medir se o
harness emite `PreToolUse` para o tool `Monitor` antes de armar `Bash|PowerShell|Monitor`.

## Lições catalogadas

Três da própria sessão, todas registradas: **LC-08** (o erro do #7314), **LC-13** (o Monitor mudo,
2ª em ~5h) e a dívida de 08-31. Duas cometidas *durante* o trabalho e contidas antes de virar
afirmação: `/tmp` do Bash ≠ `/tmp` do Node no Windows (§5 2026-08-21) e `<ref>:<path>` do git
devolvendo falso-vazio para path que começa com ponto (§5 2026-08-23) — as duas já tinham lápide,
nenhuma abriu entrada nova.

**O que mais vale desta sessão:** o teste de identidade pegou o `main` avançando **durante** a
escrita (o [#7316](https://github.com/wagnerra23/oimpresso.com/pull/7316) criou a regra de *lápide
nova não escreve `nº N`*, que me afetava diretamente). Em vez de forçar o diff, fui ver por quê —
e rebaseei antes de seguir.
