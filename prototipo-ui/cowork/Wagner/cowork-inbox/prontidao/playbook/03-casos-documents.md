---
sessao: "03"
titulo: casos.md com UC · Essentials Documents + Knowledge + Messages (abrir com `/onda prontidao --thread 03`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: resources/js/Pages/Essentials/Documents/Index.casos.md · resources/js/Pages/Essentials/Knowledge/Index.casos.md · resources/js/Pages/Essentials/Messages/Index.casos.md · tests/
nao_toca: resources/js/Pages/Essentials/Documents/Index.tsx · resources/js/Pages/Essentials/Knowledge/Index.tsx · resources/js/Pages/Essentials/Messages/Index.tsx · resources/js/Pages/Essentials/Documents/Index.charter.md · resources/js/Pages/Essentials/Knowledge/Index.charter.md · resources/js/Pages/Essentials/Messages/Index.charter.md
---
# 03 · casos.md com UC · Essentials Documents + Knowledge + Messages

## Telas
- `Essentials/Documents/Index` · charter `resources/js/Pages/Essentials/Documents/Index.charter.md` · alvo `resources/js/Pages/Essentials/Documents/Index.casos.md`
- `Essentials/Knowledge/Index` · charter `resources/js/Pages/Essentials/Knowledge/Index.charter.md` · alvo `resources/js/Pages/Essentials/Knowledge/Index.casos.md`
- `Essentials/Messages/Index` · charter `resources/js/Pages/Essentials/Messages/Index.charter.md` · alvo `resources/js/Pages/Essentials/Messages/Index.casos.md`

**Nota:** Messages é mural bespoke (fora dos 5 Padrões de Tela, segundo o próprio readiness). O UC descreve o comportamento que existe; não reclassificar o padrão nesta thread.

## O que fazer
1. Ler `scripts/lib/uc-regex.mjs`: é ele que conta UC (`contaUCs` delega para lá). O heading precisa casar **com essa lib**, não com a intuição.
2. Usar como molde uma tela pronta do mesmo arquétipo, por exemplo `resources/js/Pages/Cliente/Index.casos.md`.
3. Derivar os UCs **do charter + controller real** (`Inertia::render`), nunca do protótipo. Mínimo: 1 UC do caminho feliz da persona. Não inventar fluxo que a tela não tem.
4. Cada UC-id citado por ≥1 teste (casos-gate G-2). Teste roda no CT 100, nunca local.

## PARAR SE
- O charter contradisser o que o `.tsx` faz: parar e reportar. Charter é oráculo, e consertar charter está fora do prefixo.
- Passar de 300 linhas: dividir por tela, 1 PR por tela.

## Prova
- `resources/js/Pages/Essentials/Documents/Index.casos.md` contém UC-id reconhecido pela lib
- `resources/js/Pages/Essentials/Knowledge/Index.casos.md` contém UC-id reconhecido pela lib
- `resources/js/Pages/Essentials/Messages/Index.casos.md` contém UC-id reconhecido pela lib
- teste citando cada UC-id · `_saida-03.md` com a saída de `node scripts/qa/prototipo-readiness.mjs` mostrando as telas fora de 1-ciclo
