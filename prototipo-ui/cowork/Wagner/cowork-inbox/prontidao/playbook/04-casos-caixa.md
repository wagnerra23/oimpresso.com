---
sessao: "04"
titulo: casos.md com UC · Sells/Caixa (abrir com `/onda prontidao --thread 04`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: resources/js/Pages/Sells/Caixa/Index.casos.md · tests/
nao_toca: resources/js/Pages/Sells/Caixa/Index.tsx · resources/js/Pages/Sells/Caixa/Index.charter.md
---
# 04 · casos.md com UC · Sells/Caixa

## Telas
- `Sells/Caixa/Index` · charter `resources/js/Pages/Sells/Caixa/Index.charter.md` · alvo `resources/js/Pages/Sells/Caixa/Index.casos.md`

**Nota:** Já tem scorecard (`sells-caixa-index.yaml`). Só falta o casos.md.

## O que fazer
1. Ler `scripts/lib/uc-regex.mjs`: é ele que conta UC (`contaUCs` delega para lá). O heading precisa casar **com essa lib**, não com a intuição.
2. Usar como molde uma tela pronta do mesmo arquétipo, por exemplo `resources/js/Pages/Cliente/Index.casos.md`.
3. Derivar os UCs **do charter + controller real** (`Inertia::render`), nunca do protótipo. Mínimo: 1 UC do caminho feliz da persona. Não inventar fluxo que a tela não tem.
4. Cada UC-id citado por ≥1 teste (casos-gate G-2). Teste roda no CT 100, nunca local.

## PARAR SE
- O charter contradisser o que o `.tsx` faz: parar e reportar. Charter é oráculo, e consertar charter está fora do prefixo.
- Passar de 300 linhas: dividir por tela, 1 PR por tela.

## Prova
- `resources/js/Pages/Sells/Caixa/Index.casos.md` contém UC-id reconhecido pela lib
- teste citando cada UC-id · `_saida-04.md` com a saída de `node scripts/qa/prototipo-readiness.mjs` mostrando as telas fora de 1-ciclo
