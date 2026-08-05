---
date: "2026-08-02"
hour: "17:00 BRT"
duration: "4h"
topic: "B7-cobertura: 1ª tela fechada (Conciliação, UC-FCC-01..13) e as 2 travas que impediam provar qualquer UC dela — quarentena classificada por grep que casou a negação, e veredito só por título de teste. Censo: 135 de 383 UCs improváveis."
authors: [C]
prs: [5172, 5174, 5175, 5177, 5178, 5180, 5183]
outcomes:
  - "B7-cobertura destravado e 1ª tela fechada: Financeiro/Conciliacao ganhou casos.md com UC-FCC-01..13, derivados do SDD §6.2 (CU-FIN-10/11/12) + Goals do charter, NÃO do .tsx. Ratchet do casos-gate aterrissado 191→145."
  - "ACHADO 1 (o que pagou a sessão) — a quarentena da lane Financeiro classificou 11 arquivos como bucket 'B) RefreshDatabase' por GREP da string; medido, 1 USA o trait e 10 só CITAM, dizendo o OPOSTO ('NÃO usa RefreshDatabase'). 10 testes ficaram fora de uma lane REQUIRED por um motivo inexistente, um deles com [must] [T0] cross-tenant que ninguém rodava. 4 saíram (lane 325 testcases · 0 failures), 6 foram pro bucket certo."
  - "ACHADO 2 — teto estrutural de prova nunca medido: o manifesto G-7 lê o UC do atributo name do <testcase>, e método PHPUnit vira nome humanizado sem hífen. Censo com as próprias funções do guard: 383 UCs declarados · 236 com UC no título (alcançáveis) · 135 SÓ em docblock (NUNCA viram ✅) · 12 órfãos. O exec_backed_pct vinha sendo lido como 'falta rodar teste'."
  - "ACHADO 3 — o ledger LICOES_CODE.md estava corrompido desde 07-31 (#5110): LC-09..LC-15 duplicados (o parser empilha em array → alarme dobrado toda sessão), cópias divergentes (LC-13 = 7 numa e 5 na outra), LC-08 sem a linha Gate (cobrado por um gate que tem) e LC-17 exibindo o gate do LC-08. Restaurado com prova de não-perda (72 tokens distintivos, 0 faltando) e prova do fix (parseLicoes: 24 → 17 entradas, 0 duplicados)."
  - "Fixture consertada: ConciliacaoAuditReabrirTest montava a linha com titulo_id = 12345 hardcoded, violava FK e MORRIA NO SETUP sem nunca exercer reabrir() — o [must] [T0] do UC-FCC-06 ficava sem prova enquanto o arquivo parecia '1 failed' de produto. Saiu de 1 failed para 5/0/0."
  - "3 erros MEUS corrigidos no caminho, nenhum apagado: (a) reportei '−46 telas' quando eram 30 (8 eram telas DELETADAS, 8 outros); (b) escrevi afirmação FALSA no casos.md ('são os únicos que podem virar ✅') — errata por cima, não no lugar; (c) reportei '13/13 UCs ligados a teste' como cobertura quando o G-2 é string-match e EU tinha escrito a string. Ledger: LC-08 39→40, LC-11 5→6."
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0365-trio-de-tela-fica-colocado-reverte-eixo-0364", "0093-multi-tenant-isolation-tier-0", "0236-extrato-conciliacao-modelo-unificado", "0344-two-strikes-cobre-processo"]
---

# B7-cobertura — 1ª tela, 2 travas derrubadas, 1 ledger consertado

Continuação do programa **Opção B** ([proposal 2026-08-01](../decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md)),
parte **B7**. A sessão da manhã ([2026-08-02 B6/B7-censo](2026-08-02-b6-subtracao-fosseis-b7-censo.md), [F])
fechou B6 e o censo; esta pegou a **cobertura**, que aquela deixou explicitamente aberta como
*"ratchet incremental tela-a-tela, não batch autônomo"*.

