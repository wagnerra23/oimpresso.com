# 2026-08-12 — O glob do Inertia é escolha nossa, e o gate que o protegia estava mudo em DUAS camadas

> Sessão de correção de canon que virou correção de gate — e, no meio, produziu três erros meus
> da exata classe que estava consertando. Os três estão contados no ledger; este log conta como
> apareceram, porque o *como* é a parte reutilizável.

## O pedido

[W] trouxe uma afirmação FALSA já em canon, com reincidência medida: o session log
[`2026-05-15-wave3-b6-repair.md:26`](2026-05-15-wave3-b6-repair.md) diz que *"Inertia resolve global
resources path"* — como se o local das Pages fosse imposição do framework. Em 2026-08-12 um agente
repetiu isso ao [W] **como fato de arquitetura**, por ter lido canon sem medir.

Pedido explícito: **não editar o session log** (append-only) — errata em doc vivo.

## O que era verdade (medido, não lido)

| # | Fato | Recibo |
|---|---|---|
| 1 | O `resolve` do `createInertiaApp` é **callback arbitrário** | [`app.tsx:100-105`](../../resources/js/app.tsx) |
| 2 | Não é 1 glob, são **2**, sincronizados à mão | `app.tsx:104` + [`ssr.tsx:17`](../../resources/js/ssr.tsx) |
| 3 | Alguém **fixou por teste** — ninguém fixa o que o framework impõe | o `describe` do `.jsx` cravava a string exata |
| 4 | A restrição é **extensão + path, ambas escolhidas** | é assim que `Pages/Financeiro/_cowork-bundle/` (10 `.jsx`) fica inerte |

## O achado que mudou o escopo: o gate era mudo

Ao verificar a prova (3), achei o defeito real. O teste que crava o glob **não era disparado por
mudança no glob**: `resources/js/**` não estava no `push.paths` nem no filtro `fin:` do dorny →
PR que trocasse o glob caía em **skip-as-pass**, verde sem executar nada.

Medido com picomatch sobre o filtro real: **ANTES `false/false` → DEPOIS `true/true`**, com
controles negativos (`Pages/Sells/Create.tsx`, `README.md`) não disparando.

### E a SEGUNDA camada, que só apareceu no log do CI

[W] mergeou o [#5679](https://github.com/wagnerra23/oimpresso.com/pull/5679) com o body dizendo
*"ciclo completo provado"*. **Estava errado.** Fui ler o log do run `31603374244`:

```
grep "discovery Inertia" no log  →  0 ocorrências
a suíte reportava                →  348 passed (1488 assertions)
```

O arquivo está na **quarentena da lane** por causa de *outros 4 describes* (bundle CSS, quebrados
desde o #2127). A lane monta o run-set como *diretório − quarentena* → excluía o arquivo **inteiro**,
e o contrato do glob era **vítima colateral**.

O bite-test no CT 100 não podia revelar isso: ele roda `php artisan test <arquivo>` **direto**, e a
quarentena só age na composição do run-set da lane. **Rodar direto prova que o assert morde, nunca
que a lane executa.**

## Como ficou provado (o contador, não o verde)

Extraí o contrato pra `InertiaPagesGlobContratoTest.php`, fora da quarentena. Matriz de mordida no
CT 100, com restauração verificada por checksum:

| Cenário | Resultado |
|---|---|
| boa | **4 passed** (7 assertions) |
| `ssr.tsx` mutado p/ `.jsx` | 1 failed → UC-2 morde |
| trigger sem os entrypoints | 1 failed → UC-3 morde |
| entrada real na quarentena | 1 failed → UC-4 morde |
| lista ausente | 4 passed, degrada limpo |

E no CI real: describe **0 → 4** ocorrências no log; suíte **348 → 352 passed**, **1488 → 1495
assertions** — delta `+4/+7`, exatamente o arquivo novo.

## Os três erros meus (o valor reutilizável desta sessão)

**1. LC-13 — declarei "ciclo completo" tendo medido metade.** Consertei a *inclusão* (trigger) e não
chequei o lado *subtrativo* (quarentena). Chegou a main antes de eu perceber; errata em comentário
no #5679.

**2. LC-11 — o UC anti-reincidência era ele mesmo um presence-gate.** O UC-4 fazia `toContain` no
texto bruto do `.list` e ficava vermelho por causa de um **comentário**. Só peguei porque as fases
*boa* e *ruim* deram **o mesmo número** — o agregado `Tests:` esconde qual caso falhou.
> Regra que sobra: **boa e ruim empatadas = o bite-test não provou nada; abra o detalhe por caso.**

**3. LC-10 — afirmei em tempo presente o estado de outro artefato, e apodreceu em horas.** Escrevi
na rule que *"a mensagem do hook ainda oferece o override e o teste asserta que ela o cite —
ignore-a"*. O [#5683](https://github.com/wagnerra23/oimpresso.com/pull/5683) (a sessão do chip que
[W] iniciou) inverteu as duas coisas no mesmo dia. Pior que o fato errado: o *"ignore-a"* apagava
uma saída **legítima** — o `/mwart-override` não é bypass mecânico, mas existe como **registro
humano no PR**.

E um quarto, pego antes de publicar: ao **verificar** o conserto do UC-4, meu próprio `grep -c`
contou a menção no comentário e quase reportei "0 = fora da quarentena" com um número que dizia `1`.
A classe não é falta de conhecimento — eu tinha acabado de escrever o conserto. É que **substring é
o reflexo barato**, e só some quando se pergunta *"quem consome esse arquivo, e como?"* antes de contar.

## Achados colhidos no caminho (fora do pedido)

- **`components-tree-guard.mjs`** prometia *"migram quando a tela for tocada — catraca"*. Medido
  desde `ac5e46e0d4b`: as pastas foram editadas em **10 commits** (Compras 3 · Jana 7 · Financeiro 0)
  e as telas irmãs em **24** — **nenhuma** das 4 migrou. Não existe catraca (LC-15). O comentário
  parou de prometer; migrar segue decisão [W].
- **`pages.md` linkava um `.ps1` que não existe** (o hook virou `.mjs`) e **propagava** o
  `/mwart-override` inexistente. Virou chip; [W] rodou, e o [#5683](https://github.com/wagnerra23/oimpresso.com/pull/5683)
  fechou o chokepoint.

## Armadilhas de ambiente reencontradas

- **Edit normalizou line endings** de 16 linhas alheias no `.yml` (arquivo misto: 182 CRLF + 16 LF).
  Refiz byte-preserving; diff final `8 inserções / 0 deleções`. Teste de identidade obrigatório.
- **MSYS mangleia `git show <ref>:<path>`** (2×) — resolvido por `ls-tree` + `cat-file -p <hash>`.
- **`/tmp` diverge** entre bash MSYS e node (`D:\tmp`) — usar path absoluto do scratchpad.
- **`grep -oE '[0-9]+'` numa URL do GitHub** pega o `23` de `wagnerra23`. Extrair por `/runs/([0-9]*)/`.

## Pointers

- Handoff: [`2026-08-12-1755-glob-inertia-e-as-duas-camadas-de-mudez.md`](../handoffs/2026-08-12-1755-glob-inertia-e-as-duas-camadas-de-mudez.md)
- Emenda §5: `memory/licoes-rejeitadas.md` (2026-08-12, EMENDA da lápide 2026-08-02)
- Ledger: `memory/LICOES_CODE.md` — LC-11 `8→9`, LC-13 `12→13`
