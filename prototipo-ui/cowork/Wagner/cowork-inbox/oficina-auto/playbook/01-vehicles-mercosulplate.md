---
sessao: "01"
titulo: Vehicles: placa via MercosulPlate nas 4 telas
dono: "[CL]"
base: ebe1fc8be7e4
---
# 01 · Vehicles: placa via MercosulPlate nas 4 telas

O conteúdo completo (por quê, o que fazer, o que não fazer) está em `../PEDIDO-VEHICLES-MERCOSULPLATE-2026-09-23.md`. Resumo: `Vehicles/Index.tsx:398` imprime a placa em texto puro, contra o `Index.charter.md:34` (Goal) e o `:56` (Anti-pattern). Trocar por `<MercosulPlate size="sm">` no Index e no Create/Edit (prévia), e `size="md"` no Show. `vehicle_number` passa a rótulo secundário.

## Prova
As provas desta thread estão no JSON do `00-INDICE.md` — o placar as confere. Recibo: `_saida-01.md`, escrito por quem executar.
