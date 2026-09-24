---
sessao: "01"
titulo: Estado `sem recibo` no placar da lista
executor: "[CC]"
base: 701f40c6ec66
---
# _saida 01

## Checklist
1. ✅ `semRecibo` derivado em `avaliarIndice` · 2. ✅ contado e DITO à parte (texto + `--md`)
3. ✅ 9 casos novos no bite-test · 4. ✅ os 65 casos anteriores intactos · 5. ✅ `--check` inalterado
6. ✅ só o prefixo tocado

## Como ficou — e por que NÃO é um valor de `estado`

A spec diz "um 6º estado". Implementei como **flag derivada ao lado do `estado`**, espelhando o
`indecidivel` que já existia. O motivo é o próprio `PARAR SE` da thread: a população-alvo
(provas verdes, sem `_saida`) hoje tem `estado === 'proximo'`, então um 6º valor de `estado`
**mudaria o veredito de casos existentes** — e a thread manda parar se isso acontecer.

A forma de flag entrega o que a spec pede, literalmente: `entregue 0 de 11 · 2 sem recibo (02, 03)`.
O `sem recibo` fica **fora** do `fecha`, fora do `entregue X de Y`, e o `--check` não muda de
veredito por causa dele.

Condições (as 4 da spec, sem acrescentar nenhuma): sem `_saida` · ≥1 prova **explícita**, todas
estruturais e `ok` · dependências `feito` ou `sem recibo` · sem `bloqueio`.

Resolve **topológico**, não um `for` simples: a condição 3 lê o `semRecibo` das dependências, e
num `for` a thread que vem antes leria `undefined` da que vem depois.

## Antes → depois (`npm run placar:lista`, 13 índices)

| | antes | depois |
|---|---|---|
| threads `sem recibo` | **0** (o conceito não existia) | **14**, em 8 módulos |
| `entregue` cumulativo | 1 de 63 | **1 de 63 — inalterado** |

| módulo | sem recibo |
|---|---|
| Patrimonio | 4 (01, 02, 03, 05) |
| Governanca | 2 (02, 03a) |
| Hrm | 2 (02, 03) |
| jana | 2 (01, 02) |
| ancora · Compras · Fiscal · Ponto | 1 cada (01) |

## A prova de que mede a coisa certa

Não é "provas verdes" no vácuo — bate com verdade que eu conheço por **outra via**:

- **Hrm 02/03** — os dois que a própria spec nomeia como em produção (`EssentialsLeaveController.php:191` · `EssentialsLeaveTypeController.php:73`).
- **Patrimonio 01/02/03** — descobertos hoje lendo o código, antes desta thread: o fix da 01 landou em **#7011**, o da 02 em **#7062**, e a guarda da 03 está em `AssetController.php:91`.
- **jana 01/02** — o próprio `00-INDICE.md` do Jana declara "implementadas e mergeadas (#7587 · #7591)".

Três confirmações independentes. É o discriminante que faltava: antes, essas 14 eram
indistinguíveis de quem nunca começou.

## Duas divergências com a spec, declaradas (não "ajustadas")

**1. "uma prova vermelha faz a thread cair para `pendente`".** MEDIDO: o motor dá `proximo`.
Esse eixo é pré-existente e ortogonal à cor da prova (`proximo` = executável; `pendente` =
travada por dependência/decisão). Forçar `pendente` mudaria o veredito dos casos 3 e T5 — o
`PARAR SE`. O bite-test assere o que importa e é verdade: prova vermelha **nunca** vira
`sem recibo`, e o relato nomeia a prova.

**2. "os 48 casos atuais do bite-test".** São **65** hoje. O `PARAR SE` foi lido pelo que ele
protege — nenhum veredito anterior muda —, não pelo número: 65 verdes antes, 65+9 = **74** depois.

**`nao_toca` · `_saida-*.md`:** li como "não editar `_saida` de outras threads" — são o dado que
o medidor consome, e mexer neles falsificaria o resultado. Este arquivo é o recibo exigido pela
§Prova da própria thread. Nenhum `00-INDICE.md` foi tocado.

## Escopo
`scripts/qa/placar-indice.mjs` + `scripts/qa/placar-indice.test.mjs` + este recibo.
Zero `placar.mjs` (suíte dele rodada: verde), zero `placar-de-lista.yml`, zero `--check` ligado.
