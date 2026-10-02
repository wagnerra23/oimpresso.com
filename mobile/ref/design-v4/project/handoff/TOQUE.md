# Item 03 — alvos de toque ≥ 44 px

Regra do DS mobile (Mobile DS Proposta, seção 02): nenhum alvo abaixo de 44 px. Visível pode ser 36 com `hitSlop` 4.

| Peça | Antes | Depois | Onde |
| --- | --- | --- | --- |
| OiBtn `sm` | 32 | 36 + hitSlop 4 | onda-2/OiBtn.tsx.patch |
| Ações do OiHeader | 38 | 44 | onda-2/OiHeader.tsx.patch |
| OiChip | ~28 | 36 + hitSlop 4 | onda-2/OiChips.tsx.patch |
| Voltar do OiDetailHeader | 40 + 6 | igual (52) + rótulo de acessibilidade | onda-2/OiDetailHeader.tsx.patch |
| Botões do erp-ui | ~28 (py-1.5) | 44 / 36+4 (viram OiBtn) | onda-3/erp-ui.tsx |
| Select do erp-ui | ~32 | 44 | onda-3/erp-ui.tsx → OiSelectField |
| "×" de remover (OS, Veículos, Estoque, Orçamentos) | 18 | ícone 18 em área 36 + hitSlop 8–10 | onda-2 telas + onda-3/PATCHES.md |
| Faixa offline "Sincronizar agora" | ~24 | 36 + hitSlop 4 | onda-4/offline-banner.tsx |
| Campos de formulário | ~36 | 44 | onda-2/OiForm.tsx (`touch.default`) |

Token novo em `oi-theme.ts` (onda 1): `touch = { lg: 48, default: 44, sm: 36, hitSlop: 4 }`.

## Conferir no aparelho
Ligar "Mostrar limites de layout" (Android, opções do desenvolvedor) e passar por OS, Veículos e Estoque: nenhum retângulo tocável menor que 44 dp.