**A [ADR 0365](../decisions/0365-trio-de-tela-fica-colocado-reverte-eixo-0364.md) foi cunhada por [W]
durante esta sessão** ([#5179](https://github.com/wagnerra23/oimpresso.com/pull/5179)) — o B0 caiu em
paralelo, e o trio colocado virou canon *de jure*. Isso valida onde os `casos.md` desta sessão foram
escritos, mas **não foi pré-requisito**: a proposal já dizia que B7 não depende de B0, e o
`casos-coverage-guard` (required) sempre resolveu por path-irmão colocado.

## O que foi entregue

| PR | O quê |
|---|---|
| [#5172](https://github.com/wagnerra23/oimpresso.com/pull/5172) | ratchet do `casos-gate` aterrissado **191 → 146** |
| [#5174](https://github.com/wagnerra23/oimpresso.com/pull/5174) | `Financeiro/Conciliacao/Index.casos.md` — `UC-FCC-01..13` |
| [#5175](https://github.com/wagnerra23/oimpresso.com/pull/5175) | errata da afirmação falsa que eu escrevi no `casos.md` |
| [#5177](https://github.com/wagnerra23/oimpresso.com/pull/5177) | `ConciliacaoLeExtratoApiTest` → Pest `it()` (UC-FCC-10..13 atribuíveis) |
| [#5178](https://github.com/wagnerra23/oimpresso.com/pull/5178) | 4 testes saem da quarentena + fixture do `UC-FCC-06` |
| [#5180](https://github.com/wagnerra23/oimpresso.com/pull/5180) | `ConciliacaoUploadDedupeTest` → Pest `it()` (UC-FCC-01..03) |
| [#5183](https://github.com/wagnerra23/oimpresso.com/pull/5183) | `LICOES_CODE.md` restaurado (24 → 17 entradas) |

## As duas travas (é o conteúdo real da sessão)

O `casos.md` nasceu com **13 UCs e ZERO capazes de virar ✅**. Não por falta de teste — os testes
existiam e passavam. Duas travas independentes:

**Trava 1 — quarentena por motivo falso.** 3 dos 4 arquivos estavam fora da lane, no bucket
*"B) RefreshDatabase — dropam o schema e envenenam o processo compartilhado"*. Medido
`uses(RefreshDatabase::class)` / `use RefreshDatabase;` REAL: **1 usa, 10 só citam** — e o que citam
é a negação (*"NÃO usa RefreshDatabase"*, *"sem RefreshDatabase"*). O bucket tinha sido montado por
**grep da string**. Consequência: 10 testes fora de uma lane **required**, um deles carregando um
`[must]` `[T0]` cross-tenant, por um motivo que nunca existiu.

**Trava 2 — atribuição de veredito.** O `casos-results-collect.mjs` lê o UC do atributo `name` do
`<testcase>`. Método PHPUnit vira nome **humanizado sem hífen**; o regex canônico exige `UC-FCC-NN`.
Medido no JUnit real: **82 de 82** UCs do manifesto vêm de título `it()`, **0** de método `test_`.
Logo os UCs em docblock rodavam verdes e valiam **0** — e não havia forma de docblock que
funcionasse.

Derrubadas as duas, os 13 UCs da tela carregam o id no JUnit (`17` ocorrências de `UC-FCC` no
artifact) e passam a receber veredito pelo cron `casos-results-publish` (07:30 BRT), sem manifesto
commitado à mão.

## O teto que ninguém tinha medido

Censo com as **próprias funções do guard** (`ucsDeclaredInCasos` + `isPageScreenPath` — os 12 órfãos
batem exatamente com o que o `casos:report` reporta, o que valida o método):

| | |
|---|---:|
| UCs declarados em `casos.md` | 383 |
| com UC no **título** — teto alcançável | 236 → **239** |
| **só em docblock — NUNCA vira ✅** | 135 → **132** |
| sem citação (órfão G-2) | 12 |

**35% dos UCs do projeto são improváveis por construção.** O manifesto parado em 82 não é preguiça
de bookkeeping — tem teto estrutural, e o `exec_backed_pct` vinha sendo lido como "falta rodar
teste". Os 132 restantes **não** foram varridos em lote de propósito (big-bang de legado morre no
CI, §5 2026-07-12): o caminho é oportunístico, quando a tela já for tocada por trabalho real.

## Erros meus, registrados e não apagados

1. **Denominador inflado** — reportei "−46 telas fechadas"; decompondo, eram **30** que ganharam
   `casos.md`, 8 telas **deletadas** (Admin/*, ADR 0360) e 8 outros. Corrigido no mesmo dia.
2. **Afirmação falsa em doc canônico** — escrevi no `casos.md` que `UC-FCC-10..13` *"são os únicos
   desta tela que podem virar ✅"*. É falso, e o erro era meu. Errata **por cima** do texto errado
   ([#5175](https://github.com/wagnerra23/oimpresso.com/pull/5175)), não no lugar dele.
3. **LC-11 dentro de um PR que citava LC-11** — pus os UC-ids em docblock e reportei "13/13 UCs
   ligados a teste" como cobertura. O G-2 é `testCorpus.includes(uc)`: ficou verde porque **eu
   escrevi a string no arquivo**. Presence, não prova.
4. **Esqueci de enumerar o `module-surface`** entre os gates diff-aware antes de commitar (§5 emenda
   2026-07-27) — o CI pegou. Corrigido regenerando pelo dono do tema.

Ledger atualizado: **LC-08 39→40** (a quarentena por grep — o contador é da classe, não de quem
errou) e **LC-11 5→6** (a minha).

## O ledger estava quebrado

Ao ir registrar os incrementos, o `LICOES_CODE.md` acusou **24 entradas para 17 ids**. Corrompido
desde 2026-07-31 ([#5110](https://github.com/wagnerra23/oimpresso.com/pull/5110)), atravessando 4
commits de outras sessões: `LC-09..LC-15` duplicados (o parser empilha em **array**, então cada
classe alarmava **duas vezes** por sessão), cópias **divergentes** (`LC-13` = 7 numa e 5 na outra),
`LC-08` **sem linha `Gate:`** (cobrado toda sessão por um gate que tem) e `LC-17` exibindo o gate do
`LC-08`.

Restaurado em [#5183](https://github.com/wagnerra23/oimpresso.com/pull/5183). Toda deleção é
duplicata **provada**, nenhuma é conteúdo: o bloco duplicado é subconjunto estrito (diff = 1 linha),
a `Ref` órfã é byte-idêntica à viva, o `Gate` veio do commit anterior à corrupção, e o recibo do
`20632` foi **reintegrado**. Prova de não-perda: **72 tokens distintivos do antes, todos no depois**.

## Método que se pagou (vale pra próxima sessão)

- **Perguntar ao oráculo, não ao texto.** Todo achado desta sessão veio de rodar o instrumento certo
  — o parser do hook, o JUnit da lane, `uses()` real vs menção — nunca de reler o arquivo. As duas
  travas e o ledger quebrado eram **invisíveis à leitura**.
- **Verde não é veredito.** Em 3 momentos o CI estava verde e não provava o que eu queria: o "99
  SUCCESS" era de um commit que não continha meu código (auto-merge do [W] fechou o #5175 antes); a
  lane verde não dizia se o UC chegava ao `name`; o gate verde não dizia se o teste rodou. Baixar o
  **artifact** foi o que fechou os três.
- **Rodar TODOS os modos do job** (§5 2026-07-28) — o `casos-gate` roda dois passos sobre o mesmo
  script; verde num só não é verde no job.
