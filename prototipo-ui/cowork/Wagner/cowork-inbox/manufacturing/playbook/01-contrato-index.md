---
sessao: "01"
titulo: Contrato de Tela manufacturing-index + data-contract no Index.tsx
dono: "[CL]"
base: 2c115a5ca250
---
# 01 · Contrato de Tela `manufacturing-index`

O conteúdo completo (seções, copy literal, o que não fazer) está em `../PEDIDO-MANUFACTURING-ORDENS-2026-09-25.md` §"O que sobra" item 1. Resumo: criar `governance/design/contracts/manufacturing-index.contract.json` no molde do `manufacturing-recipes` e pôr `data-contract` (cabecalho · abas · kpis · filtros · lista) no `Index.tsx` — só atributos.

## Verificação de quem executa (fora do placar — registrar no `_saida-01.md`)
- `node scripts/contrato-de-tela.mjs --contract governance/design/contracts/manufacturing-index.contract.json` → exit 0
- caso de sanidade: trocar 1 string do contrato → o gate reprova nomeando a string → reverter

## Prova
As provas desta thread estão no JSON do `00-INDICE.md` — o placar as confere. Recibo: `_saida-01.md`, escrito por quem executar.
