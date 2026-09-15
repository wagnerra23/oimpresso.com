---
thread: "05"
modulo: ancora
dono: "[CL]"
prefixo: prototipo-ui/ancora.mjs
depende: ["04", "D-PRECEDENCIA"]
base: remedir antes de escrever
---
# 05 · Campo duplo divergente para de resolver em silêncio

## Problema (medido em 2026-09-10, árvore `ed4398d77437`)
Quatro telas do Fiscal declaram **dois caminhos diferentes**:

| Tela | `related_prototype` | `bundle_source` |
|---|---|---|
| `Fiscal/Config` | `fiscal-subpages.jsx` | `fiscal-page.jsx` |
| `Fiscal/Dfe` | `fiscal-subpages.jsx` | `fiscal-page.jsx` |
| `Fiscal/Eventos` | `fiscal-subpages.jsx` | `fiscal-page.jsx` |
| `Fiscal/Sped` | `fiscal-subpages.jsx` | `fiscal-page.jsx` |

`Sells/Index` vai além: carrega **os três** campos (`bundle_source`, `related_prototype` e `visual_source`). Hoje a máquina escolhe por ordem de campo e **não diz que houve escolha** — quem lê o `✓` acha que a tela tem uma âncora, e ela tem duas que discordam.

## O que fazer
1. Ao resolver, se **mais de um campo** trouxer caminho e os caminhos **não forem o mesmo arquivo**, marcar `conflito: true` e listar os dois em `caminhos[]`.
2. Saída de 1 tela imprime os dois com o rótulo do campo, e o veredito vira **aviso** (não é exit 2 — a tela continua tendo âncora; o que ela não tem é âncora **única**).
3. `--list --json` ganha `conflito` por linha, e o `resumo` da thread 04 ganha `conflitos: N`.
4. **Precedência não é invenção sua:** enquanto `D-PRECEDENCIA` estiver aberta, mantenha a ordem atual e só **declare** o conflito.

## Prova (execução)
- `node prototipo-ui/ancora.mjs Fiscal/Config` → cita `fiscal-subpages.jsx` **e** `fiscal-page.jsx`, com `conflito`.
- **Controle positivo obrigatório:** `node prototipo-ui/ancora.mjs Cliente/Index` (que tem `related` + `bundle` apontando **o mesmo** `clientes-page.jsx`) → `conflito: false`. Sem esse caso, "achei 4 conflitos" pode ser o detector marcando tudo.
- `--list --json` → os 4 do Fiscal com `conflito:true`; o número total entra no `_saida-05.md`.

## Parar se
- O detector marcar `Cliente/Index` → a comparação está em string crua, não em caminho normalizado.
- Aparecer vontade de "consertar" o charter do Fiscal → **não**. Charter é onda de módulo, depois de `D-PRECEDENCIA`.
