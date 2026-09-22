---
sessao: "01"
titulo: Estado `sem recibo` no placar da lista (abrir com `/onda placar --thread 01`)
dono: "[CL]"
base: 701f40c6ec66
prefixo: scripts/qa/placar-indice.mjs · scripts/qa/placar-indice.test.mjs
nao_toca: scripts/qa/placar.mjs (a flag já existe) · placar-de-lista.yml (segue advisory, sem --check) · qualquer 00-INDICE.md ou _saida-*.md
---
# 01 · `sem recibo`

## O problema, medido
`avaliarIndice()` (`placar-indice.mjs:229-240`) só tem dois caminhos pra uma thread sem `_saida`: `pendente` ou `proximo`. Uma thread cujas provas estão **todas verdes** mas que ninguém fechou com recibo cai no mesmo balde da que nunca foi começada. O caso é real: HRM 02 e 03 estão em produção (`EssentialsLeaveController.php:191` · `EssentialsLeaveTypeController.php:73`), e o placar as mostra como trabalho a fazer.

## O que muda
Um 6º estado, **`sem recibo`**, quando **todas** as condições valem:
1. não há `_saida-NN.md`;
2. há ≥1 prova explícita, e todas são estruturais e estão `ok`. Nenhuma pode ser não medida nem indefinida;
3. as dependências de thread estão `feito` ou `sem recibo`;
4. não há `bloqueio`.

**Não é `feito`.** A Lei 2 continua: sem recibo, a thread não conta em `entregue X de Y` e `fecha` não fica verdadeiro por causa dela. O estado só serve para ser **dito**, à parte: `entregue 0 de 11 · 2 sem recibo (02, 03)`. No `--md`, uma linha `**Sem recibo:** … — falta o _saida-NN.md de quem executou`.

Thread vazia de provas (só a implícita) **nunca** vira `sem recibo`: sem prova explícita, não há evidência de entrega.

## Bite-test (acrescentar ao `placar-indice.test.mjs`)
- **BITE:** provas verdes sem `_saida` resultam em `sem recibo`, e `feito` continua 0.
- **BITE:** com o `_saida` presente, a mesma thread vira `feito`.
- **BITE:** uma prova vermelha faz a thread cair para `pendente`, e o relato nomeia a prova.
- **CONTROLE:** thread com 0 provas explícitas nunca é `sem recibo`.
- **CONTROLE:** prova de recibo não medida impede `sem recibo`, como já impede `feito`.
- **CONTROLE:** `--check` não muda de veredito por causa de `sem recibo`.

## PARAR SE
- A mudança exigir tocar `placar.mjs` ou o workflow: parar e reportar (fora do prefixo).
- Algum dos 48 casos atuais do bite-test mudar de veredito: parar. A mudança é aditiva.

## Prova
- `placar-indice.mjs` contém `'sem recibo'` · `placar-indice.test.mjs` contém `sem recibo` · `node scripts/qa/placar-indice.test.mjs` verde
- `_saida-01.md` nesta pasta, com a saída de `npm run placar:lista` antes e depois (quantas threads viraram `sem recibo`, por módulo)
