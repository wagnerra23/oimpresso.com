---
sessao: "04"
titulo: "Ordens de produção: De/Até aplica no change"
dono: "[CL]"
base: 2c115a5ca250
---
# 04 · Filtro De/Até aplica na hora

Detalhe em `../PEDIDO-MANUFACTURING-ORDENS-2026-09-25.md` §"O que sobra" item 4. Resumo: em `Index.tsx`, aplicar o intervalo no `onChange` quando os dois campos estão vazios ou os dois válidos (ano ≥ 2000); tirar `onBlur` e o botão lupa; manter partial reload e rótulos. +1 caso no `Index.casos.md`.

## Verificação de quem executa (fora do placar — registrar no `_saida-04.md`)
- `npm run lint && npx tsc --noEmit` → exit 0
- runtime: escolher De e Até recarrega a lista sem clicar; digitar o ano pela metade (ex. 0002) NÃO dispara request; apagar os dois volta a lista inteira; controle positivo: Local e "Só finalizadas" seguem funcionando

## Prova
As provas desta thread estão no JSON do `00-INDICE.md` — o placar as confere. Recibo: `_saida-04.md`, escrito por quem executar.
