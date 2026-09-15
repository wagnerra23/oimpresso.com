---
sessao: "07"
titulo: Contratos 4/4 → required
dono: "[CL]"
base: e86130722de1
prefixo: prototipo-ui/contrato/ponto-fechamento.contract.json · ponto-rep-p.contract.json · o ponto onde o repo declara contrato `required` (ler onde os 2 atuais estão declarados — não inventar lugar novo)
nao_toca: ponto-painel.contract.json · ponto-espelho.contract.json (copy/ordem são lei [W])
depende: 04 · 05 · 06 feitas — vaga 3, sempre por último
---
# 07 · Contratos required

## Estado
`prototipo-ui/contrato/`: **2 de 4** do Ponto (`ponto-painel`, `ponto-espelho`). Os 2 que faltam nascem **dentro** das threads 04 (fechamento) e 06 (rep-p) pelo `criar-tela.mjs` — esta thread **não escreve contrato**: promove os 4 a `required` e prova que ficam verdes **3× seguidas** (DoD lane 6 do doc de 04/09).

## Passo a passo
1. `gh pr list` × `prototipo-ui/contrato/ponto-*`.
2. Localizar onde `painel`/`espelho` estão declarados como gate (`contrato-de-tela.mjs --contract …` no workflow) e acrescentar os 2 novos **no mesmo lugar**.
3. Rodar `node scripts/contrato-de-tela.mjs --contract <cada um>` → 4 exit codes no `_saida`.
4. 3 execuções verdes seguidas no CI antes de marcar required.
5. `_saida-07.md`.

## PARAR SE
- Um contrato reprovar por copy/ordem divergente → **não** ajustar o contrato: reportar a tela (lei [W]).
- O gate exigir estrutura que os 2 atuais não têm → parar e reportar; não criar 2º padrão.

## Prova
- `ponto-fechamento.contract.json` e `ponto-rep-p.contract.json` existem · 4 exit 0 no `_saida-07.md` · required declarado no mesmo lugar dos 2 antigos
